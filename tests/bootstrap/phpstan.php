<?php
/**
 * PHPStan bootstrap.
 *
 * Declares the two constants PHPStan cannot work out for itself. It reads the other
 * plugin constants straight from the `define()` calls in the main plugin file, which
 * it now analyses, but these two are built by WordPress functions at runtime.
 *
 * Only their names matter. Both are listed under `dynamicConstantNames` in
 * phpstan.neon.dist, so the values below are never treated as real.
 */

declare(strict_types=1);

define( 'WP_PLUGIN_BOILERPLATE_DIR', '' );
define( 'WP_PLUGIN_BOILERPLATE_URL', '' );
