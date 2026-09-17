<?php
/**
 * Posted-field reading trait.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Concerns;

use FuelChef\Subscriptions\Utils\Narrow;

defined( 'ABSPATH' ) || exit;

/**
 * Reads one `$_POST` field at a time, narrowed and sanitized to a known type.
 */
trait Reads_Request_Fields {


	/**
	 * A posted field, sanitized as plain text.
	 */
	private function posted_text( string $key ): string {
		return sanitize_text_field( wp_unslash( Narrow::string( $_POST[ $key ] ?? null ) ) );
	}

	/**
	 * A posted field, as a non-negative integer.
	 */
	private function posted_int( string $key ): int {
		return absint( Narrow::string( $_POST[ $key ] ?? null ) );
	}
}
