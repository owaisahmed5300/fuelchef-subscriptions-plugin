<?php
/**
 * Admin assets.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin;

use FuelChef\Subscriptions\Utils\Locale;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Values\Day_Of_Week;
use FuelChef\Subscriptions\Values\Destination_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues this plugin's CSS/JS, only on its own two admin screens.
 */
final class Assets {


	/**
	 * Registers the enqueue hook.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueues the shared and screen-specific assets for the current admin screen, doing
	 * nothing on any screen that is not one of this plugin's own.
	 *
	 * Matched on `$_GET['page']` rather than the `$hook_suffix` WordPress passes in:
	 * the submenu's hook suffix is derived from its parent in a way that is easy to
	 * get wrong (verified by hand against the actual admin screen), while `page` is
	 * exactly the slug this plugin registered its menus under.
	 */
	public function enqueue(): void {
		$page = Narrow::string( $_GET['page'] ?? null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$screen = match ( $page ) {
			Menu::SETTINGS_SLUG => 'settings',
			Menu::SCHEDULES_SLUG => 'schedules',
			default => null,
		};

		if ( null === $screen ) {
			return;
		}

		wp_enqueue_style(
			'fcs-admin-common',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/admin/css/common.css',
			[],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_script(
			'fcs-admin-common',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/admin/js/common.js',
			[ 'jquery' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_enqueue_style(
			"fcs-admin-{$screen}",
			FUELCHEF_SUBSCRIPTIONS_URL . "assets/admin/css/{$screen}.css",
			[ 'fcs-admin-common' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_script(
			"fcs-admin-{$screen}",
			FUELCHEF_SUBSCRIPTIONS_URL . "assets/admin/js/{$screen}.js",
			[ 'fcs-admin-common' ],
			FUELCHEF_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_localize_script(
			"fcs-admin-{$screen}",
			'fcsAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fuelchef_subscriptions_admin' ),
				'i18n'    => $this->strings(),
			]
		);
	}

	/**
	 * Every string the admin scripts display, translated once here rather than hardcoded
	 * in JavaScript.
	 *
	 * @return array<string, string|list<string>|array<string, string>> The strings, keyed by name.
	 */
	private function strings(): array {
		return [
			'unsavedChanges'           => esc_html__( 'Unsaved changes', 'fuelchef-subscriptions' ),
			'allChangesSaved'          => esc_html__( 'All changes saved', 'fuelchef-subscriptions' ),
			'dateMarkedUnavailable'    => esc_html__( 'Date closed', 'fuelchef-subscriptions' ),
			'dateRemoved'              => esc_html__( 'Date removed', 'fuelchef-subscriptions' ),
			'noteSaved'                => esc_html__( 'Note saved', 'fuelchef-subscriptions' ),
			'couldNotAddDate'          => esc_html__( 'Could not add that date.', 'fuelchef-subscriptions' ),
			'couldNotSaveNote'         => esc_html__( 'Could not save that note.', 'fuelchef-subscriptions' ),
			'couldNotSaveSettings'     => esc_html__( 'Could not save settings.', 'fuelchef-subscriptions' ),
			'settingsSaved'            => esc_html__( 'Settings saved successfully', 'fuelchef-subscriptions' ),
			'couldNotSaveDay'          => esc_html__( 'Could not save that day.', 'fuelchef-subscriptions' ),
			'scheduleUpdated'          => esc_html__( 'Schedule updated', 'fuelchef-subscriptions' ),
			'couldNotSaveScheduleName' => esc_html__( 'Could not save the schedule name.', 'fuelchef-subscriptions' ),
			'scheduleNameSaved'        => esc_html__( 'Schedule name saved', 'fuelchef-subscriptions' ),
			'scheduleSaved'            => esc_html__( 'Schedule saved successfully', 'fuelchef-subscriptions' ),
			'couldNotCreateSchedule'   => esc_html__( 'Could not create that schedule.', 'fuelchef-subscriptions' ),
			'couldNotDeleteSchedule'   => esc_html__( 'Could not delete that schedule.', 'fuelchef-subscriptions' ),
			'destinationRemoved'       => esc_html__( 'Destination removed', 'fuelchef-subscriptions' ),
			'couldNotSaveDestination'  => esc_html__( 'Could not save that destination.', 'fuelchef-subscriptions' ),
			'destinationAdded'         => esc_html__( 'Destination added', 'fuelchef-subscriptions' ),
			'noGlobalClosures'         => esc_html__( 'No global closures scheduled. Orders can be fulfilled on all standard active days.', 'fuelchef-subscriptions' ),
			'noLocalClosures'          => esc_html__( 'No localized closure dates set for this schedule.', 'fuelchef-subscriptions' ),
			'dayActive'                => esc_html__( 'Active', 'fuelchef-subscriptions' ),
			'dayClosed'                => esc_html__( 'Closed', 'fuelchef-subscriptions' ),
			'scheduleStart'            => esc_html__( 'Start', 'fuelchef-subscriptions' ),
			'scheduleEnd'              => esc_html__( 'End', 'fuelchef-subscriptions' ),
			'endBeforeStart'           => esc_html__( 'End time must be after the start time.', 'fuelchef-subscriptions' ),
			'copyToDaysBelow'          => esc_html__( 'Copy these hours to every day below', 'fuelchef-subscriptions' ),
			'couldNotCopySchedule'     => esc_html__( 'Could not copy this schedule to the days below.', 'fuelchef-subscriptions' ),
			'scheduleCopied'           => esc_html__( 'Hours copied to the days below', 'fuelchef-subscriptions' ),
			'removeDestination'        => esc_html__( 'Remove destination', 'fuelchef-subscriptions' ),
			'removeDate'               => esc_html__( 'Remove date', 'fuelchef-subscriptions' ),
			'noDestinationsYet'        => esc_html__( 'No destinations assigned yet.', 'fuelchef-subscriptions' ),
			'destinationUnavailable'   => esc_html__( 'No longer available', 'fuelchef-subscriptions' ),
			'destinationTypeLabels'    => [
				Destination_Type::SHIPPING_ZONE   => esc_html__( 'Shipping Zones', 'fuelchef-subscriptions' ),
				Destination_Type::PICKUP_LOCATION => esc_html__( 'Pickup Locations', 'fuelchef-subscriptions' ),
			],
			'dayNames'                 => array_map(
				static fn ( int $day ): string => Day_Of_Week::label( $day ),
				Day_Of_Week::all()
			),
			'monthNames'               => array_values( Locale::current()->month ),
			'weekdayNamesShort'        => array_values( Locale::current()->weekday_abbrev ),
		];
	}
}
