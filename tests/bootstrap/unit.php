<?php
/**
 * PHPUnit bootstrap for the unit test suite.
 *
 * No WordPress is loaded. WordPress functions are mocked with Brain Monkey through
 * WPPluginBoilerplate\Tests\TestCase, so this only needs the Composer autoloader,
 * which maps the plugin classes and the test classes.
 */

declare(strict_types=1);

// Plugin files exit when ABSPATH is undefined, so the autoloader could not load any of
// them without this. The path is never read: nothing under test includes a WordPress file.
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/wordpress/' );

// Defined by the main plugin file, which the suite never runs. Classes that read them
// need them to exist; no test asserts on these values.
define( 'WP_PLUGIN_BOILERPLATE_FILE', dirname( __DIR__, 2 ) . '/plugin/wp-plugin-boilerplate.php' );
define( 'WP_PLUGIN_BOILERPLATE_DIR', dirname( __DIR__, 2 ) . '/plugin/' );

$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! is_file( $autoload ) ) {
	fwrite( STDERR, 'Run "./scripts/dev composer install" before running the tests.' . PHP_EOL );
	exit( 1 );
}

require_once $autoload;
