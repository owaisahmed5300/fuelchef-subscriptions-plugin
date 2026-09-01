<?php
/**
 * Unit tests for the Plugin class.
 */

declare(strict_types=1);

namespace WPPluginBoilerplate\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use WPPluginBoilerplate\Database\Installer;
use WPPluginBoilerplate\Plugin;
use WPPluginBoilerplate\Plugin_Activator;
use WPPluginBoilerplate\Tests\TestCase;

/**
 * @covers \WPPluginBoilerplate\Plugin
 */
final class Plugin_Test extends TestCase {

	public function test_it_hooks_the_textdomain_loader_onto_init(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );

		Actions\expectAdded( 'init' )->once();

		$this->plugin();
	}

	/**
	 * The hook has to name the main plugin file, not any file in the plugin:
	 * WordPress keys activation callbacks by it and would never fire otherwise.
	 */
	public function test_it_registers_activation_against_the_main_plugin_file(): void {
		$activator = $this->activator();

		Functions\expect( 'register_activation_hook' )
			->once()
			->with( WP_PLUGIN_BOILERPLATE_FILE, [ $activator, 'activate' ] );

		new Plugin( $activator );
	}

	public function test_it_loads_the_textdomain_from_the_languages_directory(): void {
		Functions\when( 'register_activation_hook' )->justReturn( true );
		Functions\when( 'plugin_basename' )->justReturn( 'wp-plugin-boilerplate' );

		Functions\expect( 'load_plugin_textdomain' )
			->once()
			->with( 'wp-plugin-boilerplate', false, 'wp-plugin-boilerplate/languages' );

		$this->plugin()->load_plugin_textdomain();
	}

	private function plugin(): Plugin {
		return new Plugin( $this->activator() );
	}

	private function activator(): Plugin_Activator {
		return new Plugin_Activator( new Installer() );
	}
}
