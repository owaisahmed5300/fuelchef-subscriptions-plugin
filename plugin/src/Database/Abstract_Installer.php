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
	 * Site schema definition methods.
	 *
	 * @var list<string>|array{}
	 */
	protected array $schemas = [];

	/**
	 * Site database migrations.
	 *
	 * @var array<string, list<string>>
	 */
	protected array $migrations = [];

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
	 * Synchronizes database tables using dbDelta().
	 */
	private function sync_schema(): void {
		if ( [] === $this->schemas ) {
			return;
		}

		// dbDelta() lives in an admin include that is not always loaded.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		foreach ( $this->schemas as $method ) {
			/** @var array<string>|string $schema */
			$schema = $this->$method();

			dbDelta( $schema );
		}
	}

	/**
	 * Executes pending migrations.
	 */
	private function run_migrations(): void {
		$current_version = $this->get_current_version();

		foreach ( $this->migrations as $target_version => $methods ) {
			if ( version_compare( $current_version, $target_version, '>=' ) ) {
				continue;
			}

			foreach ( $methods as $method ) {
				if ( is_callable( [ $this, $method ] ) ) {
					$this->$method();
				}
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
