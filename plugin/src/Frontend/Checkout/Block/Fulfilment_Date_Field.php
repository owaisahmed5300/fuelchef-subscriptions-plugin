<?php
/**
 * Block checkout fulfilment date field.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout\Block;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use DateTimeImmutable;
use FuelChef\Subscriptions\Frontend\Checkout\Block\Concerns\Reads_Persisted_Field;
use FuelChef\Subscriptions\Services\Current_Fulfilment_Window;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Values\DateTime;
use WC_Order;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the fulfilment date field for the Checkout block's "Order information"
 * section.
 *
 * A native `select`, not the Additional Checkout Fields API's `date` type or a
 * Flatpickr-enhanced `text` field (an earlier version of this field used the latter), by
 * choice on investigation:
 *
 * - The `date` type still does not exist as of WooCommerce 11.1.0 (well past this
 *   plugin's own 9.8 floor) - verified against the installed source
 *   (`CheckoutFields::$supported_field_types`), which lists only `text`, `select` and
 *   `checkbox`.
 * - Even where WooCommerce's own docs describe the `date` type, its only configuration is
 *   a `min`/`max` range - there is no option to exclude individual dates within that
 *   range, which is exactly what this field needs for closed weekdays and blackout
 *   dates. A plain range can never express that, so it could not replace anything here
 *   even if WooCommerce shipped it.
 * - `select` *is* natively supported, unlike `date`, so React renders it - no calendar
 *   popup escaping the block's own DOM subtree, no `altInput` fighting the block's field
 *   wrapper, none of the workarounds Flatpickr needed on this specific surface (classic
 *   checkout keeps Flatpickr; it has no React to fight in the first place, so there is
 *   nothing fragile to fix there).
 *
 * `options` is fixed at registration time the same way a `text` field's `attributes`
 * were - and, confirmed the hard way (a real order placement failing with "is not one of
 * ..."), WooCommerce validates a submission against exactly that registered set, not
 * against whatever `<option>` elements a script injects into the live DOM afterwards.
 * `register_field()` therefore registers every calendar date within the store's
 * configured lookahead window (`Settings::max_fulfilment_window_days()`) as a real,
 * individually valid option up front - not just a placeholder - so any date the
 * enhancement script later enables is already part of the set WooCommerce itself will
 * accept. `assets/checkout/js/block-fulfilment-date-field.js` only ever toggles which of
 * those pre-existing options are `disabled`, fetched from the same read-only REST route
 * classic checkout's own script uses; it never adds, removes or relabels an `<option>`.
 * `validate()` still rejects a date outside the customer's *actual* eligible set - the
 * wider registered list only satisfies WooCommerce's own schema, it is not itself the
 * authority on what a given customer may submit.
 *
 * Unlike classic checkout, a block checkout field's value is not available to read back
 * mid-form (see `Block\Subscribe_And_Save` for why) - but `woocommerce_validate_additional_field`
 * hands the posted value directly, so validating and rejecting an ineligible date needs
 * nothing extra.
 *
 * `validate()` alone is not enough to guarantee a schedule-bound order always carries a
 * date, though: confirmed against the installed source
 * (`StoreApi\Routes\V1\Checkout::process_order()`), WooCommerce skips calling
 * `woocommerce_validate_additional_field` entirely for a field whose posted value is empty
 * and whose registration is not `required: true` - which this one is, deliberately, since
 * it must never be required for a destination with no schedule at all, and registration
 * happens once at `woocommerce_init` with no per-customer context to condition `required`
 * on (the same reasoning `register_rest_route()`'s docblock already gives for why
 * eligibility cannot be baked into registration either). A customer who never touches the
 * field therefore reaches place-order with nothing for `validate()` to have ever seen.
 * `validate_order()` is the backstop: hooked to `woocommerce_store_api_checkout_order_processed`,
 * which always fires once per place-order attempt regardless of any field's own
 * required/empty state, it re-checks whatever ended up persisted on the order and throws
 * to reject the order outright if a schedule applies and nothing eligible was ever saved -
 * whether nothing was ever selected, or nothing was ever available to select at all.
 */
final class Fulfilment_Date_Field {


	use Reads_Persisted_Field;

	/**
	 * This field's registered ID. Namespaced per the Additional Checkout Fields API's
	 * own requirement.
	 */
	public const FIELD_ID = 'fuelchef-subscriptions/fulfilment-date';

	/**
	 * The data attribute the enhancement script looks for on the rendered select.
	 */
	public const DATA_ATTRIBUTE = 'data-fcs-block-fulfilment-date';

	/**
	 * Prefix for a month/year group heading's option value. Never a valid date, and
	 * always registered `disabled`, so it can never reach `validate()` as a real
	 * submission - a customer's browser will not let them select a disabled option, and
	 * even a crafted request naming one fails `is_eligible_date()` the same as any other
	 * ineligible value.
	 */
	private const GROUP_VALUE_PREFIX = '__group_';

	/**
	 * The REST namespace and route the enhancement script fetches eligible dates from.
	 */
	public const REST_NAMESPACE = 'fuelchef-subscriptions/v1';
	public const REST_ROUTE     = '/eligible-dates';

	/**
	 * Creates the field handler.
	 */
	public function __construct(
		private Current_Fulfilment_Window $window,
		private Settings_Store $settings,
		private Clock $clock
	) {
	}

