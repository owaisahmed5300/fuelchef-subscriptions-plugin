<?php
/**
 * Destination type enum value.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Values;

defined( 'ABSPATH' ) || exit;

/**
 * The kind of place a schedule can be assigned to fulfil.
 *
 * PHP 8.0 has no native enum type, so fixed value sets are represented as a
 * final class of named constants plus validation helpers. This class has no
 * instances; it only describes the valid values of a `string destination_type`.
 */
final class Destination_Type {


	public const SHIPPING_ZONE   = 'shipping_zone';
	public const PICKUP_LOCATION = 'pickup_location';

	/**
	 * Prevents instantiation. This class is a namespace for constants and
	 * static helpers only.
	 */
	private function __construct() {
		// No instances. See class docblock.
	}

	/**
	 * Returns every valid destination type.
	 *
	 * @return list<string> Every valid destination type.
	 */
	public static function all(): array {
		return [
			self::SHIPPING_ZONE,
			self::PICKUP_LOCATION,
		];
	}

	/**
	 * Determines whether a value is a valid destination type.
	 *
	 * @param string $destination_type Destination type value.
	 */
	public static function is_valid( string $destination_type ): bool {
		return in_array( $destination_type, self::all(), true );
	}

	/**
	 * Returns the translated, human-readable label for a destination type.
	 *
	 * Falls back to the value itself when it is not a known one, since this is a display
	 * helper, not a validator.
	 *
	 * @param string $destination_type Destination type value.
	 */
	public static function label( string $destination_type ): string {
		$labels = [
			self::SHIPPING_ZONE   => __( 'Shipping zone', 'fuelchef-subscriptions' ),
			self::PICKUP_LOCATION => __( 'Pickup location', 'fuelchef-subscriptions' ),
		];

		return $labels[ $destination_type ] ?? $destination_type;
	}
}
