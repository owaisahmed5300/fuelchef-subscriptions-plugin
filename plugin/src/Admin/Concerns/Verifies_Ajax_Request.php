<?php
/**
 * Ajax request verification trait.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Concerns;

use FuelChef\Subscriptions\Admin\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Backs every admin-ajax handler with the same nonce and capability check.
 */
trait Verifies_Ajax_Request {


	/**
	 * Verifies the request nonce and the user's capability, halting the request with a
	 * JSON error when either fails.
	 *
	 * `check_ajax_referer()` itself halts the request on an invalid or missing nonce.
	 */
	private function verify_ajax_request(): void {
		check_ajax_referer( 'fuelchef_subscriptions_admin', 'nonce' );

		if ( current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		wp_send_json_error(
			[ 'message' => esc_html__( 'You do not have permission to do this.', 'fuelchef-subscriptions' ) ],
			403
		);
	}
}
