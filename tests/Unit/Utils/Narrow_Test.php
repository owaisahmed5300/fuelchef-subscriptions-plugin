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
}
