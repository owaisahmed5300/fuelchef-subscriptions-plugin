<?php
/**
 * Block checkout fulfilment date field.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout\Block;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use DateTimeImmutable;
use FuelChef\Subscriptions\Frontend\Checkout\Block\Concerns\Reads_Persisted_Field;
use FuelChef\Subscriptions\Services\Checkout\Current_Fulfilment_Window_Service;
use FuelChef\Subscriptions\Services\Settings_Service;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Values\DateTime;
use WC_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the fulfilment date field for the Checkout block's "Order information"
 * section.
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
	 * Prefix for a month/year group heading's option value. Always registered `disabled`.
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
		private Current_Fulfilment_Window_Service $window,
		private Settings_Service $settings,
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
	 *
	 * A native `select`, not the `date` field type: WooCommerce's `date` field has no way
	 * to exclude individual dates, only a `min`/`max` range, so it cannot express blackout
	 * dates or closed weekdays. `options` is fixed here with every calendar date in the
	 * lookahead window, since WooCommerce validates a submission against the registered
	 * set, not the live DOM - `assets/checkout/js/block-fulfilment-date-field.js` only
	 * toggles which options are `disabled`.
	 */
	public function register_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$label = $this->settings->get()->fulfilment_date_label();

		woocommerce_register_additional_checkout_field(
			[
				'id'            => self::FIELD_ID,
				// Not esc_html__(): the Checkout block renders this as a plain React text
				// node, not raw HTML, so an HTML-escaped string shows its literal entities
				// (e.g. "&amp;") instead of being decoded.
				'label'         => $label,
				// WooCommerce appends "(optional)" to a non-required field's label by
				// default - misleading here, since a schedule can still make this field
				// effectively required (see the class docblock); this field is only ever
				// truly optional when no schedule applies at all.
				'optionalLabel' => $label,
				'location'      => 'order',
				'type'          => 'select',
				'required'      => false,
				// Not esc_html__() either - same reason as 'label' above, a placeholder is a
				// plain prop, not HTML.
				'placeholder'   => __( 'Choose a date', 'fuelchef-subscriptions' ),
				'options'       => $this->window_options(),
				'attributes'    => [
					self::DATA_ATTRIBUTE => '1',
				],
			]
		);
	}

	/**
	 * Every calendar date in the store's configured lookahead window, each its own
	 * option, grouped visually by month with a leading disabled heading option.
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

			$label_format = $date->format( 'Y' ) === $today->format( 'Y' ) ? 'D, M j' : 'D, M j, Y';

			$options[] = [
				'value' => $date->format( DateTime::DATABASE_DATE_FORMAT ),
				'label' => $this->localized_date( $date, $label_format ),
			];
		}

		return $options;
	}

	/**
	 * A date formatted with the site's translated month/weekday names, falling back to an
	 * untranslated format if `wp_date()` fails.
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
			sprintf(
				/* translators: %s: the admin-configured fulfilment date field label. */
				__( '%s is required to complete this order.', 'fuelchef-subscriptions' ),
				$this->settings->get()->fulfilment_date_label()
			)
		);
	}

	/**
	 * Rejects the order outright when a schedule applies but nothing eligible was ever
	 * saved to it.
	 *
	 * The backstop for `validate()`, which WooCommerce skips entirely when nothing was
	 * posted and the field is not `required: true` - which it deliberately is not, since
	 * it must stay optional for a destination with no schedule. This always fires once per
	 * place-order attempt regardless. Throwing `RouteException` is the Store API's own way
	 * to reject an order from this hook; `AbstractRoute::get_response()` converts it to a
	 * proper REST error response.
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

		throw new RouteException(
			'fcs_fulfilment_date_required',
			sprintf(
				/* translators: %s: the admin-configured fulfilment date field label. */
				__( '%s is required to complete this order.', 'fuelchef-subscriptions' ),
				$this->settings->get()->fulfilment_date_label()
			),
			400
		);
	}

	/**
	 * Registers the read-only route the enhancement script polls for the currently
	 * eligible fulfilment dates.
	 */
	public function register_rest_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_eligible_dates' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'rate_id' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * The dates the customer's currently chosen destination is eligible for.
	 *
	 * `destinationChosen` and `hasSchedule` are deliberately separate: the enhancement
	 * script needs to tell "nothing chosen yet" (stay hidden) apart from "chosen, but
	 * nothing can fulfil it" (say so) - both would otherwise collapse into the same
	 * `hasSchedule: false`.
	 *
	 * `rate_id`: the enhancement script already knows the shipping rate the customer's
	 * cart store shows as selected, and passes it explicitly rather than this route
	 * resolving it from WooCommerce's own session - that session write is a separate
	 * request the script's own re-fetch could otherwise race ahead of.
	 *
	 * `wc_load_cart()` is needed because a bare REST request has no cart loaded yet,
	 * unlike classic checkout's own page render and AJAX handler.
	 */
	public function rest_eligible_dates( WP_REST_Request $request ): WP_REST_Response {
		wc_load_cart();

		$rate_id            = Narrow::nullable_string( $request->get_param( 'rate_id' ) );
		$destination_chosen = $this->window->destination_chosen( $rate_id );
		$schedule           = $this->window->schedule( $rate_id );

		if ( null === $schedule ) {
			return new WP_REST_Response(
				[
					'destinationChosen' => $destination_chosen,
					'hasSchedule'       => false,
					'dates'             => [],
					'windows'           => [],
				]
			);
		}

		$dates = $this->window->eligible_dates( $schedule );

		return new WP_REST_Response(
			[
				'destinationChosen' => $destination_chosen,
				'hasSchedule'       => true,
				'dates'             => $dates,
				'windows'           => $this->window->windows_for_dates( $schedule, $dates ),
			]
		);
	}
}
