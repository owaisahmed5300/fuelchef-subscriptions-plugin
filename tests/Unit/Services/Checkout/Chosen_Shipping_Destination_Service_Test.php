<?php
/**
 * Unit tests for the chosen shipping destination resolver.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services\Checkout;

use FuelChef\Subscriptions\Services\Checkout\Chosen_Shipping_Destination_Service;
use FuelChef\Subscriptions\Services\Scheduling\Destination_Catalog_Service;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Values\Destination_Type;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

/**
 * @covers \FuelChef\Subscriptions\Services\Checkout\Chosen_Shipping_Destination_Service
 */
final class Chosen_Shipping_Destination_Service_Test extends TestCase {


	protected function setUp(): void {
		parent::setUp();

		WC_Shipping_Zones::$matching_zone = null;
	}

	private function resolver(): Chosen_Shipping_Destination_Service {
		return new Chosen_Shipping_Destination_Service( new Destination_Catalog_Service() );
	}

	public function test_type_for_a_pickup_rate_is_pickup_location(): void {
		$this->assertSame(
			Destination_Type::PICKUP_LOCATION,
			$this->resolver()->type_for( 'pickup_location:2' )
		);
	}

	public function test_type_for_any_other_rate_is_shipping_zone(): void {
		$this->assertSame(
			Destination_Type::SHIPPING_ZONE,
			$this->resolver()->type_for( 'flat_rate:5' )
		);
	}

	public function test_key_for_a_pickup_rate_is_its_index(): void {
		$this->assertSame(
			'2',
			$this->resolver()->key_for( 'pickup_location:2', [] )
		);
	}

	public function test_key_for_a_pickup_rate_with_no_index_is_null(): void {
		$this->assertNull(
			$this->resolver()->key_for( 'pickup_location:', [] )
		);
	}

	public function test_key_for_any_other_rate_is_the_matching_zone_id(): void {
		WC_Shipping_Zones::$matching_zone = new WC_Shipping_Zone( 'Karachi', '', 7 );

		$this->assertSame(
			'7',
			$this->resolver()->key_for( 'flat_rate:5', [ 'destination' => [] ] )
		);
	}

	public function test_key_for_any_other_rate_falls_back_to_the_catch_all_zone(): void {
		$this->assertSame(
			'0',
			$this->resolver()->key_for( 'flat_rate:5', [ 'destination' => [] ] )
		);
	}
}
