<?php
/**
 * Request input narrowing.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Narrows a `$_POST`/`$_GET` value's `mixed` type to a string.
 *
 * A superglobal entry is `string` for every field this plugin reads; the narrowing
 * itself is what PHPStan's strict rules need before the value can reach `absint()` or
 * `sanitize_text_field()`, not a sanitization step - those functions still do that.
 */
final class Input {


	/**
	 * No instances. This class is a namespace for static helpers only.
	 */
	private function __construct() {
		// No instances. See class docblock.
	}

	/**
	 * Narrows a raw request value to a string.
	 *
	 * @param mixed $value Raw request value.
	 */
	public static function string( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
