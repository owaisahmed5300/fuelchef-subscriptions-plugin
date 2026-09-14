<?php
/**
 * Admin assets.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin;

use FuelChef\Subscriptions\Utils\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues this plugin's CSS/JS, only on its own two admin screens.
 */
final class Assets {


	/**
	 * Registers the enqueue hook.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueues the shared and screen-specific assets for the current admin screen, doing
	 * nothing on any screen that is not one of this plugin's own.
	 *
	 * Matched on `$_GET['page']` rather than the `$hook_suffix` WordPress passes in:
	 * the submenu's hook suffix is derived from its parent in a way that is easy to
	 * get wrong (verified by hand against the actual admin screen), while `page` is
	 * exactly the slug this plugin registered its menus under.
	 */
	public function enqueue(): void {
		$page = Input::string( $_GET['page'] ?? null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$screen = match ( $page ) {
			Menu::GLOBAL_SETTINGS_SLUG => 'global-settings',
			Menu::SCHEDULES_SLUG => 'schedules',
			default => null,
		};

		if ( null === $screen ) {
			return;
		}

		wp_enqueue_style(
			'fcs-admin-common',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/admin/css/common.css',
			[],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_script(
			'fcs-admin-common',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/admin/js/common.js',
			[ 'jquery' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_enqueue_style(
			"fcs-admin-{$screen}",
			FUELCHEF_SUBSCRIPTIONS_URL . "assets/admin/css/{$screen}.css",
			[ 'fcs-admin-common' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_script(
			"fcs-admin-{$screen}",
			FUELCHEF_SUBSCRIPTIONS_URL . "assets/admin/js/{$screen}.js",
			[ 'fcs-admin-common' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_localize_script(
			"fcs-admin-{$screen}",
			'fcsAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fuelchef_subscriptions_admin' ),
			]
		);
	}
}
