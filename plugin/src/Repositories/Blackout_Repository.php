<?php
/**
 * Blackout repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories;

use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Database\Tables;
use FuelChef\Subscriptions\Entities\Blackout;
use FuelChef\Subscriptions\Repositories\Abstracts\Abstract_Repository;
use FuelChef\Subscriptions\Utils\Row_Caster;
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
	 * @return list<Blackout>
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

		$blackouts = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$blackouts[] = $this->hydrate( $row );
			}
		}

		wp_cache_set( $cache_key, $blackouts, self::$cache_group );

		return $blackouts;
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
	 * @param array<string, mixed> $row Raw database row.
	 */
	protected function hydrate( array $row ): Blackout {
		$blackout = new Blackout(
			Row_Caster::nullable_int( $row['schedule_id'] ?? null ),
			Row_Caster::string( $row['blackout_date'] ?? null ),
			Row_Caster::nullable_string( $row['reason'] ?? null )
		);

		$blackout
			->set_id( Row_Caster::int( $row['id'] ?? null ) )
			->set_date_created( DateTime::from_database( Row_Caster::string( $row['date_created'] ?? null ) ) )
			->set_date_updated( DateTime::from_database( Row_Caster::string( $row['date_updated'] ?? null ) ) );

		return $blackout;
	}

	/**
	 * @return array<string, mixed>
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
