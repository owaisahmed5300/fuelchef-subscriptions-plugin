<?php
/**
 * Unit tests for the destination catalog.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Values\Destination_Type;
use FuelChef\Subscriptions\WooCommerce\Destination_Catalog;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

/**
 * @covers \FuelChef\Subscriptions\WooCommerce\Destination_Catalog
 */
final class Destination_Catalog_Test extends TestCase {


	protected function setUp(): void {
		parent::setUp();

		WC_Shipping_Zones::$zones = [];
		WC_Shipping_Zones::$zone  = false;

		Functions\when( 'get_option' )->justReturn( [] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function pickup_settings( bool $enabled ): array {
		return [ 'enabled' => $enabled ? 'yes' : 'no' ];
	}

	public function test_zones_are_read_from_wc_shipping_zones(): void {
		WC_Shipping_Zones::$zones = [
			5 => [
				'zone_id'                 => 5,
				'zone_name'                => 'Karachi',
				'formatted_zone_location' => 'Karachi, Sindh',
			],
		];

		$options = ( new Destination_Catalog() )->for_type( Destination_Type::SHIPPING_ZONE );

		$this->assertCount( 1, $options );
		$this->assertSame( Destination_Type::SHIPPING_ZONE, $options[0]->type() );
		$this->assertSame( '5', $options[0]->key() );
		$this->assertSame( 'Karachi', $options[0]->label() );
		$this->assertSame( 'Karachi, Sindh', $options[0]->description() );
	}

	public function test_the_catch_all_zone_is_included_when_it_exists(): void {
		WC_Shipping_Zones::$zone = new WC_Shipping_Zone( 'Rest of the World' );

		Functions\when( 'esc_html__' )->returnArg( 1 );

		$options = ( new Destination_Catalog() )->for_type( Destination_Type::SHIPPING_ZONE );

		$this->assertCount( 1, $options );
		$this->assertSame( '0', $options[0]->key() );
		$this->assertSame( 'Rest of the World', $options[0]->label() );
	}

	public function test_pickup_locations_are_read_from_the_option(): void {
		Functions\when( 'get_option' )->alias(
			fn ( string $option, mixed $default = false ): mixed => match ( $option ) {
				'pickup_location_pickup_locations' => [
					[
						'name'    => 'Central Depot',
						'enabled' => true,
						'address' => [ 'address_1' => '1 Main St', 'city' => 'Karachi' ],
					],
				],
				'woocommerce_pickup_location_settings' => $this->pickup_settings( true ),
				default => $default,
			}
		);

		$options = ( new Destination_Catalog() )->for_type( Destination_Type::PICKUP_LOCATION );

		$this->assertCount( 1, $options );
		$this->assertSame( Destination_Type::PICKUP_LOCATION, $options[0]->type() );
		$this->assertSame( '0', $options[0]->key() );
		$this->assertSame( 'Central Depot', $options[0]->label() );
		$this->assertSame( '1 Main St, Karachi', $options[0]->description() );
		$this->assertTrue( $options[0]->enabled() );
	}

	public function test_a_pickup_location_is_disabled_when_the_method_itself_is_off(): void {
		Functions\when( 'get_option' )->alias(
			fn ( string $option, mixed $default = false ): mixed => match ( $option ) {
				'pickup_location_pickup_locations' => [
					[ 'name' => 'Central Depot', 'enabled' => true, 'address' => [] ],
				],
				'woocommerce_pickup_location_settings' => $this->pickup_settings( false ),
				default => $default,
			}
		);

		$options = ( new Destination_Catalog() )->for_type( Destination_Type::PICKUP_LOCATION );

		$this->assertFalse( $options[0]->enabled() );
	}

	public function test_a_blank_named_pickup_location_is_skipped(): void {
		Functions\when( 'get_option' )->alias(
			fn ( string $option, mixed $default = false ): mixed => match ( $option ) {
				'pickup_location_pickup_locations' => [
					[ 'name' => '', 'enabled' => true, 'address' => [] ],
				],
				'woocommerce_pickup_location_settings' => $this->pickup_settings( true ),
				default => $default,
			}
		);

		$this->assertSame( [], ( new Destination_Catalog() )->for_type( Destination_Type::PICKUP_LOCATION ) );
	}

	public function test_find_returns_null_when_nothing_matches(): void {
		$this->assertNull( ( new Destination_Catalog() )->find( Destination_Type::SHIPPING_ZONE, '99' ) );
	}

	public function test_find_returns_the_matching_option(): void {
		WC_Shipping_Zones::$zones = [
			5 => [ 'zone_id' => 5, 'zone_name' => 'Karachi', 'formatted_zone_location' => '' ],
		];

		$found = ( new Destination_Catalog() )->find( Destination_Type::SHIPPING_ZONE, '5' );

		$this->assertNotNull( $found );
		$this->assertSame( 'Karachi', $found->label() );
	}

	public function test_grouped_includes_every_type_even_when_one_has_nothing(): void {
		WC_Shipping_Zones::$zones = [
			5 => [ 'zone_id' => 5, 'zone_name' => 'Karachi', 'formatted_zone_location' => '' ],
		];

		$grouped = ( new Destination_Catalog() )->grouped();

		$this->assertCount( 1, $grouped[ Destination_Type::SHIPPING_ZONE ] );
		$this->assertSame( [], $grouped[ Destination_Type::PICKUP_LOCATION ] );
	}

	public function test_results_are_memoised_for_the_request(): void {
		WC_Shipping_Zones::$zones = [
			5 => [ 'zone_id' => 5, 'zone_name' => 'Karachi', 'formatted_zone_location' => '' ],
		];

		$catalog = new Destination_Catalog();
		$first   = $catalog->all();

		WC_Shipping_Zones::$zones = [];

		$this->assertSame( $first, $catalog->all() );
	}
}
