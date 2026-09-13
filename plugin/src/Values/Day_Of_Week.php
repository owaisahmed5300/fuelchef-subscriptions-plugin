<?php
/**
 * Day-of-week enum value.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Values;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a day of the week, from 0 (Sunday) to 6 (Saturday).
 *
 * PHP 8.0 has no native enum type, so fixed value sets are represented as a
 * final class of named constants plus validation helpers. This class has no
 * instances; it only describes the valid range of an `int day_of_week`.
 */
final class Day_Of_Week {


	public const SUNDAY    = 0;
	public const MONDAY    = 1;
	public const TUESDAY   = 2;
	public const WEDNESDAY = 3;
	public const THURSDAY  = 4;
	public const FRIDAY    = 5;
	public const SATURDAY  = 6;

	/**
	 * Prevents instantiation. This class is a namespace for constants and
	 * static helpers only.
	 */
	private function __construct() {
		// No instances. See class docblock.
	}

	/**
	 * Returns every valid day-of-week value, Sunday through Saturday.
	 *
	 * @return list<int>
	 */
	public static function all(): array {
		return [
			self::SUNDAY,
			self::MONDAY,
			self::TUESDAY,
			self::WEDNESDAY,
			self::THURSDAY,
			self::FRIDAY,
			self::SATURDAY,
		];
	}

	/**
	 * Returns every day of week, starting on the day the site starts its
	 * week on.
	 *
	 * @return list<int>
	 */
	public static function in_site_order(): array {
		$raw_start = get_option( 'start_of_week', 0 );
		$start     = is_numeric( $raw_start ) ? (int) $raw_start : 0;
		$days      = [];

		for ( $offset = 0; $offset < 7; $offset++ ) {
			$days[] = ( $start + $offset ) % 7;
		}

		return $days;
	}

	/**
	 * Determines whether a value is a valid day of week.
	 *
	 * @param int $day_of_week Day of week value.
	 */
	public static function is_valid( int $day_of_week ): bool {
		return $day_of_week >= self::SUNDAY && $day_of_week <= self::SATURDAY;
	}

	/**
	 * Returns the translated, human-readable label for a day of week.
	 *
	 * @param int $day_of_week Day of week value.
	 *
	 * @throws InvalidArgumentException When the value is not a day of week.
	 */
	public static function label( int $day_of_week ): string {
		$labels = self::labels();

		if ( ! isset( $labels[ $day_of_week ] ) ) {
			throw new InvalidArgumentException(
				esc_html__( 'Day of week must be between 0 and 6.', 'fuelchef-subscriptions' )
			);
		}

		return $labels[ $day_of_week ];
	}

	/**
	 * Returns the translated, abbreviated label for a day of week.
	 *
	 * @param int $day_of_week Day of week value.
	 *
	 * @throws InvalidArgumentException When the value is not a day of week.
	 */
	public static function short_label( int $day_of_week ): string {
		$labels = self::short_labels();

		if ( ! isset( $labels[ $day_of_week ] ) ) {
			throw new InvalidArgumentException(
				esc_html__( 'Day of week must be between 0 and 6.', 'fuelchef-subscriptions' )
			);
		}

		return $labels[ $day_of_week ];
	}

	/**
	 * Returns every day-of-week label, keyed by value.
	 *
	 * @return array<int, string>
	 */
	private static function labels(): array {
		return [
			self::SUNDAY    => esc_html__( 'Sunday', 'fuelchef-subscriptions' ),
			self::MONDAY    => esc_html__( 'Monday', 'fuelchef-subscriptions' ),
			self::TUESDAY   => esc_html__( 'Tuesday', 'fuelchef-subscriptions' ),
			self::WEDNESDAY => esc_html__( 'Wednesday', 'fuelchef-subscriptions' ),
			self::THURSDAY  => esc_html__( 'Thursday', 'fuelchef-subscriptions' ),
			self::FRIDAY    => esc_html__( 'Friday', 'fuelchef-subscriptions' ),
			self::SATURDAY  => esc_html__( 'Saturday', 'fuelchef-subscriptions' ),
		];
	}

	/**
	 * Returns every abbreviated day-of-week label, keyed by value.
	 *
	 * @return array<int, string>
	 */
	private static function short_labels(): array {
		return [
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::SUNDAY    => esc_html__( 'Sun', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::MONDAY    => esc_html__( 'Mon', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::TUESDAY   => esc_html__( 'Tue', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::WEDNESDAY => esc_html__( 'Wed', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::THURSDAY  => esc_html__( 'Thu', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::FRIDAY    => esc_html__( 'Fri', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::SATURDAY  => esc_html__( 'Sat', 'fuelchef-subscriptions' ),
		];
	}
}
