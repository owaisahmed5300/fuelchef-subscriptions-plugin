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
 * Entity/Repository abstraction - see `Settings_Service`.
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
	 * @param float  $minimum_order_amount Cart subtotal a customer needs to be offered
	 *                                     the subscribe discount. Zero means no restriction.
	 * @param int    $minimum_cart_quantity Cart item quantity a customer needs to be
	 *                                      offered the subscribe discount. Zero means no
	 *                                      restriction.
	 * @param string $ineligible_message Shown instead of the subscribe discount when the
	 *                                   cart does not meet the minimums, or an empty string
	 *                                   to show the default wording.
	 * @param string $logged_out_message Shown instead of the subscribe discount when the
	 *                                   customer is not logged in, or an empty string to
	 *                                   show the default wording.
	 * @param string $fulfilment_window_message Shown under the fulfilment date field once a
	 *                                          date is chosen, or an empty string to show
	 *                                          the default wording. May contain the
	 *                                          placeholders `{start}` and `{end}`.
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
		private string $subscribe_save_description,
		private float $minimum_order_amount = 0.0,
		private int $minimum_cart_quantity = 0,
		private string $ineligible_message = '',
		private string $logged_out_message = '',
		private string $fulfilment_window_message = ''
	) {
		if ( $cutoff_days < 0 ) {
			throw new InvalidArgumentException( __( 'Cutoff days cannot be negative.', 'fuelchef-subscriptions' ) );
		}

		if ( ! DateTime::is_valid_time( $cutoff_time ) ) {
			throw new InvalidArgumentException( __( 'Invalid cutoff time.', 'fuelchef-subscriptions' ) );
		}

		if ( $subscribe_discount_percent < 0 || $subscribe_discount_percent > 100 ) {
			throw new InvalidArgumentException(
				__( 'Subscribe discount must be between 0 and 100.', 'fuelchef-subscriptions' )
			);
		}

		if ( ! Subscribe_Applicability::is_valid( $subscribe_applicability ) ) {
			throw new InvalidArgumentException( __( 'Invalid subscribe applicability.', 'fuelchef-subscriptions' ) );
		}

		if ( $max_fulfilment_window_days < 1 ) {
			throw new InvalidArgumentException(
				__( 'Maximum delivery/pickup window must be at least 1 day.', 'fuelchef-subscriptions' )
			);
		}

		if ( $max_fulfilment_window_days > self::MAX_FULFILMENT_WINDOW_DAYS ) {
			throw new InvalidArgumentException(
				__( 'Maximum delivery/pickup window is too far in the future.', 'fuelchef-subscriptions' )
			);
		}

		if ( $minimum_order_amount < 0.0 ) {
			throw new InvalidArgumentException( __( 'Minimum order amount cannot be negative.', 'fuelchef-subscriptions' ) );
		}

		if ( $minimum_cart_quantity < 0 ) {
			throw new InvalidArgumentException( __( 'Minimum cart quantity cannot be negative.', 'fuelchef-subscriptions' ) );
		}

		// Reads the promoted properties above, already assigned by this point - not their
		// local parameter names, since this validates the finished object, not the call.
		$this->validate_checkout_copy();
		$this->validate_eligibility_messages();
	}

	/**
	 * Rejects a blank or over-length fulfilment date or subscribe-discount label, or an
	 * over-length description. A description may be blank - that means the store shows
	 * none.
	 *
	 * Every message below is `__()`, not `esc_html__()`: a controller catches the
	 * exception this throws and sends its message to the browser as JSON, displayed as
	 * plain text by an admin toast - see `Validation_Exception`'s class docblock for the
	 * full reasoning.
	 */
	private function validate_checkout_copy(): void {
		if ( '' === trim( $this->fulfilment_date_label ) ) {
			throw new InvalidArgumentException( __( 'Delivery/pickup date label cannot be blank.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->fulfilment_date_label ) > self::MAX_LABEL_LENGTH ) {
			throw new InvalidArgumentException( __( 'Delivery/pickup date label is too long.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->fulfilment_date_description ) > self::MAX_DESCRIPTION_LENGTH ) {
			throw new InvalidArgumentException( __( 'Delivery/pickup date description is too long.', 'fuelchef-subscriptions' ) );
		}

		if ( '' === trim( $this->subscribe_save_label ) ) {
			throw new InvalidArgumentException( __( 'Subscribe discount label cannot be blank.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->subscribe_save_label ) > self::MAX_LABEL_LENGTH ) {
			throw new InvalidArgumentException( __( 'Subscribe discount label is too long.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->subscribe_save_description ) > self::MAX_DESCRIPTION_LENGTH ) {
			throw new InvalidArgumentException( __( 'Subscribe discount description is too long.', 'fuelchef-subscriptions' ) );
		}
	}

	/**
	 * Rejects an over-length ineligible-cart, logged-out or fulfilment-window message.
	 * Any of the three may be blank - that means the store shows its default wording.
	 */
	private function validate_eligibility_messages(): void {
		if ( strlen( $this->ineligible_message ) > self::MAX_DESCRIPTION_LENGTH ) {
			throw new InvalidArgumentException( __( 'Ineligible subscription message is too long.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->logged_out_message ) > self::MAX_DESCRIPTION_LENGTH ) {
			throw new InvalidArgumentException( __( 'Logged-out subscription message is too long.', 'fuelchef-subscriptions' ) );
		}

		if ( strlen( $this->fulfilment_window_message ) > self::MAX_DESCRIPTION_LENGTH ) {
			throw new InvalidArgumentException( __( 'Delivery/pickup window message is too long.', 'fuelchef-subscriptions' ) );
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
	 * The subscribe-discount checkbox label, with `{percent}` replaced by the current
	 * discount percentage.
	 */
	public function subscribe_save_label_resolved(): string {
		return str_replace( '{percent}', (string) $this->subscribe_discount_percent, $this->subscribe_save_label );
	}

	/**
	 * The subscribe-discount help text, with `{percent}` replaced by the current discount
	 * percentage. Empty when the store shows none.
	 */
	public function subscribe_save_description_resolved(): string {
		return str_replace( '{percent}', (string) $this->subscribe_discount_percent, $this->subscribe_save_description );
	}

	/**
	 * Cart subtotal a customer needs to be offered the subscribe discount. Zero means no
	 * restriction.
	 */
	public function minimum_order_amount(): float {
		return $this->minimum_order_amount;
	}

	/**
	 * Cart item quantity a customer needs to be offered the subscribe discount. Zero means
	 * no restriction.
	 */
	public function minimum_cart_quantity(): int {
		return $this->minimum_cart_quantity;
	}

	/**
	 * The message shown instead of the subscribe discount when the cart does not meet the
	 * minimums, exactly as stored. Empty when the store shows the default wording - see
	 * {@see self::ineligible_message_resolved()}.
	 */
	public function ineligible_message(): string {
		return $this->ineligible_message;
	}

	/**
	 * The message shown instead of the subscribe discount when the cart does not meet the
	 * minimums, falling back to a sensible default when the store has not customised it.
	 *
	 * The default is deliberately `__()`, not `esc_html__()`: every consumer of this
	 * value already escapes it for its own context on the way out (a classic template's
	 * `esc_html()`, or a block script's jQuery `.text()`) - pre-escaping it here would
	 * double-escape it, rendering a literal "&amp;" instead of "&". The customised value
	 * stored via `Settings_Service` is raw for the same reason.
	 */
	public function ineligible_message_resolved(): string {
		return '' !== $this->ineligible_message
			? $this->ineligible_message
			: __( 'Add more to your cart to unlock the subscribe discount.', 'fuelchef-subscriptions' );
	}

	/**
	 * The message shown instead of the subscribe discount when the customer is not logged
	 * in, exactly as stored. Empty when the store shows the default wording - see
	 * {@see self::logged_out_message_resolved()}.
	 */
	public function logged_out_message(): string {
		return $this->logged_out_message;
	}

	/**
	 * The message shown instead of the subscribe discount when the customer is not logged
	 * in, falling back to a sensible default when the store has not customised it. See
	 * {@see self::ineligible_message_resolved()} for why the default is `__()`, not
	 * `esc_html__()`.
	 */
	public function logged_out_message_resolved(): string {
		return '' !== $this->logged_out_message
			? $this->logged_out_message
			: __( 'Log in to your account to unlock the subscribe discount.', 'fuelchef-subscriptions' );
	}

	/**
	 * The message shown under the fulfilment date field once a date is chosen, exactly as
	 * stored. Empty when the store shows the default wording - see
	 * {@see self::fulfilment_window_message_resolved()}.
	 */
	public function fulfilment_window_message(): string {
		return $this->fulfilment_window_message;
	}

	/**
	 * The message shown under the fulfilment date field once a date is chosen, with
	 * `{start}` and `{end}` replaced by the chosen date's fulfilment window, falling back to
	 * a sensible default when the store has not customised it. See
	 * {@see self::ineligible_message_resolved()} for why the default is `__()`, not
	 * `esc_html__()`.
	 */
	public function fulfilment_window_message_resolved( string $start, string $end ): string {
		$message = '' !== $this->fulfilment_window_message
			? $this->fulfilment_window_message
			: __( 'Available between {start} and {end}.', 'fuelchef-subscriptions' );

		return str_replace( [ '{start}', '{end}' ], [ $start, $end ], $message );
	}
}
