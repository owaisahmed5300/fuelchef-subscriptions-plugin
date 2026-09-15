<?php
/**
 * Chosen shipping destination resolver.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

use FuelChef\Subscriptions\Values\Destination_Option;
use FuelChef\Subscriptions\Values\Destination_Type;
use WC_Shipping_Zones;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the destination the customer has currently chosen at checkout.
 *
 * WooCommerce tracks a chosen shipping rate per package, not a destination. This turns
 * that rate into the `(type, key)` pair `Destination_Catalog` and `Availability_Service`
 * already understand, so a schedule can be looked up without the checkout layer knowing
 * anything about zones or pickup locations itself.
 */
final class Chosen_Shipping_Destination {


	/**
	 * The rate ID prefix WooCommerce's local pickup gives every pickup location rate,
	 * e.g. `pickup_location:2`.
	 */
	private const PICKUP_RATE_PREFIX = 'pickup_location:';

	/**
	 * Creates the resolver.
	 */
	public function __construct(
		private Destination_Catalog $catalog
	) {
	}

	/**
	 * The destination the customer's chosen shipping rate resolves to.
	 *
	 * Null when no rate is chosen yet, or the destination it names is no longer one a
	 * customer can reach - a pickup location that was disabled after being cached in the
	 * session, for instance.
	 */
	public function resolve(): ?Destination_Option {
		$package = $this->current_package();

		if ( null === $package ) {
			return null;
		}

		$rate_id = $this->chosen_rate_id( $package );

		if ( null === $rate_id ) {
			return null;
		}

		$key = $this->key_for( $rate_id, $package );

		if ( null === $key ) {
			return null;
		}

		$destination = $this->catalog->find( $this->type_for( $rate_id ), $key );

		return null !== $destination && $destination->enabled() ? $destination : null;
	}

	/**
	 * The destination type a chosen rate ID belongs to.
	 */
	public function type_for( string $rate_id ): string {
		return str_starts_with( $rate_id, self::PICKUP_RATE_PREFIX )
			? Destination_Type::PICKUP_LOCATION
			: Destination_Type::SHIPPING_ZONE;
	}

	/**
	 * The destination key a chosen rate ID and its package resolve to.
	 *
	 * A pickup rate carries its own key in the rate ID itself. Any other rate belongs to
	 * whichever zone matches the package's destination address, since every rate offered
	 * for one package comes from that single zone.
	 *
	 * @param string               $rate_id The chosen rate ID.
	 * @param array<string, mixed> $package The shipping package the rate was chosen for.
	 */
	public function key_for( string $rate_id, array $package ): ?string {
		if ( str_starts_with( $rate_id, self::PICKUP_RATE_PREFIX ) ) {
			$index = substr( $rate_id, strlen( self::PICKUP_RATE_PREFIX ) );

			return '' === $index ? null : $index;
		}

		return (string) WC_Shipping_Zones::get_zone_matching_package( $package )->get_id();
	}

	/**
	 * The customer's first shipping package, or null when none is calculated yet.
	 * Calculates it itself when `WC_Shipping` has nothing to read yet, since not every
	 * caller (a bare REST route, for instance) has already triggered that upstream.
	 *
	 * @return array<string, mixed>|null The package, shaped as WooCommerce builds it.
	 */
	private function current_package(): ?array {
		$packages = WC()->shipping()->get_packages();

		if ( ( ! is_array( $packages ) || [] === $packages ) && null !== WC()->cart ) {
			$packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
		}

		if ( ! is_array( $packages ) || ! isset( $packages[0] ) || ! is_array( $packages[0] ) ) {
			return null;
		}

		/** @var array<string, mixed> $package */
		$package = $packages[0];

		return $package;
	}

	/**
	 * The rate ID WooCommerce has chosen for a package, resolving its own default when
	 * nothing has been explicitly picked yet.
	 *
	 * @param array<string, mixed> $package The shipping package to resolve a rate for.
	 */
	private function chosen_rate_id( array $package ): ?string {
		$chosen = wc_get_chosen_shipping_method_for_package( 0, $package );

		return is_string( $chosen ) && '' !== $chosen ? $chosen : null;
	}
}
