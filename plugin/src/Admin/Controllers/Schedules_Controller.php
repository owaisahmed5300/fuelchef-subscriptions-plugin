<?php
/**
 * Schedules controller.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Controllers;

use FuelChef\Subscriptions\Admin\Concerns\Verifies_Ajax_Request;
use FuelChef\Subscriptions\Admin\Menu;
use FuelChef\Subscriptions\Entities\Blackout;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Entities\Schedule_Destination;
use FuelChef\Subscriptions\Entities\Schedule_Weekday;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Services\Schedule_Service;
use FuelChef\Subscriptions\Templating\Renderer;
use FuelChef\Subscriptions\Utils\Input;
use FuelChef\Subscriptions\Values\Destination_Option;
use FuelChef\Subscriptions\WooCommerce\Destination_Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Schedules screen and handles its ajax actions.
 *
 * Not unit-tested: view/glue code that reads a request, calls a service, and renders a
 * template or sends a JSON response, with no branchy logic of its own to break - see
 * docs/guidelines/03-testing.md.
 */
final class Schedules_Controller {


	use Verifies_Ajax_Request;

	/**
	 * Creates the controller.
	 */
	public function __construct(
		private Schedule_Service $schedule_service,
		private Schedule_Repository $schedules,
		private Schedule_Weekday_Repository $weekdays,
		private Blackout_Repository $blackouts,
		private Schedule_Destination_Repository $destinations,
		private Destination_Catalog $destination_catalog,
		private Renderer $renderer
	) {
	}

