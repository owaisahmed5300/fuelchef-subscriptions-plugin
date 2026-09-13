<?php
/**
 * Minimal `wpdb` stand-in for the unit suite.
 *
 * No WordPress is loaded, so the real class does not exist, but repositories and the
 * installer type-hint against it. Every method here is overridden by a Mockery mock in
 * the tests that use it; the bodies below only exist so the class has the right shape.
 */

declare(strict_types=1);

define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );

/**
 * Minimal `wpdb` stand-in for the unit suite.
 */
class wpdb {

	public string $prefix = 'wp_';

	public int $insert_id = 0;

	public string $last_error = '';

	public function get_charset_collate(): string {
		return '';
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_row( string $query, string $output = OBJECT ): array|object|null {
		return null;
	}

	/**
	 * @return list<array<string, mixed>>|null
	 */
	public function get_results( string $query, string $output = OBJECT ): ?array {
		return null;
	}

	public function prepare( string $query, mixed ...$args ): string {
		return $query;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( string $table, array $data ): int|false {
		return false;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $where
	 */
	public function update( string $table, array $data, array $where ): int|false {
		return false;
	}

	/**
	 * @param array<string, mixed> $where
	 * @param array<int, string>   $where_format
	 */
	public function delete( string $table, array $where, array $where_format = [] ): int|false {
		return false;
	}
}
