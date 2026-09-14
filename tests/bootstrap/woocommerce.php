<?php
/**
 * Minimal WooCommerce stand-ins for the unit suite.
 *
 * `WooCommerce\Destination_Catalog` reads shipping zones through
 * `WC_Shipping_Zones`, whose methods are static and therefore cannot be replaced by
 * Brain Monkey or Mockery. The suite never loads WooCommerce, so these classes do not
 * otherwise exist - defining a minimal version of each here gives the catalog something
 * real to call, and gives a test somewhere to put the answer.
 *
 * Same principle as `wpdb.php`: nothing here is ever asserted on. It is a seam, not a
 * fixture.
 */

declare(strict_types=1);

/**
 * Stands in for one WooCommerce shipping zone.
 */
class WC_Shipping_Zone {


	/**
	 * Creates the zone stand-in.
	 *
	 * @param string $zone_name The zone's name.
	 * @param string $location The zone's formatted location summary.
	 */
	public function __construct(
		private string $zone_name = '',
		private string $location = ''
	) {
	}

	/**
	 * The zone's name.
	 */
	public function get_zone_name(): string {
		return $this->zone_name;
	}

	/**
	 * The zone's formatted location summary.
	 */
	public function get_formatted_location(): string {
		return $this->location;
	}
}

/**
 * Stands in for WooCommerce's shipping zone registry.
 *
 * Both answers are public static properties rather than constructor arguments, because
 * the real class is reached statically too: a test sets them directly the way it would
 * stub a method on a mock.
 */
class WC_Shipping_Zones {


	/**
	 * The zones `get_zones()` returns.
	 *
	 * @var array<int, mixed>
	 */
	public static array $zones = [];

	/**
	 * The zone `get_zone()` returns, or false when there is none.
	 *
	 * @var WC_Shipping_Zone|false
	 */
	public static $zone = false;

	/**
	 * The configured shipping zones, keyed by zone ID.
	 *
	 * @return array<int, mixed> The configured zones.
	 */
	public static function get_zones( string $context = 'admin' ): array {
		return self::$zones;
	}

	/**
	 * One shipping zone by ID.
	 *
	 * @return WC_Shipping_Zone|false The zone, or false when there is none.
	 */
	public static function get_zone( int $zone_id ) {
		return self::$zone;
	}
}
