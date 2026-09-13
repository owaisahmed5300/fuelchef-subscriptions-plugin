<?php
/**
 * Table name registry.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Database;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * The bare name of every table this plugin owns, without the site or `fcs_`
 * prefix.
 *
 * The single source of truth for a table's name: `Installer` builds its
 * schemas from these constants and every repository points its `$table` at
 * one, so a name is never typed twice and the `$wpdb->prefix . 'fcs_'` rule
 * lives in exactly one method.
 */
final class Tables {


	/**
	 * Bare name of the schedules table.
	 */
	public const SCHEDULES = 'schedules';

	/**
	 * Bare name of the schedule weekdays table.
	 */
	public const SCHEDULE_WEEKDAYS = 'schedule_weekdays';

	/**
	 * Bare name of the blackouts table.
	 */
	public const BLACKOUTS = 'blackouts';

	/**
	 * Bare name of the schedule destinations table.
	 */
	public const SCHEDULE_DESTINATIONS = 'schedule_destinations';

	/**
	 * No instances. This class is a namespace for constants only.
	 */
	private function __construct() {
	}

	/**
	 * The full name of one of the plugin's tables, for callers that do not
	 * already have a `$wpdb` instance to hand.
	 *
	 * @param string $name One of this class's constants.
	 *
	 * @return string The name to use in a query, prefixed for the current site.
	 */
	public static function get_full_name( string $name ): string {
		/** @var wpdb $wpdb */
		$wpdb = $GLOBALS['wpdb'];

		return self::prefixed( $wpdb->prefix, $name );
	}

	/**
	 * The full name of one of the plugin's tables, given a site prefix.
	 *
	 * Repositories call this with their own injected `$wpdb->prefix` rather
	 * than `get_full_name()`, so a test can supply a mocked `$wpdb`.
	 *
	 * @param string $site_prefix The site's table prefix, e.g. `$wpdb->prefix`.
	 * @param string $name One of this class's constants.
	 *
	 * @return string The name to use in a query, prefixed for the current site.
	 */
	public static function prefixed( string $site_prefix, string $name ): string {
		return $site_prefix . 'fcs_' . $name;
	}
}
