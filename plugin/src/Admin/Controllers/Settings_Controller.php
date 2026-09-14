<?php
/**
 * Settings controller.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Controllers;

use FuelChef\Subscriptions\Admin\Concerns\Verifies_Ajax_Request;
use FuelChef\Subscriptions\Admin\Menu;
use FuelChef\Subscriptions\Entities\Blackout;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Services\Blackout_Service;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Utils\Input;
use FuelChef\Subscriptions\Utils\Renderer;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Settings screen and handles its ajax actions.
 *
 * Not unit-tested: view/glue code that reads a request, calls a service, and renders a
 * template or sends a JSON response, with no branchy logic of its own to break - see
 * docs/guidelines/03-testing.md.
 */
final class Settings_Controller {


	use Verifies_Ajax_Request;

	/**
	 * Creates the controller.
	 */
	public function __construct(
		private Settings_Store $settings_store,
		private Blackout_Service $blackout_service,
		private Blackout_Repository $blackout_repository,
		private Renderer $renderer
	) {
	}

	/**
	 * Registers this controller's ajax actions.
	 *
	 * `fcs_save_blackout`/`fcs_delete_blackout` are registered here only, even though
	 * `Schedules_Controller`'s screen uses them too for a schedule-scoped blackout - both
	 * delegate to `Blackout_Service`, which already accepts a nullable schedule ID, so
	 * one shared handler covers both cases and avoids two callbacks racing on the same
	 * action.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fcs_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_fcs_save_blackout', [ $this, 'ajax_save_blackout' ] );
		add_action( 'wp_ajax_fcs_delete_blackout', [ $this, 'ajax_delete_blackout' ] );
	}

	/**
	 * Renders the Settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'fuelchef-subscriptions' ) );
		}

		wp_localize_script(
			'fcs-admin-settings',
			'fcsSettings',
			[ 'blackouts' => $this->blackouts_for_js( $this->blackout_repository->find_by_schedule( null ) ) ]
		);

		$html = $this->renderer->render(
			'admin/settings',
			[
				'settings'        => $this->settings_store->get(),
				'applicabilities' => Subscribe_Applicability::all(),
			]
		);

		// The template escapes every dynamic value itself; this is its own fully-built page markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Shapes a list of blackouts for the calendar script.
	 *
	 * @param list<Blackout> $blackouts Blackouts to shape.
	 *
	 * @return list<array{id: int|null, date: string, label: string, reason: string|null}> The shaped blackouts.
	 */
	private function blackouts_for_js( array $blackouts ): array {
		return array_map(
			static fn ( Blackout $blackout ): array => [
				'id'     => $blackout->id(),
				'date'   => $blackout->date(),
				'label'  => gmdate( 'M d, Y', (int) strtotime( $blackout->date() ) ),
				'reason' => $blackout->reason(),
			],
			$blackouts
		);
	}

	/**
	 * Saves the cutoff, discount and applicability settings.
	 */
	public function ajax_save_settings(): void {
		$this->verify_ajax_request();

		try {
			$settings = new Settings(
				absint( Input::string( $_POST['cutoff_days'] ?? null ) ),
				sanitize_text_field( wp_unslash( Input::string( $_POST['cutoff_time'] ?? null ) ) ),
				absint( Input::string( $_POST['subscribe_discount_percent'] ?? null ) ),
				sanitize_text_field( wp_unslash( Input::string( $_POST['subscribe_applicability'] ?? null ) ) )
			);
		} catch ( InvalidArgumentException $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		$this->settings_store->save( $settings );

		wp_send_json_success();
	}

	/**
	 * Creates or updates a store-wide blackout date.
	 *
	 * Shared with `Schedules_Controller`, which registers the same action for a
	 * schedule-scoped blackout - both delegate to `Blackout_Service`, which already
	 * accepts a nullable schedule ID, so one handler covers both cases.
	 */
	public function ajax_save_blackout(): void {
		$this->verify_ajax_request();

		$blackout_id     = absint( Input::string( $_POST['blackout_id'] ?? null ) );
		$raw_schedule_id = absint( Input::string( $_POST['schedule_id'] ?? null ) );
		$schedule_id     = $raw_schedule_id > 0 ? $raw_schedule_id : null;
		$date            = sanitize_text_field( wp_unslash( Input::string( $_POST['date'] ?? null ) ) );
		$reason          = isset( $_POST['reason'] )
			? sanitize_textarea_field( wp_unslash( Input::string( $_POST['reason'] ) ) )
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
	 * Shared with `Schedules_Controller` - see `ajax_save_blackout()`.
	 */
	public function ajax_delete_blackout(): void {
		$this->verify_ajax_request();

		$this->blackout_service->remove( absint( Input::string( $_POST['blackout_id'] ?? null ) ) );

		wp_send_json_success();
	}
}
