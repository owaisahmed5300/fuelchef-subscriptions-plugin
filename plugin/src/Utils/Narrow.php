<?php
/**
 * Untyped value narrowing.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Narrows a `mixed` value from an untyped source to a known PHP type.
 *
 * Two sources hand this plugin values with no static type: a `$wpdb` row (every column
 * comes back as `string` or `null`) and a `$_POST`/`$_GET` superglobal entry. Both need the
 * same kind of narrowing check before PHPStan's strict rules allow the value anywhere
 * further - a repository's `hydrate()` and a controller's request reading both call these
 * instead of casting a `mixed` value directly.
 */
final class Narrow {


	/**
	 * No instances. This class is a namespace for static helpers only.
	 */
	private function __construct() {
		// No instances. See class docblock.
	}

	/**
	 * Narrows a value to a string.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function string( mixed $value ): string {
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Narrows a value to a string or null.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function nullable_string( mixed $value ): ?string {
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Narrows a value to an int.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function int( mixed $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Narrows a value to an int or null.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function nullable_int( mixed $value ): ?int {
		return is_numeric( $value ) ? (int) $value : null;
	}

	/**
	 * Narrows a value to a bool. `$wpdb` returns a tinyint as `'0'`/`'1'`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function bool( mixed $value ): bool {
		return is_numeric( $value ) && 0 !== (int) $value;
	}
}
