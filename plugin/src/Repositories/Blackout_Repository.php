<?php
/**
 * Blackout repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories;

use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Database\Tables;
use FuelChef\Subscriptions\Entities\Blackout;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * @extends Abstract_Repository<Blackout>
 */
final class Blackout_Repository extends Abstract_Repository {


	/**
	 * Bare table name.
	 */
	protected static string $table = Tables::BLACKOUTS;

	/**
	 * WordPress object cache group.
	 */
	protected static string $cache_group = 'fcs_blackouts';

	/**
	 * Every blackout for a schedule, or every store-wide one when null,
	 * ordered by date.
	 *
	 * @return list<Blackout> The matching blackouts.
	 */
	public function find_by_schedule( ?int $schedule_id ): array {
		$cache_key = $this->by_schedule_cache_key( $schedule_id );

		/** @var list<Blackout>|false $cached */
		$cached = wp_cache_get( $cache_key, self::$cache_group );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( null === $schedule_id ) {
			/** @var list<array<string, mixed>>|null $rows */
			$rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					'SELECT * FROM %i WHERE schedule_id IS NULL ORDER BY blackout_date ASC',
					$this->table_name()
				),
				ARRAY_A
			);
		} else {
			/** @var list<array<string, mixed>>|null $rows */
			$rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					'SELECT * FROM %i WHERE schedule_id = %d ORDER BY blackout_date ASC',
					$this->table_name(),
					$schedule_id
				),
				ARRAY_A
			);
		}

		$blackouts = $this->hydrate_all( $rows );

		wp_cache_set( $cache_key, $blackouts, self::$cache_group );

		return $blackouts;
	}

	/**
	 * Every blackout for a schedule, or every store-wide one when null,
	 * falling within a date range.
	 *
	 * Not cached: the range differs on nearly every call, so a cache entry
	 * would rarely be reused. The result passes through a filter so other
	 * code can add closures the database does not know about.
	 *
	 * @param int|null $schedule_id Schedule ID, or null for store-wide.
	 * @param string   $from_date First date of the range, inclusive, in `Y-m-d` form.
	 * @param string   $to_date Last date of the range, inclusive, in `Y-m-d` form.
	 *
	 * @return list<Blackout> The matching blackouts.
	 */
	public function find_by_schedule_between( ?int $schedule_id, string $from_date, string $to_date ): array {
		if ( null === $schedule_id ) {
			/** @var list<array<string, mixed>>|null $rows */
			$rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					'SELECT * FROM %i WHERE schedule_id IS NULL AND blackout_date BETWEEN %s AND %s ORDER BY blackout_date ASC',
					$this->table_name(),
					$from_date,
					$to_date
				),
				ARRAY_A
			);
		} else {
			/** @var list<array<string, mixed>>|null $rows */
			$rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					'SELECT * FROM %i WHERE schedule_id = %d AND blackout_date BETWEEN %s AND %s ORDER BY blackout_date ASC',
					$this->table_name(),
					$schedule_id,
					$from_date,
					$to_date
				),
				ARRAY_A
			);
		}

		$blackouts = $this->hydrate_all( $rows );

		/**
		 * Filters the blackouts found for a schedule within a date range.
		 *
		 * @param list<Blackout> $blackouts The blackouts found in the database.
		 * @param int|null       $schedule_id Schedule ID, or null for store-wide.
		 * @param string         $from_date First date of the range, inclusive.
		 * @param string         $to_date Last date of the range, inclusive.
		 */
		$filtered = apply_filters( 'fuelchef_subscriptions/blackouts/between', $blackouts, $schedule_id, $from_date, $to_date );

		if ( ! is_array( $filtered ) ) {
			return $blackouts;
		}

		/** @var list<Blackout> $filtered */
		$filtered = array_values( array_filter( $filtered, static fn ( mixed $item ): bool => $item instanceof Blackout ) );

		return $filtered;
	}

	/**
	 * Whether a blackout already exists on a date, for a schedule or globally.
	 */
	public function exists_on_date( ?int $schedule_id, string $date ): bool {
		foreach ( $this->find_by_schedule( $schedule_id ) as $blackout ) {
			if ( $blackout->date() === $date ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Deletes every blackout for a schedule. Store-wide blackouts
	 * (`schedule_id` null) are never touched by this.
	 */
	public function delete_by_schedule( int $schedule_id ): void {
		foreach ( $this->find_by_schedule( $schedule_id ) as $blackout ) {
			$id = $blackout->id();

			if ( null === $id ) {
				continue;
			}

			$this->delete( $id );
		}
	}

	/**
	 * Builds a blackout from a database row.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 */
	protected function hydrate( array $row ): Blackout {
		$blackout = new Blackout(
			Narrow::nullable_int( $row['schedule_id'] ?? null ),
			Narrow::string( $row['blackout_date'] ?? null ),
			Narrow::nullable_string( $row['reason'] ?? null )
		);

		$blackout
			->set_id( Narrow::int( $row['id'] ?? null ) )
			->set_date_created( DateTime::from_database( Narrow::string( $row['date_created'] ?? null ) ) )
			->set_date_updated( DateTime::from_database( Narrow::string( $row['date_updated'] ?? null ) ) );

		return $blackout;
	}

	/**
	 * Builds the row data to write for a blackout.
	 *
	 * @return array<string, mixed> The row data to persist.
	 */
	protected function dehydrate( Entity $entity ): array {
		return [
			'schedule_id'   => $entity->schedule_id(),
			'blackout_date' => $entity->date(),
			'reason'        => $entity->reason(),
		];
	}

	/**
	 * Invalidates the cached blackout list for this entity's schedule (or the
	 * store-wide list, when it is a global blackout).
	 */
	protected function invalidate_related( Entity $entity ): void {
		wp_cache_delete( $this->by_schedule_cache_key( $entity->schedule_id() ), self::$cache_group );
	}

	/**
	 * Cache key for a schedule's blackout list, or the store-wide one.
	 */
	private function by_schedule_cache_key( ?int $schedule_id ): string {
		return 'by_schedule_' . ( null === $schedule_id ? 'global' : (string) $schedule_id );
	}
}
