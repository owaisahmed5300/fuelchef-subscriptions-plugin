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
 * Adds a fulfilment date field to classic checkout, alongside the shipping options in the
 * order review table.
 *
 * Rendered on `woocommerce_review_order_after_shipping`, inside the `<table>` WooCommerce's
 * own order review renders (see `templates/frontend/checkout/fulfilment-date-field.php` -
 * it outputs a `<tr>`, not a `<div>`, because a `<div>` is not valid content directly
 * inside a `<tfoot>`; an earlier version of this field rendered a `<div>` at this exact
 * hook, and the browser's own HTML parser silently moved it outside the table entirely,
 * which is the "incorrect HTML" this plugin's own task list flagged in the checkout
 * layout). Because this hook is *inside* the order review table, it re-renders on every
 * `update_order_review` AJAX refresh (address changes, shipping method changes) with
 * fresh eligible dates every time - unlike the field's previous position outside that
 * table, this needs no separate REST-fetch-and-patch script; the enhancement script only
 * has to re-attach Flatpickr to whatever `<input>` the latest refresh rendered.
 *
 * A refresh replaces this row's markup entirely, which would otherwise discard whatever
 * date the customer had already picked. `capture_posted_date()`, hooked to
 * `woocommerce_checkout_update_order_review`, reads the AJAX request's raw `post_data`
 * (WooCommerce serializes the whole checkout form into it before triggering the refresh -
 * this field's own input included) before the table re-renders, so `render()` can restore
 * that selection - but only once `Current_Fulfilment_Window::is_eligible_date()` confirms
 * it is still eligible for whatever destination the refresh just resolved to.
 *
 * Reads and writes nothing else of its own: it asks `Current_Fulfilment_Window` what
 * schedule and dates apply to whatever the customer picked, and renders, validates and
 * persists strictly within what that already decided.
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
	 * Hooks this field into the classic checkout lifecycle: rendered alongside the
	 * shipping options in the order review table, its posted value captured on every AJAX
	 * refresh so a still-eligible selection survives one, validated before an order is
	 * created, and saved to the order once it is.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_posted_date' ] );
		add_action( 'woocommerce_review_order_after_shipping', [ $this, 'render' ] );
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ], 10, 2 );
	}

	/**
	 * Captures this field's own value out of an `update_order_review` AJAX request's raw,
	 * serialized form data, ahead of the order review table re-rendering within the same
	 * request.
	 *
	 * @param string $post_data The request's raw, urlencoded form data.
	 */
	public function capture_posted_date( string $post_data ): void {
		parse_str( $post_data, $parsed );

		$this->captured_date = Narrow::nullable_string( $parsed[ self::FIELD_NAME ] ?? null );
	}

	/**
	 * Renders the field's table row, only when a schedule applies to whatever destination
	 * the customer's most recent selection resolved to. Nothing to render otherwise - this
	 * re-runs on every order review refresh, so the row reappears as soon as one does.
	 */
	public function render(): void {
		$schedule = $this->window->schedule();

		if ( null === $schedule ) {
			return;
		}

		$eligible_dates = $this->window->eligible_dates( $schedule );
		$settings       = $this->settings->get();
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
	 * dates - a destination change since the customer picked it may have made it
	 * ineligible, in which case this renders unselected rather than restoring a date the
	 * field itself would immediately reject.
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
	 * actually fulfil - missing, malformed, or no longer eligible since it was chosen.
	 *
	 * Reads `$_POST` directly rather than the `$data` this hook is also given: `$data` is
	 * `WC_Checkout::get_posted_data()`'s own curated array, built strictly from WC's own
	 * registered checkout fieldsets (billing, shipping, order) - a field rendered by hand
	 * outside that registry, as this one is, never appears in it regardless of what was
	 * actually posted. Confirmed the hard way: a real order placement kept failing this
	 * validation with a genuinely-selected, genuinely-eligible date until this was traced
	 * to `$data[self::FIELD_NAME]` always being absent. Every request this runs in has
	 * already passed WooCommerce's own nonce check before this class is ever reached.
	 *
	 * @param array<string, mixed> $data The posted checkout data. Unused - see above.
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
			esc_html__( 'Please choose a fulfilment date.', 'fuelchef-subscriptions' )
		);
	}

	/**
	 * Saves the chosen date to the order once a schedule applies, having already been
	 * confirmed eligible by {@see self::validate()}.
	 *
	 * Reads `$_POST` directly rather than `$data` - see {@see self::validate()} for why.
	 *
	 * @param WC_Order             $order The order being created.
	 * @param array<string, mixed> $data The posted checkout data. Unused - see `validate()`.
	 */
	public function persist( WC_Order $order, array $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( null === $this->window->schedule() ) {
			return;
		}

		$order->update_meta_data( self::META_KEY, Narrow::string( wp_unslash( $_POST[ self::FIELD_NAME ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
}
