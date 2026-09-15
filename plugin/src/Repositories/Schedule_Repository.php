<?php
/**
 * Schedule repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories;

use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Database\Tables;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * @extends Abstract_Repository<Schedule>
 */
final class Schedule_Repository extends Abstract_Repository {


	/**
	 * Bare table name.
	 */
	protected static string $table = Tables::SCHEDULES;

	/**
	 * WordPress object cache group.
	 */
	protected static string $cache_group = 'fcs_schedules';

	/**
	 * Cache key for the full list of schedules.
	 */
	private const ALL_CACHE_KEY = 'all';

	/**
	 * Every schedule, ordered by name.
	 *
	 * @return list<Schedule> Every schedule.
	 */
	public function all(): array {
		/** @var list<Schedule>|false $cached */
		$cached = wp_cache_get( self::ALL_CACHE_KEY, self::$cache_group );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		/** @var list<array<string, mixed>>|null $rows */
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( 'SELECT * FROM %i ORDER BY name ASC', $this->table_name() ),
			ARRAY_A
		);

		$schedules = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$schedules[] = $this->hydrate( $row );
			}
		}

		wp_cache_set( self::ALL_CACHE_KEY, $schedules, self::$cache_group );

		return $schedules;
	}

	/**
	 * Builds a schedule from a database row.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 */
	protected function hydrate( array $row ): Schedule {
		$schedule = new Schedule( Narrow::string( $row['name'] ?? null ) );

		$schedule
			->set_id( Narrow::int( $row['id'] ?? null ) )
			->set_date_created( DateTime::from_database( Narrow::string( $row['date_created'] ?? null ) ) )
			->set_date_updated( DateTime::from_database( Narrow::string( $row['date_updated'] ?? null ) ) );

		return $schedule;
	}

	/**
	 * Builds the row data to write for a schedule.
	 *
	 * @return array<string, mixed> The row data to persist.
	 */
	protected function dehydrate( Entity $entity ): array {
		return [
			'name' => $entity->name(),
		];
	}

	/**
	 * Invalidates the cached full schedule list.
	 */
	protected function invalidate_related( Entity $entity ): void {
		wp_cache_delete( self::ALL_CACHE_KEY, self::$cache_group );
	}
}
