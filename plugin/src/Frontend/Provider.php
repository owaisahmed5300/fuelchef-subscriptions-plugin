<?php
/**
 * Frontend service provider.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend;

use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container as Base_Container;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\ServiceProvider;
use FuelChef\Subscriptions\Frontend\Checkout\Delivery_Date_Field;
use FuelChef\Subscriptions\Services\Availability_Service;
use FuelChef\Subscriptions\Templating\Renderer;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\WooCommerce\Chosen_Shipping_Destination;
use FuelChef\Subscriptions\WooCommerce\Destination_Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Registers this plugin's storefront checkout fields and assets.
 */
final class Provider implements ServiceProvider {


	/**
	 * Registers the chosen-destination resolver, the delivery date field and the
	 * checkout assets handler as singletons.
	 */
	public function register( Base_Container $container ): void {
		$container
			->singleton( Chosen_Shipping_Destination::class )
			->addParameter( Destination_Catalog::class, true );

		$container
			->singleton( Delivery_Date_Field::class )
			->addParameter( Chosen_Shipping_Destination::class, true )
			->addParameter( Availability_Service::class, true )
			->addParameter( Clock::class, true )
			->addParameter( Renderer::class, true );

		$container->singleton( Assets::class );
	}

	/**
	 * Hooks the delivery date field into checkout and registers the asset enqueue hook.
	 */
	public function boot( Base_Container $container ): void {
		$container->get( Delivery_Date_Field::class )->register();
		$container->get( Assets::class )->register();
	}
}
