<?php
/**
 * Validation exception.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a service rejects input on a business rule, not a programming error.
 *
 * A controller catches this and sends the message to the browser as JSON, where an admin
 * script displays it as plain text (a toast's `.textContent`, never innerHTML). Every
 * message here is therefore plain, translated text via `__()`, never `esc_html__()` or
 * `esc_html()` on an interpolated value - pre-escaping it would bake HTML entities into the
 * string that plain-text display never decodes, so a customer would see a literal
 * "&quot;" or "&#039;" instead of the character it stands for.
 */
final class Validation_Exception extends RuntimeException {

	/**
	 * Creates the exception for a blank name.
	 */
	public static function for_blank_name(): self {
		return new self( __( 'Name cannot be blank.', 'fuelchef-subscriptions' ) );
	}

	/**
	 * Creates the exception for a date that is not a valid calendar date.
	 *
	 * @param string $value The rejected value.
	 */
	public static function for_invalid_date( string $value ): self {
		return new self(
			sprintf(
				/* translators: %s: the rejected date value. */
				__( '"%s" is not a valid date.', 'fuelchef-subscriptions' ),
				$value
			)
		);
	}

	/**
	 * Creates the exception for a date that already has a blackout.
	 *
	 * @param string $value The duplicate date.
	 */
	public static function for_duplicate_date( string $value ): self {
		return new self(
			sprintf(
				/* translators: %s: the duplicate date. */
				__( '"%s" is already blacked out.', 'fuelchef-subscriptions' ),
				$value
			)
		);
	}

	/**
	 * Creates the exception for a time that is not a valid time of day.
	 *
	 * @param string $value The rejected value.
	 */
	public static function for_invalid_time( string $value ): self {
		return new self(
			sprintf(
				/* translators: %s: the rejected time value. */
				__( '"%s" is not a valid time.', 'fuelchef-subscriptions' ),
				$value
			)
		);
	}

	/**
	 * Creates the exception for an end time that is not after its start time.
	 *
	 * @param string $start_time The rejected start time.
	 * @param string $end_time The rejected end time.
	 */
	public static function for_end_before_start( string $start_time, string $end_time ): self {
		return new self(
			sprintf(
				/* translators: 1: start time, 2: end time. */
				__( 'End time (%2$s) must be after start time (%1$s).', 'fuelchef-subscriptions' ),
				$start_time,
				$end_time
			)
		);
	}

	/**
	 * Creates the exception for a day of week outside 0-6, or one a schedule has no row
	 * for.
	 *
	 * @param int $value The rejected value.
	 */
	public static function for_unknown_day_of_week( int $value ): self {
		return new self(
			sprintf(
				/* translators: %d: the rejected day-of-week value. */
				__( '"%d" is not a known day of week.', 'fuelchef-subscriptions' ),
				$value
			)
		);
	}

	/**
	 * Creates the exception for one or more destinations already assigned to another
	 * schedule. Each conflict becomes its own sentence, so every conflict is reported at
	 * once instead of one at a time across repeated save attempts.
	 *
	 * @param list<array{label: string, schedule_name: string}> $conflicts The rejected
	 *                                                                     destinations.
	 */
	public static function for_destinations_already_assigned( array $conflicts ): self {
		$sentences = array_map(
			static fn ( array $conflict ): string => sprintf(
				/* translators: 1: destination name. 2: the schedule it is already assigned to. */
				__( '"%1$s" is already assigned to "%2$s".', 'fuelchef-subscriptions' ),
				$conflict['label'],
				$conflict['schedule_name']
			),
			$conflicts
		);

		return new self( implode( ' ', $sentences ) );
	}
}
