<?php
/**
 * Checkout subscribe-and-save discount.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout;

use FuelChef\Subscriptions\Services\Checkout_Presence;
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
 * Adds the subscribe-discount checkbox to classic checkout, and the cart discount it
 * unlocks. Only offered to a logged-in customer whose cart meets the store's configured
 * minimum order amount and quantity - a logged-out customer sees a message with a Log in
 * link instead, and a logged-in customer with an ineligible cart sees a different message.
 * Regardless of any of that, {@see self::render()} always renders the delivery-date notice
 * first - every customer who has chosen a fulfilment date is told when their order arrives,
 * whether or not they can see (or check) the discount checkbox at all.
 *
 * The checkbox is never pre-checked from a previous visit - it is an explicit choice made
 * fresh on every checkout attempt, never remembered. {@see self::render()} reflects only
 * the current request's own posted value, and {@see self::reset_session_on_fresh_visit()}
 * clears the session flag block checkout relies on (see below) the moment a customer lands
 * on checkout, before anything can read a stale value left over from an earlier attempt.
 *
 * Block checkout has no `$_POST` at all - its own checkbox drives this cart-level fee
 * through {@see self::set_session_checked()} instead, called by `Block\Subscribe_And_Save`'s
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
		private Subscribe_Eligibility_Service $eligibility_service,
		private Checkout_Presence $checkout_presence
	) {
	}

	/**
	 * Hooks this discount into the classic checkout lifecycle.
	 */
	public function register(): void {
		add_action( 'template_redirect', [ $this, 'reset_session_on_fresh_visit' ] );
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'render' ] );
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'maybe_apply_discount' ] );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ], 10, 2 );
	}

	/**
	 * Clears block checkout's session flag the moment a customer lands on checkout, so a
	 * value left over from an earlier, separate checkout attempt never leaks into this one.
	 * Runs on `template_redirect` - a real page load, never `update_order_review`'s ajax
	 * refresh or the Store API's own requests - so it never fights a choice the customer
	 * has already made during the current visit.
	 */
	public function reset_session_on_fresh_visit(): void {
		if ( $this->checkout_presence->has_either() ) {
			$this->set_session_checked( false );
		}
	}

	/**
	 * Always renders the delivery-date notice marker first - `assets/checkout/js/delivery-notice.js`
	 * fills it in once a fulfilment date is chosen, whether or not this customer can even
	 * see the checkbox below. Then the checkbox for a logged-in, eligible customer, checked
	 * only when the current request's own posted data already has it checked - never from
	 * session, so a fresh page load always renders unchecked - the logged-out message when
	 * the customer has no account, or the ineligible message when the cart does not meet
	 * the store's configured minimums.
	 */
	public function render(): void {
		$cart = WC()->cart;

		if ( null === $cart ) {
			return;
		}

		echo $this->renderer->render( 'frontend/checkout/delivery-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$settings = $this->settings->get();

		if ( ! is_user_logged_in() ) {
			$html = $this->renderer->render(
				'frontend/checkout/subscribe-and-save-logged-out',
				[
					'message'   => $settings->logged_out_message_resolved(),
					'login_url' => $this->login_url(),
				]
			);

			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$eligible = $this->is_cart_eligible( $cart, $settings );

		$html = $this->renderer->render(
			'frontend/checkout/subscribe-and-save',
			[
				'eligible'           => $eligible,
				'checked'            => $eligible && $this->checked_in_posted_data(),
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
			esc_html__( 'Subscribe Discount', 'fuelchef-subscriptions' ),
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
	 * The login URL for a logged-out customer's "Log in" link, redirecting back to
	 * checkout once signed in.
	 */
	private function login_url(): string {
		return wp_login_url( wc_get_checkout_url() );
	}

	/**
	 * Whether the checkbox is checked in the current request - read from `post_data` on
	 * an `update_order_review` AJAX refresh, or the field directly on a final classic
	 * checkout submission. False when neither `$_POST` source is present at all, rather
	 * than falling back to anything remembered - used by {@see self::render()}, which must
	 * never show the checkbox pre-checked from an earlier attempt.
	 */
	private function checked_in_posted_data(): bool {
		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_data = Narrow::string( wp_unslash( $_POST['post_data'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			parse_str( $post_data, $parsed );

			return '' !== Narrow::string( $parsed[ self::FIELD_NAME ] ?? null );
		}

		return isset( $_POST[ self::FIELD_NAME ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Whether the checkbox is checked in the current request - {@see self::checked_in_posted_data()},
	 * or {@see self::SESSION_KEY} when neither `$_POST` source is present at all, which is
	 * always true for block checkout's own Store API requests. Safe to fall back to session
	 * here (unlike `render()`): {@see self::reset_session_on_fresh_visit()} guarantees that
	 * value only ever reflects a choice made during the customer's current checkout visit.
	 */
	private function is_checked_in_request(): bool {
		if ( isset( $_POST['post_data'] ) || isset( $_POST[ self::FIELD_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $this->checked_in_posted_data();
		}

		return null !== WC()->session && (bool) WC()->session->get( self::SESSION_KEY, false );
	}
}
