<?php
/**
 * Destination catalog.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Utils\Str;
use FuelChef\Subscriptions\Values\Destination_Option;
use FuelChef\Subscriptions\Values\Destination_Type;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the destinations WooCommerce currently offers, so a schedule can be assigned to
 * one.
 *
 * Zones and pickup locations only; `Repositories\Schedule_Destination_Repository` holds
 * what's actually assigned.
 */
final class Destination_Catalog {


	/**
	 * The option WooCommerce local pickup stores its locations in.
	 */
	private const PICKUP_LOCATIONS_OPTION = 'pickup_location_pickup_locations';

	/**
	 * The option holding WooCommerce local pickup's own settings.
	 */
	private const PICKUP_SETTINGS_OPTION = 'woocommerce_pickup_location_settings';

	/**
	 * The ID WooCommerce gives the catch-all "Rest of the World" zone.
	 */
	private const CATCH_ALL_ZONE_ID = 0;

	/**
	 * Every destination, resolved once per request.
	 *
	 * @var list<Destination_Option>|null
	 */
	private ?array $options = null;

	/**
	 * Every destination that can be assigned, zones before pickup locations, each in the
	 * order WooCommerce itself lists them.
	 *
	 * @return list<Destination_Option> Every assignable destination.
	 */
	public function all(): array {
		if ( null === $this->options ) {
			$this->options = array_merge( $this->zones(), $this->pickup_locations() );
		}

		return $this->options;
	}

	/**
	 * Every destination of one type.
	 *
	 * @param string $destination_type One of the `Destination_Type` constants.
	 *
	 * @return list<Destination_Option> The matching destinations.
	 */
	public function for_type( string $destination_type ): array {
		return array_values(
			array_filter(
				$this->all(),
				static fn ( Destination_Option $option ): bool => $option->type() === $destination_type
			)
		);
	}

	/**
	 * Every destination, grouped by type, in `Destination_Type` order and including a
	 * type that currently has nothing to offer.
	 *
	 * @return array<string, list<Destination_Option>> The destinations, keyed by type.
	 */
	public function grouped(): array {
		$grouped = [];

		foreach ( Destination_Type::all() as $destination_type ) {
			$grouped[ $destination_type ] = $this->for_type( $destination_type );
		}

		return $grouped;
	}

	/**
	 * Finds one destination by the `(type, key)` pair identifying it.
	 *
	 * Returns null when WooCommerce no longer offers it - a zone that was deleted, or a
	 * pickup location that was removed - which a caller renders as "no longer
	 * available" rather than silently dropping.
	 *
	 * @param string $destination_type Destination type.
	 * @param string $destination_key Destination key.
	 */
	public function find( string $destination_type, string $destination_key ): ?Destination_Option {
		foreach ( $this->all() as $option ) {
			if ( $option->matches( $destination_type, $destination_key ) ) {
				return $option;
			}
		}

		return null;
	}

	/**
	 * Reads every WooCommerce shipping zone, catch-all zone last.
	 *
	 * @return list<Destination_Option> The configured zones.
	 */
	private function zones(): array {
		$options = [];

		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			if ( ! is_array( $zone ) ) {
				continue;
			}

			$options[] = new Destination_Option(
				Destination_Type::SHIPPING_ZONE,
				(string) Narrow::int( $zone['zone_id'] ?? null ),
				Narrow::string( $zone['zone_name'] ?? null ),
				Str::blank_to_null( Narrow::string( $zone['formatted_zone_location'] ?? null ) )
			);
		}

		$catch_all = WC_Shipping_Zones::get_zone( self::CATCH_ALL_ZONE_ID );

		if ( $catch_all instanceof WC_Shipping_Zone ) {
			$options[] = new Destination_Option(
				Destination_Type::SHIPPING_ZONE,
				(string) self::CATCH_ALL_ZONE_ID,
				$catch_all->get_zone_name(),
				esc_html__( 'Everywhere your other zones do not cover.', 'fuelchef-subscriptions' )
			);
		}

		return $options;
	}

	/**
	 * Reads every WooCommerce local pickup location.
	 *
	 * A location's key is its index in the option, because that is the identity
	 * WooCommerce itself uses at checkout (`pickup_location:2`). Deleting a location
	 * therefore renumbers the ones after it - a caller sees an assignment it can no
	 * longer resolve rather than this silently guessing.
	 *
	 * @return list<Destination_Option> The configured pickup locations.
	 */
	private function pickup_locations(): array {
		$locations = Narrow::array( get_option( self::PICKUP_LOCATIONS_OPTION, [] ) );

		$pickup_enabled = $this->pickup_enabled();
		$options        = [];

		foreach ( array_values( $locations ) as $index => $location ) {
			if ( ! is_array( $location ) ) {
				continue;
			}

			$name = Narrow::string( $location['name'] ?? null );

			if ( Str::is_blank( $name ) ) {
				continue;
			}

			$options[] = new Destination_Option(
				Destination_Type::PICKUP_LOCATION,
				(string) $index,
				$name,
				$this->format_address( $location['address'] ?? null ),
				$pickup_enabled && true === ( $location['enabled'] ?? false )
			);
		}

		return $options;
	}

	/**
	 * Whether WooCommerce local pickup is switched on at all.
	 *
	 * An individual location can be enabled while the method itself is off, in which
	 * case no customer can reach any of them.
	 */
	private function pickup_enabled(): bool {
		$settings = Narrow::array( get_option( self::PICKUP_SETTINGS_OPTION, [] ) );

		return 'yes' === Narrow::string( $settings['enabled'] ?? null );
	}

	/**
	 * Turns a pickup location's stored address into one readable line.
	 *
	 * @param mixed $address Address parts, as WooCommerce stores them.
	 */
	private function format_address( mixed $address ): ?string {
		if ( ! is_array( $address ) ) {
			return null;
		}

		$parts = [];

		foreach ( [ 'address_1', 'city', 'state', 'postcode', 'country' ] as $part ) {
			$value = Narrow::string( $address[ $part ] ?? null );

			if ( Str::is_blank( $value ) ) {
				continue;
			}

			$parts[] = $value;
		}

		return [] === $parts ? null : implode( ', ', $parts );
	}
}
