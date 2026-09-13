<?php
/**
 * Repositories service provider.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Repositories;

use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container as Base_Container;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\ServiceProvider;
use FuelChef\Subscriptions\Utils\Clock;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Registers this plugin's repositories.
 */
final class Provider implements ServiceProvider {


	/**
	 * Every repository this provider registers, in registration order.
	 */
	private const REPOSITORIES = [
		Schedule_Repository::class,
		Schedule_Weekday_Repository::class,
		Blackout_Repository::class,
		Schedule_Destination_Repository::class,
	];

	/**
	 * Registers every repository as a singleton, wired to the shared `wpdb`
	 * and `Clock` instances.
	 */
	public function register( Base_Container $container ): void {
		foreach ( self::REPOSITORIES as $repository ) {
			$container
				->singleton( $repository )
				->addParameter( wpdb::class, true )
				->addParameter( Clock::class, true );
		}
	}

	/**
	 * Nothing to wire up after registration.
	 */
	public function boot( Base_Container $container ): void {
		// Nothing to do; see the docblock above.
	}
}
