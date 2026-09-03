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
	 * No instances. This class is a namespace for constants only.
	 */
	private function __construct() {
	}

	/**
	 * The full name of one of the plugin's tables.
	 *
	 * Static because repositories need it without taking the installer as a
	 * dependency, and the answer depends only on `$wpdb`.
	 *
	 * @param string $name One of this class's constants.
	 *
	 * @return string The name to use in a query, prefixed for the current site.
	 */
	public static function get_full_name( string $name ): string {
		/** @var wpdb $wpdb */
		$wpdb = $GLOBALS['wpdb'];

		return $wpdb->prefix . 'fcs_' . $name;
	}
}
