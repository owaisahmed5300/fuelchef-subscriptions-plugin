<?php
/**
 * Timestamped entity trait.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Entities\Concerns;

use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Backs the Timestamped contract with storage and accessors.
 */
trait Has_Timestamps {

	/**
	 * When the row was created.
	 */
	private ?DateTime $date_created = null;

	/**
	 * When the row was last updated.
	 */
	private ?DateTime $date_updated = null;

	/**
	 * When the row was created, or null before it has been persisted.
	 */
	public function date_created(): ?DateTime {
		return $this->date_created;
	}

	/**
	 * Sets when the row was created.
	 */
	public function set_date_created( ?DateTime $date_created ): static {
		$this->date_created = $date_created;

		return $this;
	}

	/**
	 * When the row was last updated, or null before it has been persisted.
	 */
	public function date_updated(): ?DateTime {
		return $this->date_updated;
	}

	/**
	 * Sets when the row was last updated.
	 */
	public function set_date_updated( ?DateTime $date_updated ): static {
		$this->date_updated = $date_updated;

		return $this;
	}
}
