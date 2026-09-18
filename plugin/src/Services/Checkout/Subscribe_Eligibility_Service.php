<?php
/**
 * Subscribe-and-save eligibility service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services\Checkout;

use FuelChef\Subscriptions\Values\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a cart meets the store's minimums to be offered the subscribe discount,
 * shared by classic and block checkout the same way `Subscribe_Discount_Service` is -
 * both need the same rule at different moments (a live cart, and the final order), so
 * this takes plain values rather than a `WC_Cart` or `WC_Order` directly.
 */
final class Subscribe_Eligibility_Service {


	/**
	 * Whether a subtotal and item quantity meet the store's configured minimums. A
	 * minimum of zero places no restriction on that dimension.
	 */
	public function is_eligible( float $subtotal, int $quantity, Settings $settings ): bool {
		if ( $settings->minimum_order_amount() > 0.0 && $subtotal < $settings->minimum_order_amount() ) {
			return false;
		}

		return ! ( $settings->minimum_cart_quantity() > 0 && $quantity < $settings->minimum_cart_quantity() );
	}
}
