<?php
/**
 * Plugin Name:       FuelChef Subscriptions
 * Plugin URI:        https://fuelchef.com
 * Description:       FuelChef Meal Delivery Subscription Plugin.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.8
 * Author:            WPTechnix
 * Author URI:        https://wptechnix.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fuelchef-subscriptions
 * Domain Path:       /languages
 */

use FuelChef\Subscriptions\Container;
use FuelChef\Subscriptions\Requirements;
use FuelChef\Subscriptions\Plugin;

defined( 'ABSPATH' ) || exit;

if ( defined( 'FUELCHEF_SUBSCRIPTIONS_VERSION' ) ) {
	return;
}

define( 'FUELCHEF_SUBSCRIPTIONS_VERSION', '0.1.0' );
define( 'FUELCHEF_SUBSCRIPTIONS_MIN_PHP_VERSION', '8.0' );
define( 'FUELCHEF_SUBSCRIPTIONS_FILE', __FILE__ );
define( 'FUELCHEF_SUBSCRIPTIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FUELCHEF_SUBSCRIPTIONS_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/src/Requirements.php';

$fcs_requirements = Requirements::for_plugin(
	__FILE__,
	'FuelChef Subscriptions'
);

$fcs_requirements
	->require_php( FUELCHEF_SUBSCRIPTIONS_MIN_PHP_VERSION )
	->require_wp( '6.6' )
	->require_plugin( 'woocommerce', 'WooCommerce', '9.8' )
	->require_autoloader(
		FUELCHEF_SUBSCRIPTIONS_DIR . 'vendor-prefixed/scoper-autoload.php',
		FUELCHEF_SUBSCRIPTIONS_DIR . 'vendor-prefixed/autoload.php'
	);

if ( ! $fcs_requirements->satisfied() ) {
	return;
}

require_once $fcs_requirements->autoloader();

$fcs_container = Container::instance();

$fcs_container->singleton(
	Requirements::class,
	function () use ( $fcs_requirements ) {
		return $fcs_requirements;
	}
);

$fcs_container->get( Plugin::class );
