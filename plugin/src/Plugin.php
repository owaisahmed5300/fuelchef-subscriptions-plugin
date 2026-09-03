<?php
/**
 * Main plugin class.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Bootstraps the plugin by registering its hooks.
 *
 * This class only ever runs when the environment is already known to be good:
 * the main plugin file returns early when `Requirements` is not satisfied, so
 * there is nothing to re-check here.
 */
final class Plugin {


	/**
	 * WooCommerce feature compatibility declarations.
	 */
	private const WOOCOMMERCE_COMPATIBILITY = [
		'custom_order_tables' => true,
	];

	/**
	 * Constructor.
	 *
	 * @param Plugin_Activator $activator Plugin activator.
	 */
	public function __construct(
		protected Plugin_Activator $activator,
	) {
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );
		add_action(
			'before_woocommerce_init',
			[ $this, 'declare_woocommerce_compatibility' ]
		);

		register_activation_hook(
			FUELCHEF_SUBSCRIPTIONS_FILE,
			[ $this->activator, 'activate' ]
		);
	}

	/**
	 * Load the plugin text domain.
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'fuelchef-subscriptions',
			false,
			plugin_basename( FUELCHEF_SUBSCRIPTIONS_DIR ) . '/languages'
		);
	}

	/**
	 * Declare WooCommerce feature compatibility.
	 */
	public function declare_woocommerce_compatibility(): void {
		foreach ( self::WOOCOMMERCE_COMPATIBILITY as $feature => $compatible ) {
			FeaturesUtil::declare_compatibility(
				$feature,
				FUELCHEF_SUBSCRIPTIONS_FILE,
				$compatible
			);
		}
	}
}
