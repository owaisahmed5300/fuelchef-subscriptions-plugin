<?php
/**
 * Subscribe-and-save discount service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services;

use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates the subscribe discount, shared by classic and block checkout's own
 * subscribe-discount field classes rather than duplicated - both apply the same business
 * rule, at two different moments (a live cart total, and the final order).
 */
final class Subscribe_Discount_Service {


	/**
	 * The discount amount for a subtotal under a given settings configuration. Zero
	 * when the applicability setting excludes the initial order, the discount percent
	 * is zero, or the subtotal itself is zero.
	 */
	public function discount_amount( float $subtotal, Settings $settings ): float {
		if ( Subscribe_Applicability::RENEWAL_ONLY === $settings->subscribe_applicability() ) {
			return 0.0;
		}

		if ( $settings->subscribe_discount_percent() <= 0 || $subtotal <= 0.0 ) {
			return 0.0;
		}

		return round( $subtotal * $settings->subscribe_discount_percent() / 100, wc_get_price_decimals() );
	}
}
