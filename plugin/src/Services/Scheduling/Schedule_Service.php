<?php
/**
 * Schedule service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services\Scheduling;

use FuelChef\Subscriptions\Database\Transaction_Manager;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Entities\Schedule_Destination;
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
 * Creates, renames, reconfigures and deletes fulfilment schedules.
 */
final class Schedule_Service {


	/**
	 * Every new weekday row's starting fulfilment time, before an admin sets one.
	 */
	private const DEFAULT_START_TIME = '12:00:00';

	/**
	 * Every new weekday row's ending fulfilment time, before an admin sets one.
	 */
	private const DEFAULT_END_TIME = '17:00:00';

	/**
	 * Creates the service.
	 */
	public function __construct(
		private Schedule_Repository $schedules,
		private Schedule_Weekday_Repository $weekdays,
		private Blackout_Repository $blackouts,
		private Schedule_Destination_Repository $destinations,
		private Destination_Catalog_Service $destination_catalog,
		private Transaction_Manager $transactions
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
			$this->weekdays->insert(
				new Schedule_Weekday( $schedule_id, $day, false, self::DEFAULT_START_TIME, self::DEFAULT_END_TIME )
			);
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
	 * Updates one weekday's availability, start time and end time.
	 *
	 * @throws Validation_Exception When either time is invalid, the end time is not after
	 *                               the start time, or the schedule has no row for that
	 *                               day of week.
	 */
	public function update_weekday(
		int $schedule_id,
		int $day_of_week,
		bool $enabled,
		string $start_time,
		string $end_time
	): Schedule_Weekday {
		$this->validate_hours( $start_time, $end_time );

		$weekday = $this->find_weekday( $schedule_id, $day_of_week );

		$weekday->set_enabled( $enabled )->set_start_time( $start_time )->set_end_time( $end_time );

		return $this->weekdays->update( $weekday );
	}

	/**
	 * Updates every weekday row passed in, as one atomic change - the admin screen batches
	 * every pending weekday edit behind its own "Save Schedule" button rather than saving
	 * each toggle or time change individually, so this either applies the whole batch or
	 * rejects it and leaves every row exactly as it was.
	 *
	 * @param int                                                                                $schedule_id The schedule every row belongs to.
	 * @param list<array{day_of_week: int, enabled: bool, start_time: string, end_time: string}> $rows The weekdays to update.
	 *
	 * @throws Validation_Exception When any row's times are invalid or out of order, or the
	 *                               schedule has no row for one of the given days - nothing
	 *                               in the batch is saved when this is thrown.
	 *
	 * @return list<Schedule_Weekday> The updated weekdays, in the order they were given.
	 */
	public function update_weekdays( int $schedule_id, array $rows ): array {
		return $this->transactions->run(
			function () use ( $schedule_id, $rows ): array {
				return array_map(
					fn ( array $row ): Schedule_Weekday => $this->update_weekday(
						$schedule_id,
						$row['day_of_week'],
						$row['enabled'],
						$row['start_time'],
						$row['end_time']
					),
					$rows
				);
			}
		);
	}

	/**
	 * Copies one weekday's enabled state, start time and end time onto every day below it
	 * in the site's configured week order.
	 *
	 * @throws Validation_Exception When the schedule has no row for the source day.
	 *
	 * @return list<Schedule_Weekday> The updated weekdays, in the order they were saved.
	 */
	public function copy_weekday_to_days_below( int $schedule_id, int $source_day_of_week ): array {
		$source = $this->find_weekday( $schedule_id, $source_day_of_week );
		$order  = Day_Of_Week::in_site_order();
		$index  = array_search( $source_day_of_week, $order, true );

		if ( false === $index ) {
			throw Validation_Exception::for_unknown_day_of_week( $source_day_of_week );
		}

		$updated = [];

		foreach ( array_slice( $order, (int) $index + 1 ) as $day_of_week ) {
			$weekday = $this->find_weekday( $schedule_id, $day_of_week )
				->set_enabled( $source->enabled() )
				->set_start_time( $source->start_time() )
				->set_end_time( $source->end_time() );

			$updated[] = $this->weekdays->update( $weekday );
		}

		return $updated;
	}

	/**
	 * Replaces every destination assigned to a schedule, rejecting the whole batch when any
	 * of them is already assigned to a different schedule. An entry the catalog no longer
	 * recognises (a zone or pickup location since deleted in WooCommerce) is silently
	 * dropped rather than failing the save.
	 *
	 * @param int                                    $schedule_id The schedule to assign destinations to.
	 * @param list<array{type: string, key: string}> $destinations Destinations to assign.
	 *
	 * @throws Validation_Exception When any destination is already assigned to a different
	 *                               schedule - nothing is saved when this is thrown.
	 *
	 * @return list<Schedule_Destination> The schedule's destinations after saving.
	 */
	public function assign_destinations( int $schedule_id, array $destinations ): array {
		$resolved  = [];
		$conflicts = [];

		foreach ( $destinations as $destination ) {
			$option = $this->destination_catalog->find( $destination['type'], $destination['key'] );

			if ( null === $option ) {
				continue;
			}

			$owner = $this->destination_owner( $destination['type'], $destination['key'], $schedule_id );

			if ( null !== $owner ) {
				$conflicts[] = [
					'label'         => $option->label(),
					'schedule_name' => $owner->name(),
				];

				continue;
			}

			$resolved[] = new Schedule_Destination( $schedule_id, $destination['type'], $destination['key'] );
		}

		if ( [] !== $conflicts ) {
			throw Validation_Exception::for_destinations_already_assigned( $conflicts );
		}

		return $this->transactions->run(
			function () use ( $schedule_id, $resolved ): array {
				$this->destinations->replace_for_schedule( $schedule_id, $resolved );

				return $this->destinations->find_by_schedule( $schedule_id );
			}
		);
	}

	/**
	 * The schedule already assigned to a destination, other than the one given - null when
	 * none is.
	 */
	public function destination_owner( string $destination_type, string $destination_key, int $excluding_schedule_id ): ?Schedule {
		foreach ( $this->destinations->find_by_destination( $destination_type, $destination_key ) as $assignment ) {
			if ( $assignment->schedule_id() !== $excluding_schedule_id ) {
				return $this->schedules->find( $assignment->schedule_id() );
			}
		}

		return null;
	}

	/**
	 * Rejects times that are not valid, or where the end time does not come after the
	 * start time.
	 *
	 * @throws Validation_Exception When either time is invalid or out of order.
	 */
	private function validate_hours( string $start_time, string $end_time ): void {
		if ( ! DateTime::is_valid_time( $start_time ) ) {
			throw Validation_Exception::for_invalid_time( $start_time );
		}

		if ( ! DateTime::is_valid_time( $end_time ) ) {
			throw Validation_Exception::for_invalid_time( $end_time );
		}

		if ( $end_time <= $start_time ) {
			throw Validation_Exception::for_end_before_start( $start_time, $end_time );
		}
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
