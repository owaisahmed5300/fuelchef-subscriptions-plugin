<?php
/**
 * Admin service provider.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin;

use FuelChef\Subscriptions\Admin\Controllers\Closures_Controller;
use FuelChef\Subscriptions\Admin\Controllers\Schedules_Controller;
use FuelChef\Subscriptions\Admin\Controllers\Settings_Controller;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container as Base_Container;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\ServiceProvider;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Services\Scheduling\Blackout_Service;
use FuelChef\Subscriptions\Services\Scheduling\Destination_Catalog_Service;
use FuelChef\Subscriptions\Services\Scheduling\Schedule_Service;
use FuelChef\Subscriptions\Services\Settings_Service;
use FuelChef\Subscriptions\Utils\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Registers this plugin's admin menu, screens and assets.
 */
final class Provider implements ServiceProvider {


	/**
	 * Registers the two controllers, the menu and the assets handler as singletons.
	 */
	public function register( Base_Container $container ): void {
		$container
			->singleton( Settings_Controller::class )
			->addParameter( Settings_Service::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Closures_Controller::class )
			->addParameter( Blackout_Service::class, true )
			->addParameter( Blackout_Repository::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Schedules_Controller::class )
			->addParameter( Schedule_Service::class, true )
			->addParameter( Schedule_Repository::class, true )
			->addParameter( Schedule_Weekday_Repository::class, true )
			->addParameter( Blackout_Repository::class, true )
			->addParameter( Schedule_Destination_Repository::class, true )
			->addParameter( Destination_Catalog_Service::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Menu::class )
			->addParameter( Settings_Controller::class, true )
			->addParameter( Schedules_Controller::class, true )
			->addParameter( Closures_Controller::class, true );

		$container->singleton( Assets::class );
	}

	/**
	 * Hooks the menu registration and registers each controller's ajax actions and the
	 * asset enqueue hook.
	 */
	public function boot( Base_Container $container ): void {
		add_action( 'admin_menu', [ $container->get( Menu::class ), 'register' ] );

		$container->get( Settings_Controller::class )->register();
		$container->get( Closures_Controller::class )->register();
		$container->get( Schedules_Controller::class )->register();
		$container->get( Assets::class )->register();
	}
}
