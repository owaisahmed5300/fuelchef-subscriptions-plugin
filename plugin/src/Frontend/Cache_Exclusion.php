<?php
/**
 * Checkout page cache exclusion.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend;

use FuelChef\Subscriptions\Services\Checkout_Presence;

defined( 'ABSPATH' ) || exit;

/**
 * Marks the current page uncacheable whenever the checkout shortcode or block is present on
 * it, regardless of whether WooCommerce itself considers it the checkout page.
 *
 * WooCommerce's own cache exclusion only ever covers the page configured under
 * WooCommerce > Settings > Advanced, so a page that merely embeds the checkout shortcode or
 * block elsewhere would otherwise be cached like any other page - freezing one customer's
 * cart contents, login state and subscribe-discount eligibility into a full-page cache and
 * serving them to every later visitor.
 */
final class Cache_Exclusion {


	/**
	 * Creates the cache-exclusion handler.
	 */
	public function __construct(
		private Checkout_Presence $checkout
	) {
	}

	/**
	 * Registers the exclusion hook.
	 */
	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_exclude' ] );
	}

	/**
	 * Marks the page uncacheable, the same way WooCommerce marks its own cart and checkout
	 * pages - a constant every major caching plugin already respects, plus the matching HTTP
	 * headers for anything downstream that reads those instead.
	 */
	public function maybe_exclude(): void {
		if ( ! $this->checkout->has_either() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();
	}
}
