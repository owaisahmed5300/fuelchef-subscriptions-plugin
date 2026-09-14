<?php
/**
 * Subscribe-and-save applicability enum value.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Values;

defined( 'ABSPATH' ) || exit;

/**
 * Which orders the subscribe-and-save discount applies to.
 *
 * PHP 8.0 has no native enum type, so fixed value sets are represented as a final class
 * of named constants plus validation helpers. This class has no instances; it only
 * describes the valid values of a `string subscribe_applicability`.
 */
final class Subscribe_Applicability {


	public const INITIAL_AND_RENEWALS = 'initial_and_renewals';
	public const RENEWAL_ONLY         = 'renewal_only';

	/**
	 * Prevents instantiation. This class is a namespace for constants and static helpers
	 * only.
	 */
	private function __construct() {
		// No instances. See class docblock.
	}

	/**
	 * Returns every valid applicability value.
	 *
	 * @return list<string> Every valid applicability value.
	 */
	public static function all(): array {
		return [
			self::INITIAL_AND_RENEWALS,
			self::RENEWAL_ONLY,
		];
	}

	/**
	 * Determines whether a value is a valid applicability value.
	 *
	 * @param string $applicability Applicability value.
	 */
	public static function is_valid( string $applicability ): bool {
		return in_array( $applicability, self::all(), true );
	}

	/**
	 * Returns the translated, human-readable label for an applicability value.
	 *
	 * Falls back to the value itself when it is not a known one, since this is a display
	 * helper, not a validator.
	 *
	 * @param string $applicability Applicability value.
	 */
	public static function label( string $applicability ): string {
		$labels = [
			self::INITIAL_AND_RENEWALS => esc_html__( 'Initial order + renewals', 'fuelchef-subscriptions' ),
			self::RENEWAL_ONLY         => esc_html__( 'Renewals only', 'fuelchef-subscriptions' ),
		];

		return $labels[ $applicability ] ?? $applicability;
	}
}
