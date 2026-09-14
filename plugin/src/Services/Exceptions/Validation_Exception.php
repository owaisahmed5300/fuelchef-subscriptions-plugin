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
 * A controller catches this and shows the message to the user, so every message is
 * already translated and safe to display as-is.
 */
final class Validation_Exception extends RuntimeException {

	/**
	 * Creates the exception for a blank name.
	 */
	public static function for_blank_name(): self {
		return new self( esc_html__( 'Name cannot be blank.', 'fuelchef-subscriptions' ) );
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
				esc_html__( '"%s" is not a valid date.', 'fuelchef-subscriptions' ),
				esc_html( $value )
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
				esc_html__( '"%s" is already blacked out.', 'fuelchef-subscriptions' ),
				esc_html( $value )
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
				esc_html__( '"%s" is not a valid time.', 'fuelchef-subscriptions' ),
				esc_html( $value )
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
				esc_html__( '"%d" is not a known day of week.', 'fuelchef-subscriptions' ),
				$value
			)
		);
	}
}
