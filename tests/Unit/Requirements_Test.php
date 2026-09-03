<?php
/**
 * Unit tests for the Requirements gate.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit;

use Brain\Monkey\Actions;

/**
 * @covers \FuelChef\Subscriptions\Requirements
 */
final class Requirements_Test extends Requirements_TestCase {



	/**
	 * A path that certainly does not exist.
	 */
	private const MISSING_FILE = __DIR__ . '/no-such-autoload.php';

	public function test_a_gate_with_nothing_required_is_satisfied(): void {
		$this->assertTrue( $this->requirements()->satisfied() );
	}

	public function test_nothing_is_hooked_when_every_requirement_passes(): void {
		Actions\expectAdded( 'admin_notices' )->never();

		$this->requirements()->require_wp( '6.5' )->satisfied();

		$this->assertSame( 0, $this->activation_hooks );
	}

	/**
	 * @dataProvider php_versions
	 */
	public function test_php_version_is_checked_against_the_minimum(
		string $required,
		bool $expected_failure
	): void {
		$failures = $this->requirements()->require_php( $required )->failures();

		$this->assertSame( $expected_failure, isset( $failures[0] ) );
	}

	/**
	 * Expressed relative to the PHP running the suite, so this stays true on every
	 * version of the matrix.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function php_versions(): array {
		return [
			'above the running version'   => [ '99.0', true ],
			'exactly the running version' => [ PHP_VERSION, false ],
			'below the running version'   => [ '7.2', false ],
		];
	}

	public function test_php_failure_reports_the_required_and_running_versions(): void {
		$failures = $this->requirements()->require_php( '99.0' )->failures();

		$this->assertSame(
			[
				'type'     => 'php',
				'required' => '99.0',
				'current'  => PHP_VERSION,
			],
			$failures[0]
		);
	}

	/**
	 * @dataProvider wordpress_versions
	 */
	public function test_wordpress_version_is_checked_against_the_minimum(
		string $installed,
		bool $expected_failure
	): void {
		$this->wp_version = $installed;

		$failures = $this->requirements()->require_wp( '6.5' )->failures();

		$this->assertSame( $expected_failure, isset( $failures[0] ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function wordpress_versions(): array {
		return [
			'one minor below the minimum' => [ '6.4', true ],
			'exactly the minimum'         => [ '6.5', false ],
			'one minor above'             => [ '6.6', false ],
		];
	}

	public function test_wordpress_failure_reports_the_required_and_installed_versions(): void {
		$this->wp_version = '6.4';

		$failures = $this->requirements()->require_wp( '6.5' )->failures();

		$this->assertSame(
			[
				'type'     => 'wp',
				'required' => '6.5',
				'current'  => '6.4',
			],
			$failures[0]
		);
	}

	public function test_reports_a_required_plugin_that_is_not_installed(): void {
		$failures = $this->requirements()->require_plugin( 'woocommerce', 'WooCommerce' )->failures();

		$this->assertSame(
			[
				'type' => 'plugin_missing',
				'slug' => 'woocommerce',
				'name' => 'WooCommerce',
			],
			$failures[0]
		);
	}

	public function test_reports_a_required_plugin_that_is_installed_but_inactive(): void {
		$this->install_woocommerce( '10.0', false );

		$failures = $this->requirements()->require_plugin( 'woocommerce', 'WooCommerce' )->failures();

		$this->assertSame(
			[
				'type' => 'plugin_inactive',
				'slug' => 'woocommerce',
				'name' => 'WooCommerce',
				'file' => 'woocommerce/woocommerce.php',
			],
			$failures[0]
		);
	}

	/**
	 * @dataProvider plugin_versions
	 */
	public function test_required_plugin_version_is_checked_against_the_minimum(
		string $installed,
		bool $expected_failure
	): void {
		$this->install_woocommerce( $installed );

		$failures = $this->requirements()
						->require_plugin( 'woocommerce', 'WooCommerce', '10.0' )
						->failures();

		$this->assertSame( $expected_failure, isset( $failures[0] ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function plugin_versions(): array {
		return [
			'below the minimum'        => [ '9.9', true ],
			'exactly the minimum'      => [ '10.0', false ],
			'above the minimum'        => [ '11.2', false ],
			'no version in the header' => [ '', true ],
		];
	}

	public function test_plugin_version_failure_reports_both_versions(): void {
		$this->install_woocommerce( '9.9' );

		$failures = $this->requirements()
						->require_plugin( 'woocommerce', 'WooCommerce', '10.0' )
						->failures();

		$this->assertSame(
			[
				'type'     => 'plugin_version',
				'slug'     => 'woocommerce',
				'name'     => 'WooCommerce',
				'file'     => 'woocommerce/woocommerce.php',
				'required' => '10.0',
				'current'  => '9.9',
			],
			$failures[0]
		);
	}

	/**
	 * A plugin required without a version is satisfied by any version, so a missing
	 * version header must not be treated as a failure.
	 */
	public function test_a_plugin_required_without_a_version_accepts_any_version(): void {
		$this->install_woocommerce( '' );

		$this->assertTrue(
			$this->requirements()->require_plugin( 'woocommerce', 'WooCommerce' )->satisfied()
		);
	}

	public function test_reports_a_missing_autoloader(): void {
		$failures = $this->requirements()->require_autoloader( self::MISSING_FILE )->failures();

		$this->assertSame( [ 'type' => 'autoloader' ], $failures[0] );
	}

	/**
	 * The general autoloader must be reachable as a fallback, because php-scoper only
	 * writes scoper-autoload.php when symbols are exposed. Uses real paths, since
	 * file_exists() is native and cannot be stubbed.
	 */
	public function test_prefers_the_first_autoloader_candidate_that_exists(): void {
		$requirements = $this->requirements()->require_autoloader( self::MISSING_FILE, __FILE__ );

		$this->assertTrue( $requirements->satisfied() );
		$this->assertSame( __FILE__, $requirements->autoloader() );
	}

	public function test_autoloader_is_empty_when_no_candidate_exists(): void {
		$this->assertSame( '', $this->requirements()->require_autoloader( self::MISSING_FILE )->autoloader() );
	}

	/**
	 * An admin should be able to fix everything in one pass rather than discovering
	 * the next problem after solving the first.
	 */
	public function test_reports_every_unmet_requirement_together(): void {
		$this->wp_version = '6.4';

		$failures = $this->requirements()
						->require_php( '99.0' )
						->require_wp( '6.5' )
						->require_plugin( 'woocommerce', 'WooCommerce' )
						->require_autoloader( self::MISSING_FILE )
						->failures();

		$this->assertSame(
			[ 'php', 'wp', 'plugin_missing', 'autoloader' ],
			array_column( $failures, 'type' )
		);
	}

	public function test_installed_plugins_are_read_once_per_instance(): void {
		$this->install_woocommerce( '10.0' );

		$requirements = $this->requirements()
							->require_plugin( 'woocommerce', 'WooCommerce', '10.0' )
							->require_plugin( 'jetpack', 'Jetpack' );

		$requirements->failures();
		$requirements->failures();
		$requirements->satisfied();

		$this->assertSame( 1, $this->get_plugins_calls );
	}

	public function test_failures_are_computed_once_and_reused(): void {
		$requirements = $this->requirements()->require_wp( '6.5' );
		$first        = $requirements->failures();

		$this->wp_version = '1.0';

		$this->assertSame( $first, $requirements->failures() );
	}

	public function test_failure_registers_the_notice_and_the_activation_guard(): void {
		$this->wp_version = '6.4';

		Actions\expectAdded( 'admin_notices' )->once();

		$this->requirements()->require_wp( '6.5' )->satisfied();

		$this->assertSame( 1, $this->activation_hooks );
	}

	/**
	 * Nothing stops a second caller asking again, and a second registration would
	 * double the notice.
	 */
	public function test_asking_twice_registers_the_handlers_once(): void {
		$this->wp_version = '6.4';

		Actions\expectAdded( 'admin_notices' )->once();

		$requirements = $this->requirements()->require_wp( '6.5' );
		$requirements->satisfied();
		$requirements->satisfied();

		$this->assertSame( 1, $this->activation_hooks );
	}

	public function test_notice_renders_nothing_when_requirements_are_met(): void {
		$requirements = $this->requirements()->require_wp( '6.5' );

		$this->assertSame( '', $this->capture( static fn () => $requirements->render_notice() ) );
	}

	public function test_notice_reports_the_php_version_without_hardcoding_it(): void {
		$requirements = $this->requirements()->require_php( '99.0' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'requires PHP 99.0 or later', $output );
		$this->assertStringContainsString( PHP_VERSION, $output );
	}

	public function test_notice_reports_the_wordpress_version(): void {
		$this->wp_version = '6.4';
		$requirements     = $this->requirements()->require_wp( '6.5' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'requires WordPress 6.5 or later', $output );
		$this->assertStringContainsString( '6.4', $output );
	}

	public function test_notice_asks_for_a_reinstall_when_the_autoloader_is_missing(): void {
		$requirements = $this->requirements()->require_autoloader( self::MISSING_FILE );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'missing its Composer dependencies', $output );
	}

	public function test_a_single_failure_renders_as_a_paragraph_not_a_list(): void {
		$requirements = $this->requirements()->require_php( '99.0' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringNotContainsString( '<ul>', $output );
		$this->assertStringNotContainsString( 'The following requirements are not met:', $output );
	}

	public function test_multiple_failures_render_as_a_list(): void {
		$this->wp_version = '6.4';
		$requirements     = $this->requirements()->require_php( '99.0' )->require_wp( '6.5' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'The following requirements are not met:', $output );
		$this->assertSame( 2, substr_count( $output, '<li>' ) );
	}

	public function test_install_link_is_offered_to_a_user_who_can_install_plugins(): void {
		$this->capabilities = [ 'install_plugins' ];
		$requirements       = $this->requirements()->require_plugin( 'woocommerce', 'WooCommerce' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'Install Plugin', $output );
		$this->assertStringContainsString( 'wp-admin/update.php?', $output );
		$this->assertStringContainsString( 'action=install-plugin', $output );
		$this->assertStringContainsString( 'plugin=woocommerce', $output );
		$this->assertStringContainsString( '_wpnonce=', $output );
	}

	public function test_activate_link_is_offered_to_a_user_who_can_activate_plugins(): void {
		$this->capabilities = [ 'activate_plugins' ];
		$this->install_woocommerce( '10.0', false );
		$requirements = $this->requirements()->require_plugin( 'woocommerce', 'WooCommerce' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'Activate Plugin', $output );
		$this->assertStringContainsString( 'action=activate', $output );
		$this->assertStringContainsString( 'plugin=woocommerce%2Fwoocommerce.php', $output );
	}

	/**
	 * The capability guarding each link must be the specific one, not merely "the
	 * user can do something".
	 */
	public function test_install_link_is_withheld_from_a_user_who_can_only_activate(): void {
		$this->capabilities = [ 'activate_plugins' ];
		$requirements       = $this->requirements()->require_plugin( 'woocommerce', 'WooCommerce' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'which is not installed', $output );
		$this->assertStringNotContainsString( 'Install Plugin', $output );
	}

	public function test_activate_link_is_withheld_from_a_user_who_can_only_install(): void {
		$this->capabilities = [ 'install_plugins' ];
		$this->install_woocommerce( '10.0', false );
		$requirements = $this->requirements()->require_plugin( 'woocommerce', 'WooCommerce' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'not active', $output );
		$this->assertStringNotContainsString( 'Activate Plugin', $output );
	}

	public function test_version_failures_never_offer_a_link(): void {
		$this->capabilities = [ 'install_plugins', 'activate_plugins' ];
		$this->install_woocommerce( '9.9' );
		$requirements = $this->requirements()->require_plugin( 'woocommerce', 'WooCommerce', '10.0' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringNotContainsString( '<a href', $output );
	}

	public function test_a_plugin_with_no_version_header_is_described_in_words(): void {
		$this->install_woocommerce( '' );
		$requirements = $this->requirements()->require_plugin( 'woocommerce', 'WooCommerce', '10.0' );

		$output = $this->capture( static fn () => $requirements->render_notice() );

		$this->assertStringContainsString( 'an unknown version', $output );
	}

	public function test_activation_is_blocked_with_the_reason(): void {
		$this->wp_version = '6.4';

		$this->requirements()->require_wp( '6.5' )->block_activation();

		$this->assertNotNull( $this->died );
		$this->assertStringContainsString( 'could not be activated', $this->died['message'] );
		$this->assertStringContainsString( 'requires WordPress 6.5 or later', $this->died['message'] );
	}

	public function test_activation_lists_every_reason(): void {
		$this->wp_version = '6.4';

		$this->requirements()
			->require_php( '99.0' )
			->require_wp( '6.5' )
			->block_activation();

		$this->assertNotNull( $this->died );
		$this->assertSame( 2, substr_count( $this->died['message'], '<li>' ) );
	}
}
