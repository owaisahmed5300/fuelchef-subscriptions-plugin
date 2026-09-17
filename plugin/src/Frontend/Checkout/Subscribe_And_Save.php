<?php
/**
 * Checkout subscribe-and-save discount.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout;

use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Services\Subscribe_Discount_Service;
use FuelChef\Subscriptions\Services\Subscribe_Eligibility_Service;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Utils\Renderer;
use FuelChef\Subscriptions\Values\Settings;
use WC_Cart;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "Subscribe & Save" checkbox to classic checkout, and the cart discount it
 * unlocks. Only offered to a logged-in customer whose cart meets the store's configured
 * minimum order amount and quantity - an ineligible cart sees a message instead.
 *
 * Reads nothing of its own beyond the checkbox's current value, read straight from the
 * current request on every use rather than cached on the instance. Block checkout has no
 * `$_POST` at all - its own checkbox drives this cart-level fee through
 * {@see self::set_session_checked()} instead, called by `Block\Subscribe_And_Save`'s
 * Store API update callback.
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
	 * The session key block checkout's own checkbox reports its current value under, so
	 * `woocommerce_cart_calculate_fees` can see it on requests with no `$_POST` at all.
	 */
	private const SESSION_KEY = 'fcs_subscribe_and_save_checked';

	/**
	 * Creates the discount handler.
	 */
	public function __construct(
		private Settings_Store $settings,
		private Renderer $renderer,
		private Subscribe_Discount_Service $discount_service,
		private Subscribe_Eligibility_Service $eligibility_service
	) {
	}

	/**
	 * Hooks this discount into the classic checkout lifecycle.
	 */
	public function register(): void {
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'render' ] );
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'maybe_apply_discount' ] );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ], 10, 2 );
	}

	/**
	 * Renders the checkbox for a logged-in, eligible customer, checked when the current
	 * request already has it checked - or the ineligible message when the cart does not
	 * meet the store's configured minimums.
	 */
	public function render(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$cart = WC()->cart;

		if ( null === $cart ) {
			return;
		}

		$settings = $this->settings->get();
		$eligible = $this->is_cart_eligible( $cart, $settings );

		$html = $this->renderer->render(
			'frontend/checkout/subscribe-and-save',
			[
				'eligible'           => $eligible,
				'checked'            => $eligible && $this->is_checked_in_request(),
				'label'              => $settings->subscribe_save_label_resolved(),
				'description'        => $settings->subscribe_save_description_resolved(),
				'ineligible_message' => $settings->ineligible_message_resolved(),
			]
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Adds the discount as a cart fee when the checkbox is checked and the cart is
	 * eligible.
	 */
	public function maybe_apply_discount( WC_Cart $cart ): void {
		if ( ! is_user_logged_in() || ! $this->is_checked_in_request() ) {
			return;
		}

		$settings = $this->settings->get();

		if ( ! $this->is_cart_eligible( $cart, $settings ) ) {
			return;
		}

		// WC_Cart::get_subtotal() is declared to return float but actually returns the
		// value formatted as a numeric string; cast it back at this one boundary.
		$amount = $this->discount_service->discount_amount( (float) $cart->get_subtotal(), $settings );

		if ( $amount <= 0.0 ) {
			return;
		}

		$cart->add_fee(
			esc_html__( 'Subscribe & Save discount', 'fuelchef-subscriptions' ),
			-$amount
		);
	}

	/**
	 * Whether a cart's subtotal and item quantity meet the store's configured minimums.
	 */
	private function is_cart_eligible( WC_Cart $cart, Settings $settings ): bool {
		// WC_Cart::get_subtotal() is declared to return float but actually returns the
		// value formatted as a numeric string; cast it back at this one boundary.
		return $this->eligibility_service->is_eligible(
			(float) $cart->get_subtotal(),
			$cart->get_cart_contents_count(),
			$settings
		);
	}

	/**
	 * Saves whether the customer chose to subscribe to the order.
	 *
	 * Reuses {@see self::is_checked_in_request()} rather than reading the `$data` this
	 * hook is also given: `$data` is `WC_Checkout::get_posted_data()`'s own curated array,
	 * built strictly from WC's own registered checkout fieldsets, so a hand-rendered field
	 * never appears in it regardless of what was actually posted.
	 *
	 * @param WC_Order             $order The order being created.
	 * @param array<string, mixed> $data Unused - see above.
	 */
	public function persist( WC_Order $order, array $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$cart       = WC()->cart;
		$subscribed = is_user_logged_in()
			&& $this->is_checked_in_request()
			&& null !== $cart
			&& $this->is_cart_eligible( $cart, $this->settings->get() );

		$order->update_meta_data( self::META_KEY, $subscribed ? 'yes' : 'no' );
	}

	/**
	 * Records block checkout's own checkbox value for `maybe_apply_discount()` to read on
	 * a later request that has no `$_POST` of its own.
	 */
	public function set_session_checked( bool $checked ): void {
		if ( null !== WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $checked );
		}
	}

	/**
	 * Whether the checkbox is checked in the current request - read from `post_data` on
	 * an `update_order_review` AJAX refresh, the field directly on a final classic
	 * checkout submission, or {@see self::SESSION_KEY} when neither `$_POST` source is
	 * present at all, which is always true for block checkout's own Store API requests.
	 */
	private function is_checked_in_request(): bool {
		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_data = Narrow::string( wp_unslash( $_POST['post_data'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			parse_str( $post_data, $parsed );

			return '' !== Narrow::string( $parsed[ self::FIELD_NAME ] ?? null );
		}

		if ( isset( $_POST[ self::FIELD_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}

		return null !== WC()->session && (bool) WC()->session->get( self::SESSION_KEY, false );
	}
}
