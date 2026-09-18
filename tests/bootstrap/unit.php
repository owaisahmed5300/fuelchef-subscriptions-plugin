<?php
/**
 * PHPUnit bootstrap for the unit test suite.
 *
 * No WordPress is loaded. WordPress functions are mocked with Brain Monkey through
 * FuelChef\Subscriptions\Tests\TestCase, so this only needs the Composer autoloader,
 * which maps the plugin classes and the test classes.
 */

declare(strict_types=1);

// Plugin files exit when ABSPATH is undefined, so the autoloader could not load any of
// them without this. The path is never read: nothing under test includes a WordPress file.
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/wordpress/' );

// Defined by the main plugin file, which the suite never runs. Classes that read them
// need them to exist; no test asserts on these values.
define( 'FUELCHEF_SUBSCRIPTIONS_FILE', dirname( __DIR__, 2 ) . '/plugin/fuelchef-subscriptions.php' );
define( 'FUELCHEF_SUBSCRIPTIONS_DIR', dirname( __DIR__, 2 ) . '/plugin/' );

// WordPress core time constants, used for cutoff-window arithmetic.
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! is_file( $autoload ) ) {
	fwrite( STDERR, 'Run "./scripts/dev composer install" before running the tests.' . PHP_EOL );
	exit( 1 );
}

require_once $autoload;

// The real wpdb class does not exist without WordPress, but repositories and the
// installer type-hint against it.
require_once __DIR__ . '/wpdb.php';

// The real WP_Post class does not exist without WordPress, but Checkout_Presence
// type-hints against it.
require_once __DIR__ . '/wp-post.php';

// WooCommerce is never loaded either, but Destination_Catalog reaches its shipping
// zone registry statically, which nothing else can stand in for.
require_once __DIR__ . '/woocommerce.php';
