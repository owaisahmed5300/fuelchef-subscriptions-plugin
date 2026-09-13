<?php
/**
 * Plugin container.
 *
 * Provides access to the plugin dependency injection container.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions;

use FuelChef\Subscriptions\Database\Installer as DB_Installer;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container as Base_Container;
use FuelChef\Subscriptions\Repositories\Provider as Repositories_Provider;
use FuelChef\Subscriptions\Utils\Clock;
use wpdb;

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
	private function __construct() {
	}

	/**
	 * Get the plugin container instance.
	 */
	public static function instance(): Base_Container {
		if ( ! isset( self::$container ) ) {
			$container = new Base_Container();

			$container->singleton( DB_Installer::class );
			$container
				->singleton( Plugin_Activator::class )
				->addParameter( DB_Installer::class, true );

			$container
				->singleton( Plugin::class )
				->addParameter( Plugin_Activator::class, true );

			$container->singleton(
				wpdb::class,
				static function () {
					return $GLOBALS['wpdb'];
				}
			);
			$container->singleton( Clock::class );

			$container->provider( new Repositories_Provider() );

			$container->boot();

			self::$container = $container;
		}

		return self::$container;
	}
}
