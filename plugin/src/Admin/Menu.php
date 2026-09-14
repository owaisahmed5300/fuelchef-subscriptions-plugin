<?php
/**
 * Admin menu.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin;

use FuelChef\Subscriptions\Admin\Controllers\Global_Settings_Controller;
use FuelChef\Subscriptions\Admin\Controllers\Schedules_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the FuelChef top-level admin menu and its two screens.
 *
 * The top-level menu and the "Global Settings" submenu share
 * `GLOBAL_SETTINGS_SLUG`: WordPress always registers the top-level page itself as the
 * first submenu entry, so giving it the same slug as "Global Settings" replaces that
 * auto-added duplicate instead of leaving a redundant third entry.
 */
final class Menu {


	/**
	 * Capability required to view or change this plugin's settings.
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Slug shared by the top-level menu and the Global Settings screen.
	 */
	public const GLOBAL_SETTINGS_SLUG = 'fuelchef-global-settings';

	/**
	 * Slug of the Schedules screen.
	 */
	public const SCHEDULES_SLUG = 'fuelchef-schedules';

	/**
	 * Creates the menu.
	 */
	public function __construct(
		private Global_Settings_Controller $global_settings_controller,
		private Schedules_Controller $schedules_controller
	) {
	}

	/**
	 * Registers the top-level menu and its two submenus.
	 */
	public function register(): void {
		add_menu_page(
			esc_html__( 'FuelChef Subscriptions', 'fuelchef-subscriptions' ),
			esc_html__( 'FuelChef', 'fuelchef-subscriptions' ),
			self::CAPABILITY,
			self::GLOBAL_SETTINGS_SLUG,
			[ $this->global_settings_controller, 'render' ],
			'dashicons-calendar-alt'
		);

		add_submenu_page(
			self::GLOBAL_SETTINGS_SLUG,
			esc_html__( 'Global Settings', 'fuelchef-subscriptions' ),
			esc_html__( 'Global Settings', 'fuelchef-subscriptions' ),
			self::CAPABILITY,
			self::GLOBAL_SETTINGS_SLUG,
			[ $this->global_settings_controller, 'render' ]
		);

		add_submenu_page(
			self::GLOBAL_SETTINGS_SLUG,
			esc_html__( 'Schedules', 'fuelchef-subscriptions' ),
			esc_html__( 'Schedules', 'fuelchef-subscriptions' ),
			self::CAPABILITY,
			self::SCHEDULES_SLUG,
			[ $this->schedules_controller, 'render' ]
		);
	}
}
