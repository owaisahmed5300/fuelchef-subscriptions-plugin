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
	 * @return list<callable(): string> One closure per table, each returning its
	 *                                  `CREATE TABLE` statement.
	 */
	protected function schemas(): array {
		return [
			fn (): string => $this->schema_schedules(),
			fn (): string => $this->schema_schedule_weekdays(),
			fn (): string => $this->schema_blackouts(),
			fn (): string => $this->schema_schedule_destinations(),
		];
	}

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

	/**
	 * Schema for the schedules table.
	 */
	protected function schema_schedules(): string {
		$table           = self::table( Tables::SCHEDULES );
		$charset_collate = $this->charset_collate();

		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			date_created DATETIME NOT NULL,
			date_updated DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";
	}

	/**
	 * Schema for the schedule weekdays table.
	 */
	protected function schema_schedule_weekdays(): string {
		$table           = self::table( Tables::SCHEDULE_WEEKDAYS );
		$charset_collate = $this->charset_collate();

		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			schedule_id BIGINT UNSIGNED NOT NULL,
			day_of_week TINYINT UNSIGNED NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 0,
			start_time TIME NOT NULL DEFAULT '12:00:00',
			end_time TIME NOT NULL DEFAULT '17:00:00',
			date_created DATETIME NOT NULL,
			date_updated DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY schedule_day (schedule_id, day_of_week)
		) {$charset_collate};";
	}

	/**
	 * Schema for the blackouts table.
	 *
	 * `schedule_id` is nullable: null means a store-wide closure rather than
	 * one scoped to a schedule.
	 */
	protected function schema_blackouts(): string {
		$table           = self::table( Tables::BLACKOUTS );
		$charset_collate = $this->charset_collate();

		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			schedule_id BIGINT UNSIGNED NULL,
			blackout_date DATE NOT NULL,
			reason VARCHAR(255) NULL,
			date_created DATETIME NOT NULL,
			date_updated DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY schedule_id (schedule_id),
			KEY blackout_date (blackout_date)
		) {$charset_collate};";
	}

	/**
	 * Schema for the schedule destinations table.
	 */
	protected function schema_schedule_destinations(): string {
		$table           = self::table( Tables::SCHEDULE_DESTINATIONS );
		$charset_collate = $this->charset_collate();

		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			schedule_id BIGINT UNSIGNED NOT NULL,
			destination_type VARCHAR(20) NOT NULL,
			destination_key VARCHAR(191) NOT NULL,
			date_created DATETIME NOT NULL,
			date_updated DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY schedule_destination (schedule_id, destination_type, destination_key)
		) {$charset_collate};";
	}
}
