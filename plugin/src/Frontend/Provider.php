<?php
/**
 * Frontend service provider.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend;

use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container as Base_Container;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\ServiceProvider;
use FuelChef\Subscriptions\Frontend\Checkout\Block\Delivery_Date_Field as Block_Delivery_Date_Field;
use FuelChef\Subscriptions\Frontend\Checkout\Block\Subscribe_And_Save as Block_Subscribe_And_Save;
use FuelChef\Subscriptions\Frontend\Checkout\Current_Delivery_Window;
use FuelChef\Subscriptions\Frontend\Checkout\Delivery_Date_Field;
use FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save;
use FuelChef\Subscriptions\Services\Availability_Service;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\Utils\Renderer;
use FuelChef\Subscriptions\WooCommerce\Chosen_Shipping_Destination;
use FuelChef\Subscriptions\WooCommerce\Destination_Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Registers this plugin's storefront checkout fields and assets, for both classic and
 * block checkout.
 */
final class Provider implements ServiceProvider {


	/**
	 * Registers the chosen-destination resolver, the current-delivery-window resolver,
	 * the classic and block checkout fields, the subscribe-and-save discount for each,
	 * and the checkout assets handler as singletons.
	 */
	public function register( Base_Container $container ): void {
		$container
			->singleton( Chosen_Shipping_Destination::class )
			->addParameter( Destination_Catalog::class, true );

		$container
			->singleton( Current_Delivery_Window::class )
			->addParameter( Chosen_Shipping_Destination::class, true )
			->addParameter( Availability_Service::class, true )
			->addParameter( Clock::class, true );

		$container
			->singleton( Delivery_Date_Field::class )
			->addParameter( Current_Delivery_Window::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Subscribe_And_Save::class )
			->addParameter( Settings_Store::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Block_Delivery_Date_Field::class )
			->addParameter( Current_Delivery_Window::class, true );

		$container
			->singleton( Block_Subscribe_And_Save::class )
			->addParameter( Settings_Store::class, true )
			->addParameter( Subscribe_And_Save::class, true );

		$container->singleton( Assets::class );
	}

	/**
	 * Hooks the classic and block checkout fields and their discounts into checkout, and
	 * registers the asset enqueue hook.
	 */
	public function boot( Base_Container $container ): void {
		$container->get( Delivery_Date_Field::class )->register();
		$container->get( Subscribe_And_Save::class )->register();
		$container->get( Block_Delivery_Date_Field::class )->register();
		$container->get( Block_Subscribe_And_Save::class )->register();
		$container->get( Assets::class )->register();
	}
}
