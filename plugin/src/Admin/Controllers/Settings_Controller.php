<?php
/**
 * Settings controller.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Controllers;

use FuelChef\Subscriptions\Admin\Concerns\Reads_Request_Fields;
use FuelChef\Subscriptions\Admin\Concerns\Verifies_Ajax_Request;
use FuelChef\Subscriptions\Admin\Concerns\Verifies_Page_Access;
use FuelChef\Subscriptions\Services\Settings_Service;
use FuelChef\Subscriptions\Utils\Renderer;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Settings screen and handles its ajax actions.
 */
final class Settings_Controller {


	use Reads_Request_Fields;
	use Verifies_Ajax_Request;
	use Verifies_Page_Access;

	/**
	 * Creates the controller.
	 */
	public function __construct(
		private Settings_Service $settings_service,
		private Renderer $renderer
	) {
	}

	/**
	 * Registers this controller's ajax actions.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fcs_save_settings', [ $this, 'ajax_save_settings' ] );
	}

	/**
	 * Renders the Settings screen.
	 */
	public function render(): void {
		$this->verify_page_access();

		$html = $this->renderer->render(
			'admin/settings',
			[
				'settings'        => $this->settings_service->get(),
				'applicabilities' => Subscribe_Applicability::all(),
				'currency_symbol' => get_woocommerce_currency_symbol(),
			]
		);

		// The template escapes every dynamic value itself; this is its own fully-built page markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Saves the cutoff, discount, applicability, eligibility and checkout copy settings.
	 */
	public function ajax_save_settings(): void {
		$this->verify_ajax_request();

		try {
			$settings = new Settings(
				cutoff_days: $this->posted_int( 'cutoff_days' ),
				cutoff_time: $this->posted_text( 'cutoff_time' ),
				subscribe_discount_percent: $this->posted_int( 'subscribe_discount_percent' ),
				subscribe_applicability: $this->posted_text( 'subscribe_applicability' ),
				max_fulfilment_window_days: $this->posted_int( 'max_fulfilment_window_days' ),
				fulfilment_date_label: $this->posted_text( 'fulfilment_date_label' ),
				fulfilment_date_description: $this->posted_text( 'fulfilment_date_description' ),
				subscribe_save_label: $this->posted_text( 'subscribe_save_label' ),
				subscribe_save_description: $this->posted_text( 'subscribe_save_description' ),
				minimum_order_amount: $this->posted_float( 'minimum_order_amount' ),
				minimum_cart_quantity: $this->posted_int( 'minimum_cart_quantity' ),
				ineligible_message: $this->posted_text( 'ineligible_message' ),
				logged_out_message: $this->posted_text( 'logged_out_message' ),
				fulfilment_window_message: $this->posted_text( 'fulfilment_window_message' )
			);
		} catch ( InvalidArgumentException $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		$this->settings_service->save( $settings );

		wp_send_json_success();
	}
}
