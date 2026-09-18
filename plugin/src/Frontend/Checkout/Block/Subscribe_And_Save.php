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
use FuelChef\Subscriptions\Services\Subscribe_Eligibility_Service;
use WC_Order;
use WC_Order_Item_Fee;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the subscribe-discount checkbox for the Checkout block, and applies the same
 * discount the classic checkout checkbox unlocks. Only offered to a logged-in customer
 * whose cart is eligible, same as classic checkout's own field - but the field itself
 * stays registered for a logged-out or currently-ineligible customer too, since the
 * Additional Checkout Fields API has no per-request conditional registration.
 * `assets/checkout/js/block-subscribe-and-save.js` toggles it for the logged-out or
 * ineligible message instead, but `apply_discount()` below is the authoritative gate.
 *
 * Two mechanisms apply the discount. `assets/checkout/js/block-subscribe-and-save.js`
 * reports a live cart-total preview through the Store API's `extensionCartUpdate()`,
 * stored in the session via `Classic_Subscribe_And_Save::set_session_checked()` and read
 * by `WC_Cart`'s own recalculations. `apply_discount()` below applies the real discount
 * directly to the order at place-order, since WooCommerce Blocks defers creating the
 * order until then and no cart fee callback runs after that.
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
		private Subscribe_Discount_Service $discount_service,
		private Subscribe_Eligibility_Service $eligibility_service
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
	 * Registers the field with the Checkout block, once WooCommerce Blocks itself is ready
	 * for it. Registered unconditionally, even for a logged-out customer - unlike classic
	 * checkout's plain PHP `echo`, the Additional Checkout Fields API has no per-request
	 * conditional rendering, so `assets/checkout/js/block-subscribe-and-save.js` swaps the
	 * checkbox for the logged-out message client-side instead, the same way it already
	 * swaps it for the ineligible-cart message. `apply_discount()` below is the
	 * authoritative gate either way.
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
	 * `Classic_Subscribe_And_Save::set_session_checked()` without needing `$_POST`.
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
	 * value, only when the order is eligible. Idempotent: safe to run more than once for
	 * the same order. The authoritative eligibility gate - the checkbox's own visibility
	 * is only a client-side preview of the same rule.
	 */
	public function apply_discount( WC_Order $order ): void {
		$this->remove_existing_fee( $order );

		$settings = $this->settings->get();

		if (
			is_user_logged_in()
			&& $this->is_checked( $order )
			// WC_Order::get_item_count() is declared to return int but PHPStan's stubs see
			// it as int|string, since it sums quantities that arrive as numeric strings.
			&& $this->eligibility_service->is_eligible( (float) $order->get_subtotal(), (int) $order->get_item_count(), $settings )
		) {
			$amount = $this->discount_service->discount_amount( (float) $order->get_subtotal(), $settings );

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
	 * already added one.
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
		return esc_html__( 'Subscribe Discount', 'fuelchef-subscriptions' );
	}

	/**
	 * Whether the checkbox was checked on the given order.
	 */
	private function is_checked( WC_Order $order ): bool {
		return (bool) $this->persisted_field_value( self::FIELD_ID, $order );
	}
}
