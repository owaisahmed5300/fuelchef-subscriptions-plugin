<?php
/**
 * Schedule weekday entity.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Entities;

use FuelChef\Subscriptions\Concerns\Has_Id;
use FuelChef\Subscriptions\Concerns\Has_Timestamps;
use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Contracts\Timestamped;
use FuelChef\Subscriptions\Values\Day_Of_Week;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * One day of a schedule's weekly fulfilment hours.
 */
final class Schedule_Weekday implements Entity, Timestamped {

	use Has_Id;
	use Has_Timestamps;

	/**
	 * The schedule this weekday belongs to.
	 */
	private int $schedule_id;

	/**
	 * Day of week, 0 (Sunday) through 6 (Saturday).
	 */
	private int $day_of_week;

	/**
	 * Whether fulfilment happens on this day.
	 */
	private bool $enabled;

	/**
	 * Earliest fulfilment time on this day, in `H:i:s` form.
	 */
	private string $start_time;

	/**
	 * Latest fulfilment time on this day, in `H:i:s` form.
	 */
	private string $end_time;

	/**
	 * Creates a schedule weekday.
	 *
	 * @param int    $schedule_id Schedule ID.
	 * @param int    $day_of_week Day of week, 0 (Sunday) through 6 (Saturday).
	 * @param bool   $enabled Whether fulfilment happens on this day.
	 * @param string $start_time Earliest fulfilment time, in `H:i:s` form.
	 * @param string $end_time Latest fulfilment time, in `H:i:s` form.
	 */
	public function __construct(
		int $schedule_id,
		int $day_of_week,
		bool $enabled,
		string $start_time,
		string $end_time
	) {
		if ( ! Day_Of_Week::is_valid( $day_of_week ) ) {
			throw new InvalidArgumentException(
				__( 'Day of week must be between 0 and 6.', 'fuelchef-subscriptions' )
			);
		}

		$this->schedule_id = $schedule_id;
		$this->day_of_week = $day_of_week;
		$this->enabled     = $enabled;
		$this->start_time  = $start_time;
		$this->end_time    = $end_time;
	}

	/**
	 * The schedule this weekday belongs to.
	 */
	public function schedule_id(): int {
		return $this->schedule_id;
	}

	/**
	 * Day of week, 0 (Sunday) through 6 (Saturday).
	 */
	public function day_of_week(): int {
		return $this->day_of_week;
	}

	/**
	 * Whether fulfilment happens on this day.
	 */
	public function enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Sets whether fulfilment happens on this day.
	 *
	 * @param bool $enabled Whether fulfilment happens on this day.
	 */
	public function set_enabled( bool $enabled ): static {
		$this->enabled = $enabled;

		return $this;
	}

	/**
	 * Earliest fulfilment time on this day, in `H:i:s` form.
	 */
	public function start_time(): string {
		return $this->start_time;
	}

	/**
	 * Sets the earliest fulfilment time on this day.
	 *
	 * @param string $start_time Time of day, in `H:i:s` form.
	 */
	public function set_start_time( string $start_time ): static {
		$this->start_time = $start_time;

		return $this;
	}

	/**
	 * Latest fulfilment time on this day, in `H:i:s` form.
	 */
	public function end_time(): string {
		return $this->end_time;
	}

	/**
	 * Sets the latest fulfilment time on this day.
	 *
	 * @param string $end_time Time of day, in `H:i:s` form.
	 */
	public function set_end_time( string $end_time ): static {
		$this->end_time = $end_time;

		return $this;
	}
}
