<?php
/**
 * Abstract database installer.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Database;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the common database installation and migration workflow.
 */
abstract class Abstract_Installer {


	/**
	 * Installs or upgrades the database.
	 */
	final public function install(): void {
		$this->sync_schema();
		$this->run_migrations();
		$this->update_version();
	}

	/**
	 * Returns the currently installed database version.
	 */
	abstract protected function get_current_version(): string;

	/**
	 * Updates the installed database version.
	 */
	abstract protected function update_version(): void;

	/**
	 * Site schema definitions to keep in sync with `dbDelta()`, one closure per
	 * table. Empty by default; a subclass with tables overrides this.
	 *
	 * @return list<callable(): string> One closure per table, each returning its
	 *                                  `CREATE TABLE` statement.
	 */
	protected function schemas(): array {
		return [];
	}

	/**
	 * Site database migrations, keyed by the target version and run in array
	 * order. Empty by default; a subclass with migrations overrides this.
	 *
	 * @return array<string, list<callable(): void>> Migration steps, keyed by
	 *                                                target version.
	 */
	protected function migrations(): array {
		return [];
	}

	/**
	 * Synchronizes database tables using dbDelta().
	 */
	private function sync_schema(): void {
		$schemas = $this->schemas();

		if ( [] === $schemas ) {
			return;
		}

		// dbDelta() lives in an admin include that is not always loaded.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		foreach ( $schemas as $schema ) {
			dbDelta( $schema() );
		}
	}

	/**
	 * Executes pending migrations.
	 */
	private function run_migrations(): void {
		$current_version = $this->get_current_version();

		foreach ( $this->migrations() as $target_version => $steps ) {
			if ( version_compare( $current_version, $target_version, '>=' ) ) {
				continue;
			}

			foreach ( $steps as $step ) {
				$step();
			}
		}
	}

	/**
	 * The site's character set and collation, in the form dbDelta expects.
	 */
	protected function charset_collate(): string {
		/** @var wpdb $wpdb */
		$wpdb = $GLOBALS['wpdb'];

		return $wpdb->get_charset_collate();
	}


	/**
	 * The full name of one of the plugin's tables.
	 *
	 * Static because repositories need it without taking the installer as a dependency, and
	 * the answer depends only on `$wpdb`.
	 *
	 * @param string $name The table name without the site prefix or the `fcs_` prefix, such
	 *                     as `deliveries`.
	 *
	 * @return string The name to use in a query, prefixed for the current site.
	 */
	public static function table( string $name ): string {
		/** @var wpdb $wpdb */
		$wpdb = $GLOBALS['wpdb'];

		return $wpdb->prefix . 'fcs_' . $name;
	}
}
