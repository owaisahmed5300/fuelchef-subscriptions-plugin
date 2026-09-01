<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Runs in a bare WordPress context: the plugin's own bootstrap has NOT run, so no
 * autoloader and no plugin constants are available. Keep this file self-contained.
 */

declare(strict_types=1);

// Exit if this file is called directly, or by anything other than WordPress'
// uninstall routine.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Delete plugin options, custom tables, scheduled events and transients here
 * but ensure user consent is obtained before deleting any data.
 *
 * On multisite, remember to iterate the network's sites — uninstall.php runs once,
 * not once per site.
 */
