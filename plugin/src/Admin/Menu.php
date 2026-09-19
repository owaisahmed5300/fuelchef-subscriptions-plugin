<?php
/**
 * Admin menu.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin;

use FuelChef\Subscriptions\Admin\Controllers\Closures_Controller;
use FuelChef\Subscriptions\Admin\Controllers\Schedules_Controller;
use FuelChef\Subscriptions\Admin\Controllers\Settings_Controller;
use FuelChef\Subscriptions\Utils\Narrow;

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
	 * Slug of the Closures screen.
	 */
	public const CLOSURES_SLUG = 'fuelchef-closures';

	/**
	 * Creates the menu.
	 */
	public function __construct(
		private Settings_Controller $settings_controller,
		private Schedules_Controller $schedules_controller,
		private Closures_Controller $closures_controller
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
			$this->icon_url()
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

		add_submenu_page(
			self::SETTINGS_SLUG,
			esc_html__( 'Closures', 'fuelchef-subscriptions' ),
			esc_html__( 'Closures', 'fuelchef-subscriptions' ),
			self::CAPABILITY,
			self::CLOSURES_SLUG,
			[ $this->closures_controller, 'render' ]
		);

		add_action( 'admin_head', [ $this, 'print_icon_size' ] );
	}

	/**
	 * Shrinks the menu icon from WordPress's own 20px default to 16px - it looks
	 * oversized at 20px. Runs on every admin screen, not just this plugin's own: the
	 * sidebar itself is global, and `Admin\Assets` only enqueues on these two screens.
	 */
	public function print_icon_size(): void {
		printf(
			'<style>#%s div.wp-menu-image.svg{background-size:12px auto}</style>',
			esc_attr( 'toplevel_page_' . self::SETTINGS_SLUG )
		);
	}

	/**
	 * The top-level menu's icon, base64-encoded per `add_menu_page()`'s own convention
	 * for a custom SVG.
	 */
	private function icon_url(): string {
		$svg = Narrow::string( file_get_contents( FUELCHEF_SUBSCRIPTIONS_DIR . 'assets/images/favicon.svg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
