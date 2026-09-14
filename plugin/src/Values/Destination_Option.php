<?php
/**
 * Destination option value.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Values;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * One destination a schedule could be assigned to: a WooCommerce shipping zone or pickup
 * location, as it currently exists in WooCommerce.
 *
 * Built by `WooCommerce\Destination_Catalog`, never stored - `Repositories\
 * Schedule_Destination_Repository` stores only the `(type, key)` pair this describes.
 */
final class Destination_Option {


	/**
	 * Creates a destination option.
	 *
	 * @param string      $type One of the `Destination_Type` constants.
	 * @param string      $key Identifier within that type, e.g. a shipping zone ID.
	 * @param string      $label The destination's name, as WooCommerce has it configured.
	 * @param string|null $description Optional detail, such as a formatted location or address.
	 * @param bool        $enabled Whether a customer can currently reach this destination.
	 */
	public function __construct(
		private string $type,
		private string $key,
		private string $label,
		private ?string $description = null,
		private bool $enabled = true
	) {
		if ( ! Destination_Type::is_valid( $type ) ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid destination type.', 'fuelchef-subscriptions' ) );
		}
	}

	/**
	 * One of the `Destination_Type` constants.
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Identifier within this option's type, e.g. a shipping zone ID.
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * The destination's name, as WooCommerce has it configured.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Optional detail, such as a formatted location or address.
	 */
	public function description(): ?string {
		return $this->description;
	}

	/**
	 * Whether a customer can currently reach this destination.
	 */
	public function enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Whether this option is the one identified by a `(type, key)` pair.
	 */
	public function matches( string $type, string $key ): bool {
		return $this->type === $type && $this->key === $key;
	}
}
