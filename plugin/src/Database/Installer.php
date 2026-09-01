<?php
/**
 * Site database installer.
 */

declare( strict_types=1 );

namespace WPPluginBoilerplate\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Installs and upgrades site-scoped database tables.
 */
final class Installer extends Abstract_Installer {

	/**
	 * Database Version
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Site database version option.
	 */
	public const OPTION_KEY = 'wp_plugin_boilerplate_db_version';

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
	 * @inheritDoc
	 */
	protected function get_current_version(): string {
		return (string) get_option( self::OPTION_KEY, '0.0.0' );
	}

	/**
	 * @inheritDoc
	 */
	protected function update_version(): void {
		update_option( self::OPTION_KEY, self::DB_VERSION );
	}
}
