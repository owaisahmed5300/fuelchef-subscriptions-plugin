<?php
/**
 * Global settings store.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Settings;

use FuelChef\Subscriptions\Values\Cutoff_Unit;
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
	private const OPTION_KEY = 'fuelchef_subscriptions_global_settings';

	private const DEFAULT_CUTOFF_AMOUNT              = 24;
	private const DEFAULT_CUTOFF_UNIT                = Cutoff_Unit::HOURS;
	private const DEFAULT_SUBSCRIBE_DISCOUNT_PERCENT = 5;
	private const DEFAULT_SUBSCRIBE_APPLICABILITY    = Subscribe_Applicability::INITIAL_AND_RENEWALS;

	/**
	 * The current settings, falling back to defaults for anything missing or invalid.
	 */
	public function get(): Global_Settings {
		$stored = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return new Global_Settings(
			$this->cutoff_amount( $stored['cutoff_amount'] ?? null ),
			$this->cutoff_unit( $stored['cutoff_unit'] ?? null ),
			$this->discount_percent( $stored['subscribe_discount_percent'] ?? null ),
			$this->applicability( $stored['subscribe_applicability'] ?? null )
		);
	}

	/**
	 * Persists the settings, and fires an action other code can react to.
	 */
	public function save( Global_Settings $settings ): void {
		update_option(
			self::OPTION_KEY,
			[
				'cutoff_amount'              => $settings->cutoff_amount(),
				'cutoff_unit'                => $settings->cutoff_unit(),
				'subscribe_discount_percent' => $settings->subscribe_discount_percent(),
				'subscribe_applicability'    => $settings->subscribe_applicability(),
			]
		);

		/**
		 * Fires after the global settings are saved.
		 *
		 * @param Global_Settings $settings The saved settings.
		 */
		do_action( 'fuelchef_subscriptions/settings/updated', $settings );
	}

	/**
	 * Narrows a stored cutoff amount, falling back to the default when it is missing or
	 * negative.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function cutoff_amount( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_CUTOFF_AMOUNT;
		}

		$amount = (int) $value;

		return $amount >= 0 ? $amount : self::DEFAULT_CUTOFF_AMOUNT;
	}

	/**
	 * Narrows a stored cutoff unit, falling back to the default when it is missing or
	 * unknown.
	 *
	 * @param mixed $value Raw stored value.
	 */
	private function cutoff_unit( mixed $value ): string {
		return is_string( $value ) && Cutoff_Unit::is_valid( $value ) ? $value : self::DEFAULT_CUTOFF_UNIT;
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
