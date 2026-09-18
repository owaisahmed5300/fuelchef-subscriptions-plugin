<?php
/**
 * Schedule entity.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Entities;

use FuelChef\Subscriptions\Entities\Concerns\Has_Id;
use FuelChef\Subscriptions\Entities\Concerns\Has_Timestamps;
use FuelChef\Subscriptions\Entities\Contracts\Entity;
use FuelChef\Subscriptions\Entities\Contracts\Timestamped;

defined( 'ABSPATH' ) || exit;

/**
 * A named fulfilment schedule.
 */
final class Schedule implements Entity, Timestamped {

	use Has_Id;
	use Has_Timestamps;

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