	/**
	 * Hooks this field's registration, validation and its supporting REST route.
	 */
	public function register(): void {
		add_action( 'woocommerce_init', [ $this, 'register_field' ] );
		add_action( 'woocommerce_validate_additional_field', [ $this, 'validate' ], 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'validate_order' ] );
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
				'id'          => self::FIELD_ID,
				// Not esc_html__(): the Checkout block renders this as a plain React text
				// node, not raw HTML, so an HTML-escaped string shows its literal entities
				// (e.g. "&amp;") instead of being decoded.
				'label'       => $this->settings->get()->fulfilment_date_label(),
				'location'    => 'order',
				'type'        => 'select',
				'required'    => false,
				'placeholder' => esc_html__( 'Choose a date', 'fuelchef-subscriptions' ),
				'options'     => $this->window_options(),
				'attributes'  => [
					self::DATA_ATTRIBUTE => '1',
				],
			]
		);
	}

	/**
	 * Every calendar date in the store's configured lookahead window, each its own
	 * option, grouped visually by month with a leading disabled heading option -
	 * WooCommerce's own `options` schema is a flat list with no `<optgroup>` concept.
	 * Weekday-correct regardless of who is checking out, so - unlike eligibility itself -
	 * these labels need no client-side correction once rendered.
	 *
	 * @return list<array{value: string, label: string}> The options to register with.
	 */
	private function window_options(): array {
		$today = new DateTimeImmutable( $this->clock->now_wp()->format( DateTime::DATABASE_DATE_FORMAT ) );
		$days  = $this->settings->get()->max_fulfilment_window_days();

		$options    = [];
		$last_month = null;

		for ( $offset = 0; $offset <= $days; $offset++ ) {
			$date      = $today->modify( "+{$offset} days" );
			$month_key = $date->format( 'Y-m' );

			if ( $month_key !== $last_month ) {
				$options[]  = [
					'value' => self::GROUP_VALUE_PREFIX . $month_key,
					'label' => $this->localized_date( $date, 'F Y' ),
				];
				$last_month = $month_key;
			}

			$options[] = [
				'value' => $date->format( DateTime::DATABASE_DATE_FORMAT ),
				'label' => $this->localized_date( $date, 'D, M j' ),
			];
		}

		return $options;
	}

	/**
	 * A date formatted with the site's translated month/weekday names, falling back to an
	 * untranslated format on the same date when `wp_date()` itself fails - which happens
	 * only for a format or timestamp it cannot parse, never for the well-formed values
	 * this method is only ever called with.
	 */
	private function localized_date( DateTimeImmutable $date, string $format ): string {
		$formatted = wp_date( $format, $date->getTimestamp() );

		return is_string( $formatted ) ? $formatted : $date->format( $format );
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

		$posted = Narrow::string( $field_value );

		if ( $this->window->is_eligible_date( $schedule, $posted ) ) {
			return;
		}

		$errors->add(
			'fcs_fulfilment_date',
			esc_html__( 'Please choose a fulfilment date.', 'fuelchef-subscriptions' )
		);
	}

	/**
	 * Rejects the order outright when a schedule applies but nothing eligible was ever
	 * actually saved to it - the backstop `validate()` above cannot always be; see the
	 * class docblock for why. Reads the order's own persisted field value rather than the
	 * request: `persist_additional_fields_for_order()` has already saved whatever was (or
	 * was not) posted by the time this fires.
	 *
	 * Throws `RouteException` rather than returning one - the Store API's own documented
	 * way for a `woocommerce_store_api_checkout_order_processed` handler to reject an
	 * order in flight, caught and converted into a proper REST error response by
	 * `AbstractRoute::get_response()`. Not covered by any available PHPStan stub package
	 * (confirmed against the installed source: no `Automattic\WooCommerce\StoreApi\*`
	 * class is stubbed at all), so both the `@throws` tag and the `throw` below would
	 * otherwise report a false "class not found".
	 */
	public function validate_order( WC_Order $order ): void {
		$schedule = $this->window->schedule();

		if ( null === $schedule ) {
			return;
		}

		$posted = Narrow::string( $this->persisted_field_value( self::FIELD_ID, $order ) );

		if ( $this->window->is_eligible_date( $schedule, $posted ) ) {
			return;
		}

		throw new RouteException( // @phpstan-ignore-line
			'fcs_fulfilment_date_required',
			esc_html__( 'Please choose a fulfilment date.', 'fuelchef-subscriptions' ),
			400
		);
	}

	/**
	 * Registers the read-only route the enhancement script polls for the currently
	 * eligible fulfilment dates, since a registered field's own options cannot carry a
	 * live, per-request eligibility state - see the class docblock.
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
	 * Only `assets/checkout/js/block-fulfilment-date-field.js` polls this now - classic
	 * checkout's own field re-renders server-side with fresh eligible dates on every
	 * `update_order_review` AJAX refresh instead (it sits inside the order review table),
	 * so its script has no REST fetch of its own to point at this route. It still lives
	 * here rather than on the classic field class only because a bare REST request needs
	 * the cart-loading workaround below, which has nothing to do with either field's own
	 * concerns.
	 *
	 * A bare REST request has no cart loaded yet - unlike classic checkout's own page
	 * render and AJAX handler, which both load it earlier in the same request before
	 * `Current_Fulfilment_Window` is ever asked to resolve anything (confirmed by reading
	 * both code paths) - so `WC()->cart` is null here until `wc_load_cart()` is called.
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
