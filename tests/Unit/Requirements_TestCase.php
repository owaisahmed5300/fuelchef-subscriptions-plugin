<?php
/**
 * Shared base for the Requirements unit tests.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Requirements;
use FuelChef\Subscriptions\Tests\TestCase;

/**
 * Base test case for the Requirements gate.
 *
 * Models the slice of WordPress the gate reads — version, installed and active
 * plugins, current capabilities — as properties, and answers the stubbed functions
 * from that state. A stub that ignores its arguments would approve a check the
 * source got wrong; these answer what WordPress would answer.
 *
 * Only the functions the gate actually calls are stubbed, so anything else it
 * reaches for raises an undefined-function error instead of getting a convenient
 * answer.
 *
 * The file name does not end in `Test.php`, so PHPUnit never collects it.
 */
abstract class Requirements_TestCase extends TestCase {


	protected const PLUGIN_NAME = 'FuelChef Subscriptions';
	protected const PLUGIN_FILE = '/plugins/fuelchef-subscriptions/fuelchef-subscriptions.php';

	/**
	 * The WordPress version the fake site reports.
	 */
	protected string $wp_version = '6.7';

	/**
	 * Installed plugins, keyed by plugin file, as `get_plugins()` returns them.
	 *
	 * @var array<string, array<string, string>>
	 */
	protected array $installed_plugins = [];

	/**
	 * Plugin files WordPress considers active.
	 *
	 * Site and network activation are one list because `is_plugin_active()` is
	 * itself defined as "active here, or active for the network".
	 *
	 * @var list<string>
	 */
	protected array $active_plugins = [];

	/**
	 * Capabilities the current user has.
	 *
	 * @var list<string>
	 */
	protected array $capabilities = [];

	/**
	 * How many times the gate has called `get_plugins()`.
	 *
	 * Counted by hand because `Functions\expect()` would collide with the
	 * `Functions\when()` stub registered for the same function.
	 */
	protected int $get_plugins_calls = 0;

	/**
	 * How many times the gate registered an activation hook.
	 */
	protected int $activation_hooks = 0;

	/**
	 * Arguments passed to `wp_die()`, or null when it was never called.
	 *
	 * @var array{message: string, title: string}|null
	 */
	protected ?array $died = null;

	protected function setUp(): void {
		parent::setUp();

		$this->wp_version        = '6.7';
		$this->installed_plugins = [];
		$this->active_plugins    = [];
		$this->capabilities      = [];
		$this->get_plugins_calls = 0;
		$this->activation_hooks  = 0;
		$this->died              = null;

		$this->stub_wordpress();
	}

	/**
	 * Build a gate for the scenario under test.
	 *
	 * Every requirement is opt-in, so a test declares only what it exercises.
	 */
	protected function requirements(): Requirements {
		return Requirements::for_plugin( self::PLUGIN_FILE, self::PLUGIN_NAME );
	}

	/**
	 * Record a plugin as installed on the fake site.
	 *
	 * @param string $file Plugin file, e.g. `woocommerce/woocommerce.php`.
	 * @param string $name Plugin name.
	 * @param string $version Installed version. An empty string mimics a plugin
	 *                        whose header declares no version.
	 */
	protected function install_plugin( string $file, string $name, string $version ): void {
		$this->installed_plugins[ $file ] = [
			'Name'    => $name,
			'Version' => $version,
		];
	}

	/**
	 * Install WooCommerce at a given version, optionally activating it.
	 *
	 * @param string $version WooCommerce version.
	 * @param bool   $active Whether WordPress reports it as active.
	 */
	protected function install_woocommerce( string $version, bool $active = true ): void {
		$this->install_plugin( 'woocommerce/woocommerce.php', 'WooCommerce', $version );

		if ( ! $active ) {
			return;
		}

		$this->active_plugins[] = 'woocommerce/woocommerce.php';
	}

	/**
	 * Capture output produced by a callback.
	 *
	 * @param callable $callback Callback that echoes output.
	 */
	protected function capture( callable $callback ): string {
		ob_start();
		$callback();

		return (string) ob_get_clean();
	}

	/**
	 * Point the WordPress functions the gate calls at the state above.
	 */
	private function stub_wordpress(): void {
		Functions\when( 'get_bloginfo' )->alias(
			fn ( string $show ): string => 'version' === $show ? $this->wp_version : ''
		);

		// Defining this stub also satisfies the source's function_exists() guard, so
		// the gate never reaches for the admin include.
		Functions\when( 'is_plugin_active' )->alias(
			fn ( string $file ): bool => in_array( $file, $this->active_plugins, true )
		);

		Functions\when( 'get_plugins' )->alias(
			function (): array {
				++$this->get_plugins_calls;

				return $this->installed_plugins;
			}
		);

		Functions\when( 'current_user_can' )->alias(
			fn ( string $capability ): bool => in_array( $capability, $this->capabilities, true )
		);

		Functions\when( 'register_activation_hook' )->alias(
			function (): bool {
				++$this->activation_hooks;

				return true;
			}
		);

		Functions\when( 'wp_die' )->alias(
			function ( string $message = '', string $title = '' ): void {
				$this->died = [
					'message' => $message,
					'title'   => $title,
				];
			}
		);

		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'wp_kses_post' )->returnArg( 1 );

		// The URL helpers do real work, so link assertions check what the source
		// built rather than a constant the test handed back to itself.
		Functions\when( 'self_admin_url' )->alias(
			static fn ( string $path = '' ): string => 'https://example.test/wp-admin/' . $path
		);

		Functions\when( 'add_query_arg' )->alias(
			static fn ( array $args, string $url ): string => $url . '?' . http_build_query( $args )
		);

		Functions\when( 'wp_nonce_url' )->alias(
			static fn ( string $url, string $action ): string => $url . '&_wpnonce=' . md5( $action )
		);
	}
}
