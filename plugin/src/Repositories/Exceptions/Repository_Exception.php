<?php
/**
 * Repository exception.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a `$wpdb` operation fails.
 */
final class Repository_Exception extends RuntimeException {

	/**
	 * Creates the exception for a failed `$wpdb` operation.
	 *
	 * @param class-string $repository_class The repository class.
	 * @param string       $operation The operation that failed, e.g. `insert`.
	 * @param string       $wpdb_error The last `$wpdb` error message.
	 */
	public static function for_wpdb_error( string $repository_class, string $operation, string $wpdb_error ): self {
		return new self(
			sprintf( '%s failed to %s: %s', $repository_class, $operation, $wpdb_error )
		);
	}
}
