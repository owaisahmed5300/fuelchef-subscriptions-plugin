<?php
/**
 * Admin assets.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin;

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
	 */
	public function enqueue( string $hook_suffix ): void {
		$screen = match ( $hook_suffix ) {
			'toplevel_page_' . Menu::GLOBAL_SETTINGS_SLUG => 'global-settings',
			Menu::GLOBAL_SETTINGS_SLUG . '_page_' . Menu::SCHEDULES_SLUG => 'schedules',
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
			[],
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
