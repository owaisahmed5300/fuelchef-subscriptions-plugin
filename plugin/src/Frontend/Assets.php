<?php
/**
 * Frontend checkout assets.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend;

use FuelChef\Subscriptions\Frontend\Checkout\Block\Fulfilment_Date_Field as Block_Fulfilment_Date_Field;
use FuelChef\Subscriptions\Services\Login_Url_Resolver;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Utils\Locale;
use FuelChef\Subscriptions\Utils\Narrow;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the calendar widget and checkout scripts, only on the checkout page.
 */
final class Assets {


	/**
	 * The vendored flatpickr build's version, used as its own cache-busting query arg
	 * since it does not change with plugin releases.
	 */
	private const FLATPICKR_VERSION = '4.6.13';

	/**
	 * Creates the asset handler.
	 */
	public function __construct(
		private Settings_Store $settings,
		private Login_Url_Resolver $login_url_resolver
	) {
	}

	/**
	 * Registers the enqueue hook and the login form's redirect-back-to-checkout field.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'woocommerce_login_form', [ $this, 'render_login_redirect_field' ] );
	}

	/**
	 * Outputs a hidden `redirect` field inside WooCommerce's own login form, carrying
	 * {@see Login_Url_Resolver::REDIRECT_PARAM} through to `WC_Form_Handler::
	 * process_login()`, which already validates it against the site's own host before
	 * ever redirecting to it. Does nothing outside a request that arrived via this
	 * plugin's own login link.
	 */
	public function render_login_redirect_field(): void {
		if ( ! isset( $_GET[ Login_Url_Resolver::REDIRECT_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$target = Narrow::string( wp_unslash( $_GET[ Login_Url_Resolver::REDIRECT_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $target ) {
			return;
		}

		printf( '<input type="hidden" name="redirect" value="%s" />', esc_attr( $target ) );
	}

	/**
	 * Enqueues flatpickr and this plugin's checkout assets, doing nothing on any page
	 * that is not checkout.
	 */
	public function enqueue(): void {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'fcs-flatpickr',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/lib/flatpickr/airbnb.css',
			[],
			self::FLATPICKR_VERSION
		);

		wp_enqueue_script(
			'fcs-flatpickr',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/lib/flatpickr/flatpickr.min.js',
			[],
			self::FLATPICKR_VERSION,
			true
		);

		wp_enqueue_style(
			'fcs-fulfilment-date',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/css/fulfilment-date-field.css',
			[ 'fcs-flatpickr' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_style(
			'fcs-block-fulfilment-date',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/css/block-fulfilment-date-field.css',
			[],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_style(
			'fcs-block-checkout-section',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/css/block-checkout.css',
			[],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_style(
			'fcs-checkout-subscribe',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/css/subscribe-and-save.css',
			[],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_script(
			'fcs-checkout-shared',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/checkout-shared.js',
			[],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_enqueue_script(
			'fcs-fulfilment-date',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/fulfilment-date-field.js',
			[ 'jquery', 'fcs-flatpickr', 'fcs-checkout-shared' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		// Unlike the classic field, the block field is a native <select> - no Flatpickr,
		// so no dependency on it or on the script that loads it.
		wp_enqueue_script(
			'fcs-block-fulfilment-date',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/block-fulfilment-date-field.js',
			[ 'jquery', 'fcs-checkout-shared' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_enqueue_script(
			'fcs-block-checkout-subscribe',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/block-subscribe-and-save.js',
			// wc-blocks-data-store exposes window.wc.wcBlocksData and pulls in wp-data,
			// needed to read live cart totals for the eligibility check below.
			[ 'jquery', 'fcs-checkout-shared', 'wc-blocks-data-store' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_enqueue_script(
			'fcs-recurring-day-notice',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/recurring-day-notice.js',
			[ 'jquery', 'fcs-checkout-shared' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		$settings = $this->settings->get();

		// Localized on fcs-checkout-shared, not fcs-fulfilment-date: every checkout script
		// depends on the shared script, but only the classic field depends on Flatpickr,
		// so the shared script is the one handle guaranteed to load before any of them.
		wp_localize_script(
			'fcs-checkout-shared',
			'fcsCheckout',
			[
				'startOfWeek'               => $this->start_of_week(),
				'eligibleDatesUrl'          => rest_url( Block_Fulfilment_Date_Field::REST_NAMESPACE . Block_Fulfilment_Date_Field::REST_ROUTE ),
				'fulfilmentDateDescription' => $settings->fulfilment_date_description(),
				'subscribeSaveDescription'  => $settings->subscribe_save_description_resolved(),
				'minimumOrderAmount'        => $settings->minimum_order_amount(),
				'minimumCartQuantity'       => $settings->minimum_cart_quantity(),
				'ineligibleMessage'         => $settings->ineligible_message_resolved(),
				'isLoggedIn'                => is_user_logged_in(),
				'loggedOutMessage'          => $settings->logged_out_message_resolved(),
				'loginUrl'                  => $this->login_url_resolver->checkout_login_url(),
				'i18n'                      => $this->strings(),
			]
		);
	}

	/**
	 * The site's configured first day of the week, falling back to Monday when the
	 * stored option is missing or not numeric.
	 */
	private function start_of_week(): int {
		$value = get_option( 'start_of_week', 1 );

		return is_numeric( $value ) ? (int) $value : 1;
	}

	/**
	 * Every string the checkout script displays, translated once here rather than
	 * hardcoded in JavaScript. Month and weekday names reuse WordPress's own translated
	 * locale data instead of asking translators for the same strings again.
	 *
	 * @return array<string, string|list<string>> The strings, keyed by name.
	 */
	private function strings(): array {
		return [
			'chooseDate'            => esc_html__( 'Choose a date', 'fuelchef-subscriptions' ),
			'logIn'                 => esc_html__( 'Log in', 'fuelchef-subscriptions' ),
			'noFulfilmentDateMatch' => esc_html__( 'No fulfilment dates are available for this location.', 'fuelchef-subscriptions' ),
			/* translators: %1$s: opening time, %2$s: closing time. Resolved client-side. */
			'fulfilmentWindow'      => esc_html__( 'Fulfilment available between %1$s and %2$s.', 'fuelchef-subscriptions' ),
			/* translators: %s: weekday name, e.g. "Thursday". Resolved client-side. */
			'recurringDayNotice'    => esc_html__( 'Your subscription will renew every %s.', 'fuelchef-subscriptions' ),
			'monthNames'            => array_values( Locale::current()->month ),
			'monthNamesShort'       => array_values( Locale::current()->month_abbrev ),
			'dayNames'              => array_values( Locale::current()->weekday ),
			'dayNamesShort'         => array_values( Locale::current()->weekday_abbrev ),
		];
	}
}
