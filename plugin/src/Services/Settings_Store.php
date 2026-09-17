<?php
/**
 * Settings store.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Values\DateTime;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's store-wide settings.
 *
 * Wraps `get_option()`/`update_option()` under one option key. No custom `wp_cache`
 * layer - WordPress's own options cache already covers this. A stored value that is
 * missing or no longer valid (an old plugin version, hand-edited data) falls back to its
 * default rather than failing to load the settings page.
 */
final class Settings_Store {


	/**
	 * The option key every setting is stored under.
	 */
	private const OPTION_KEY = 'fuelchef_subscriptions_settings';

	private const DEFAULT_CUTOFF_DAYS                = 1;
	private const DEFAULT_CUTOFF_TIME                = '17:00:00';
	private const DEFAULT_SUBSCRIBE_DISCOUNT_PERCENT = 5;
	private const DEFAULT_SUBSCRIBE_APPLICABILITY    = Subscribe_Applicability::INITIAL_AND_RENEWALS;
	private const DEFAULT_MAX_FULFILMENT_WINDOW_DAYS = 60;
	private const DEFAULT_MINIMUM_ORDER_AMOUNT       = 0.0;
	private const DEFAULT_MINIMUM_CART_QUANTITY      = 0;

	/**
	 * The current settings, falling back to defaults for anything missing or invalid.
	 */
	public function get(): Settings {
		/** @var array<string, mixed> $stored */
		$stored = Narrow::array( get_option( self::OPTION_KEY, [] ) );

		return new Settings(
			cutoff_days: $this->cutoff_days( $this->raw( $stored, 'cutoff_days' ) ),
			cutoff_time: $this->cutoff_time( $this->raw( $stored, 'cutoff_time' ) ),
			subscribe_discount_percent: $this->discount_percent( $this->raw( $stored, 'subscribe_discount_percent' ) ),
			subscribe_applicability: $this->applicability( $this->raw( $stored, 'subscribe_applicability' ) ),
			max_fulfilment_window_days: $this->max_fulfilment_window_days( $this->raw( $stored, 'max_fulfilment_window_days' ) ),
			fulfilment_date_label: $this->label( $this->raw( $stored, 'fulfilment_date_label' ), $this->default_fulfilment_date_label() ),
			fulfilment_date_description: $this->description( $this->raw( $stored, 'fulfilment_date_description' ) ),
			subscribe_save_label: $this->label( $this->raw( $stored, 'subscribe_save_label' ), $this->default_subscribe_save_label() ),
			subscribe_save_description: $this->description( $this->raw( $stored, 'subscribe_save_description' ) ),
			minimum_order_amount: $this->minimum_order_amount( $this->raw( $stored, 'minimum_order_amount' ) ),
			minimum_cart_quantity: $this->minimum_cart_quantity( $this->raw( $stored, 'minimum_cart_quantity' ) ),
			ineligible_message: $this->description( $this->raw( $stored, 'ineligible_message' ) )
		);
	}

	/**
	 * One field out of the stored settings array, or null when it is not set.
	 *
	 * @param array<string, mixed> $stored The stored settings array.
	 */
	private function raw( array $stored, string $key ): mixed {
		return $stored[ $key ] ?? null;
	}

	/**
	 * Persists the settings, and fires an action other code can react to.
	 */
	public function save( Settings $settings ): void {
		update_option(
			self::OPTION_KEY,
			[
				'cutoff_days'                 => $settings->cutoff_days(),
				'cutoff_time'                 => $settings->cutoff_time(),
				'subscribe_discount_percent'  => $settings->subscribe_discount_percent(),
				'subscribe_applicability'     => $settings->subscribe_applicability(),
				'max_fulfilment_window_days'  => $settings->max_fulfilment_window_days(),
				'fulfilment_date_label'       => $settings->fulfilment_date_label(),
				'fulfilment_date_description' => $settings->fulfilment_date_description(),
				'subscribe_save_label'        => $settings->subscribe_save_label(),
				'subscribe_save_description'  => $settings->subscribe_save_description(),
				'minimum_order_amount'        => $settings->minimum_order_amount(),
				'minimum_cart_quantity'       => $settings->minimum_cart_quantity(),
				'ineligible_message'          => $settings->ineligible_message(),
			]
		);

		/**
		 * Fires after the settings are saved.
		 *
		 * @param Settings $settings The saved settings.
		 */
		do_action( 'fuelchef_subscriptions/settings/updated', $settings );
	}

