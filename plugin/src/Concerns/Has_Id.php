<?php
/**
 * Identified entity trait.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Concerns;

defined( 'ABSPATH' ) || exit;

/**
 * Backs the Entity contract with storage and accessors.
 */
trait Has_Id {

	/**
	 * Row ID, or null before it has been persisted.
	 */
	private ?int $id = null;

	/**
	 * Row ID, or null before it has been persisted.
	 */
	public function id(): ?int {
		return $this->id;
	}

	/**
	 * Sets the row ID. Called by the repository after an insert.
	 *
	 * @param int $id Row ID.
	 */
	public function set_id( int $id ): static {
		$this->id = $id;

		return $this;
	}
}
