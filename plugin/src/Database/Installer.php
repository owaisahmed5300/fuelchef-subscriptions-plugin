<?php
/**
 * Site database installer.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Installs and upgrades site-scoped database tables.
 */
final class Installer extends Abstract_Installer {


	/**
	 * Database version.
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Site database version option.
	 */
	public const OPTION_KEY = 'fuelchef_subscriptions_db_version';

	/**
	 * Site database schema definitions.
	 *
	 * @var list<string>
	 */
	protected array $schemas = [];

	/**
	 * Site database migrations.
	 *
	 * @var array<string, list<string>>
	 */
	protected array $migrations = [];

	/**
	 * Returns the currently installed database version.
	 */
	protected function get_current_version(): string {
		$version = get_option( self::OPTION_KEY, '0.0.0' );

		return is_string( $version ) ? $version : '0.0.0';
	}

	/**
	 * Updates the installed database version.
	 */
	protected function update_version(): void {
		update_option( self::OPTION_KEY, self::DB_VERSION );
	}
}
