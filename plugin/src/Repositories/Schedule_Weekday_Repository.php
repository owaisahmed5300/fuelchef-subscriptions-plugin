<?php
/**
 * Schedule weekday repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories;

use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Database\Tables;
use FuelChef\Subscriptions\Entities\Schedule_Weekday;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * @extends Abstract_Repository<Schedule_Weekday>
 */
final class Schedule_Weekday_Repository extends Abstract_Repository {


	/**
	 * Bare table name.
	 */
	protected static string $table = Tables::SCHEDULE_WEEKDAYS;

	/**
	 * WordPress object cache group.
	 */
	protected static string $cache_group = 'fcs_schedule_weekdays';

	/**
	 * Every weekday row for a schedule, ordered by day of week.
	 *
	 * @return list<Schedule_Weekday> The schedule's weekday rows.
	 */
	public function find_by_schedule( int $schedule_id ): array {
		$cache_key = $this->by_schedule_cache_key( $schedule_id );

		/** @var list<Schedule_Weekday>|false $cached */
		$cached = wp_cache_get( $cache_key, self::$cache_group );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		/** @var list<array<string, mixed>>|null $rows */
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE schedule_id = %d ORDER BY day_of_week ASC',
				$this->table_name(),
				$schedule_id
			),
			ARRAY_A
		);

		$weekdays = $this->hydrate_all( $rows );

		wp_cache_set( $cache_key, $weekdays, self::$cache_group );

		return $weekdays;
	}

	/**
	 * Deletes every weekday row for a schedule.
	 */
	public function delete_by_schedule( int $schedule_id ): void {
		foreach ( $this->find_by_schedule( $schedule_id ) as $weekday ) {
			$id = $weekday->id();

			if ( null === $id ) {
				continue;
			}

			$this->delete( $id );
		}
	}

	/**
	 * Builds a schedule weekday from a database row.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 */
	protected function hydrate( array $row ): Schedule_Weekday {
		$weekday = new Schedule_Weekday(
			Narrow::int( $row['schedule_id'] ?? null ),
			Narrow::int( $row['day_of_week'] ?? null ),
			Narrow::bool( $row['enabled'] ?? null ),
			Narrow::string( $row['start_time'] ?? null ),
			Narrow::string( $row['end_time'] ?? null )
		);

		$weekday
			->set_id( Narrow::int( $row['id'] ?? null ) )
			->set_date_created( DateTime::from_database( Narrow::string( $row['date_created'] ?? null ) ) )
			->set_date_updated( DateTime::from_database( Narrow::string( $row['date_updated'] ?? null ) ) );

		return $weekday;
	}

	/**
	 * Builds the row data to write for a schedule weekday.
	 *
	 * @return array<string, mixed> The row data to persist.
	 */
	protected function dehydrate( Entity $entity ): array {
		return [
			'schedule_id' => $entity->schedule_id(),
			'day_of_week' => $entity->day_of_week(),
			'enabled'     => $entity->enabled() ? 1 : 0,
			'start_time'  => $entity->start_time(),
			'end_time'    => $entity->end_time(),
		];
	}

	/**
	 * Invalidates the cached weekday list for this entity's schedule.
	 */
	protected function invalidate_related( Entity $entity ): void {
		wp_cache_delete( $this->by_schedule_cache_key( $entity->schedule_id() ), self::$cache_group );
	}

	/**
	 * Cache key for a schedule's weekday list.
	 */
	private function by_schedule_cache_key( int $schedule_id ): string {
		return 'by_schedule_' . $schedule_id;
	}
}
