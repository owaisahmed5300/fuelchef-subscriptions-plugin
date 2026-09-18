<?php
/**
 * Unit tests for untyped value narrowing.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Utils;

use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Utils\Narrow;

/**
 * @covers \FuelChef\Subscriptions\Utils\Narrow
 */
final class Narrow_Test extends TestCase {


	public function test_array_keeps_an_array_value(): void {
		$this->assertSame( [ 'a', 'b' ], Narrow::array( [ 'a', 'b' ] ) );
	}

	public function test_array_narrows_a_non_array_value_to_an_empty_array(): void {
		$this->assertSame( [], Narrow::array( 'not an array' ) );
	}

	public function test_array_narrows_null_to_an_empty_array(): void {
		$this->assertSame( [], Narrow::array( null ) );
	}

	public function test_string_keeps_a_string_value(): void {
		$this->assertSame( 'value', Narrow::string( 'value' ) );
	}

	public function test_string_narrows_a_non_string_value_to_an_empty_string(): void {
		$this->assertSame( '', Narrow::string( 5 ) );
	}

	public function test_nullable_string_keeps_a_string_value(): void {
		$this->assertSame( 'value', Narrow::nullable_string( 'value' ) );
	}

	public function test_nullable_string_narrows_a_non_string_value_to_null(): void {
		$this->assertNull( Narrow::nullable_string( 5 ) );
	}

	public function test_int_narrows_a_numeric_string(): void {
		$this->assertSame( 5, Narrow::int( '5' ) );
	}

	public function test_int_narrows_a_non_numeric_value_to_zero(): void {
		$this->assertSame( 0, Narrow::int( 'not a number' ) );
	}

	public function test_nullable_int_narrows_a_numeric_string(): void {
		$this->assertSame( 5, Narrow::nullable_int( '5' ) );
	}

	public function test_nullable_int_narrows_a_non_numeric_value_to_null(): void {
		$this->assertNull( Narrow::nullable_int( 'not a number' ) );
	}

	public function test_bool_is_true_for_a_wpdb_tinyint_one(): void {
		$this->assertTrue( Narrow::bool( '1' ) );
	}

	public function test_bool_is_false_for_a_wpdb_tinyint_zero(): void {
		$this->assertFalse( Narrow::bool( '0' ) );
	}

	public function test_bool_is_false_for_a_non_numeric_value(): void {
		$this->assertFalse( Narrow::bool( 'not a number' ) );
	}
}
