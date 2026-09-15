<?php
/**
 * Abstract repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories;

use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Contracts\Timestamped;
use FuelChef\Subscriptions\Database\Tables;
use FuelChef\Subscriptions\Repositories\Exceptions\Entity_Not_Found_Exception;
use FuelChef\Subscriptions\Repositories\Exceptions\Repository_Exception;
use FuelChef\Subscriptions\Utils\Clock;
use InvalidArgumentException;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Common database access, hydration and caching for one table.
 *
 * A successful `insert()`, `update()` or `delete()` fires a
 * `fuelchef_subscriptions/{table}/created` (or `updated`, `deleted`) action.
 *
 * @template TEntity of Entity
 */
abstract class Abstract_Repository {


	/**
	 * Bare table name, one of the `Tables` constants. Set by each subclass.
	 */
	protected static string $table = '';

	/**
	 * WordPress object cache group for this repository's rows. Set by each
	 * subclass.
	 */
	protected static string $cache_group = '';

	/**
	 * Creates a repository.
	 */
	public function __construct(
		protected wpdb $wpdb,
		protected Clock $clock
	) {
	}

	/**
	 * Builds an entity from a database row.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 *
	 * @return TEntity The hydrated entity.
	 */
	abstract protected function hydrate( array $row ): Entity;

	/**
	 * Builds the row data to write for an entity, excluding ID and timestamps
	 * (the repository sets those).
	 *
	 * @param TEntity $entity Entity to write.
	 *
	 * @return array<string, mixed> The row data to persist.
	 */
	abstract protected function dehydrate( Entity $entity ): array;

	/**
	 * Invalidates any cache entries that depend on this entity beyond its own
	 * row, e.g. a "for this schedule" list. No-op by default.
	 *
	 * @param TEntity $entity Entity that was written or deleted.
	 */
	protected function invalidate_related( Entity $entity ): void {
		// No-op by default; see the docblock above.
	}

	/**
	 * Finds a row by ID.
	 *
	 * @return TEntity|null The entity, or null when no row has this ID.
	 */
	public function find( int $id ): ?Entity {
		/** @var TEntity|false $cached */
		$cached = wp_cache_get( $id, static::$cache_group );

		if ( $cached instanceof Entity ) {
			return $cached;
		}

		/** @var array<string, mixed>|null $row */
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->table_name(),
				$id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$entity = $this->hydrate( $row );

		wp_cache_set( $id, $entity, static::$cache_group );

		return $entity;
	}

	/**
	 * Finds a row by ID, or throws when it does not exist.
	 *
	 * @return TEntity The entity.
	 *
	 * @throws Entity_Not_Found_Exception When no row has this ID.
	 */
	public function find_or_fail( int $id ): Entity {
		$entity = $this->find( $id );

		if ( null === $entity ) {
			throw Entity_Not_Found_Exception::for_id( static::class, $id );
		}

		return $entity;
	}

	/**
	 * Inserts a new row and returns the entity with its ID and timestamps set.
	 *
	 * @param TEntity $entity Entity to insert.
	 *
	 * @return TEntity The inserted entity.
	 *
	 * @throws Repository_Exception When the insert fails.
	 */
	public function insert( Entity $entity ): Entity {
		$now  = $this->clock->now();
		$data = $this->dehydrate( $entity );

		$data['date_created'] = $now->to_database();
		$data['date_updated'] = $now->to_database();

		$result = $this->wpdb->insert( $this->table_name(), $data );

		if ( false === $result ) {
			throw Repository_Exception::for_wpdb_error( static::class, 'insert', $this->wpdb->last_error );
		}

		$id = (int) $this->wpdb->insert_id;

		$entity->set_id( $id );

		if ( $entity instanceof Timestamped ) {
			$entity->set_date_created( $now );
			$entity->set_date_updated( $now );
		}

		wp_cache_set( $id, $entity, static::$cache_group );

		$this->invalidate_related( $entity );

		/**
		 * Fires after a row is inserted.
		 *
		 * @param TEntity $entity The inserted entity.
		 */
		do_action( 'fuelchef_subscriptions/' . static::$table . '/created', $entity );

		return $entity;
	}

	/**
	 * Updates an existing row from an entity's current state.
	 *
	 * @param TEntity $entity Entity to update. Must already have an ID.
	 *
	 * @return TEntity The updated entity.
	 *
	 * @throws Repository_Exception When the update fails.
	 */
	public function update( Entity $entity ): Entity {
		$id = $entity->id();

		if ( null === $id ) {
			throw new InvalidArgumentException( 'Cannot update an entity that has not been persisted.' );
		}

		$now  = $this->clock->now();
		$data = $this->dehydrate( $entity );

		$data['date_updated'] = $now->to_database();

		$result = $this->wpdb->update( $this->table_name(), $data, [ 'id' => $id ] );

		if ( false === $result ) {
			throw Repository_Exception::for_wpdb_error( static::class, 'update', $this->wpdb->last_error );
		}

		if ( $entity instanceof Timestamped ) {
			$entity->set_date_updated( $now );
		}

		wp_cache_set( $id, $entity, static::$cache_group );

		$this->invalidate_related( $entity );

		/**
		 * Fires after a row is updated.
		 *
		 * @param TEntity $entity The updated entity.
		 */
		do_action( 'fuelchef_subscriptions/' . static::$table . '/updated', $entity );

		return $entity;
	}

	/**
	 * Deletes a row by ID. Does nothing if it does not exist.
	 *
	 * @throws Repository_Exception When the delete fails.
	 */
	public function delete( int $id ): void {
		$entity = $this->find( $id );

		$result = $this->wpdb->delete( $this->table_name(), [ 'id' => $id ], [ '%d' ] );

		if ( false === $result ) {
			throw Repository_Exception::for_wpdb_error( static::class, 'delete', $this->wpdb->last_error );
		}

		wp_cache_delete( $id, static::$cache_group );

		if ( null === $entity ) {
			return;
		}

		$this->invalidate_related( $entity );

		/**
		 * Fires after a row is deleted.
		 *
		 * @param int     $id The deleted row's ID.
		 * @param TEntity $entity The entity that was deleted.
		 */
		do_action( 'fuelchef_subscriptions/' . static::$table . '/deleted', $id, $entity );
	}

	/**
	 * This repository's table name, prefixed for the current site.
	 */
	protected function table_name(): string {
		return Tables::prefixed( $this->wpdb->prefix, static::$table );
	}
}
