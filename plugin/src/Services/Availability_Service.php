<?php
/**
 * Availability service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

use DateTimeImmutable;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Entities\Schedule_Weekday;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Settings\Settings_Store;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\Values\Cutoff_Unit;
use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a schedule can fulfil on a given date.
 *
 * A date is available when its weekday is enabled, no blackout (global or
 * schedule-scoped) covers it, and its cutoff has not passed. A destination with no
 * schedule assigned has no availability at all.
 */
final class Availability_Service {


	/**
	 * The most days one range query walks, so a caller cannot ask for an unbounded
	 * range and hang the request.
	 */
	public const MAX_RANGE_DAYS = 730;

	/**
	 * Creates the service.
	 */
	public function __construct(
		private Schedule_Weekday_Repository $weekdays,
		private Blackout_Repository $blackouts,
		private Settings_Store $settings,
		private Clock $clock,
		private Schedule_Destination_Repository $destinations,
		private Schedule_Repository $schedules
	) {
	}

	/**
	 * The schedule assigned to a destination, or null when none is.
	 *
	 * When more than one schedule is assigned to the same destination - not prevented at
	 * the data layer, only within one schedule's own destination list - the one with the
	 * lowest ID wins, since that is the order `find_by_destination()` returns them in.
	 */
	public function schedule_for_destination( string $destination_type, string $destination_key ): ?Schedule {
		$assignments = $this->destinations->find_by_destination( $destination_type, $destination_key );

		if ( [] === $assignments ) {
			return null;
		}

		$schedule_id = $assignments[0]->schedule_id();

		return $this->schedules->find( $schedule_id );
	}

	/**
	 * Whether a schedule can fulfil on a date.
	 */
	public function is_available( ?Schedule $schedule, string $date ): bool {
		$available = null !== $schedule
			&& DateTime::is_valid_date( $date )
			&& null !== $this->weekday_for( (int) $schedule->id(), $date )
			&& ! $this->is_closed( (int) $schedule->id(), $date )
			&& ! $this->cutoff_has_passed( $schedule, $date );

		/**
		 * Filters whether a date is available.
		 *
		 * @param bool          $available Whether the date can be fulfilled.
		 * @param Schedule|null $schedule The schedule, or null when none applies.
		 * @param string        $date The date, as `Y-m-d`.
		 */
		return (bool) apply_filters( 'fuelchef_subscriptions/availability/is_available', $available, $schedule, $date );
	}

	/**
	 * Every date in a range the schedule can fulfil on: open, unblocked, and its cutoff
	 * has not passed.
	 *
	 * @return list<string> The eligible dates, in order.
	 */
	public function eligible_dates( ?Schedule $schedule, string $from, string $to ): array {
		if ( null === $schedule ) {
			return [];
		}

		$eligible = [];

		foreach ( $this->open_dates( (int) $schedule->id(), $from, $to ) as $date ) {
			if ( ! $this->cutoff_has_passed( $schedule, $date ) ) {
				$eligible[] = $date;
			}
		}

		return $eligible;
	}

	/**
	 * Every date in a range the schedule is open on: weekday enabled, no blackout -
	 * ignoring the cutoff, which depends on the current time rather than the schedule's
	 * own configuration.
	 *
	 * @return list<string> The open dates, in order.
	 */
	public function open_dates( int $schedule_id, string $from, string $to ): array {
		if ( ! DateTime::is_valid_date( $from ) || ! DateTime::is_valid_date( $to ) || $to < $from ) {
			return [];
		}

		$open_weekdays = $this->open_weekdays_for( $schedule_id );

		if ( [] === $open_weekdays ) {
			return [];
		}

		$dates = $this->walk_open_dates( $open_weekdays, $this->closed_dates( $schedule_id, $from, $to ), $from, $to );

		/**
		 * Filters the dates a schedule is open on within a range.
		 *
		 * @param list<string> $dates The open dates, in order.
		 * @param int          $schedule_id Schedule ID.
		 * @param string       $from First date of the range, inclusive.
		 * @param string       $to Last date of the range, inclusive.
		 */
		$filtered = apply_filters( 'fuelchef_subscriptions/availability/open_dates', $dates, $schedule_id, $from, $to );

		if ( ! is_array( $filtered ) ) {
			return $dates;
		}

		/** @var list<string> $filtered */
		$filtered = array_values( array_filter( $filtered, 'is_string' ) );

		return $filtered;
	}

