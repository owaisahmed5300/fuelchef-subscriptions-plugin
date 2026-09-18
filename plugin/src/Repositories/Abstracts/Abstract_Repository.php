<?php
/**
 * Abstract repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories\Abstracts;

use FuelChef\Subscriptions\Utils\Clock;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Base for anything that reads or writes this plugin's own database tables.
 *
 * @template TEntity
 */
abstract class Abstract_Repository {


	/**
	 * Creates a repository.
	 */
	public function __construct(
		protected wpdb $wpdb,
		protected Clock $clock
	) {
	}
}
