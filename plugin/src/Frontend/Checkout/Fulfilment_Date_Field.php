<?php
/**
 * Checkout fulfilment date field.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout;

use FuelChef\Subscriptions\Services\Current_Fulfilment_Window;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Utils\Renderer;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a fulfilment date field to classic checkout, alongside the other checkout fields.
 *
 * Rendered once, on the initial page load - unlike the order review table, this part of
 * checkout is not replaced by WooCommerce's own `update_order_review` AJAX refresh, so
 * `assets/checkout/js/fulfilment-date-field.js` re-fetches eligible dates itself (the same
 * REST route the block checkout field already uses) whenever the customer's shipping
 * destination might have changed, and shows or hides the field accordingly.
 *
 * Reads and writes nothing of its own: it asks `Current_Fulfilment_Window` what schedule
 * and dates apply to whatever the customer picked, and renders, validates and persists
 * strictly within what that already decided.
 */
final class Fulfilment_Date_Field {


	/**
	 * The checkout field's name, used both in the posted form data and the order meta
	 * key's un-prefixed form.
	 */
	public const FIELD_NAME = 'fcs_fulfilment_date';

	/**
	 * The order meta key the chosen date is saved under.
	 */
	public const META_KEY = '_fcs_fulfilment_date';

	/**
	 * Creates the field handler.
	 */
	public function __construct(
		private Current_Fulfilment_Window $window,
		private Settings_Store $settings,
		private Renderer $renderer
	) {
	}

	/**
	 * Hooks this field into the classic checkout lifecycle: rendered alongside the other
	 * checkout fields, validated before an order is created, and saved to the order once
	 * it is.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'render' ] );
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ], 10, 2 );
	}

	/**
	 * Renders the field, initially visible only when a schedule already applies to
	 * whatever destination the customer has chosen - the enhancement script takes over
	 * showing and hiding it as that changes, since this only runs once per page load.
	 */
	public function render(): void {
		$schedule       = $this->window->schedule();
		$eligible_dates = null !== $schedule ? $this->window->eligible_dates( $schedule ) : [];
		$settings       = $this->settings->get();

		$html = $this->renderer->render(
			'frontend/checkout/fulfilment-date-field',
			[
				'has_schedule'   => null !== $schedule,
				'eligible_dates' => $eligible_dates,
				'windows'        => null !== $schedule ? $this->window->windows_for_dates( $schedule, $eligible_dates ) : [],
				'label'          => $settings->fulfilment_date_label(),
				'description'    => $settings->fulfilment_date_description(),
			]
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Rejects checkout when a schedule applies but the posted date is not one it can
	 * actually fulfil - missing, malformed, or no longer eligible since it was chosen.
	 *
	 * @param array<string, mixed> $data The posted checkout data.
	 * @param WP_Error             $errors Validation errors, added to by reference.
	 */
	public function validate( array $data, WP_Error $errors ): void {
		$schedule = $this->window->schedule();

		if ( null === $schedule ) {
			return;
		}

		$posted = Narrow::string( $data[ self::FIELD_NAME ] ?? null );

		if ( $this->window->is_eligible_date( $schedule, $posted ) ) {
			return;
		}

		$errors->add(
			'fcs_fulfilment_date',
			esc_html__( 'Please choose a fulfilment date.', 'fuelchef-subscriptions' )
		);
	}

	/**
	 * Saves the chosen date to the order once a schedule applies, having already been
	 * confirmed eligible by {@see self::validate()}.
	 *
	 * @param WC_Order             $order The order being created.
	 * @param array<string, mixed> $data The posted checkout data.
	 */
	public function persist( WC_Order $order, array $data ): void {
		if ( null === $this->window->schedule() ) {
			return;
		}

		$order->update_meta_data( self::META_KEY, Narrow::string( $data[ self::FIELD_NAME ] ?? null ) );
	}
}
