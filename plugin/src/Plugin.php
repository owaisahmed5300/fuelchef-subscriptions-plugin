<?php
/**
 * Main plugin class.
 */

declare(strict_types=1);

namespace WPPluginBoilerplate;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the plugin by registering its hooks.
 *
 * This class only ever runs when the environment is already known to be good:
 * the main plugin file returns early when `Requirements` is not satisfied, so
 * there is nothing to re-check here.
 */
final class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct(
		protected Plugin_Activator $activator,
	) {
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );

		register_activation_hook( WP_PLUGIN_BOILERPLATE_FILE, [ $this->activator, 'activate' ] );
	}

	/**
	 * Load the plugin text domain.
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'wp-plugin-boilerplate',
			false,
			plugin_basename( WP_PLUGIN_BOILERPLATE_DIR ) . '/languages'
		);
	}
}
