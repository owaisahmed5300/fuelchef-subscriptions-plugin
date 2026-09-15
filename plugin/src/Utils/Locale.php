<?php
/**
 * WordPress locale access.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Utils;

use WP_Locale;

defined( 'ABSPATH' ) || exit;

/**
 * Reads WordPress's own translated locale data.
 */
final class Locale {


	/**
	 * No instances. This class is a namespace for static helpers only.
	 */
	private function __construct() {
		// No instances. See class docblock.
	}

	/**
	 * WordPress's own translated month and weekday names, already maintained by core
	 * translators - reused instead of asking for the same strings again.
	 */
	public static function current(): WP_Locale {
		/** @var WP_Locale $wp_locale */
		$wp_locale = $GLOBALS['wp_locale'];

		return $wp_locale;
	}
}
