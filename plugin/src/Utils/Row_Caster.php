<?php
/**
 * Database row value narrowing.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Narrows a `$wpdb` row's `mixed` column values to a known type.
 *
 * `$wpdb` returns every column as a string (or null), so these are narrowing
 * checks, not real conversions - a repository's `hydrate()` calls them
 * instead of casting a `mixed` value directly, which PHPStan disallows.
 */
final class Row_Caster {


	/**
	 * No instances. This class is a namespace for static helpers only.
	 */
	private function __construct() {
	}

	/**
	 * Narrows a column value to a string.
	 *
	 * @param mixed $value Raw column value.
	 */
	public static function string( mixed $value ): string {
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Narrows a nullable column value to a string or null.
	 *
	 * @param mixed $value Raw column value.
	 */
	public static function nullable_string( mixed $value ): ?string {
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Narrows a column value to an int.
	 *
	 * @param mixed $value Raw column value.
	 */
	public static function int( mixed $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Narrows a nullable column value to an int or null.
	 *
	 * @param mixed $value Raw column value.
	 */
	public static function nullable_int( mixed $value ): ?int {
		return is_numeric( $value ) ? (int) $value : null;
	}

	/**
	 * Narrows a column value to a bool. `$wpdb` returns a tinyint as `'0'`/`'1'`.
	 *
	 * @param mixed $value Raw column value.
	 */
	public static function bool( mixed $value ): bool {
		return is_numeric( $value ) && 0 !== (int) $value;
	}
}
