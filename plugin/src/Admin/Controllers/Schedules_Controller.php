<?php
/**
 * Schedules controller.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Controllers;

use FuelChef\Subscriptions\Admin\Concerns\Presents_Blackouts;
use FuelChef\Subscriptions\Admin\Concerns\Reads_Request_Fields;
use FuelChef\Subscriptions\Admin\Concerns\Verifies_Ajax_Request;
use FuelChef\Subscriptions\Admin\Menu;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Entities\Schedule_Destination;
use FuelChef\Subscriptions\Entities\Schedule_Weekday;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Services\Scheduling\Destination_Catalog_Service;
use FuelChef\Subscriptions\Services\Scheduling\Schedule_Service;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Utils\Renderer;
use FuelChef\Subscriptions\Values\Day_Of_Week;
use FuelChef\Subscriptions\Values\Destination_Option;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Schedules screen and handles its ajax actions.
 */
final class Schedules_Controller {


	use Presents_Blackouts;
	use Reads_Request_Fields;
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
		private Destination_Catalog_Service $destination_catalog,
		private Renderer $renderer
	) {
	}

	/**
	 * Registers this controller's ajax actions.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fcs_save_schedule', [ $this, 'ajax_save_schedule' ] );
		add_action( 'wp_ajax_fcs_delete_schedule', [ $this, 'ajax_delete_schedule' ] );
		add_action( 'wp_ajax_fcs_save_schedule_weekdays', [ $this, 'ajax_save_schedule_weekdays' ] );
		add_action( 'wp_ajax_fcs_copy_schedule_weekday', [ $this, 'ajax_copy_schedule_weekday' ] );
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
		$requested = absint( Narrow::string( $_GET['schedule_id'] ?? null ) );
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
					'catalog'      => $this->catalog_for_js( $schedule_id ),
				]
			);
		}

		$html = $this->renderer->render(
			'admin/schedules',
			[
				'schedules'          => $all,
				'selected'           => $selected,
				'destination_counts' => $this->destination_counts( $all ),
			]
		);

		// The template escapes every dynamic value itself; this is its own fully-built page markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Shapes a list of weekdays for the weekly-hours script, ordered to match the site's
	 * configured week start so "days below" in the UI matches the copy-down feature.
	 *
	 * @param list<Schedule_Weekday> $weekdays Weekdays to shape.
	 *
	 * @return list<array{day_of_week: int, enabled: bool, start_time: string, end_time: string}> The shaped weekdays.
	 */
	private function weekdays_for_js( array $weekdays ): array {
		$by_day = [];

		foreach ( $weekdays as $weekday ) {
			$by_day[ $weekday->day_of_week() ] = $weekday;
		}

		$ordered = [];

		foreach ( Day_Of_Week::in_site_order() as $day_of_week ) {
			if ( ! isset( $by_day[ $day_of_week ] ) ) {
				continue;
			}

			$weekday   = $by_day[ $day_of_week ];
			$ordered[] = [
				'day_of_week' => $weekday->day_of_week(),
				'enabled'     => $weekday->enabled(),
				'start_time'  => $weekday->start_time(),
				'end_time'    => $weekday->end_time(),
			];
		}

		return $ordered;
	}

	/**
	 * Shapes a single weekday for a JSON response.
	 *
	 * @return array{day_of_week: int, enabled: bool, start_time: string, end_time: string}
	 */
	private function weekday_for_js( Schedule_Weekday $weekday ): array {
		return [
			'day_of_week' => $weekday->day_of_week(),
			'enabled'     => $weekday->enabled(),
			'start_time'  => $weekday->start_time(),
			'end_time'    => $weekday->end_time(),
		];
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
	 * Shapes the destination catalog for the "add a destination" script, noting which
	 * destinations are already assigned to a different schedule so the picker can grey
	 * them out instead of letting an admin hit the same conflict on save.
	 *
	 * @return list<array{
	 *     type: string,
	 *     key: string,
	 *     label: string,
	 *     description: string|null,
	 *     enabled: bool,
	 *     assignedTo: string|null
	 * }> The shaped catalog.
	 */
	private function catalog_for_js( int $schedule_id ): array {
		return array_map(
			function ( Destination_Option $option ) use ( $schedule_id ): array {
				$owner = $this->schedule_service->destination_owner( $option->type(), $option->key(), $schedule_id );

				return [
					'type'        => $option->type(),
					'key'         => $option->key(),
					'label'       => $option->label(),
					'description' => $option->description(),
					'enabled'     => $option->enabled(),
					'assignedTo'  => $owner?->name(),
				];
			},
			$this->destination_catalog->all()
		);
	}

	/**
	 * Creates a new schedule, or renames an existing one.
	 */
	public function ajax_save_schedule(): void {
		$this->verify_ajax_request();

		$schedule_id = $this->posted_int( 'schedule_id' );
		$name        = $this->posted_text( 'name' );

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

		$this->schedule_service->delete( $this->posted_int( 'schedule_id' ) );

		wp_send_json_success();
	}

	/**
	 * Updates every weekday's availability, start time and end time in one batch - the
	 * screen's own "Save Schedule" button is the only thing that triggers this, never an
	 * individual toggle or time field, so every pending edit is saved (or rejected)
	 * together instead of firing one request per change.
	 */
	public function ajax_save_schedule_weekdays(): void {
		$this->verify_ajax_request();

		$schedule_id = $this->posted_int( 'schedule_id' );

		/** @var array<int, array{day_of_week?: string, enabled?: string, start_time?: string, end_time?: string}> $raw */
		$raw = wp_unslash( Narrow::array( $_POST['weekdays'] ?? null ) );

		$rows = [];

		foreach ( $raw as $entry ) {
			if ( ! isset( $entry['day_of_week'], $entry['start_time'], $entry['end_time'] ) ) {
				continue;
			}

			$rows[] = [
				'day_of_week' => absint( $entry['day_of_week'] ),
				// A checkbox posts '1' when checked and is omitted entirely when not -
				// this plugin's own JS always sends the key either way, with '' for
				// unchecked, but isset() alone would be true for both.
				'enabled'     => '1' === ( $entry['enabled'] ?? '' ),
				'start_time'  => sanitize_text_field( $entry['start_time'] ),
				'end_time'    => sanitize_text_field( $entry['end_time'] ),
			];
		}

		try {
			$weekdays = $this->schedule_service->update_weekdays( $schedule_id, $rows );
		} catch ( Validation_Exception $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		wp_send_json_success( [ 'weekdays' => array_map( [ $this, 'weekday_for_js' ], $weekdays ) ] );
	}

	/**
	 * Copies a weekday's enabled state, start time and end time onto every day below it.
	 */
	public function ajax_copy_schedule_weekday(): void {
		$this->verify_ajax_request();

		try {
			$updated = $this->schedule_service->copy_weekday_to_days_below(
				$this->posted_int( 'schedule_id' ),
				$this->posted_int( 'day_of_week' )
			);
		} catch ( Validation_Exception $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		wp_send_json_success( [ 'weekdays' => array_map( [ $this, 'weekday_for_js' ], $updated ) ] );
	}

	/**
	 * Replaces every destination assigned to a schedule, rejecting the batch when any of
	 * them is already assigned to a different schedule.
	 */
	public function ajax_save_schedule_destinations(): void {
		$this->verify_ajax_request();

		$schedule_id = $this->posted_int( 'schedule_id' );

		/** @var array<int, array{type?: string, key?: string}> $raw */
		$raw = wp_unslash( Narrow::array( $_POST['destinations'] ?? null ) );

		$destinations = [];

		foreach ( $raw as $entry ) {
			if ( ! isset( $entry['type'], $entry['key'] ) ) {
				continue;
			}

			$destinations[] = [
				'type' => sanitize_text_field( $entry['type'] ),
				'key'  => sanitize_text_field( $entry['key'] ),
			];
		}

		try {
			$this->schedule_service->assign_destinations( $schedule_id, $destinations );
		} catch ( Validation_Exception $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

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

	/**
	 * How many destinations each schedule has assigned, for the sidebar list.
	 *
	 * @param list<Schedule> $all Every schedule.
	 *
	 * @return array<int, int> Destination counts, keyed by schedule id.
	 */
	private function destination_counts( array $all ): array {
		$counts = [];

		foreach ( $all as $schedule ) {
			$counts[ (int) $schedule->id() ] = count( $this->destinations->find_by_schedule( (int) $schedule->id() ) );
		}

		return $counts;
	}
}
