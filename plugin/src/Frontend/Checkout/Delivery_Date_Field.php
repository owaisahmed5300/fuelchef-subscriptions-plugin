<?php
/**
 * Checkout delivery date field.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout;

use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Services\Availability_Service;
use FuelChef\Subscriptions\Templating\Renderer;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\Utils\Input;
use FuelChef\Subscriptions\Values\DateTime;
use FuelChef\Subscriptions\WooCommerce\Chosen_Shipping_Destination;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a delivery date field to classic checkout, shown only once the customer has
 * chosen a shipping zone or pickup location a schedule is assigned to.
 *
 * Reads and writes nothing of its own: it asks `Chosen_Shipping_Destination` what the
 * customer picked, `Availability_Service` which dates that allows, and renders,
 * validates and persists strictly within what those two already decided.
 */
final class Delivery_Date_Field {


	/**
	 * The checkout field's name, used both in the posted form data and the order meta
	 * key's un-prefixed form.
	 */
	public const FIELD_NAME = 'fcs_delivery_date';

	/**
	 * The order meta key the chosen date is saved under.
	 */
	public const META_KEY = '_fcs_delivery_date';

	/**
	 * How many days ahead eligible dates are offered for. Wide enough for a customer to
	 * plan a few weeks out, narrow enough that computing it on every checkout refresh
	 * stays cheap.
	 */
	private const LOOKAHEAD_DAYS = 60;

	/**
	 * The date posted in the current request, captured from `post_data` during an
	 * `update_order_review` AJAX refresh so a re-rendered field keeps its selection.
	 * Null on a normal page load, where nothing has been posted yet.
	 */
	private ?string $posted_date = null;

	/**
	 * Creates the field handler.
	 */
	public function __construct(
		private Chosen_Shipping_Destination $destination,
		private Availability_Service $availability,
		private Clock $clock,
		private Renderer $renderer
	) {
	}

	/**
	 * Hooks this field into the classic checkout lifecycle: captured on every AJAX
	 * refresh, rendered after the shipping method list, validated before an order is
	 * created, and saved to the order once it is.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_posted_date' ] );
		add_action( 'woocommerce_review_order_after_shipping', [ $this, 'render' ] );
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'persist' ], 10, 2 );
	}

	/**
	 * Reads this field's value out of an `update_order_review` AJAX request's raw
	 * `post_data`, so the field can re-render with the customer's choice still selected.
	 *
	 * @param string $post_data The checkout form, serialized.
	 */
	public function capture_posted_date( string $post_data ): void {
		parse_str( $post_data, $parsed );

		$this->posted_date = Input::string( $parsed[ self::FIELD_NAME ] ?? null );
	}

	/**
	 * Renders the field after the shipping method list, once a schedule applies to what
	 * the customer chose. Renders nothing otherwise, so the field never appears without
	 * something behind it to fulfil the order.
	 */
	public function render(): void {
		$schedule = $this->resolve_schedule();

		if ( null === $schedule ) {
			return;
		}

		$html = $this->renderer->render(
			'frontend/checkout/delivery-date-field',
			[
				'eligible_dates' => $this->eligible_dates( $schedule ),
				'selected_date'  => $this->selected_date( $schedule ),
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
		$schedule = $this->resolve_schedule();

		if ( null === $schedule ) {
			return;
		}

		$posted = Input::string( $data[ self::FIELD_NAME ] ?? null );

		if ( in_array( $posted, $this->eligible_dates( $schedule ), true ) ) {
			return;
		}

		$errors->add(
			'fcs_delivery_date',
			esc_html__( 'Please choose a delivery date.', 'fuelchef-subscriptions' )
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
		if ( null === $this->resolve_schedule() ) {
			return;
		}

		$order->update_meta_data( self::META_KEY, Input::string( $data[ self::FIELD_NAME ] ?? null ) );
	}

	/**
	 * The schedule that applies to whatever destination the customer has currently
	 * chosen, or null when nothing chosen yet resolves to one.
	 */
	private function resolve_schedule(): ?Schedule {
		$destination = $this->destination->resolve();

		if ( null === $destination ) {
			return null;
		}

		return $this->availability->schedule_for_destination( $destination->type(), $destination->key() );
	}

	/**
	 * Every date the schedule can fulfil on within the lookahead window.
	 *
	 * @return list<string> The eligible dates, in order.
	 */
	private function eligible_dates( Schedule $schedule ): array {
		$today = $this->clock->now_wp();
		$from  = $today->format( DateTime::DATABASE_DATE_FORMAT );
		$to    = $today->native()
			->modify( sprintf( '+%d days', self::LOOKAHEAD_DAYS ) )
			->format( DateTime::DATABASE_DATE_FORMAT );

		return $this->availability->eligible_dates( $schedule, $from, $to );
	}

	/**
	 * The posted date, when it names a date the schedule can still fulfil on. Null
	 * otherwise, including when nothing has been posted yet.
	 */
	private function selected_date( Schedule $schedule ): ?string {
		if ( null === $this->posted_date ) {
			return null;
		}

		return in_array( $this->posted_date, $this->eligible_dates( $schedule ), true )
			? $this->posted_date
			: null;
	}
}
