<?php
/**
 * Closures controller.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Controllers;

use FuelChef\Subscriptions\Admin\Concerns\Presents_Blackouts;
use FuelChef\Subscriptions\Admin\Concerns\Verifies_Ajax_Request;
use FuelChef\Subscriptions\Admin\Concerns\Verifies_Page_Access;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Services\Scheduling\Blackout_Service;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Utils\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Closures screen and handles its ajax actions.
 *
 * `fcs_save_blackout`/`fcs_delete_blackout` are registered here only, even though
 * `Schedules_Controller`'s screen uses them too for a schedule-scoped blackout - both
 * delegate to `Blackout_Service`, which already accepts a nullable schedule ID, so
 * one shared handler covers both cases and avoids two callbacks racing on the same
 * action.
 */
final class Closures_Controller {


	use Presents_Blackouts;
	use Verifies_Ajax_Request;
	use Verifies_Page_Access;

	/**
	 * Creates the controller.
	 */
	public function __construct(
		private Blackout_Service $blackout_service,
		private Blackout_Repository $blackout_repository,
		private Renderer $renderer
	) {
	}

	/**
	 * Registers this controller's ajax actions.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fcs_save_blackout', [ $this, 'ajax_save_blackout' ] );
		add_action( 'wp_ajax_fcs_delete_blackout', [ $this, 'ajax_delete_blackout' ] );
	}

	/**
	 * Renders the Closures screen.
	 */
	public function render(): void {
		$this->verify_page_access();

		wp_localize_script(
			'fcs-admin-closures',
			'fcsClosures',
			[ 'blackouts' => $this->blackouts_for_js( $this->blackout_repository->find_by_schedule( null ) ) ]
		);

		$html = $this->renderer->render( 'admin/closures', [] );

		// The template escapes every dynamic value itself; this is its own fully-built page markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Creates or updates a store-wide blackout date.
	 *
	 * Shared with `Schedules_Controller` - see `register()`.
	 */
	public function ajax_save_blackout(): void {
		$this->verify_ajax_request();

		$blackout_id     = absint( Narrow::string( $_POST['blackout_id'] ?? null ) );
		$raw_schedule_id = absint( Narrow::string( $_POST['schedule_id'] ?? null ) );
		$schedule_id     = $raw_schedule_id > 0 ? $raw_schedule_id : null;
		$date            = sanitize_text_field( wp_unslash( Narrow::string( $_POST['date'] ?? null ) ) );
		$reason          = isset( $_POST['reason'] )
			? sanitize_textarea_field( wp_unslash( Narrow::string( $_POST['reason'] ) ) )
			: null;

		try {
			$blackout = $blackout_id > 0
				? $this->blackout_service->update_reason( $blackout_id, '' === $reason ? null : $reason )
				: $this->blackout_service->add( $schedule_id, $date, '' === $reason ? null : $reason );
		} catch ( Validation_Exception $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		wp_send_json_success(
			[
				'id'     => $blackout->id(),
				'date'   => $blackout->date(),
				'reason' => $blackout->reason(),
			]
		);
	}

	/**
	 * Removes a blackout date.
	 *
	 * Shared with `Schedules_Controller` - see `register()`.
	 */
	public function ajax_delete_blackout(): void {
		$this->verify_ajax_request();

		$this->blackout_service->remove( absint( Narrow::string( $_POST['blackout_id'] ?? null ) ) );

		wp_send_json_success();
	}
}
