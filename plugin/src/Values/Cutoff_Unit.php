<?php
/**
 * Cutoff unit enum value.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Values;

defined( 'ABSPATH' ) || exit;

/**
 * The unit a store's order-cutoff window is measured in.
 *
 * PHP 8.0 has no native enum type, so fixed value sets are represented as a final class
 * of named constants plus validation helpers. This class has no instances; it only
 * describes the valid values of a `string cutoff_unit`.
 */
final class Cutoff_Unit {


	public const HOURS = 'hours';
	public const DAYS  = 'days';

	/**
	 * Prevents instantiation. This class is a namespace for constants and static helpers
	 * only.
	 */
	private function __construct() {
		// No instances. See class docblock.
	}

	/**
	 * Returns every valid cutoff unit.
	 *
	 * @return list<string> Every valid cutoff unit.
	 */
	public static function all(): array {
		return [
			self::HOURS,
			self::DAYS,
		];
	}

	/**
	 * Determines whether a value is a valid cutoff unit.
	 *
	 * @param string $unit Cutoff unit value.
	 */
	public static function is_valid( string $unit ): bool {
		return in_array( $unit, self::all(), true );
	}

	/**
	 * Returns the translated, human-readable label for a cutoff unit.
	 *
	 * Falls back to the unit's own value when it is not a known one, since this is a
	 * display helper, not a validator.
	 *
	 * @param string $unit Cutoff unit value.
	 */
	public static function label( string $unit ): string {
		$labels = [
			self::HOURS => esc_html__( 'hours', 'fuelchef-subscriptions' ),
			self::DAYS  => esc_html__( 'days', 'fuelchef-subscriptions' ),
		];

		return $labels[ $unit ] ?? $unit;
	}
}