	/**
	 * The days of the week a schedule is enabled on, as a lookup keyed by day of week.
	 *
	 * @return array<int, true> Enabled days as keys.
	 */
	private function open_weekdays_for( int $schedule_id ): array {
		$open_weekdays = [];

		foreach ( $this->weekdays->find_by_schedule( $schedule_id ) as $weekday ) {
			if ( $weekday->enabled() ) {
				$open_weekdays[ $weekday->day_of_week() ] = true;
			}
		}

		return $open_weekdays;
	}

	/**
	 * Walks a date range, keeping the dates whose weekday is open and are not closed.
	 *
	 * @param array<int, true>    $open_weekdays Enabled days of the week, as keys.
	 * @param array<string, true> $closed Closed dates, as keys.
	 *
	 * @return list<string> The open dates, in order.
	 */
	private function walk_open_dates( array $open_weekdays, array $closed, string $from, string $to ): array {
		$dates  = [];
		$cursor = new DateTimeImmutable( $from );
		$last   = new DateTimeImmutable( $to );
		$walked = 0;

		while ( $cursor <= $last && $walked < self::MAX_RANGE_DAYS ) {
			$date = $cursor->format( DateTime::DATABASE_DATE_FORMAT );

			if ( isset( $open_weekdays[ (int) $cursor->format( 'w' ) ] ) && ! isset( $closed[ $date ] ) ) {
				$dates[] = $date;
			}

			$cursor = $cursor->modify( '+1 day' );
			++$walked;
		}

		return $dates;
	}

	/**
	 * The schedule's weekday row for a date, when that weekday is enabled.
	 */
	public function weekday_for( int $schedule_id, string $date ): ?Schedule_Weekday {
		if ( ! DateTime::is_valid_date( $date ) ) {
			return null;
		}

		$day_of_week = (int) ( new DateTimeImmutable( $date ) )->format( 'w' );

		foreach ( $this->weekdays->find_by_schedule( $schedule_id ) as $weekday ) {
			if ( $weekday->day_of_week() === $day_of_week && $weekday->enabled() ) {
				return $weekday;
			}
		}

		return null;
	}

	/**
	 * Whether a closure - global or scoped to this schedule - covers a date.
	 */
	public function is_closed( int $schedule_id, string $date ): bool {
		return isset( $this->closed_dates( $schedule_id, $date, $date )[ $date ] );
	}

	/**
	 * The instant after which a date can no longer be ordered or changed, counted back
	 * from the start of the day's fulfilment window.
	 *
	 * Null when the schedule is unknown or the date's weekday is not open, since there
	 * is then no window to count back from.
	 */
	public function cutoff_deadline( ?Schedule $schedule, string $date ): ?DateTime {
		if ( null === $schedule ) {
			return null;
		}

		$weekday = $this->weekday_for( (int) $schedule->id(), $date );

		if ( null === $weekday ) {
			return null;
		}

		$window_start = DateTime::from_wp( $date . ' ' . $weekday->start_time() );

		return DateTime::from(
			$window_start->native()->modify( sprintf( '-%d seconds', $this->cutoff_seconds() ) )
		);
	}

	/**
	 * Whether the deadline for a date has already gone by.
	 */
	public function cutoff_has_passed( ?Schedule $schedule, string $date ): bool {
		$deadline = $this->cutoff_deadline( $schedule, $date );

		if ( null === $deadline ) {
			return false;
		}

		return $this->clock->now()->native() > $deadline->native();
	}

	/**
	 * Every date closed in a range, from this schedule and store-wide, as a lookup keyed
	 * by date.
	 *
	 * @return array<string, true> Closed dates as keys.
	 */
	private function closed_dates( int $schedule_id, string $from, string $to ): array {
		$closed = [];

		foreach ( $this->blackouts->find_by_schedule_between( null, $from, $to ) as $blackout ) {
			$closed[ $blackout->date() ] = true;
		}

		foreach ( $this->blackouts->find_by_schedule_between( $schedule_id, $from, $to ) as $blackout ) {
			$closed[ $blackout->date() ] = true;
		}

		return $closed;
	}

	/**
	 * The store's current cutoff window, in seconds.
	 */
	private function cutoff_seconds(): int {
		$settings         = $this->settings->get();
		$seconds_per_unit = Cutoff_Unit::DAYS === $settings->cutoff_unit() ? DAY_IN_SECONDS : HOUR_IN_SECONDS;

		return $settings->cutoff_amount() * $seconds_per_unit;
	}
}
