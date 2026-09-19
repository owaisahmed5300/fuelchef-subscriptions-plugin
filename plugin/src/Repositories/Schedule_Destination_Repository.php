<?php
/**
 * Schedule destination repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories;

use FuelChef\Subscriptions\Database\Tables;
use FuelChef\Subscriptions\Entities\Contracts\Entity;
use FuelChef\Subscriptions\Entities\Schedule_Destination;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * @extends Abstract_Repository<Schedule_Destination>
 */
final class Schedule_Destination_Repository extends Abstract_Repository {


	/**
	 * Bare table name.
	 */
	protected static string $table = Tables::SCHEDULE_DESTINATIONS;

	/**
	 * Every destination assigned to a schedule.
	 *
	 * @return list<Schedule_Destination> The schedule's destinations.
	 */
	public function find_by_schedule( int $schedule_id ): array {
		$cache_key = $this->by_schedule_cache_key( $schedule_id );

		/** @var list<Schedule_Destination>|false $cached */
		$cached = wp_cache_get( $cache_key, $this->cache_group() );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		/** @var list<array<string, mixed>>|null $rows */
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE schedule_id = %d ORDER BY id ASC',
				$this->table_name(),
				$schedule_id
			),
			ARRAY_A
		);

		$destinations = $this->hydrate_all( $rows );

		wp_cache_set( $cache_key, $destinations, $this->cache_group() );

		return $destinations;
	}

	/**
	 * Every schedule assigned to a destination.
	 *
	 * The checkout uses this to resolve which schedule, if any, serves the
	 * customer's chosen shipping zone or pickup location.
	 *
	 * @param string $destination_type One of the `Destination_Type` constants.
	 * @param string $destination_key Identifier within that type.
	 *
	 * @return list<Schedule_Destination> The matching assignments.
	 */
	public function find_by_destination( string $destination_type, string $destination_key ): array {
		$cache_key = $this->by_destination_cache_key( $destination_type, $destination_key );

		/** @var list<Schedule_Destination>|false $cached */
		$cached = wp_cache_get( $cache_key, $this->cache_group() );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		/** @var list<array<string, mixed>>|null $rows */
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE destination_type = %s AND destination_key = %s ORDER BY schedule_id ASC',
				$this->table_name(),
				$destination_type,
				$destination_key
			),
			ARRAY_A
		);

		$destinations = $this->hydrate_all( $rows );

		wp_cache_set( $cache_key, $destinations, $this->cache_group() );

		return $destinations;
	}

	/**
	 * Deletes every destination assigned to a schedule.
	 */
	public function delete_by_schedule( int $schedule_id ): void {
		foreach ( $this->find_by_schedule( $schedule_id ) as $destination ) {
			$id = $destination->id();

			if ( null === $id ) {
				continue;
			}

			$this->delete( $id );
		}
	}

	/**
	 * Replaces every destination assigned to a schedule with a new set.
	 *
	 * @param int                        $schedule_id Schedule ID.
	 * @param list<Schedule_Destination> $destinations Destinations to assign.
	 */
	public function replace_for_schedule( int $schedule_id, array $destinations ): void {
		$this->delete_by_schedule( $schedule_id );

		foreach ( $destinations as $destination ) {
			$this->insert( $destination );
		}
	}

	/**
	 * Builds a schedule destination from a database row.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 */
	protected function hydrate( array $row ): Schedule_Destination {
		$destination = new Schedule_Destination(
			Narrow::int( $row['schedule_id'] ?? null ),
			Narrow::string( $row['destination_type'] ?? null ),
			Narrow::string( $row['destination_key'] ?? null )
		);

		$destination
			->set_id( Narrow::int( $row['id'] ?? null ) )
			->set_date_created( DateTime::from_database( Narrow::string( $row['date_created'] ?? null ) ) )
			->set_date_updated( DateTime::from_database( Narrow::string( $row['date_updated'] ?? null ) ) );

		return $destination;
	}

	/**
	 * Builds the row data to write for a schedule destination.
	 *
	 * @return array<string, mixed> The row data to persist.
	 */
	protected function dehydrate( Entity $entity ): array {
		return [
			'schedule_id'      => $entity->schedule_id(),
			'destination_type' => $entity->destination_type(),
			'destination_key'  => $entity->destination_key(),
		];
	}

	/**
	 * Invalidates the cached destination list for this entity's schedule, and
	 * for the destination it is assigned to.
	 */
	protected function invalidate_related( Entity $entity ): void {
		wp_cache_delete( $this->by_schedule_cache_key( $entity->schedule_id() ), $this->cache_group() );
		wp_cache_delete(
			$this->by_destination_cache_key( $entity->destination_type(), $entity->destination_key() ),
			$this->cache_group()
		);
	}

	/**
	 * Cache key for a destination's assigned-schedule list.
	 */
	private function by_destination_cache_key( string $destination_type, string $destination_key ): string {
		return 'by_destination_' . $destination_type . '_' . $destination_key;
	}
}
