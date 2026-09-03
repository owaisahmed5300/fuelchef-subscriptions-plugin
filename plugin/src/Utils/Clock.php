<?php
/**
 * Provides access to the current date and time.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Utils;

use DateTimeImmutable;
use DateTimeZone;
use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Provides current date and time utilities.
 */
final class Clock {

	/**
	 * Returns the UTC timezone.
	 */
	public static function utc_timezone(): DateTimeZone {
		return new DateTimeZone( 'UTC' );
	}

	/**
	 * Returns the WordPress timezone.
	 */
	public static function wp_timezone(): DateTimeZone {
		return wp_timezone();
	}

	/**
	 * Returns the current UTC time.
	 */
	public function now(): DateTime {
		return DateTime::from(
			new DateTimeImmutable(
				'now',
				self::utc_timezone()
			)
		);
	}

	/**
	 * Returns the current WordPress/site time.
	 */
	public function now_wp(): DateTime {
		return $this->now()->in_wp_timezone();
	}
}
