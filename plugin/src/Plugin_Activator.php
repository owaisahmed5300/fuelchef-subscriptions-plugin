<?php
/**
 * Plugin activation handler.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions;

use FuelChef\Subscriptions\Database\Installer as DB_Installer;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation.
 */
final class Plugin_Activator {


	/**
	 * Constructor.
	 *
	 * @param DB_Installer $db_installer Site database installer.
	 */
	public function __construct(
		private DB_Installer $db_installer,
	) {
	}

	/**
	 * Activates the plugin.
	 *
	 * @param bool $network_wide Whether the plugin is being activated
	 *                           network-wide.
	 */
	public function activate( bool $network_wide ): void {
		if ( ! is_multisite() || ! $network_wide ) {
			$this->db_installer->install();

			return;
		}

		foreach ( get_sites( [ 'fields' => 'ids' ] ) as $blog_id ) {
			switch_to_blog( (int) $blog_id );

			try {
				$this->db_installer->install();
			} finally {
				restore_current_blog();
			}
		}
	}
}
