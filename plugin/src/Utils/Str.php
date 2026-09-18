<?php
/**
 * String utility.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Provides common string operations.
 *
 * This class contains generic string helpers that are useful across the
 * application. Domain-specific validation belongs to the relevant domain
 * object or value object.
 */
final class Str {


	/**
	 * Determines whether a string is blank.
	 *
	 * A string is considered blank when it is null or contains only whitespace.
	 *
	 * @param string|null $value String value.
	 *
	 * @return bool True when the value is blank.
	 */
	public static function is_blank( ?string $value ): bool {
		return null === $value || '' === trim( $value );
	}

	/**
	 * Turns a blank string into null.
	 *
	 * @param string $value String value.
	 *
	 * @return string|null The value, or null when it is blank.
	 */
	public static function blank_to_null( string $value ): ?string {
		return self::is_blank( $value ) ? null : $value;
	}

	/**
	 * Returns the length of a string in characters.
	 *
	 * Uses multibyte string handling when available and falls back to the
	 * standard string length otherwise.
	 *
	 * @param string $value String value.
	 *
	 * @return int String length.
	 */
	public static function length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value );
		}

		return strlen( $value );
	}

	/**
	 * Returns part of a string, counted in characters.
	 *
	 * @param string   $value String value.
	 * @param int      $start Character to start at.
	 * @param int|null $length How many characters to take.
	 *
	 * @return string The extracted part.
	 */
	public static function substr( string $value, int $start, ?int $length = null ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, $start, $length );
		}

		return null === $length ? substr( $value, $start ) : substr( $value, $start, $length );
	}
}
