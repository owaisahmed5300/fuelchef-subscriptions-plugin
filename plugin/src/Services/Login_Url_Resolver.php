<?php
/**
 * Checkout login URL resolver.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the login link shown to a logged-out customer at checkout, shared by classic
 * and block checkout's own "Subscribe & Save" logged-out message rather than duplicated.
 *
 * Points at WooCommerce's own My Account page, which renders WooCommerce's styled login
 * form, not WordPress's bare `wp-login.php` - filterable so a merchant can point it
 * elsewhere entirely. {@see self::REDIRECT_PARAM} carries the customer back to checkout
 * after logging in; `Frontend\Assets::render_login_redirect_field()` is the other half of
 * that, reading it back out via the `woocommerce_login_form` action.
 */
final class Login_Url_Resolver {


	/**
	 * The query arg carrying the return destination through to WooCommerce's login form,
	 * and from there into its own hidden `redirect` field.
	 */
	public const REDIRECT_PARAM = 'fcs_redirect';

	/**
	 * The login URL for a logged-out customer's "Log in" link, redirecting back to
	 * checkout once signed in.
	 */
	public function checkout_login_url(): string {
		$url = add_query_arg(
			self::REDIRECT_PARAM,
			rawurlencode( wc_get_checkout_url() ),
			wc_get_page_permalink( 'myaccount' )
		);

		/**
		 * Filters the login URL shown to a logged-out customer at checkout.
		 *
		 * @param string $url The resolved URL, WooCommerce's own My Account page by default.
		 */
		return (string) apply_filters( 'fuelchef_subscriptions/checkout/login_url', $url );
	}
}
