<?php
/**
 * Admin page access verification trait.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Concerns;

use FuelChef\Subscriptions\Admin\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Backs every admin screen's `render()` with the same capability check.
 */
trait Verifies_Page_Access {


	/**
	 * Halts the request with a translated die screen when the current user lacks the
	 * capability to view this plugin's admin screens.
	 */
	private function verify_page_access(): void {
		if ( current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		wp_die( esc_html__( 'You do not have permission to access this page.', 'fuelchef-subscriptions' ) );
	}
}
