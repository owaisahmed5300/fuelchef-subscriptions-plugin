<?php
/**
 * Block checkout delivery date field.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout\Block;

use FuelChef\Subscriptions\Frontend\Checkout\Current_Delivery_Window;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Utils\Input;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the delivery date field for the Checkout block's "Order information" section.
 *
 * The Additional Checkout Fields API has no native date type on the WooCommerce version
 * this plugin targets (verified against the installed source - only `text`, `select` and
 * `checkbox` are supported), so this registers a plain `text` field and leaves the
 * calendar widget to `assets/checkout/js/block-delivery-date-field.js`, which enhances it
 * with flatpickr client-side the same way the classic checkout field is enhanced.
 *
 * Unlike classic checkout, a block checkout field's value is not available to read back
 * mid-form (see `Block\Subscribe_And_Save` for why) - but `woocommerce_validate_additional_field`
 * hands the posted value directly, so validating and rejecting an ineligible date needs
 * nothing extra.
 */
final class Delivery_Date_Field {


	/**
	 * This field's registered ID. Namespaced per the Additional Checkout Fields API's
	 * own requirement.
	 */
	public const FIELD_ID = 'fuelchef-subscriptions/delivery-date';

	/**
	 * The data attribute the enhancement script looks for on the rendered input.
	 */
	public const DATA_ATTRIBUTE = 'data-fcs-block-delivery-date';

	/**
	 * The REST namespace and route the enhancement script fetches eligible dates from.
	 */
	public const REST_NAMESPACE = 'fuelchef-subscriptions/v1';
	public const REST_ROUTE     = '/eligible-dates';

	/**
	 * Creates the field handler.
	 */
	public function __construct(
		private Current_Delivery_Window $window,
		private Settings_Store $settings
	) {
	}

	/**
	 * Hooks this field's registration, validation and its supporting REST route.
	 */
	public function register(): void {
		add_action( 'woocommerce_init', [ $this, 'register_field' ] );
		add_action( 'woocommerce_validate_additional_field', [ $this, 'validate' ], 10, 3 );
		add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
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
				// node, not raw HTML, so an HTML-escaped string shows its literal entities
				// (e.g. "&amp;") instead of being decoded.
				'label'      => $this->settings->get()->delivery_date_label(),
				'location'   => 'order',
				'type'       => 'text',
				'required'   => false,
				'attributes' => [
					'readOnly'           => true,
					'autocomplete'       => 'off',
					self::DATA_ATTRIBUTE => '1',
				],
			]
		);
	}

	/**
	 * Rejects checkout when a schedule applies to the customer's chosen destination but
	 * the posted date is not one it can actually fulfil.
	 *
	 * @param WP_Error $errors Validation errors, added to by reference.
	 * @param string   $field_key The ID of the field being validated.
	 * @param mixed    $field_value The posted field value.
	 */
	public function validate( WP_Error $errors, string $field_key, mixed $field_value ): void {
		if ( self::FIELD_ID !== $field_key ) {
			return;
		}

		$schedule = $this->window->schedule();

		if ( null === $schedule ) {
			return;
		}

		$posted = Input::string( $field_value );

		if ( in_array( $posted, $this->window->eligible_dates( $schedule ), true ) ) {
			return;
		}

		$errors->add(
			'fcs_delivery_date',
			esc_html__( 'Please choose a delivery date.', 'fuelchef-subscriptions' )
		);
	}

	/**
	 * Registers the read-only route the enhancement script polls for the currently
	 * eligible delivery dates, since a registered field's own options are fixed at
	 * registration time and cannot carry a live, per-request date list.
	 */
	public function register_rest_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_eligible_dates' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * The dates the customer's currently chosen destination is eligible for, empty when
	 * nothing chosen yet resolves to a schedule. Read-only and carries nothing specific
	 * to the customer beyond what their own cart/session already determines.
	 *
	 * Shared by both checkout implementations' enhancement scripts, despite living on the
	 * block checkout field class - registering it once here and pointing classic
	 * checkout's own script at the same URL avoids the alternative of running the same
	 * route (and the cart-loading workaround below) twice.
	 *
	 * Classic checkout's own page render and AJAX handler both load the cart earlier in
	 * the same request, before `Current_Delivery_Window` is ever asked to resolve
	 * anything - confirmed by reading both code paths. A bare REST request has none of
	 * that: `WC()->cart` is null here until `wc_load_cart()` is called.
	 * `Chosen_Shipping_Destination` calculates shipping itself once the cart exists, so
	 * nothing further is needed here.
	 */
	public function rest_eligible_dates(): WP_REST_Response {
		wc_load_cart();

		$schedule = $this->window->schedule();

		if ( null === $schedule ) {
			return new WP_REST_Response(
				[
					'hasSchedule' => false,
					'dates'       => [],
					'windows'     => [],
				]
			);
		}

		$dates = $this->window->eligible_dates( $schedule );

		return new WP_REST_Response(
			[
				'hasSchedule' => true,
				'dates'       => $dates,
				'windows'     => $this->window->windows_for_dates( $schedule, $dates ),
			]
		);
	}
}
