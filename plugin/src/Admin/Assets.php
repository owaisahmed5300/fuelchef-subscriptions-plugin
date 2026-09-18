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
	 * The vendored Popper.js build's version, used as its own cache-busting query arg
	 * since it does not change with plugin releases.
	 */
	private const POPPER_VERSION = '2.11.8';

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
			'fcs-popper',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/lib/popper/popper.min.js',
			[],
			self::POPPER_VERSION,
			true
		);

		wp_enqueue_script(
			'fcs-admin-common',
			FUELCHEF_SUBSCRIPTIONS_URL . 'assets/admin/js/common.js',
			[ 'jquery', 'fcs-popper' ],
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
	 * Every value is `__()`, not `esc_html__()`: each one is only ever inserted client-side
	 * through a toast's `.textContent` or the admin scripts' own `FCS.escapeHtml()` when
	 * building an HTML string - neither decodes an HTML entity, so a pre-escaped value
	 * would show a literal "&quot;" or "&#039;" instead of the character it represents. See
	 * `Frontend\Assets::strings()` for the same reasoning on the checkout side.
	 *
	 * @return array<string, string|list<string>|array<string, string>> The strings, keyed by name.
	 */
	private function strings(): array {
		return [
			'unsavedChanges'             => __( 'Unsaved changes', 'fuelchef-subscriptions' ),
			'allChangesSaved'            => __( 'All changes saved', 'fuelchef-subscriptions' ),
			'dateMarkedUnavailable'      => __( 'Date closed', 'fuelchef-subscriptions' ),
			'dateRemoved'                => __( 'Date removed', 'fuelchef-subscriptions' ),
			'noteSaved'                  => __( 'Note saved', 'fuelchef-subscriptions' ),
			'couldNotAddDate'            => __( 'Could not add that date.', 'fuelchef-subscriptions' ),
			'couldNotSaveNote'           => __( 'Could not save that note.', 'fuelchef-subscriptions' ),
			'couldNotSaveSettings'       => __( 'Could not save settings.', 'fuelchef-subscriptions' ),
			'settingsSaved'              => __( 'Settings saved successfully', 'fuelchef-subscriptions' ),
			'couldNotSaveDay'            => __( 'Could not save that day.', 'fuelchef-subscriptions' ),
			'couldNotSaveScheduleName'   => __( 'Could not save the schedule name.', 'fuelchef-subscriptions' ),
			'couldNotSaveDestinations'   => __( 'Could not save the assigned destinations.', 'fuelchef-subscriptions' ),
			'scheduleSaved'              => __( 'Schedule saved successfully', 'fuelchef-subscriptions' ),
			'couldNotCreateSchedule'     => __( 'Could not create that schedule.', 'fuelchef-subscriptions' ),
			'couldNotDeleteSchedule'     => __( 'Could not delete that schedule.', 'fuelchef-subscriptions' ),
			'noGlobalClosures'           => __( 'No global closures scheduled. Orders can be fulfilled on all standard active days.', 'fuelchef-subscriptions' ),
			'noLocalClosures'            => __( 'No localized closure dates set for this schedule.', 'fuelchef-subscriptions' ),
			'dayActive'                  => __( 'Active', 'fuelchef-subscriptions' ),
			'dayClosed'                  => __( 'Closed', 'fuelchef-subscriptions' ),
			'scheduleStart'              => __( 'Start', 'fuelchef-subscriptions' ),
			'scheduleEnd'                => __( 'End', 'fuelchef-subscriptions' ),
			'endBeforeStart'             => __( 'End time must be after the start time.', 'fuelchef-subscriptions' ),
			'copyToDaysBelow'            => __( 'Copy these hours to every day below', 'fuelchef-subscriptions' ),
			'couldNotCopySchedule'       => __( 'Could not copy this schedule to the days below.', 'fuelchef-subscriptions' ),
			'scheduleCopied'             => __( 'Hours copied to the days below', 'fuelchef-subscriptions' ),
			'removeDestination'          => __( 'Remove destination', 'fuelchef-subscriptions' ),
			'removeDate'                 => __( 'Remove date', 'fuelchef-subscriptions' ),
			'noDestinationsYet'          => __( 'No destinations assigned yet.', 'fuelchef-subscriptions' ),
			'destinationUnavailable'     => __( 'No longer available', 'fuelchef-subscriptions' ),
			/* translators: {schedule} is replaced with the owning schedule's name client-side. */
			'destinationAlreadyAssigned' => __( 'Already assigned to "{schedule}"', 'fuelchef-subscriptions' ),
			'destinationTypeLabels'      => [
				Destination_Type::SHIPPING_ZONE   => __( 'Shipping Zones', 'fuelchef-subscriptions' ),
				Destination_Type::PICKUP_LOCATION => __( 'Pickup Locations', 'fuelchef-subscriptions' ),
			],
			'dayNames'                   => array_map(
				static fn ( int $day ): string => Day_Of_Week::label( $day ),
				Day_Of_Week::all()
			),
			'monthNames'                 => array_values( Locale::current()->month ),
			'weekdayNamesShort'          => array_values( Locale::current()->weekday_abbrev ),
		];
	}
}
