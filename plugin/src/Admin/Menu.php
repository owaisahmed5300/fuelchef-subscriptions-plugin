<?php
/**
 * Admin menu.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin;

use FuelChef\Subscriptions\Admin\Controllers\Schedules_Controller;
use FuelChef\Subscriptions\Admin\Controllers\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the FuelChef top-level admin menu and its two screens.
 *
 * The top-level menu and the "Settings" submenu share `SETTINGS_SLUG`: WordPress always
 * registers the top-level page itself as the first submenu entry, so giving it the same
 * slug as "Settings" replaces that auto-added duplicate instead of leaving a redundant
 * third entry.
 */
final class Menu {


	/**
	 * Capability required to view or change this plugin's settings.
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Slug shared by the top-level menu and the Settings screen.
	 */
	public const SETTINGS_SLUG = 'fuelchef-settings';

	/**
	 * Slug of the Schedules screen.
	 */
	public const SCHEDULES_SLUG = 'fuelchef-schedules';

	/**
	 * Creates the menu.
	 */
	public function __construct(
		private Settings_Controller $settings_controller,
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
			self::SETTINGS_SLUG,
			[ $this->settings_controller, 'render' ],
			'dashicons-calendar-alt'
		);

		add_submenu_page(
			self::SETTINGS_SLUG,
			esc_html__( 'Settings', 'fuelchef-subscriptions' ),
			esc_html__( 'Settings', 'fuelchef-subscriptions' ),
			self::CAPABILITY,
			self::SETTINGS_SLUG,
			[ $this->settings_controller, 'render' ]
		);

		add_submenu_page(
			self::SETTINGS_SLUG,
			esc_html__( 'Schedules', 'fuelchef-subscriptions' ),
			esc_html__( 'Schedules', 'fuelchef-subscriptions' ),
			self::CAPABILITY,
			self::SCHEDULES_SLUG,
			[ $this->schedules_controller, 'render' ]
		);
	}
}
