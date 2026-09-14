<?php
/**
 * Destination catalog.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\WooCommerce;

use FuelChef\Subscriptions\Utils\Row_Caster;
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
 * This is the only place that knows where a destination comes from: shipping zones from
 * `WC_Shipping_Zones`, pickup locations from the `pickup_location_pickup_locations`
 * option WooCommerce's local pickup settings write. It is deliberately read-only and
 * holds no repository - it answers "what could be assigned", never "what is assigned",
 * which is `Repositories\Schedule_Destination_Repository`'s job. An admin controller
 * joins the two to render them together.
 *
 * Results are memoised for the request, since both sources are already cached by
 * WordPress (an autoloaded option, and the shipping zone data store's own cache) - this
 * only avoids re-shaping the same rows more than once while rendering one screen.
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
				(string) Row_Caster::int( $zone['zone_id'] ?? null ),
				Row_Caster::string( $zone['zone_name'] ?? null ),
				$this->blank_to_null( Row_Caster::string( $zone['formatted_zone_location'] ?? null ) )
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
		$locations = get_option( self::PICKUP_LOCATIONS_OPTION, [] );

		if ( ! is_array( $locations ) ) {
			return [];
		}

		$pickup_enabled = $this->pickup_enabled();
		$options        = [];

		foreach ( array_values( $locations ) as $index => $location ) {
			if ( ! is_array( $location ) ) {
				continue;
			}

			$name = Row_Caster::string( $location['name'] ?? null );

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
		$settings = get_option( self::PICKUP_SETTINGS_OPTION, [] );

		if ( ! is_array( $settings ) ) {
			return false;
		}

		return 'yes' === Row_Caster::string( $settings['enabled'] ?? null );
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
			$value = Row_Caster::string( $address[ $part ] ?? null );

			if ( Str::is_blank( $value ) ) {
				continue;
			}

			$parts[] = $value;
		}

		return [] === $parts ? null : implode( ', ', $parts );
	}

	/**
	 * Turns a blank string into null.
	 *
	 * `Row_Caster::string()` narrows a missing value to `''`; a zone with no formatted
	 * location should read as "no description" rather than an empty one.
	 */
	private function blank_to_null( string $value ): ?string {
		return Str::is_blank( $value ) ? null : $value;
	}
}
