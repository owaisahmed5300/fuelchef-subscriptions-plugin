<?php
/**
 * Settings value object.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Settings;

use FuelChef\Subscriptions\Values\Cutoff_Unit;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's store-wide settings.
 *
 * A setting is not a database row, so this does not go through the
 * Entity/Repository abstraction - see `Settings_Store`.
 */
final class Settings {


	/**
	 * Creates the settings value object.
	 *
	 * @param int    $cutoff_amount How far ahead of fulfilment an order locks.
	 * @param string $cutoff_unit One of the `Cutoff_Unit` constants.
	 * @param int    $subscribe_discount_percent Discount applied when a customer
	 *                                            subscribes, 0-100.
	 * @param string $subscribe_applicability One of the `Subscribe_Applicability`
	 *                                        constants.
	 */
	public function __construct(
		private int $cutoff_amount,
		private string $cutoff_unit,
		private int $subscribe_discount_percent,
		private string $subscribe_applicability
	) {
		if ( $cutoff_amount < 0 ) {
			throw new InvalidArgumentException( esc_html__( 'Cutoff amount cannot be negative.', 'fuelchef-subscriptions' ) );
		}

		if ( ! Cutoff_Unit::is_valid( $cutoff_unit ) ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid cutoff unit.', 'fuelchef-subscriptions' ) );
		}

		if ( $subscribe_discount_percent < 0 || $subscribe_discount_percent > 100 ) {
			throw new InvalidArgumentException(
				esc_html__( 'Subscribe discount must be between 0 and 100.', 'fuelchef-subscriptions' )
			);
		}

		if ( ! Subscribe_Applicability::is_valid( $subscribe_applicability ) ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid subscribe applicability.', 'fuelchef-subscriptions' ) );
		}
	}

	/**
	 * How far ahead of fulfilment an order locks, in `cutoff_unit()` units.
	 */
	public function cutoff_amount(): int {
		return $this->cutoff_amount;
	}

	/**
	 * The unit `cutoff_amount()` is measured in. One of the `Cutoff_Unit` constants.
	 */
	public function cutoff_unit(): string {
		return $this->cutoff_unit;
	}

	/**
	 * Discount percentage applied when a customer subscribes, 0-100.
	 */
	public function subscribe_discount_percent(): int {
		return $this->subscribe_discount_percent;
	}

	/**
	 * Which orders the subscribe discount applies to. One of the
	 * `Subscribe_Applicability` constants.
	 */
	public function subscribe_applicability(): string {
		return $this->subscribe_applicability;
	}
}
