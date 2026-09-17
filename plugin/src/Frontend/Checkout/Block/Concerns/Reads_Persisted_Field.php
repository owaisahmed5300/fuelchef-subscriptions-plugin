<?php
/**
 * Persisted additional-field reader trait.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout\Block\Concerns;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Reads one of this plugin's own Additional Checkout Fields API fields back off an order,
 * shared by `Block\Fulfilment_Date_Field` and `Block\Subscribe_And_Save`.
 */
trait Reads_Persisted_Field {


	/**
	 * A field's persisted value on an order, read through the Additional Checkout Fields
	 * API rather than the order's raw meta.
	 */
	private function persisted_field_value( string $field_id, WC_Order $order ): mixed {
		// Automattic\WooCommerce\Blocks\Container is not covered by any available
		// PHPStan stub package, so its return type is unknowable here; narrowed straight
		// to the one class this plugin actually calls a method on.
		$container = Package::container();

		/** @var CheckoutFields $checkout_fields */
		$checkout_fields = $container->get( CheckoutFields::class );

		return $checkout_fields->get_field_from_object( $field_id, $order, 'other' );
	}
}
