<?php
/**
 * Unit tests for the activation handler.
 */

declare(strict_types=1);

namespace WPPluginBoilerplate\Tests\Unit;

use Brain\Monkey\Functions;
use WPPluginBoilerplate\Database\Installer;
use WPPluginBoilerplate\Plugin_Activator;
use WPPluginBoilerplate\Tests\TestCase;

/**
 * The installer is real rather than mocked: `install()` is final, so Mockery cannot
 * intercept it. With no schemas and no migrations declared it does nothing but write
 * the version option, which makes `update_option` a faithful count of installs.
 *
 * @covers \WPPluginBoilerplate\Plugin_Activator
 */
final class Plugin_Activator_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_option' )->justReturn( '0.0.0' );
	}

	public function test_a_single_site_activation_installs_once(): void {
		Functions\when( 'is_multisite' )->justReturn( false );

		Functions\expect( 'update_option' )->once();
		Functions\expect( 'switch_to_blog' )->never();

		$this->activator()->activate( false );
	}

	/**
	 * Activating one site of a network must not touch the other sites, even though
	 * the site is part of a network.
	 */
	public function test_a_per_site_activation_on_multisite_installs_only_there(): void {
		Functions\when( 'is_multisite' )->justReturn( true );

		Functions\expect( 'update_option' )->once();
		Functions\expect( 'switch_to_blog' )->never();

		$this->activator()->activate( false );
	}

	public function test_a_network_activation_installs_on_every_site(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_sites' )->justReturn( [ 1, 2, 3 ] );

		Functions\expect( 'switch_to_blog' )->times( 3 );
		Functions\expect( 'update_option' )->times( 3 );
		Functions\expect( 'restore_current_blog' )->times( 3 );

		$this->activator()->activate( true );
	}

	/**
	 * Every switch has to be undone, or the request finishes pointed at the wrong
	 * site. The `finally` in the source is what guarantees it.
	 */
	public function test_the_original_site_is_restored_even_when_an_install_fails(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_sites' )->justReturn( [ 1 ] );
		Functions\when( 'switch_to_blog' )->justReturn( true );
		Functions\when( 'update_option' )->alias(
			static function (): void {
				throw new \RuntimeException( 'database is gone' );
			}
		);

		Functions\expect( 'restore_current_blog' )->once();

		$this->expectException( \RuntimeException::class );

		$this->activator()->activate( true );
	}

	private function activator(): Plugin_Activator {
		return new Plugin_Activator( new Installer() );
	}
}
