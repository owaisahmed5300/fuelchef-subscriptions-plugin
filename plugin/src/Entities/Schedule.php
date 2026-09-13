<?php
/**
 * Schedule entity.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Entities;

use FuelChef\Subscriptions\Concerns\Has_Timestamps;
use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Contracts\Timestamped;

defined( 'ABSPATH' ) || exit;

/**
 * A named fulfillment schedule.
 */
final class Schedule implements Entity, Timestamped {

	use Has_Timestamps;

	/**
	 * Row ID, or null before it has been persisted.
	 */
	private ?int $id = null;

	/**
	 * Schedule name.
	 */
	private string $name;

	/**
	 * Creates a schedule.
	 *
	 * @param string $name Schedule name.
	 */
	public function __construct( string $name ) {
		$this->name = $name;
	}

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

	/**
	 * Schedule name.
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Sets the schedule name.
	 *
	 * @param string $name Schedule name.
	 */
	public function set_name( string $name ): static {
		$this->name = $name;

		return $this;
	}
}
