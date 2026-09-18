<?php
/**
 * Current checkout fulfilment window.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services\Checkout;

use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Services\Scheduling\Availability_Service;
use FuelChef\Subscriptions\Services\Settings_Service;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the schedule and eligible fulfilment dates for whatever destination the
 * customer currently has chosen at checkout.
 */
final class Current_Fulfilment_Window_Service {


	/**
	 * Creates the resolver.
	 */
	public function __construct(
		private Chosen_Shipping_Destination_Service $destination,
		private Availability_Service $availability,
		private Settings_Service $settings,
		private Clock $clock
	) {
	}

	/**
	 * The schedule that applies to whatever destination the customer has currently
	 * chosen, or null when nothing chosen yet resolves to one, or a schedule covers
	 * nothing for it - see {@see self::destination_chosen()} to tell those two apart.
	 *
	 * @param string|null $explicit_rate_id Forwarded to
	 *                                      {@see Chosen_Shipping_Destination_Service::resolve()}.
	 */
	public function schedule( ?string $explicit_rate_id = null ): ?Schedule {
		$destination = $this->destination->resolve( $explicit_rate_id );

		if ( null === $destination ) {
			return null;
		}

		return $this->availability->schedule_for_destination( $destination->type(), $destination->key() );
	}

	/**
	 * Whether the customer has a shipping address or pickup location resolved to a real
	 * destination yet, regardless of whether that destination has a schedule. Lets a
	 * caller tell "nothing chosen yet" (stay silent) apart from "chosen, but nothing can
	 * fulfil it" (say so) - both of which otherwise collapse into the same null from
	 * {@see self::schedule()}.
	 *
	 * @param string|null $explicit_rate_id Forwarded to
	 *                                      {@see Chosen_Shipping_Destination_Service::resolve()}.
	 */
	public function destination_chosen( ?string $explicit_rate_id = null ): bool {
		return null !== $this->destination->resolve( $explicit_rate_id );
	}

	/**
	 * Every date the schedule can fulfil on within the lookahead window.
	 *
	 * @return list<string> The eligible dates, in order.
	 */
	public function eligible_dates( Schedule $schedule ): array {
		$today = $this->clock->now_wp();
		$from  = $today->format( DateTime::DATABASE_DATE_FORMAT );
		$to    = $today->native()
			->modify( sprintf( '+%d days', $this->settings->get()->max_fulfilment_window_days() ) )
			->format( DateTime::DATABASE_DATE_FORMAT );

		return $this->availability->eligible_dates( $schedule, $from, $to );
	}

	/**
	 * Whether a posted date is one of the schedule's currently eligible dates - the same
	 * check classic and block checkout's own fulfilment date field each validate a
	 * submission against.
	 */
	public function is_eligible_date( Schedule $schedule, string $date ): bool {
		return in_array( $date, $this->eligible_dates( $schedule ), true );
	}

	/**
	 * The fulfilment hours for each of a schedule's eligible dates, formatted in the
	 * site's configured time format.
	 *
	 * @param Schedule     $schedule The schedule to read hours from.
	 * @param list<string> $dates Dates to look up, in `Y-m-d` form.
	 *
	 * @return array<string, array{start: string, end: string}> Formatted hours, keyed by date.
	 */
	public function windows_for_dates( Schedule $schedule, array $dates ): array {
		$time_format = get_option( 'time_format' );
		$time_format = is_string( $time_format ) && '' !== $time_format ? $time_format : 'g:i a';

		$windows = [];

		foreach ( $dates as $date ) {
			$weekday = $this->availability->weekday_for( (int) $schedule->id(), $date );

			if ( null === $weekday ) {
				continue;
			}

			$windows[ $date ] = [
				'start' => DateTime::from_wp( $date . ' ' . $weekday->start_time() )->format( $time_format ),
				'end'   => DateTime::from_wp( $date . ' ' . $weekday->end_time() )->format( $time_format ),
			];
		}

		return $windows;
	}
}
