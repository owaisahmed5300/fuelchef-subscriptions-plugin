<?php
/**
 * Current checkout delivery window.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout;

use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Services\Availability_Service;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\Values\DateTime;
use FuelChef\Subscriptions\WooCommerce\Chosen_Shipping_Destination;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the schedule and eligible delivery dates for whatever destination the
 * customer currently has chosen at checkout.
 *
 * Shared between classic and block checkout's own delivery date field, since both ask
 * exactly this question the same way - only how each reads and renders the answer
 * differs.
 */
final class Current_Delivery_Window {


	/**
	 * Creates the resolver.
	 */
	public function __construct(
		private Chosen_Shipping_Destination $destination,
		private Availability_Service $availability,
		private Settings_Store $settings,
		private Clock $clock
	) {
	}

	/**
	 * The schedule that applies to whatever destination the customer has currently
	 * chosen, or null when nothing chosen yet resolves to one.
	 */
	public function schedule(): ?Schedule {
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
	public function eligible_dates( Schedule $schedule ): array {
		$today = $this->clock->now_wp();
		$from  = $today->format( DateTime::DATABASE_DATE_FORMAT );
		$to    = $today->native()
			->modify( sprintf( '+%d days', $this->settings->get()->max_delivery_window_days() ) )
			->format( DateTime::DATABASE_DATE_FORMAT );

		return $this->availability->eligible_dates( $schedule, $from, $to );
	}

	/**
	 * Whether a posted date is one of the schedule's currently eligible dates - the same
	 * check classic and block checkout's own delivery date field each validate a
	 * submission against.
	 */
	public function is_eligible_date( Schedule $schedule, string $date ): bool {
		return in_array( $date, $this->eligible_dates( $schedule ), true );
	}

	/**
	 * The delivery hours for each of a schedule's eligible dates, formatted in the site's
	 * configured time format.
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
