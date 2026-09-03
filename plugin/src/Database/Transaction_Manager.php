<?php
/**
 * Database transaction manager.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Database;

use Throwable;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps a unit of work in a database transaction.
 *
 * Repositories use this for a multi-row write of their own table; services
 * use it to hold several repository calls together as one atomic change.
 * Either way the caller never issues `START TRANSACTION` itself, so nesting
 * is handled in one place instead of at every call site.
 *
 * Every table this plugin owns is InnoDB - see {@see Installer}. On a
 * MyISAM table these statements are accepted and ignored, and a rollback
 * would silently leave half a write behind.
 */
final class Transaction_Manager {


	/**
	 * Current transaction nesting depth.
	 */
	private int $depth = 0;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database access object.
	 */
	public function __construct(
		private wpdb $wpdb
	) {
	}

	/**
	 * Runs a callback inside a database transaction.
	 *
	 * Commits when the callback returns normally, and rolls back and
	 * re-throws when it throws. A nested call joins the outer transaction
	 * rather than opening a second one, because MySQL has no nested
	 * transactions: a second `START TRANSACTION` would commit the first.
	 *
	 * @template T
	 *
	 * @param callable(): T $work Unit of work to run transactionally.
	 *
	 * @return T Whatever the callback returns.
	 *
	 * @throws Throwable Whatever the callback throws, after rolling back.
	 */
	public function run( callable $work ) {
		$is_outermost = 0 === $this->depth;

		if ( $is_outermost ) {
			$this->wpdb->query( 'START TRANSACTION' );
		}

		++$this->depth;

		try {
			$result = $work();
		} catch ( Throwable $exception ) {
			--$this->depth;

			if ( $is_outermost ) {
				$this->wpdb->query( 'ROLLBACK' );
			}

			throw $exception;
		}

		--$this->depth;

		if ( $is_outermost ) {
			$this->wpdb->query( 'COMMIT' );
		}

		return $result;
	}
}
