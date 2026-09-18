<?php
/**
 * Checkout shortcode/block presence detection.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services\Checkout;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Detects whether the classic checkout shortcode or the Checkout block is present on the
 * page currently being requested.
 *
 * This is the only signal this plugin's checkout assets and cache exclusion key off - never
 * the page WooCommerce itself is configured to treat as checkout under WooCommerce >
 * Settings > Advanced, since either can legitimately appear on a different page.
 */
final class Checkout_Presence_Service {


	/**
	 * The classic checkout shortcode's registered tag.
	 */
	private const SHORTCODE_TAG = 'woocommerce_checkout';

	/**
	 * The Checkout block's registered name.
	 */
	private const BLOCK_NAME = 'woocommerce/checkout';

	/**
	 * Memoized result of {@see self::has_classic_shortcode()}, resolved once per request.
	 */
	private ?bool $has_classic_shortcode = null;

	/**
	 * Memoized result of {@see self::has_block()}, resolved once per request.
	 */
	private ?bool $has_block = null;

	/**
	 * Whether the current page's content contains the classic checkout shortcode.
	 */
	public function has_classic_shortcode(): bool {
		if ( null === $this->has_classic_shortcode ) {
			$post = $this->current_post();

			$this->has_classic_shortcode = null !== $post
				&& has_shortcode( $post->post_content, self::SHORTCODE_TAG );
		}

		return $this->has_classic_shortcode;
	}

	/**
	 * Whether the current page's content contains the Checkout block.
	 */
	public function has_block(): bool {
		if ( null === $this->has_block ) {
			$post = $this->current_post();

			$this->has_block = null !== $post && has_block( self::BLOCK_NAME, $post );
		}

		return $this->has_block;
	}

	/**
	 * Whether either the classic shortcode or the Checkout block is present on the current
	 * page.
	 */
	public function has_either(): bool {
		return $this->has_classic_shortcode() || $this->has_block();
	}

	/**
	 * The currently queried post, or null before/outside the main query.
	 */
	private function current_post(): ?WP_Post {
		$post = get_post();

		return $post instanceof WP_Post ? $post : null;
	}
}
