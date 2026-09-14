<?php
/**
 * Checkout subscribe-and-save discount.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout;

use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Utils\Input;
use FuelChef\Subscriptions\Utils\Renderer;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use WC_Cart;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "Subscribe & Save" checkbox to classic checkout, and the cart discount it
 * unlocks.
 *
 * Reads nothing of its own beyond the checkbox's own posted value: whether it is checked
 * is read straight from the current request on every use, rather than cached on the
 * instance, since a fee calculation and the final order both happen in their own separate
 * requests with nothing in common but that request's own `$_POST`.
 */
final class Subscribe_And_Save {


	/**
	 * The checkbox's field name, used both in the posted form data and the order meta
	 * key's un-prefixed form.
	 */
	public const FIELD_NAME = 'fcs_subscribe_and_save';

	/**
	 * The order meta key the customer's choice is saved under.
	 */
	public const META_KEY = '_fcs_subscribed';

	/**
	 * Creates the discount handler.
	 */
	public function __construct(
		private Settings_Store $settings,
		private Renderer $renderer
	) {
	}

	/**
	 * Hooks this discount into the classic checkout lifecycle: rendered alongside the
	 * other checkout fields, applied while cart totals are calculated, and saved to the
	 * order once it is created.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'render' ] );
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'maybe_apply_discount' ] );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ], 10, 2 );
	}

	/**
	 * Renders the checkbox, checked when the current request already has it checked -
	 * the customer's own last toggle, on an AJAX refresh it triggered itself. Unchecked
	 * on a plain page load, since nothing was posted for it to read.
	 */
	public function render(): void {
		$settings = $this->settings->get();

		$html = $this->renderer->render(
			'frontend/checkout/subscribe-and-save',
			[
				'checked'     => $this->is_checked_in_request(),
				'label'       => $settings->subscribe_save_label_resolved(),
				'description' => $settings->subscribe_save_description_resolved(),
			]
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Adds the discount as a cart fee when the checkbox is checked and the store's
	 * settings currently allow it to apply to this (the initial) order.
	 */
	public function maybe_apply_discount( WC_Cart $cart ): void {
		if ( ! $this->is_checked_in_request() ) {
			return;
		}

		// WC_Cart::get_subtotal() is declared to return float but actually returns the
		// value formatted as a numeric string; cast it back at this one boundary.
		$amount = $this->discount_amount( (float) $cart->get_subtotal(), $this->settings->get() );

		if ( $amount <= 0.0 ) {
			return;
		}

		$cart->add_fee(
			esc_html__( 'Subscribe & Save discount', 'fuelchef-subscriptions' ),
			-$amount
		);
	}

	/**
	 * The discount amount for a subtotal under a given settings configuration. Zero
	 * when the applicability setting excludes the initial order, the discount percent
	 * is zero, or the subtotal itself is zero.
	 */
	public function discount_amount( float $subtotal, Settings $settings ): float {
		if ( Subscribe_Applicability::RENEWAL_ONLY === $settings->subscribe_applicability() ) {
			return 0.0;
		}

		if ( $settings->subscribe_discount_percent() <= 0 || $subtotal <= 0.0 ) {
			return 0.0;
		}

		return round( $subtotal * $settings->subscribe_discount_percent() / 100, wc_get_price_decimals() );
	}

	/**
	 * Saves whether the customer chose to subscribe to the order.
	 *
	 * @param WC_Order             $order The order being created.
	 * @param array<string, mixed> $data The posted checkout data.
	 */
	public function persist( WC_Order $order, array $data ): void {
		$subscribed = '' !== Input::string( $data[ self::FIELD_NAME ] ?? null );

		$order->update_meta_data( self::META_KEY, $subscribed ? 'yes' : 'no' );
	}

	/**
	 * Whether the checkbox is checked in the current request - read from `post_data` on
	 * an `update_order_review` AJAX refresh, or the field directly on a final checkout
	 * submission. Never checked on a plain page load, where neither is present.
	 *
	 * Reads `$_POST` directly rather than through a nonce-verified action: this decides
	 * what to display and whether a discount applies, nothing destructive, and every
	 * request it runs in has already passed WooCommerce's own nonce check before this
	 * class is ever reached.
	 */
	private function is_checked_in_request(): bool {
		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_data = Input::string( wp_unslash( $_POST['post_data'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			parse_str( $post_data, $parsed );

			return '' !== Input::string( $parsed[ self::FIELD_NAME ] ?? null );
		}

		return isset( $_POST[ self::FIELD_NAME ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
}
