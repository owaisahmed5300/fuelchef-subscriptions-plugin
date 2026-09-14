<?php
/**
 * Services service provider.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container as Base_Container;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\ServiceProvider;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Utils\Clock;

defined( 'ABSPATH' ) || exit;

/**
 * Registers this plugin's services and settings store.
 */
final class Provider implements ServiceProvider {


	/**
	 * Registers the schedule, blackout and availability services, and the settings
	 * store, as singletons wired to their repository dependencies.
	 */
	public function register( Base_Container $container ): void {
		$container
			->singleton( Schedule_Service::class )
			->addParameter( Schedule_Repository::class, true )
			->addParameter( Schedule_Weekday_Repository::class, true )
			->addParameter( Blackout_Repository::class, true )
			->addParameter( Schedule_Destination_Repository::class, true );

		$container
			->singleton( Blackout_Service::class )
			->addParameter( Blackout_Repository::class, true );

		$container->singleton( Settings_Store::class );

		$container
			->singleton( Availability_Service::class )
			->addParameter( Schedule_Weekday_Repository::class, true )
			->addParameter( Blackout_Repository::class, true )
			->addParameter( Settings_Store::class, true )
			->addParameter( Clock::class, true )
			->addParameter( Schedule_Destination_Repository::class, true )
			->addParameter( Schedule_Repository::class, true );
	}

	/**
	 * Nothing to wire up after registration.
	 */
	public function boot( Base_Container $container ): void {
		// Nothing to do; see the docblock above.
	}
}
