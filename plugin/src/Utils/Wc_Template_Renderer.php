<?php
/**
 * Theme-overridable template renderer.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a PHP template under `plugin/templates/frontend/` through WooCommerce's own
 * templating system, and returns the output as a string.
 *
 * Unlike {@see Renderer}, a template rendered this way can be overridden by a theme -
 * copied to `yourtheme/fuelchef-subscriptions/<template>.php` - the same convention
 * WooCommerce's own templates use, and for the same reason: a store's theme is allowed to
 * restyle or restructure storefront-facing markup without patching the plugin. Admin screens
 * have no such override story and keep using {@see Renderer} instead.
 */
final class Wc_Template_Renderer {


	/**
	 * The theme override sub-directory, matching this plugin's own slug.
	 */
	private const TEMPLATE_PATH = 'fuelchef-subscriptions/';

	/**
	 * Renders a template to a string, via `wc_get_template_html()`.
	 *
	 * @param string               $template Template path, relative to
	 *                                       `templates/frontend/` and without the `.php`
	 *                                       extension, e.g. `checkout/fulfilment-date-field`.
	 * @param array<string, mixed> $args Data available to the template, each key extracted
	 *                                   to its own variable - WooCommerce's own template
	 *                                   convention, rather than a `$data` array.
	 *
	 * @return string The rendered output.
	 */
	public function render( string $template, array $args = [] ): string {
		return wc_get_template_html(
			$template . '.php',
			$args,
			self::TEMPLATE_PATH,
			FUELCHEF_SUBSCRIPTIONS_DIR . 'templates/frontend/'
		);
	}
}
