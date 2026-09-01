<?php
/**
 * Plugin Name:       WP Plugin Boilerplate
 * Plugin URI:        https://github.com/wptechnix/wp-plugin-boilerplate
 * Description:       Batteries-included WordPress plugin boilerplate with Docker dev environment, coding standards, static analysis, testing, dependency scoping, and CI/CD.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            WPTechnix
 * Author URI:        https://wptechnix.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-plugin-boilerplate
 * Domain Path:       /languages
 */

use WPPluginBoilerplate\Container;
use WPPluginBoilerplate\Plugin;
use WPPluginBoilerplate\Requirements;

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_PLUGIN_BOILERPLATE_VERSION' ) ) {
	return;
}

define( 'WP_PLUGIN_BOILERPLATE_VERSION', '0.1.0' );
define( 'WP_PLUGIN_BOILERPLATE_MIN_PHP_VERSION', '8.0' );
define( 'WP_PLUGIN_BOILERPLATE_FILE', __FILE__ );
define( 'WP_PLUGIN_BOILERPLATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_PLUGIN_BOILERPLATE_URL', plugin_dir_url( __FILE__ ) );

/*
 * The pre-flight gate. Everything above this line, and this file as a whole,
 * must parse on PHP 5.6 - PHP parses the entire file before executing any of
 * it, so a single modern construct anywhere here is a fatal error on an old
 * PHP instead of the notice explaining why. `composer lint:compat` and the
 * `legacy-parse` CI job cover both this file and src/Requirements.php.
 */
require_once __DIR__ . '/src/Requirements.php';

/*
 * Declare what your plugin needs. Anything you do not call is not checked, so a
 * freshly generated plugin activates anywhere. To require another plugin:
 *
 *     ->require_plugin( 'woocommerce', 'WooCommerce', '10.0' )
 *
 * and add `Requires Plugins: woocommerce` to the header above, so WordPress
 * enforces it too. Keep require_wp() in step with `Requires at least:`.
 */
$wp_plugin_boilerplate_requirements = Requirements::for_plugin( __FILE__, 'WP Plugin Boilerplate' )
	->require_php( WP_PLUGIN_BOILERPLATE_MIN_PHP_VERSION )
	->require_wp( '6.0' )
	->require_autoloader(
		WP_PLUGIN_BOILERPLATE_DIR . 'vendor-prefixed/scoper-autoload.php',
		WP_PLUGIN_BOILERPLATE_DIR . 'vendor-prefixed/autoload.php'
	);

// Unmet requirements have already hooked an admin notice and an activation guard.
if ( ! $wp_plugin_boilerplate_requirements->satisfied() ) {
	return;
}

require_once $wp_plugin_boilerplate_requirements->autoloader();

$wp_plugin_boilerplate_container = Container::instance();

// Share the gate that already ran, so nothing re-checks the environment.
$wp_plugin_boilerplate_container->singleton(
	Requirements::class,
	static function () use ( $wp_plugin_boilerplate_requirements ) {
		return $wp_plugin_boilerplate_requirements;
	}
);

$wp_plugin_boilerplate_container->get( Plugin::class );
