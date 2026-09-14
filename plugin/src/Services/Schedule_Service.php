<?php
/**
 * Schedule service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Entities\Schedule_Weekday;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Utils\Str;
use FuelChef\Subscriptions\Values\DateTime;
use FuelChef\Subscriptions\Values\Day_Of_Week;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Creates, renames, reconfigures and deletes fulfillment schedules.
 */
final class Schedule_Service {


	/**
	 * Every new weekday row's starting fulfillment time, before an admin sets one.
	 */
	private const DEFAULT_START_TIME = '12:00:00';

	/**
	 * Creates the service.
	 */
	public function __construct(
		private Schedule_Repository $schedules,
		private Schedule_Weekday_Repository $weekdays,
		private Blackout_Repository $blackouts,
		private Schedule_Destination_Repository $destinations
	) {
	}

	/**
	 * Creates a schedule, seeding all seven weekdays disabled at the default start time.
	 *
	 * @throws Validation_Exception When the name is blank.
	 */
	public function create( string $name ): Schedule {
		if ( Str::is_blank( $name ) ) {
			throw Validation_Exception::for_blank_name();
		}

		$schedule    = $this->schedules->insert( new Schedule( $name ) );
		$schedule_id = $schedule->id();

		if ( null === $schedule_id ) {
			throw new InvalidArgumentException( 'Inserted schedule has no ID.' );
		}

		foreach ( Day_Of_Week::all() as $day ) {
			$this->weekdays->insert( new Schedule_Weekday( $schedule_id, $day, false, self::DEFAULT_START_TIME ) );
		}

		return $schedule;
	}

	/**
	 * Renames a schedule.
	 *
	 * @throws Validation_Exception When the name is blank.
	 */
	public function rename( int $schedule_id, string $name ): Schedule {
		if ( Str::is_blank( $name ) ) {
			throw Validation_Exception::for_blank_name();
		}

		$schedule = $this->schedules->find_or_fail( $schedule_id );
		$schedule->set_name( $name );

		return $this->schedules->update( $schedule );
	}

	/**
	 * Updates one weekday's availability and start time.
	 *
	 * @throws Validation_Exception When the time is invalid, or the schedule has no row
	 *                               for that day of week.
	 */
	public function update_weekday( int $schedule_id, int $day_of_week, bool $enabled, string $start_time ): Schedule_Weekday {
		if ( ! DateTime::is_valid_time( $start_time ) ) {
			throw Validation_Exception::for_invalid_time( $start_time );
		}

		$weekday = $this->find_weekday( $schedule_id, $day_of_week );

		$weekday->set_enabled( $enabled )->set_start_time( $start_time );

		return $this->weekdays->update( $weekday );
	}

	/**
	 * Deletes a schedule, cascading to its destinations, weekdays and local blackouts
	 * first.
	 *
	 * No DB-level `ON DELETE CASCADE`: each repository owns its own cache, and only
	 * going through its own `delete()` keeps that cache correct.
	 */
	public function delete( int $schedule_id ): void {
		$this->destinations->delete_by_schedule( $schedule_id );
		$this->weekdays->delete_by_schedule( $schedule_id );
		$this->blackouts->delete_by_schedule( $schedule_id );
		$this->schedules->delete( $schedule_id );
	}

	/**
	 * Finds a schedule's weekday row for a day of week.
	 *
	 * @throws Validation_Exception When the schedule has no row for that day.
	 */
	private function find_weekday( int $schedule_id, int $day_of_week ): Schedule_Weekday {
		foreach ( $this->weekdays->find_by_schedule( $schedule_id ) as $weekday ) {
			if ( $weekday->day_of_week() === $day_of_week ) {
				return $weekday;
			}
		}

		throw Validation_Exception::for_unknown_day_of_week( $day_of_week );
	}
}
