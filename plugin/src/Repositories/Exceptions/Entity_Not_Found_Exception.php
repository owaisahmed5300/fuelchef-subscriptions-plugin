<?php
/**
 * Entity-not-found exception.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a repository is asked for a row that does not exist.
 */
final class Entity_Not_Found_Exception extends RuntimeException {

	/**
	 * Creates the exception for a repository and the ID it could not find.
	 *
	 * @param class-string $repository_class The repository class.
	 * @param int          $id The ID that was not found.
	 */
	public static function for_id( string $repository_class, int $id ): self {
		return new self(
			sprintf( '%s could not find a row with ID %d.', $repository_class, $id )
		);
	}
}
