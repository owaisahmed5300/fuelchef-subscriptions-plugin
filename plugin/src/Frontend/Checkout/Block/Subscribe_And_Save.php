<?php
/**
 * Block checkout subscribe-and-save discount.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout\Block;

use FuelChef\Subscriptions\Frontend\Checkout\Block\Concerns\Reads_Persisted_Field;
use FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save as Classic_Subscribe_And_Save;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Services\Subscribe_Discount_Service;
use WC_Order;
use WC_Order_Item_Fee;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Subscribe & Save" checkbox for the Checkout block, and applies the same
 * discount the classic checkout checkbox unlocks.
 *
 * Two separate mechanisms apply the discount, for two separate moments:
 *
 * - While the customer is still filling out checkout, `assets/checkout/js/block-subscribe-
 *   and-save.js` reports the checkbox's value through the Store API's own documented
 *   `extensionCartUpdate()` mechanism, read here by `register_update_callback()`'s
 *   registered callback and stored in the session (`Classic_Subscribe_And_Save::
 *   set_session_checked()`). `WC_Cart`'s own recalculations - already firing on every
 *   address or shipping change to keep totals live - read that session value through the
 *   classic checkout class's shared `maybe_apply_discount()`, so the discount appears in
 *   the order summary immediately, the same way shipping and tax already do.
 * - At place-order, `apply_discount()` (below) applies the same discount directly to the
 *   order instead, via `woocommerce_store_api_checkout_update_order_from_request` -
 *   verified against the installed source to fire only after `order`-location fields are
 *   actually persisted onto the order (`CheckoutTrait::update_order_from_request()` calls
 *   `persist_additional_fields_for_order()` immediately before firing it). The sibling hook
 *   `woocommerce_store_api_checkout_update_order_meta` looked like the obvious choice and is
 *   even named for this - but it fires earlier, before that persistence, so the checkbox's
 *   value is not readable from the order yet at that point; confirmed by testing both end
 *   to end against the real site before settling on this one. This is the one that
 *   actually decides the order's price: WooCommerce Blocks defers creating the checkout's
 *   draft order until the customer places it, and - verified against the installed source
 *   - the one and only `calculate_totals()` call in the place-order request runs *before*
 *   that draft order exists, so nothing in that specific request could read an
 *   `order`-location field's value off a `WC_Cart` fee callback even if one were added
 *   there too.
 */
final class Subscribe_And_Save {


	use Reads_Persisted_Field;

	/**
	 * This field's registered ID. Namespaced per the Additional Checkout Fields API's
	 * own requirement.
	 */
	public const FIELD_ID = 'fuelchef-subscriptions/subscribe-and-save';

	/**
	 * The data attribute the description-enhancement script looks for on the rendered
	 * checkbox.
	 */
	public const DATA_ATTRIBUTE = 'data-fcs-block-subscribe-and-save';

	/**
	 * The Store API extension namespace the checkbox reports its value under, via
	 * `extensionCartUpdate()`.
	 */
	public const UPDATE_CALLBACK_NAMESPACE = 'fuelchef-subscriptions/subscribe-and-save';

	/**
	 * Creates the discount handler.
	 */
	public function __construct(
		private Settings_Store $settings,
		private Classic_Subscribe_And_Save $classic,
		private Subscribe_Discount_Service $discount_service
	) {
	}

	/**
	 * Hooks this field's registration, its live cart-total preview, and the order fee it
	 * unlocks at place-order.
	 */
	public function register(): void {
		add_action( 'woocommerce_init', [ $this, 'register_field' ] );
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_update_callback' ] );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'apply_discount' ] );
	}

	/**
	 * Registers the field with the Checkout block, once WooCommerce Blocks itself is
	 * ready for it.
	 */
	public function register_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			[
				'id'         => self::FIELD_ID,
				// Not esc_html__(): the Checkout block renders this as a plain React text
				// node, not raw HTML, so an HTML-escaped string (e.g. one containing "&")
				// shows its literal entity instead of being decoded.
				'label'      => $this->settings->get()->subscribe_save_label_resolved(),
				'location'   => 'order',
				'type'       => 'checkbox',
				'attributes' => [
					self::DATA_ATTRIBUTE => '1',
				],
			]
		);
	}

	/**
	 * Registers the Store API update callback the checkbox's own enhancement script calls
	 * through `extensionCartUpdate()`, so the checkbox's value reaches
	 * `Classic_Subscribe_And_Save::set_session_checked()` without needing `$_POST` -
	 * WooCommerce's own documented mechanism for a client-side action to change something
	 * on the server and have the cart reflect it immediately, no different from how a
	 * "subscribe to our newsletter for 10% off" checkbox is documented to apply a coupon.
	 */
	public function register_update_callback(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}

		woocommerce_store_api_register_update_callback(
			[
				'namespace' => self::UPDATE_CALLBACK_NAMESPACE,
				'callback'  => function ( array $data ): void {
					$this->classic->set_session_checked(
						isset( $data['checked'] ) && filter_var( $data['checked'], FILTER_VALIDATE_BOOLEAN )
					);
				},
			]
		);
	}

	/**
	 * Adds or removes the discount fee on the order to match the checkbox's current
	 * value. Fires on the place-order request, and the rare failed-payment retry that
	 * reuses a pending order from the customer's session - never on an in-progress
	 * draft, which this field's value never reaches. Idempotent: safe to run more than
	 * once for the same order.
	 */
	public function apply_discount( WC_Order $order ): void {
		$this->remove_existing_fee( $order );

		if ( $this->is_checked( $order ) ) {
			$amount = $this->discount_service->discount_amount( (float) $order->get_subtotal(), $this->settings->get() );

			if ( $amount > 0.0 ) {
				$this->add_fee( $order, $amount );
			}
		}

		$order->calculate_totals();
		$order->save();
	}

	/**
	 * Adds the discount as a non-taxable, negative fee line on the order.
	 */
	private function add_fee( WC_Order $order, float $amount ): void {
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( $this->fee_name() );
		$fee->set_tax_status( 'none' );
		$fee->set_amount( (string) ( -$amount ) );
		$fee->set_total( (string) ( -$amount ) );

		$order->add_item( $fee );
	}

	/**
	 * Removes this plugin's own discount fee line from the order, if a previous call
	 * already added one - so re-running this always leaves at most one, matching the
	 * checkbox's latest value rather than accumulating a fee per update.
	 */
	private function remove_existing_fee( WC_Order $order ): void {
		foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
			if ( $item instanceof WC_Order_Item_Fee && $this->fee_name() === $item->get_name() ) {
				$order->remove_item( $item_id );
			}
		}
	}

	/**
	 * The discount fee line's name, translated once here rather than compared against
	 * a raw string.
	 */
	private function fee_name(): string {
		return esc_html__( 'Subscribe & Save discount', 'fuelchef-subscriptions' );
	}

	/**
	 * Whether the checkbox was checked on the given order.
	 */
	private function is_checked( WC_Order $order ): bool {
		return (bool) $this->persisted_field_value( self::FIELD_ID, $order );
	}
}
