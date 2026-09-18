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
	 * @return list<int> Every day-of-week value.
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
	 * @return list<int> Every day-of-week value, in site week order.
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
	 * Deliberately `__()`, not `esc_html__()`, throughout this class: its only current
	 * caller (`Admin\Assets::strings()`) passes the result to JavaScript, which inserts it
	 * through `FCS.escapeHtml()` when building an HTML string - a pre-escaped value would
	 * be escaped twice.
	 *
	 * @param int $day_of_week Day of week value.
	 */
	public static function label( int $day_of_week ): string {
		$labels = self::labels();

		if ( ! isset( $labels[ $day_of_week ] ) ) {
			throw new InvalidArgumentException(
				__( 'Day of week must be between 0 and 6.', 'fuelchef-subscriptions' )
			);
		}

		return $labels[ $day_of_week ];
	}

	/**
	 * Returns the translated, abbreviated label for a day of week.
	 *
	 * @param int $day_of_week Day of week value.
	 */
	public static function short_label( int $day_of_week ): string {
		$labels = self::short_labels();

		if ( ! isset( $labels[ $day_of_week ] ) ) {
			throw new InvalidArgumentException(
				__( 'Day of week must be between 0 and 6.', 'fuelchef-subscriptions' )
			);
		}

		return $labels[ $day_of_week ];
	}

	/**
	 * Returns every day-of-week label, keyed by value.
	 *
	 * @return array<int, string> The labels, keyed by day-of-week value.
	 */
	private static function labels(): array {
		return [
			self::SUNDAY    => __( 'Sunday', 'fuelchef-subscriptions' ),
			self::MONDAY    => __( 'Monday', 'fuelchef-subscriptions' ),
			self::TUESDAY   => __( 'Tuesday', 'fuelchef-subscriptions' ),
			self::WEDNESDAY => __( 'Wednesday', 'fuelchef-subscriptions' ),
			self::THURSDAY  => __( 'Thursday', 'fuelchef-subscriptions' ),
			self::FRIDAY    => __( 'Friday', 'fuelchef-subscriptions' ),
			self::SATURDAY  => __( 'Saturday', 'fuelchef-subscriptions' ),
		];
	}

	/**
	 * Returns every abbreviated day-of-week label, keyed by value.
	 *
	 * @return array<int, string> The abbreviated labels, keyed by day-of-week value.
	 */
	private static function short_labels(): array {
		return [
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::SUNDAY    => __( 'Sun', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::MONDAY    => __( 'Mon', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::TUESDAY   => __( 'Tue', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::WEDNESDAY => __( 'Wed', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::THURSDAY  => __( 'Thu', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::FRIDAY    => __( 'Fri', 'fuelchef-subscriptions' ),
			/* translators: Abbreviated weekday name, as short as the language allows. */
			self::SATURDAY  => __( 'Sat', 'fuelchef-subscriptions' ),
		];
	}
}
