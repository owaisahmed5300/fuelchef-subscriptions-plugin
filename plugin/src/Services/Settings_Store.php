<?php
/**
 * Settings store.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

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

	/**
	 * The current settings, falling back to defaults for anything missing or invalid.
	 */
	public function get(): Settings {
		$stored = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return new Settings(
			$this->cutoff_days( $stored['cutoff_days'] ?? null ),
			$this->cutoff_time( $stored['cutoff_time'] ?? null ),
			$this->discount_percent( $stored['subscribe_discount_percent'] ?? null ),
			$this->applicability( $stored['subscribe_applicability'] ?? null )
		);
	}

	/**
	 * Persists the settings, and fires an action other code can react to.
	 */
	public function save( Settings $settings ): void {
		update_option(
			self::OPTION_KEY,
			[
				'cutoff_days'                => $settings->cutoff_days(),
				'cutoff_time'                => $settings->cutoff_time(),
				'subscribe_discount_percent' => $settings->subscribe_discount_percent(),
				'subscribe_applicability'    => $settings->subscribe_applicability(),
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
}
