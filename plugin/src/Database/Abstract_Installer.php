<?php
/**
 * Abstract database installer.
 */

declare( strict_types=1 );

namespace WPPluginBoilerplate\Database;

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
			dbDelta( $this->$method() );
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
}
