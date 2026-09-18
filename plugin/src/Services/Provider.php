<?php
/**
 * Services service provider.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

use FuelChef\Subscriptions\Database\Transaction_Manager;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container as Base_Container;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\ServiceProvider;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Utils\Clock;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Registers this plugin's services and settings store.
 */
final class Provider implements ServiceProvider {


	/**
	 * Registers the shared transaction manager, the schedule, blackout, availability,
	 * subscribe-discount and subscribe-eligibility services, the settings store, the
	 * checkout shortcode/block presence detector, the destination catalog and resolver, and
	 * the fulfilment window resolver, as singletons wired to their dependencies.
	 */
	public function register( Base_Container $container ): void {
		$container
			->singleton( Transaction_Manager::class )
			->addParameter( wpdb::class, true );

		$container
			->singleton( Schedule_Service::class )
			->addParameter( Schedule_Repository::class, true )
			->addParameter( Schedule_Weekday_Repository::class, true )
			->addParameter( Blackout_Repository::class, true )
			->addParameter( Schedule_Destination_Repository::class, true )
			->addParameter( Transaction_Manager::class, true );

		$container
			->singleton( Blackout_Service::class )
			->addParameter( Blackout_Repository::class, true );

		$container->singleton( Settings_Store::class );

		$container->singleton( Checkout_Presence::class );

		$container
			->singleton( Availability_Service::class )
			->addParameter( Schedule_Weekday_Repository::class, true )
			->addParameter( Blackout_Repository::class, true )
			->addParameter( Settings_Store::class, true )
			->addParameter( Clock::class, true )
			->addParameter( Schedule_Destination_Repository::class, true )
			->addParameter( Schedule_Repository::class, true );

		$container->singleton( Subscribe_Discount_Service::class );

		$container->singleton( Subscribe_Eligibility_Service::class );

		$container->singleton( Destination_Catalog::class );

		$container
			->singleton( Chosen_Shipping_Destination::class )
			->addParameter( Destination_Catalog::class, true );

		$container
			->singleton( Current_Fulfilment_Window::class )
			->addParameter( Chosen_Shipping_Destination::class, true )
			->addParameter( Availability_Service::class, true )
			->addParameter( Settings_Store::class, true )
			->addParameter( Clock::class, true );
	}

	/**
	 * Nothing to wire up after registration.
	 */
	public function boot( Base_Container $container ): void {
		// Nothing to do; see the docblock above.
	}
}
