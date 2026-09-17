<?php
/**
 * Settings value object.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Values;

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
	 * The longest a customer-facing label may be.
	 */
	public const MAX_LABEL_LENGTH = 190;

	/**
	 * The longest a customer-facing description may be.
	 */
	public const MAX_DESCRIPTION_LENGTH = 300;

	/**
	 * The most days into the future a fulfilment window may reach. Matches
	 * `Availability_Service::walk_open_dates()`'s own range-walk safety cap, so a
	 * configured window can never exceed what the service actually honours.
	 */
	public const MAX_FULFILMENT_WINDOW_DAYS = 730;

	/**
	 * Creates the settings value object.
	 *
	 * @param int    $cutoff_days How many days before the fulfilment date an order locks.
	 * @param string $cutoff_time The time of day an order locks, in `H:i:s` form.
	 * @param int    $subscribe_discount_percent Discount applied when a customer
	 *                                            subscribes, 0-100.
	 * @param string $subscribe_applicability One of the `Subscribe_Applicability`
	 *                                        constants.
	 * @param int    $max_fulfilment_window_days How many days into the future a customer
	 *                                           can choose a fulfilment date.
	 * @param string $fulfilment_date_label Checkout field label for the fulfilment date.
	 * @param string $fulfilment_date_description Checkout help text for the fulfilment
	 *                                            date, or an empty string to show none.
	 * @param string $subscribe_save_label Checkout checkbox label for the subscribe
	 *                                     discount. May contain the placeholder
	 *                                     `{percent}`, replaced with the current discount.
	 * @param string $subscribe_save_description Checkout help text for the subscribe
	 *                                           discount, or an empty string to show none.
	 *                                           May contain the placeholder `{percent}`.
	 */
	public function __construct(
		private int $cutoff_days,
		private string $cutoff_time,
		private int $subscribe_discount_percent,
		private string $subscribe_applicability,
		private int $max_fulfilment_window_days,
		private string $fulfilment_date_label,
		private string $fulfilment_date_description,
		private string $subscribe_save_label,
		private string $subscribe_save_description
	) {
		if ( $cutoff_days < 0 ) {
			throw new InvalidArgumentException( esc_html__( 'Cutoff days cannot be negative.', 'fuelchef-subscriptions' ) );
		}

		if ( ! DateTime::is_valid_time( $cutoff_time ) ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid cutoff time.', 'fuelchef-subscriptions' ) );
		}

		if ( $subscribe_discount_percent < 0 || $subscribe_discount_percent > 100 ) {
			throw new InvalidArgumentException(
				esc_html__( 'Subscribe discount must be between 0 and 100.', 'fuelchef-subscriptions' )
			);
		}

		if ( ! Subscribe_Applicability::is_valid( $subscribe_applicability ) ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid subscribe applicability.', 'fuelchef-subscriptions' ) );
		}

		if ( $max_fulfilment_window_days < 1 ) {
			throw new InvalidArgumentException(
				esc_html__( 'Maximum fulfilment window must be at least 1 day.', 'fuelchef-subscriptions' )
			);
		}

		if ( $max_fulfilment_window_days > self::MAX_FULFILMENT_WINDOW_DAYS ) {
			throw new InvalidArgumentException(
				esc_html__( 'Maximum fulfilment window is too far in the future.', 'fuelchef-subscriptions' )
			);
		}

		// Reads the promoted properties above, already assigned by this point - not their
		// local parameter names, since this validates the finished object, not the call.
		$this->validate_checkout_copy();
	}

	/**
	 * Rejects a blank or over-length fulfilment date or subscribe-and-save label, or an
	 * over-length description. A description may be blank - that means the store shows
	 * none.
	 */
	private function validate_checkout_copy(): void {
		if ( '' === trim( $this->fulfilment_date_label ) ) {
			throw new InvalidArgumentException( esc_html__( 'Fulfilment date label cannot be blank.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->fulfilment_date_label ) > self::MAX_LABEL_LENGTH ) {
			throw new InvalidArgumentException( esc_html__( 'Fulfilment date label is too long.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->fulfilment_date_description ) > self::MAX_DESCRIPTION_LENGTH ) {
			throw new InvalidArgumentException( esc_html__( 'Fulfilment date description is too long.', 'fuelchef-subscriptions' ) );
		}

		if ( '' === trim( $this->subscribe_save_label ) ) {
			throw new InvalidArgumentException( esc_html__( 'Subscribe & Save label cannot be blank.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->subscribe_save_label ) > self::MAX_LABEL_LENGTH ) {
			throw new InvalidArgumentException( esc_html__( 'Subscribe & Save label is too long.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->subscribe_save_description ) > self::MAX_DESCRIPTION_LENGTH ) {
			throw new InvalidArgumentException( esc_html__( 'Subscribe & Save description is too long.', 'fuelchef-subscriptions' ) );
		}
	}

	/**
	 * How many days before the fulfilment date an order locks.
	 */
	public function cutoff_days(): int {
		return $this->cutoff_days;
	}

	/**
	 * The time of day an order locks, in `H:i:s` form.
	 */
	public function cutoff_time(): string {
		return $this->cutoff_time;
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

	/**
	 * How many days into the future a customer can choose a fulfilment date.
	 */
	public function max_fulfilment_window_days(): int {
		return $this->max_fulfilment_window_days;
	}

	/**
	 * Checkout field label for the fulfilment date.
	 */
	public function fulfilment_date_label(): string {
		return $this->fulfilment_date_label;
	}

	/**
	 * Checkout help text for the fulfilment date, empty when the store shows none.
	 */
	public function fulfilment_date_description(): string {
		return $this->fulfilment_date_description;
	}

	/**
	 * Checkout checkbox label for the subscribe discount. May contain the placeholder
	 * `{percent}`.
	 */
	public function subscribe_save_label(): string {
		return $this->subscribe_save_label;
	}

	/**
	 * Checkout help text for the subscribe discount, empty when the store shows none. May
	 * contain the placeholder `{percent}`.
	 */
	public function subscribe_save_description(): string {
		return $this->subscribe_save_description;
	}

	/**
	 * The subscribe-and-save checkbox label, with `{percent}` replaced by the current
	 * discount percentage.
	 */
	public function subscribe_save_label_resolved(): string {
		return str_replace( '{percent}', (string) $this->subscribe_discount_percent, $this->subscribe_save_label );
	}

	/**
	 * The subscribe-and-save help text, with `{percent}` replaced by the current discount
	 * percentage. Empty when the store shows none.
	 */
	public function subscribe_save_description_resolved(): string {
		return str_replace( '{percent}', (string) $this->subscribe_discount_percent, $this->subscribe_save_description );
	}
}