	/**
	 * The default fulfilment date checkout field label, translated once here rather than
	 * hardcoded in `Settings`, which has no access to WordPress i18n context at call time.
	 */
	private function default_fulfilment_date_label(): string {
		return esc_html__( 'Fulfilment date', 'fuelchef-subscriptions' );
	}

	/**
	 * The default subscribe-and-save checkbox label.
	 */
	private function default_subscribe_save_label(): string {
		/* translators: {percent} is replaced with the discount percentage at render time, not a PHP placeholder. */
		return esc_html__( 'Subscribe & Save {percent}%', 'fuelchef-subscriptions' );
	}

	/**
	 * Narrows a stored cutoff day count, falling back to the default when it is missing or
	 * negative.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function cutoff_days( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_CUTOFF_DAYS;
		}

		$days = (int) $value;

		return $days >= 0 ? $days : self::DEFAULT_CUTOFF_DAYS;
	}

	/**
	 * Narrows a stored cutoff time, falling back to the default when it is missing or not
	 * a valid time of day.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function cutoff_time( mixed $value ): string {
		return is_string( $value ) && DateTime::is_valid_time( $value ) ? $value : self::DEFAULT_CUTOFF_TIME;
	}

	/**
	 * Narrows a stored discount percentage, falling back to the default when it is
	 * missing or out of the 0-100 range.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function discount_percent( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_SUBSCRIBE_DISCOUNT_PERCENT;
		}

		$percent = (int) $value;

		return ( $percent >= 0 && $percent <= 100 ) ? $percent : self::DEFAULT_SUBSCRIBE_DISCOUNT_PERCENT;
	}

	/**
	 * Narrows a stored applicability value, falling back to the default when it is
	 * missing or unknown.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function applicability( mixed $value ): string {
		return is_string( $value ) && Subscribe_Applicability::is_valid( $value )
			? $value
			: self::DEFAULT_SUBSCRIBE_APPLICABILITY;
	}

	/**
	 * Narrows a stored maximum fulfilment window, falling back to the default when it is
	 * missing or outside the range `Settings` itself accepts.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function max_fulfilment_window_days( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_MAX_FULFILMENT_WINDOW_DAYS;
		}

		$days = (int) $value;

		return ( $days >= 1 && $days <= Settings::MAX_FULFILMENT_WINDOW_DAYS )
			? $days
			: self::DEFAULT_MAX_FULFILMENT_WINDOW_DAYS;
	}

	/**
	 * Narrows a stored minimum order amount, falling back to the default when it is
	 * missing or negative.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function minimum_order_amount( mixed $value ): float {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_MINIMUM_ORDER_AMOUNT;
		}

		$amount = (float) $value;

		return $amount >= 0.0 ? $amount : self::DEFAULT_MINIMUM_ORDER_AMOUNT;
	}

	/**
	 * Narrows a stored minimum cart quantity, falling back to the default when it is
	 * missing or negative.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function minimum_cart_quantity( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_MINIMUM_CART_QUANTITY;
		}

		$quantity = (int) $value;

		return $quantity >= 0 ? $quantity : self::DEFAULT_MINIMUM_CART_QUANTITY;
	}

	/**
	 * Narrows a stored customer-facing label, falling back to a default when it is
	 * missing, blank, or too long to have been saved through `Settings` itself.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function label( mixed $value, string $fallback ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) || strlen( $value ) > Settings::MAX_LABEL_LENGTH ) {
			return $fallback;
		}

		return $value;
	}

	/**
	 * Narrows a stored customer-facing description, falling back to an empty string -
	 * meaning "show none" - when it is missing or too long to have been saved through
	 * `Settings` itself.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function description( mixed $value ): string {
		if ( ! is_string( $value ) || strlen( $value ) > Settings::MAX_DESCRIPTION_LENGTH ) {
			return '';
		}

		return $value;
	}
}
