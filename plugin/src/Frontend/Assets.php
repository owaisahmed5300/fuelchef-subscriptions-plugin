<?php
/**
 * Frontend checkout assets.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend;

use FuelChef\Subscriptions\Frontend\Checkout\Block\Fulfilment_Date_Field as Block_Fulfilment_Date_Field;
use FuelChef\Subscriptions\Services\Checkout\Checkout_Presence_Service;
use FuelChef\Subscriptions\Services\Settings_Service;
use FuelChef\Subscriptions\Utils\Locale;
use FuelChef\Subscriptions\Values\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues this plugin's checkout assets - classic-only ones only when the classic checkout
 * shortcode is present on the current page, block-only ones only when the Checkout block is,
 * and the assets both depend on whenever either is.
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
		private Settings_Service $settings,
		private Checkout_Presence_Service $checkout
	) {
	}

	/**
	 * Registers the enqueue hook.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueues this plugin's checkout assets, doing nothing on a page with neither the
	 * classic checkout shortcode nor the Checkout block.
	 */
	public function enqueue(): void {
		$has_classic = $this->checkout->has_classic_shortcode();
		$has_block   = $this->checkout->has_block();

		if ( ! $has_classic && ! $has_block ) {
			return;
		}

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

		if ( $has_classic ) {
			$this->enqueue_classic();
		}

		if ( $has_block ) {
			$this->enqueue_block();
		}

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
				'loginUrl'                  => wp_login_url( wc_get_checkout_url() ),
				'i18n'                      => $this->strings( $settings ),
			]
		);
	}

	/**
	 * Enqueues the assets only classic checkout's own fulfilment date field needs -
	 * flatpickr and the field's table-row styling and Flatpickr wiring.
	 */
	private function enqueue_classic(): void {
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

		wp_enqueue_script(
			'fcs-fulfilment-date',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/fulfilment-date-field.js',
			[ 'jquery', 'fcs-flatpickr', 'fcs-checkout-shared' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_enqueue_script(
			'fcs-delivery-notice',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/delivery-notice.js',
			[ 'jquery', 'fcs-checkout-shared' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);
	}

	/**
	 * Enqueues the assets only the Checkout block's own fields need - the block's shared
	 * "Order information" wrapper styling, the fulfilment date field's native <select>
	 * styling and enhancement script, and the subscribe-discount checkbox's own script.
	 * Block checkout's own delivery notice lives inside that last script, unlike classic
	 * checkout's separate fcs-delivery-notice.
	 */
	private function enqueue_block(): void {
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
	 * Every value is `__()`, not `esc_html__()`: each one is only ever inserted client-side
	 * through jQuery `.text()` or `setAttribute()`, neither of which decodes an HTML
	 * entity - a pre-escaped string would show a literal "&amp;" instead of "&". See
	 * `Values\Settings::ineligible_message_resolved()` for the same reasoning on the
	 * settings-driven strings this localizes alongside these.
	 *
	 * @return array<string, string|list<string>> The strings, keyed by name.
	 */
	private function strings( Settings $settings ): array {
		return [
			'chooseDate'              => __( 'Choose a date', 'fuelchef-subscriptions' ),
			'logIn'                   => __( 'Log in', 'fuelchef-subscriptions' ),
			'noFulfilmentDateMatch'   => __( 'No fulfilment dates are available for this location.', 'fuelchef-subscriptions' ),
			// The store's configured (or default) wording, still carrying the literal
			// {start}/{end} placeholders for the enhancement scripts to fill in per date -
			// passing the placeholder names themselves back in as the substitution values
			// reuses the same resolution/fallback logic without actually substituting yet.
			'fulfilmentWindow'        => $settings->fulfilment_window_message_resolved( '{start}', '{end}' ),
			/* translators: %s: the chosen fulfilment date, e.g. "14 October 2026". Resolved client-side. */
			'singleDeliveryNotice'    => __( 'Your order will be delivered on %s.', 'fuelchef-subscriptions' ),
			/* translators: %1$s: weekday name, e.g. "Thursday". %2$s: the first fulfilment date, e.g. "14 October 2026". Both resolved client-side. */
			'recurringDeliveryNotice' => __( 'Your meals will be delivered every %1$s, starting %2$s.', 'fuelchef-subscriptions' ),
			'monthNames'              => array_values( Locale::current()->month ),
			'monthNamesShort'         => array_values( Locale::current()->month_abbrev ),
			'dayNames'                => array_values( Locale::current()->weekday ),
			'dayNamesShort'           => array_values( Locale::current()->weekday_abbrev ),
		];
	}
}
