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
 * Adds a fulfilment date field to classic checkout, inside the order review table.
 *
 * Renders a `<tr>`, not a `<div>` - a `<div>` is not valid content directly inside a
 * `<tfoot>`. Sitting inside the table means it re-renders with fresh eligible dates on
 * every `update_order_review` AJAX refresh; `capture_posted_date()` reads the refresh's
 * raw `post_data` first so `render()` can restore a still-eligible selection across it.
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
	 * The customer's previously posted date, captured from the last `update_order_review`
	 * AJAX request - null before any refresh has happened yet (a plain page load, or
	 * outside an AJAX request entirely).
	 */
	private ?string $captured_date = null;

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
	 * Hooks this field's capture, render, validation and persistence into checkout.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_posted_date' ] );
		add_action( 'woocommerce_review_order_after_shipping', [ $this, 'render' ] );
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ], 10, 2 );
	}

	/**
	 * Captures this field's posted value from an `update_order_review` AJAX request,
	 * ahead of the order review table re-rendering.
	 *
	 * @param string $post_data The request's raw, urlencoded form data.
	 */
	public function capture_posted_date( string $post_data ): void {
		parse_str( $post_data, $parsed );

		$this->captured_date = Narrow::nullable_string( $parsed[ self::FIELD_NAME ] ?? null );
	}

	/**
	 * Renders the field's table row - nothing at all until a shipping address or pickup
	 * location resolves to a real destination, a "no fulfilment dates available" row once
	 * one does but no schedule covers it, and the full date field once one does.
	 */
	public function render(): void {
		if ( ! $this->window->destination_chosen() ) {
			return;
		}

		$schedule = $this->window->schedule();
		$settings = $this->settings->get();

		if ( null === $schedule ) {
			$html = $this->renderer->render(
				'frontend/checkout/fulfilment-date-no-match',
				[ 'label' => $settings->fulfilment_date_label() ]
			);

			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		$eligible_dates = $this->window->eligible_dates( $schedule );
		$selected_date  = $this->restored_date( $eligible_dates );

		$html = $this->renderer->render(
			'frontend/checkout/fulfilment-date-field',
			[
				'eligible_dates'  => $eligible_dates,
				'windows'         => $this->window->windows_for_dates( $schedule, $eligible_dates ),
				'label'           => $settings->fulfilment_date_label(),
				'description'     => $settings->fulfilment_date_description(),
				'selected_date'   => $selected_date,
				'selected_window' => null !== $selected_date
					? ( $this->window->windows_for_dates( $schedule, [ $selected_date ] )[ $selected_date ] ?? null )
					: null,
			]
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * The captured date to restore, only when it is still one of the currently eligible
	 * dates.
	 *
	 * @param list<string> $eligible_dates The currently eligible dates.
	 */
	private function restored_date( array $eligible_dates ): ?string {
		if ( null === $this->captured_date || ! in_array( $this->captured_date, $eligible_dates, true ) ) {
			return null;
		}

		return $this->captured_date;
	}

	/**
	 * Rejects checkout when a schedule applies but the posted date is not one it can
	 * actually fulfil.
	 *
	 * Reads `$_POST` directly rather than `$data`: `$data` is built only from WC's
	 * registered checkout fieldsets, and this field is not one of them.
	 *
	 * @param array<string, mixed> $data Unused - see above.
	 * @param WP_Error             $errors Validation errors, added to by reference.
	 */
	public function validate( array $data, WP_Error $errors ): void {
		$schedule = $this->window->schedule();

		if ( null === $schedule ) {
			return;
		}

		$posted = Narrow::string( wp_unslash( $_POST[ self::FIELD_NAME ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $this->window->is_eligible_date( $schedule, $posted ) ) {
			return;
		}

		$errors->add(
			'fcs_fulfilment_date',
			esc_html__( 'A fulfilment date is required to complete this order.', 'fuelchef-subscriptions' )
		);
	}

	/**
	 * Saves the chosen date to the order once a schedule applies.
	 *
	 * @param WC_Order             $order The order being created.
	 * @param array<string, mixed> $data Unused - see {@see self::validate()}.
	 */
	public function persist( WC_Order $order, array $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( null === $this->window->schedule() ) {
			return;
		}

		$order->update_meta_data( self::META_KEY, Narrow::string( wp_unslash( $_POST[ self::FIELD_NAME ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
}
