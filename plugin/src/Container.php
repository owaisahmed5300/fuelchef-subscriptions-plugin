<?php
/**
 * Plugin container.
 *
 * Provides access to the plugin dependency injection container.
 */

declare( strict_types=1 );

namespace WPPluginBoilerplate;

use WPPluginBoilerplate\Database\Installer as DB_Installer;
use WPPluginBoilerplate_Deps\WPTechnix\DI\Container as Base_Container;
use WPPluginBoilerplate\Database\Provider as DatabaseProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin container.
 */
final class Container {

	/**
	 * Container instance.
	 */
	private static Base_Container $container;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Get the plugin container instance.
	 */
	public static function instance(): Base_Container {
		if ( ! isset( self::$container ) ) {
			$container = new Base_Container();

			$container->provider( new DatabaseProvider() );

			$container
				->singleton( Plugin_Activator::class )
				->addParameter( DB_Installer::class, true );

			$container
				->singleton( Plugin::class )
				->addParameter( Plugin_Activator::class, true );

			$container->boot();

			self::$container = $container;
		}

		return self::$container;
	}
}
