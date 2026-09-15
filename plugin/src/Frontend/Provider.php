<?php
/**
 * Frontend service provider.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend;

use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container as Base_Container;
use FuelChef\Subscriptions\Dependencies\WPTechnix\DI\ServiceProvider;
use FuelChef\Subscriptions\Frontend\Checkout\Block\Fulfilment_Date_Field as Block_Fulfilment_Date_Field;
use FuelChef\Subscriptions\Frontend\Checkout\Block\Subscribe_And_Save as Block_Subscribe_And_Save;
use FuelChef\Subscriptions\Frontend\Checkout\Fulfilment_Date_Field;
use FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save;
use FuelChef\Subscriptions\Services\Current_Fulfilment_Window;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Services\Subscribe_Discount_Service;
use FuelChef\Subscriptions\Utils\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Registers this plugin's storefront checkout fields and assets, for both classic and
 * block checkout.
 */
final class Provider implements ServiceProvider {


	/**
	 * Registers the classic and block checkout fields, the subscribe-and-save discount
	 * for each, and the checkout assets handler as singletons.
	 *
	 * The chosen-destination resolver, the current-fulfilment-window resolver and the
	 * discount calculation are business logic and registered by `Services\Provider`
	 * instead.
	 */
	public function register( Base_Container $container ): void {
		$container
			->singleton( Fulfilment_Date_Field::class )
			->addParameter( Current_Fulfilment_Window::class, true )
			->addParameter( Settings_Store::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Subscribe_And_Save::class )
			->addParameter( Settings_Store::class, true )
			->addParameter( Renderer::class, true )
			->addParameter( Subscribe_Discount_Service::class, true );

		$container
			->singleton( Block_Fulfilment_Date_Field::class )
			->addParameter( Current_Fulfilment_Window::class, true )
			->addParameter( Settings_Store::class, true );

		$container
			->singleton( Block_Subscribe_And_Save::class )
			->addParameter( Settings_Store::class, true )
			->addParameter( Subscribe_And_Save::class, true )
			->addParameter( Subscribe_Discount_Service::class, true );

		$container
			->singleton( Assets::class )
			->addParameter( Settings_Store::class, true );
	}

	/**
	 * Hooks the classic and block checkout fields and their discounts into checkout, and
	 * registers the asset enqueue hook.
	 */
	public function boot( Base_Container $container ): void {
		$container->get( Fulfilment_Date_Field::class )->register();
		$container->get( Subscribe_And_Save::class )->register();
		$container->get( Block_Fulfilment_Date_Field::class )->register();
		$container->get( Block_Subscribe_And_Save::class )->register();
		$container->get( Assets::class )->register();
	}
}
