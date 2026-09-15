<?php
/**
 * Schedule destination entity.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Entities;

use FuelChef\Subscriptions\Concerns\Has_Id;
use FuelChef\Subscriptions\Concerns\Has_Timestamps;
use FuelChef\Subscriptions\Contracts\Entity;
use FuelChef\Subscriptions\Contracts\Timestamped;
use FuelChef\Subscriptions\Values\Destination_Type;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * A shipping zone or pickup location a schedule fulfils.
 */
final class Schedule_Destination implements Entity, Timestamped {

	use Has_Id;
	use Has_Timestamps;

	/**
	 * The schedule this destination is assigned to.
	 */
	private int $schedule_id;

	/**
	 * The kind of destination. One of the `Destination_Type` constants.
	 */
	private string $destination_type;

	/**
	 * Identifier of the destination within its type, e.g. a WooCommerce
	 * shipping zone ID.
	 */
	private string $destination_key;

	/**
	 * Creates a schedule destination.
	 *
	 * @param int    $schedule_id Schedule ID.
	 * @param string $destination_type One of the `Destination_Type` constants.
	 * @param string $destination_key Identifier within that type.
	 */
	public function __construct( int $schedule_id, string $destination_type, string $destination_key ) {
		if ( ! Destination_Type::is_valid( $destination_type ) ) {
			throw new InvalidArgumentException(
				esc_html__( 'Invalid destination type.', 'fuelchef-subscriptions' )
			);
		}

		$this->schedule_id      = $schedule_id;
		$this->destination_type = $destination_type;
		$this->destination_key  = $destination_key;
	}

	/**
	 * The schedule this destination is assigned to.
	 */
	public function schedule_id(): int {
		return $this->schedule_id;
	}

	/**
	 * The kind of destination. One of the `Destination_Type` constants.
	 */
	public function destination_type(): string {
		return $this->destination_type;
	}

	/**
	 * Identifier of the destination within its type.
	 */
	public function destination_key(): string {
		return $this->destination_key;
	}
}
