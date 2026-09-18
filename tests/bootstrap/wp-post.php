<?php
/**
 * Minimal `WP_Post` stand-in for the unit suite.
 *
 * No WordPress is loaded, so the real class does not exist, but Checkout_Presence
 * type-hints against it. Tests construct one directly with the properties they need.
 */

declare(strict_types=1);

/**
 * Minimal `WP_Post` stand-in for the unit suite.
 */
class WP_Post {

	public int $ID = 0;

	public string $post_content = '';
}
