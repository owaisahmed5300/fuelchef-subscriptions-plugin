<?php
/**
 * Shared fixtures for repository tests.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Repositories;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Utils\Clock;
use Mockery;
use Mockery\MockInterface;
use wpdb;

/**
 * A real repository is tested over a mocked `wpdb`, never a mock of the
 * repository's own (final) class. `prepare()` is stubbed to interpolate
 * `%i`/`%d`/`%s` literally rather than emulate WordPress's own escaping -
 * that's WordPress's job, not the repository's.
 */
abstract class Repository_TestCase extends TestCase {


	/**
	 * In-memory stand-in for the WordPress object cache, keyed by
	 * "{group}:{key}", so `wp_cache_get()` sees what `wp_cache_set()` stored.
	 *
	 * @var array<string, mixed>
	 */
	private array $cache = [];

	protected function setUp(): void {
		parent::setUp();

		$this->cache = [];

		Functions\when( 'wp_cache_get' )->alias(
			fn ( int|string $key, string $group = '' ): mixed => $this->cache[ "{$group}:{$key}" ] ?? false
		);

		Functions\when( 'wp_cache_set' )->alias(
			function ( int|string $key, mixed $data, string $group = '' ): bool {
				$this->cache[ "{$group}:{$key}" ] = $data;

				return true;
			}
		);

		Functions\when( 'wp_cache_delete' )->alias(
			function ( int|string $key, string $group = '' ): bool {
				unset( $this->cache[ "{$group}:{$key}" ] );

				return true;
			}
		);
	}

	/**
	 * A mocked `wpdb`, with `$prefix` set and `prepare()` stubbed to
	 * interpolate placeholders literally.
	 */
	protected function wpdb(): wpdb&MockInterface {
		$wpdb         = Mockery::mock( wpdb::class );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			static function ( string $query, mixed ...$args ): string {
				$query = str_replace( '%i', '%s', $query );

				return vsprintf( $query, $args );
			}
		);

		return $wpdb;
	}

	/**
	 * A real Clock. Its output is real wall-clock time, so tests assert a
	 * timestamp was set, never a specific value.
	 */
	protected function clock(): Clock {
		return new Clock();
	}
}
