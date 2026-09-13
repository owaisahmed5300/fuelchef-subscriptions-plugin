<?php
/**
 * Entity contract.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * A value object backed by one database row.
 */
interface Entity {

	/**
	 * Row ID, or null before it has been persisted.
	 */
	public function id(): ?int;

	/**
	 * Sets the row ID. Called by the repository after an insert.
	 *
	 * @param int $id Row ID.
	 */
	public function set_id( int $id ): static;
}
