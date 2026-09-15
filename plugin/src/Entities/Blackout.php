<?php
/**
 * Blackout entity.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Entities;

use FuelChef\Subscriptions\Concerns\Has_Id;
use FuelChef\Subscriptions\Concerns\Has_Timestamps;
use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Contracts\Timestamped;

defined( 'ABSPATH' ) || exit;

/**
 * A date fulfilment does not happen on.
 *
 * A null schedule ID means a store-wide closure; a set one means it applies
 * only to that schedule.
 */
final class Blackout implements Entity, Timestamped {

	use Has_Id;
	use Has_Timestamps;

	/**
	 * The schedule this blackout applies to, or null for store-wide.
	 */
	private ?int $schedule_id;

	/**
	 * The blocked calendar date, in `Y-m-d` form.
	 */
	private string $date;

	/**
	 * Optional note explaining the closure.
	 */
	private ?string $reason;

	/**
	 * Creates a blackout.
	 *
	 * @param int|null    $schedule_id Schedule ID, or null for store-wide.
	 * @param string      $date Blocked calendar date, in `Y-m-d` form.
	 * @param string|null $reason Optional note explaining the closure.
	 */
	public function __construct( ?int $schedule_id, string $date, ?string $reason = null ) {
		$this->schedule_id = $schedule_id;
		$this->date        = $date;
		$this->reason      = $reason;
	}

	/**
	 * The schedule this blackout applies to, or null for store-wide.
	 */
	public function schedule_id(): ?int {
		return $this->schedule_id;
	}

	/**
	 * The blocked calendar date, in `Y-m-d` form.
	 */
	public function date(): string {
		return $this->date;
	}

	/**
	 * Sets the blocked calendar date.
	 *
	 * @param string $date Calendar date, in `Y-m-d` form.
	 */
	public function set_date( string $date ): static {
		$this->date = $date;

		return $this;
	}

	/**
	 * Optional note explaining the closure.
	 */
	public function reason(): ?string {
		return $this->reason;
	}

	/**
	 * Sets the note explaining the closure.
	 *
	 * @param string|null $reason Note, or null to clear it.
	 */
	public function set_reason( ?string $reason ): static {
		$this->reason = $reason;

		return $this;
	}
}