	/**
	 * Registers this controller's ajax actions.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fcs_save_schedule', [ $this, 'ajax_save_schedule' ] );
		add_action( 'wp_ajax_fcs_delete_schedule', [ $this, 'ajax_delete_schedule' ] );
		add_action( 'wp_ajax_fcs_save_schedule_weekday', [ $this, 'ajax_save_schedule_weekday' ] );
		add_action( 'wp_ajax_fcs_save_schedule_destinations', [ $this, 'ajax_save_schedule_destinations' ] );
	}

	/**
	 * Renders the Schedules screen for the selected schedule, or the first one when none
	 * is selected.
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'fuelchef-subscriptions' ) );
		}

		$all       = $this->schedules->all();
		$requested = absint( Input::string( $_GET['schedule_id'] ?? null ) );
		$selected  = $this->find_selected( $all, $requested );

		if ( null !== $selected ) {
			$schedule_id = (int) $selected->id();

			wp_localize_script(
				'fcs-admin-schedules',
				'fcsSchedulesData',
				[
					'selectedId'   => $schedule_id,
					'baseUrl'      => admin_url( 'admin.php?page=' . Menu::SCHEDULES_SLUG ),
					'weekdays'     => $this->weekdays_for_js( $this->weekdays->find_by_schedule( $schedule_id ) ),
					'blackouts'    => $this->blackouts_for_js( $this->blackouts->find_by_schedule( $schedule_id ) ),
					'destinations' => $this->destinations_for_js( $this->destinations->find_by_schedule( $schedule_id ) ),
					'catalog'      => $this->catalog_for_js(),
				]
			);
		}

		$html = $this->renderer->render(
			'admin/schedules',
			[
				'schedules' => $all,
				'selected'  => $selected,
			]
		);

		// The template escapes every dynamic value itself; this is its own fully-built page markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Shapes a list of weekdays for the weekly-hours script.
	 *
	 * @param list<Schedule_Weekday> $weekdays Weekdays to shape.
	 *
	 * @return list<array{day_of_week: int, enabled: bool, start_time: string}> The shaped weekdays.
	 */
	private function weekdays_for_js( array $weekdays ): array {
		return array_map(
			static fn ( Schedule_Weekday $weekday ): array => [
				'day_of_week' => $weekday->day_of_week(),
				'enabled'     => $weekday->enabled(),
				'start_time'  => $weekday->start_time(),
			],
			$weekdays
		);
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
	 * Shapes a list of assigned destinations for the destinations script.
	 *
	 * Each is resolved against the catalog so the screen can show its real WooCommerce
	 * label, or flag one WooCommerce no longer offers (a deleted zone, a removed pickup
	 * location) rather than silently dropping it.
	 *
	 * @param list<Schedule_Destination> $destinations Assigned destinations to shape.
	 *
	 * @return list<array{type: string, key: string, label: string, available: bool}> The shaped destinations.
	 */
	private function destinations_for_js( array $destinations ): array {
		return array_map(
			function ( Schedule_Destination $destination ): array {
				$option = $this->destination_catalog->find(
					$destination->destination_type(),
					$destination->destination_key()
				);

				return [
					'type'      => $destination->destination_type(),
					'key'       => $destination->destination_key(),
					'label'     => $option?->label() ?? $destination->destination_key(),
					'available' => null !== $option,
				];
			},
			$destinations
		);
	}

	/**
	 * Shapes the destination catalog for the "add a destination" script.
	 *
	 * @return list<array{
	 *     type: string,
	 *     key: string,
	 *     label: string,
	 *     description: string|null,
	 *     enabled: bool
	 * }> The shaped catalog.
	 */
	private function catalog_for_js(): array {
		return array_map(
			static fn ( Destination_Option $option ): array => [
				'type'        => $option->type(),
				'key'         => $option->key(),
				'label'       => $option->label(),
				'description' => $option->description(),
				'enabled'     => $option->enabled(),
			],
			$this->destination_catalog->all()
		);
	}

	/**
	 * Creates a new schedule, or renames an existing one.
	 */
	public function ajax_save_schedule(): void {
		$this->verify_ajax_request();

		$schedule_id = absint( Input::string( $_POST['schedule_id'] ?? null ) );
		$name        = sanitize_text_field( wp_unslash( Input::string( $_POST['name'] ?? null ) ) );

		try {
			$schedule = $schedule_id > 0
				? $this->schedule_service->rename( $schedule_id, $name )
				: $this->schedule_service->create( $name );
		} catch ( Validation_Exception $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		wp_send_json_success(
			[
				'id'   => $schedule->id(),
				'name' => $schedule->name(),
			]
		);
	}

	/**
	 * Deletes a schedule and everything assigned to it.
	 */
	public function ajax_delete_schedule(): void {
		$this->verify_ajax_request();

		$this->schedule_service->delete( absint( Input::string( $_POST['schedule_id'] ?? null ) ) );

		wp_send_json_success();
	}

	/**
	 * Updates one weekday's availability and start time.
	 */
	public function ajax_save_schedule_weekday(): void {
		$this->verify_ajax_request();

		try {
			$weekday = $this->schedule_service->update_weekday(
				absint( Input::string( $_POST['schedule_id'] ?? null ) ),
				absint( Input::string( $_POST['day_of_week'] ?? null ) ),
				isset( $_POST['enabled'] ),
				sanitize_text_field( wp_unslash( Input::string( $_POST['start_time'] ?? null ) ) )
			);
		} catch ( Validation_Exception $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		wp_send_json_success(
			[
				'day_of_week' => $weekday->day_of_week(),
				'enabled'     => $weekday->enabled(),
				'start_time'  => $weekday->start_time(),
			]
		);
	}

	/**
	 * Replaces every destination assigned to a schedule.
	 *
	 * No validation service: the one rule here (the destination must currently exist in
	 * WooCommerce) is answered entirely by `Destination_Catalog::find()`, so there is no
	 * business rule left for a service to hold - see docs/technical/data-layer.md. An
	 * entry the catalog no longer recognises is silently dropped rather than failing the
	 * whole save.
	 */
	public function ajax_save_schedule_destinations(): void {
		$this->verify_ajax_request();

		$schedule_id = absint( Input::string( $_POST['schedule_id'] ?? null ) );

		/** @var array<int, array{type?: string, key?: string}> $raw */
		$raw = isset( $_POST['destinations'] ) && is_array( $_POST['destinations'] )
			? wp_unslash( $_POST['destinations'] )
			: [];

		$destinations = [];

		foreach ( $raw as $entry ) {
			if ( ! isset( $entry['type'], $entry['key'] ) ) {
				continue;
			}

			$type = sanitize_text_field( $entry['type'] );
			$key  = sanitize_text_field( $entry['key'] );

			if ( null === $this->destination_catalog->find( $type, $key ) ) {
				continue;
			}

			$destinations[] = new Schedule_Destination( $schedule_id, $type, $key );
		}

		$this->destinations->replace_for_schedule( $schedule_id, $destinations );

		wp_send_json_success();
	}

	/**
	 * The requested schedule, or the first one when the request names none or an
	 * unknown one.
	 *
	 * @param list<Schedule> $all Every schedule.
	 */
	private function find_selected( array $all, int $requested_id ): ?Schedule {
		foreach ( $all as $schedule ) {
			if ( $schedule->id() === $requested_id ) {
				return $schedule;
			}
		}

		return $all[0] ?? null;
	}
}
