<?php
/**
 * Database service provider.
 *
 * Registers the database-related services with the dependency injection
 * container.
 */

declare( strict_types=1 );

namespace WPPluginBoilerplate\Database;

use WPPluginBoilerplate_Deps\WPTechnix\DI\Container;
use WPPluginBoilerplate_Deps\WPTechnix\DI\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the database services into the plugin container.
 */
final class Provider implements ServiceProvider {

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->singleton( Installer::class );
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		// Nothing to wire together after registration.
	}
}
