<?php
/**
 * Schedules admin screen.
 *
 * Rendered by Schedules_Controller::render(). $data carries `schedules`
 * (list<Entities\Schedule>) and `selected` (Entities\Schedule|null). The weekday table,
 * blackout calendar and destination list all read their own data from the
 * `fcsSchedulesData` script localization instead - see the controller.
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Admin\Menu;

defined( 'ABSPATH' ) || exit;

$base_url = admin_url( 'admin.php?page=' . Menu::SCHEDULES_SLUG );
$selected = $data['selected'];
?>
<div class="fcs-wrap">
	<header class="fcs-page-header">
		<h1 class="fcs-page-title">
			<?php esc_html_e( 'FuelChef Subscriptions', 'fuelchef-subscriptions' ); ?>
			<span class="fcs-badge-plugin"><?php esc_html_e( 'Schedules', 'fuelchef-subscriptions' ); ?></span>
		</h1>
		<p class="fcs-page-subtitle">
			<?php esc_html_e( 'Configure operating fulfillment days, start hours, localized closures, and destination zones.', 'fuelchef-subscriptions' ); ?>
		</p>
	</header>

	<div class="fcs-schedules-layout">
		<aside>
			<div class="fcs-card">
				<div class="fcs-card__header">
					<h2 class="fcs-card__title"><?php esc_html_e( 'Schedules', 'fuelchef-subscriptions' ); ?></h2>
				</div>
				<div class="fcs-card__body" style="padding: 10px;">
					<ul class="fcs-schedule-nav">
						<?php foreach ( $data['schedules'] as $schedule ) : ?>
							<li>
								<a
									class="fcs-schedule-nav__link<?php echo $selected && $selected->id() === $schedule->id() ? ' fcs-schedule-nav__link--active' : ''; ?>"
									href="<?php echo esc_url( $base_url . '&schedule_id=' . $schedule->id() ); ?>"
								>
									<div class="fcs-schedule-nav__title"><?php echo esc_html( $schedule->name() ); ?></div>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<button type="button" class="fcs-btn fcs-btn--add fcs-sidebar-action" id="addScheduleBtn">
						<?php esc_html_e( 'Add Schedule', 'fuelchef-subscriptions' ); ?>
					</button>
				</div>
			</div>
		</aside>

		<main>
			<?php if ( null === $selected ) : ?>
				<div class="fcs-card">
					<div class="fcs-card__body">
						<p><?php esc_html_e( 'No schedules yet. Add one to configure its fulfillment days and destinations.', 'fuelchef-subscriptions' ); ?></p>
					</div>
				</div>
			<?php else : ?>
				<div class="fcs-title-wrap">
					<input
						type="text"
						class="fcs-input fcs-title-input"
						id="scheduleTitle"
						value="<?php echo esc_attr( $selected->name() ); ?>"
						placeholder="<?php esc_attr_e( 'Schedule Name', 'fuelchef-subscriptions' ); ?>"
						aria-label="<?php esc_attr_e( 'Schedule Name', 'fuelchef-subscriptions' ); ?>"
					>
				</div>

				<nav class="fcs-nav-tabs" aria-label="<?php esc_attr_e( 'Schedule Tabs', 'fuelchef-subscriptions' ); ?>">
					<button type="button" class="fcs-nav-tab fcs-nav-tab--active" data-tab="tab-availability">
						<?php esc_html_e( 'Availability', 'fuelchef-subscriptions' ); ?>
					</button>
					<button type="button" class="fcs-nav-tab" data-tab="tab-destinations">
						<?php esc_html_e( 'Destinations', 'fuelchef-subscriptions' ); ?>
					</button>
				</nav>

				<section class="fcs-tab-panel fcs-tab-panel--active" id="tab-availability">
					<div class="fcs-card">
						<div class="fcs-card__header">
							<h2 class="fcs-card__title"><?php esc_html_e( 'Weekly Fulfillment Days', 'fuelchef-subscriptions' ); ?></h2>
						</div>
						<div class="fcs-card__body">
							<p style="margin-top:0; color:var(--fcs-color-text-muted);">
								<?php esc_html_e( 'Enable the days of the week when recurring orders can be fulfilled. For active days, set the earliest daily start time.', 'fuelchef-subscriptions' ); ?>
							</p>
							<table class="fcs-weekday-table">
								<tbody id="weekdayRows"></tbody>
							</table>
						</div>
					</div>

					<div class="fcs-card">
						<div class="fcs-card__header">
							<h2 class="fcs-card__title"><?php esc_html_e( 'Local Unavailable Dates', 'fuelchef-subscriptions' ); ?></h2>
						</div>
						<div class="fcs-card__body">
							<p style="margin-top:0; color:var(--fcs-color-text-muted);">
								<?php esc_html_e( 'Block specific dates for this schedule only (e.g. municipal events, local facility maintenance).', 'fuelchef-subscriptions' ); ?>
							</p>

							<div class="fcs-calendar-toolbar">
								<button type="button" class="fcs-btn fcs-btn--icon" id="calPrevMonth" aria-label="<?php esc_attr_e( 'Previous Month', 'fuelchef-subscriptions' ); ?>">&lsaquo;</button>
								<strong class="fcs-calendar-toolbar__title" id="localCalendarMonth"></strong>
								<button type="button" class="fcs-btn fcs-btn--icon" id="calNextMonth" aria-label="<?php esc_attr_e( 'Next Month', 'fuelchef-subscriptions' ); ?>">&rsaquo;</button>
							</div>

							<div class="fcs-calendar" id="localCalendar"></div>

							<div class="fcs-calendar-legend">
								<span class="fcs-calendar-legend__dot"></span>
								<?php esc_html_e( 'Local closure', 'fuelchef-subscriptions' ); ?>
								<span><?php esc_html_e( '(Global store closures will automatically apply in addition to these.)', 'fuelchef-subscriptions' ); ?></span>
							</div>

							<div class="fcs-summary-list" id="localUnavailableList"></div>
						</div>
					</div>
				</section>

				<section class="fcs-tab-panel" id="tab-destinations">
					<div class="fcs-card">
						<div class="fcs-card__header">
							<h2 class="fcs-card__title"><?php esc_html_e( 'Assigned Fulfillment Zones', 'fuelchef-subscriptions' ); ?></h2>
						</div>
						<div class="fcs-card__body">
							<p style="margin-top:0; color:var(--fcs-color-text-muted);">
								<?php esc_html_e( 'Assign the shipping zones and pickup locations this schedule fulfils.', 'fuelchef-subscriptions' ); ?>
							</p>

							<div class="fcs-dest-list" id="destinationList"></div>

							<div class="fcs-inline-field" style="margin-top: 12px;">
								<select class="fcs-select" id="destinationCatalog"></select>
								<button type="button" class="fcs-btn" id="addDestinationBtn"><?php esc_html_e( 'Add', 'fuelchef-subscriptions' ); ?></button>
							</div>
						</div>
					</div>
				</section>

				<div class="fcs-submit-bar">
					<div style="display:flex; gap:8px;">
						<button type="button" class="fcs-btn fcs-btn--primary" id="saveScheduleBtn">
							<?php esc_html_e( 'Save Schedule', 'fuelchef-subscriptions' ); ?>
						</button>
						<button type="button" class="fcs-btn fcs-btn--danger" id="deleteScheduleBtn">
							<?php esc_html_e( 'Delete Schedule', 'fuelchef-subscriptions' ); ?>
						</button>
					</div>
					<span class="fcs-save-status fcs-save-status--saved" id="fcsSaveStatus">
						&#10003; <?php esc_html_e( 'All changes saved', 'fuelchef-subscriptions' ); ?>
					</span>
				</div>
			<?php endif; ?>
		</main>
	</div>
</div>

<?php if ( null !== $selected ) : ?>
	<div class="fcs-overlay" id="deleteModalOverlay">
		<div class="fcs-modal">
			<h3 class="fcs-modal__title"><?php esc_html_e( 'Delete this schedule?', 'fuelchef-subscriptions' ); ?></h3>
			<p class="fcs-modal__body">
				<?php esc_html_e( 'Its weekdays, local blackout dates and destination assignments are removed with it. This cannot be undone.', 'fuelchef-subscriptions' ); ?>
			</p>
			<div class="fcs-modal__actions">
				<button type="button" class="fcs-btn" id="cancelDeleteBtn"><?php esc_html_e( 'Cancel', 'fuelchef-subscriptions' ); ?></button>
				<button type="button" class="fcs-btn fcs-btn--danger" id="confirmDeleteBtn"><?php esc_html_e( 'Delete Permanently', 'fuelchef-subscriptions' ); ?></button>
			</div>
		</div>
	</div>

	<div class="fcs-popover" id="datePopover">
		<div class="fcs-popover__title" data-pop-title></div>
		<div class="fcs-popover__subtitle"><?php esc_html_e( 'Local Closure', 'fuelchef-subscriptions' ); ?></div>
		<label class="fcs-popover__label"><?php esc_html_e( 'Closure reason (optional)', 'fuelchef-subscriptions' ); ?></label>
		<textarea class="fcs-textarea" data-pop-reason maxlength="255" placeholder="<?php esc_attr_e( 'e.g. Local Road Closure or Renovation', 'fuelchef-subscriptions' ); ?>"></textarea>
		<div class="fcs-popover__count"><span data-pop-count>0</span> / 255</div>
		<div class="fcs-popover__actions">
			<button type="button" class="fcs-btn fcs-btn--danger" data-pop-remove><?php esc_html_e( 'Remove date', 'fuelchef-subscriptions' ); ?></button>
			<button type="button" class="fcs-btn fcs-btn--primary" data-pop-save><?php esc_html_e( 'Save note', 'fuelchef-subscriptions' ); ?></button>
		</div>
	</div>
<?php endif; ?>

<div class="fcs-toast" id="fcsToast"></div>
