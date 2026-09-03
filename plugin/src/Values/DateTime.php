<?php
/**
 * Date and time value.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Values;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use FuelChef\Subscriptions\Utils\Clock;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Represents an immutable date/time value.
 *
 * Database DATETIME values are stored in UTC.
 * WordPress/site datetime values use the WordPress timezone.
 */
final class DateTime {


	/**
	 * Database datetime format.
	 */
	public const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Database date format, for a `DATE` column.
	 *
	 * A `DATE` is a calendar date, not an instant: it has no time and no
	 * timezone, so converting one between zones is meaningless and can
	 * silently move it a day. Values in this format stay strings - see
	 * {@see self::is_valid_date()}.
	 */
	public const DATABASE_DATE_FORMAT = 'Y-m-d';

	/**
	 * Database time format, for a `TIME` column.
	 */
	public const DATABASE_TIME_FORMAT = 'H:i:s';

	/**
	 * The underlying immutable date/time value.
	 */
	private DateTimeImmutable $date;

	/**
	 * Creates a date/time value.
	 *
	 * @param DateTimeImmutable $date Date/time value.
	 */
	private function __construct( DateTimeImmutable $date ) {
		$this->date = $date;
	}

	/**
	 * Creates a DateTimeValue from a PHP date/time object.
	 *
	 * Accepts both DateTime and DateTimeImmutable.
	 *
	 * @param DateTimeInterface $date Date/time value.
	 */
	public static function from( DateTimeInterface $date ): self {
		return new self(
			DateTimeImmutable::createFromInterface( $date )
		);
	}

	/**
	 * Creates a DateTimeValue from a database datetime.
	 *
	 * Database datetimes are expected to be stored in UTC.
	 *
	 * @param string $value Database datetime in UTC.
	 *
	 * @throws InvalidArgumentException When the value is invalid.
	 */
	public static function from_database( string $value ): self {
		return new self(
			self::create_datetime(
				$value,
				self::DATABASE_DATETIME_FORMAT,
				Clock::utc_timezone()
			)
		);
	}

	/**
	 * Creates a DateTimeValue from a WordPress/site datetime.
	 *
	 * The value is interpreted using the WordPress timezone.
	 *
	 * @param string $value Local WordPress datetime.
	 *
	 * @throws InvalidArgumentException When the value is invalid.
	 */
	public static function from_wp( string $value ): self {
		return new self(
			self::create_datetime(
				$value,
				self::DATABASE_DATETIME_FORMAT,
				Clock::wp_timezone()
			)
		);
	}

	/**
	 * Returns this value in UTC.
	 */
	public function in_utc(): self {
		return new self(
			$this->date->setTimezone(
				Clock::utc_timezone()
			)
		);
	}

	/**
	 * Returns this value in the WordPress/site timezone.
	 */
	public function in_wp_timezone(): self {
		return new self(
			$this->date->setTimezone(
				Clock::wp_timezone()
			)
		);
	}

	/**
	 * Returns this value in the specified timezone.
	 *
	 * @param DateTimeZone $timezone Target timezone.
	 */
	public function in_timezone( DateTimeZone $timezone ): self {
		return new self(
			$this->date->setTimezone( $timezone )
		);
	}

	/**
	 * Converts this value to a database datetime.
	 *
	 * The returned value is always formatted in UTC.
	 */
	public function to_database(): string {
		return $this->in_utc()->format(
			self::DATABASE_DATETIME_FORMAT
		);
	}

	/**
	 * Returns the underlying DateTimeImmutable.
	 */
	public function native(): DateTimeImmutable {
		return $this->date;
	}

	/**
	 * Formats the date/time value.
	 *
	 * @param string $format PHP date format.
	 */
	public function format( string $format ): string {
		return $this->date->format( $format );
	}

	/**
	 * Returns the timezone of this value.
	 */
	public function timezone(): DateTimeZone {
		return $this->date->getTimezone();
	}

	/**
	 * Creates a DateTimeImmutable using a strict format.
	 *
	 * @param string       $value Value to parse.
	 * @param string       $format Expected format.
	 * @param DateTimeZone $timezone Timezone.
	 *
	 * @throws InvalidArgumentException When the value is invalid.
	 */
	private static function create_datetime(
		string $value,
		string $format,
		DateTimeZone $timezone
	): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat(
			'!' . $format,
			$value,
			$timezone
		);

		$errors = DateTimeImmutable::getLastErrors();

		if (
			false === $date
			|| (
				is_array( $errors )
				&& (
					0 < $errors['warning_count']
					|| 0 < $errors['error_count']
				)
			)
			|| $date->format( $format ) !== $value
		) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: %s: expected date/time format, e.g. Y-m-d H:i:s (not translated). */
					esc_html__( 'Invalid date/time value. Expected format: %s.', 'fuelchef-subscriptions' ),
					$format
				)
			);
		}

		return $date;
	}

	/**
	 * Checks whether a value is a calendar date in `Y-m-d` form.
	 *
	 * Rejects a date that parses but does not exist, such as `2026-02-30`.
	 *
	 * @param string $value Value to validate.
	 */
	public static function is_valid_date( string $value ): bool {
		return self::is_valid_datetime(
			$value,
			self::DATABASE_DATE_FORMAT,
			Clock::utc_timezone()
		);
	}

	/**
	 * Checks whether a value is a time of day in `H:i:s` form.
	 *
	 * @param string $value Value to validate.
	 */
	public static function is_valid_time( string $value ): bool {
		return self::is_valid_datetime(
			$value,
			self::DATABASE_TIME_FORMAT,
			Clock::utc_timezone()
		);
	}

	/**
	 * Checks whether a value is a valid date/time.
	 *
	 * @param string       $value Value to validate.
	 * @param string       $format Expected format.
	 * @param DateTimeZone $timezone Timezone.
	 */
	public static function is_valid_datetime(
		string $value,
		string $format,
		DateTimeZone $timezone
	): bool {
		try {
			self::create_datetime(
				$value,
				$format,
				$timezone
			);

			return true;
		} catch ( InvalidArgumentException ) {
			return false;
		}
	}
}
