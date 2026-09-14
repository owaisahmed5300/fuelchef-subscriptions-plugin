<?php
/**
 * Frontend checkout assets.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend;

use FuelChef\Subscriptions\Frontend\Checkout\Block\Delivery_Date_Field as Block_Delivery_Date_Field;
use WP_Locale;

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
	 * Registers the enqueue hook.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
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
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/lib/flatpickr/flatpickr.min.css',
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
			'fcs-checkout',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/css/delivery-date-field.css',
			[ 'fcs-flatpickr' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_style(
			'fcs-checkout-subscribe',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/css/subscribe-and-save.css',
			[],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_script(
			'fcs-checkout',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/delivery-date-field.js',
			[ 'jquery', 'fcs-flatpickr' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_enqueue_script(
			'fcs-block-checkout',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/checkout/js/block-delivery-date-field.js',
			[ 'jquery', 'fcs-checkout' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_localize_script(
			'fcs-checkout',
			'fcsCheckout',
			[
				'startOfWeek'      => $this->start_of_week(),
				'eligibleDatesUrl' => rest_url( Block_Delivery_Date_Field::REST_NAMESPACE . Block_Delivery_Date_Field::REST_ROUTE ),
				'i18n'             => $this->strings(),
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
			'chooseDate'       => esc_html__( 'Choose a date', 'fuelchef-subscriptions' ),
			'noDatesAvailable' => esc_html__( 'No delivery dates are currently available.', 'fuelchef-subscriptions' ),
			'monthNames'       => array_values( $this->wp_locale()->month ),
			'monthNamesShort'  => array_values( $this->wp_locale()->month_abbrev ),
			'dayNames'         => array_values( $this->wp_locale()->weekday ),
			'dayNamesShort'    => array_values( $this->wp_locale()->weekday_abbrev ),
		];
	}

	/**
	 * WordPress's own translated month and weekday names, already maintained by core
	 * translators - reused here instead of asking for the same strings again.
	 */
	private function wp_locale(): WP_Locale {
		/** @var WP_Locale $wp_locale */
		$wp_locale = $GLOBALS['wp_locale'];

		return $wp_locale;
	}
}
