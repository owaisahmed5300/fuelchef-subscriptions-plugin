<?php
/**
 * Schedules admin screen.
 *
 * Rendered by Schedules_Controller::render(). The weekday table, blackout calendar and
 * destination list all read their own data from the `fcsSchedulesData` script
 * localization instead - see the controller.
 *
 * Template Variables
 *
 * @var list<Schedule> $schedules
 * @var Schedule|null $selected
 * @var array<int, int> $destination_counts Destination counts, keyed by schedule id.
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Admin\Menu;
use FuelChef\Subscriptions\Entities\Schedule;

defined( 'ABSPATH' ) || exit;

$base_url           = admin_url( 'admin.php?page=' . Menu::SCHEDULES_SLUG );
$schedules          = $data['schedules'];
$selected           = $data['selected'];
$destination_counts = $data['destination_counts'];
?>
<div class="wrap">
	<div class="fcs-admin fcs-wrap fcs-editor-shell">
		<header class="fcs-page-header fcs-editor-header">
			<h1 class="fcs-page-title">
				<?php esc_html_e( 'FuelChef Subscriptions', 'fuelchef-subscriptions' ); ?>
				&rsaquo;
				<?php esc_html_e( 'Schedules', 'fuelchef-subscriptions' ); ?>
			</h1>
			<p class="fcs-page-subtitle">
				<?php esc_html_e( 'Configure operating fulfilment days, fulfilment hours, localized closures, and destination zones.', 'fuelchef-subscriptions' ); ?>
			</p>
		</header>

		<div class="fcs-schedules-layout">
			<aside>
				<div class="fcs-card">
					<div class="fcs-card__header">
						<h2 class="fcs-card__title">
							<?php esc_html_e( 'Schedules', 'fuelchef-subscriptions' ); ?>
						</h2>
					</div>
					<div class="fcs-card__body fcs-card__body--tight">
						<ul class="fcs-schedule-nav">
							<?php
							foreach ( $schedules as $schedule ) :
								$destination_count = $destination_counts[ (int) $schedule->id() ] ?? 0;
								?>
								<li>
									<a
										class="fcs-schedule-nav__link
								<?php
										echo $selected && $selected->id() === $schedule->id(
										) ? ' fcs-schedule-nav__link--active' : '';
								?>
								"
										href="
								<?php echo esc_url( $base_url . '&schedule_id=' . $schedule->id() ); ?>
								"
									>
										<div>
											<div class="fcs-schedule-nav__title">
												<?php echo esc_html( $schedule->name() ); ?>
											</div>
											<div class="fcs-schedule-nav__meta">
												<?php
												echo esc_html(
													sprintf(
													/* translators: %d: number of destinations assigned to the schedule. */
														_n(
															'%d destination',
															'%d destinations',
															$destination_count,
															'fuelchef-subscriptions'
														),
														$destination_count
													)
												);
												?>
											</div>
										</div>
										<svg class="fcs-schedule-nav__chev" width="16" height="16" viewBox="0 0 16 16"
											fill="none" aria-hidden="true">
											<path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.6"
													stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</a>
								</li>
								<?php
							endforeach;
							?>
						</ul>
						<button type="button" class="fcs-btn fcs-btn--add fcs-sidebar-action" id="addScheduleBtn">
							<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
								<path d="M7 2.5v9M2.5 7h9" stroke="currentColor" stroke-width="1.75"
										stroke-linecap="round"/>
							</svg>
							<?php esc_html_e( 'Add Schedule', 'fuelchef-subscriptions' ); ?>
						</button>
					</div>
				</div>
			</aside>

			<main>
				<?php
				if ( null === $selected ) :
					?>
					<div class="fcs-card">
						<div class="fcs-card__body">
							<p>
								<?php esc_html_e( 'No schedules yet. Add one to configure its fulfilment days and destinations.', 'fuelchef-subscriptions' ); ?>
							</p>
						</div>
					</div>
					<?php
				else :
					?>
					<?php $schedule_name_placeholder = __( 'Schedule Name', 'fuelchef-subscriptions' ); ?>
					<div class="fcs-title-wrap">
						<input
							type="text"
							class="fcs-input fcs-title-input"
							id="scheduleTitle"
							value="<?php echo esc_attr( $selected->name() ); ?>"
							placeholder="<?php echo esc_attr( $schedule_name_placeholder ); ?>"
							aria-label="<?php echo esc_attr( $schedule_name_placeholder ); ?>"
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
								<h2 class="fcs-card__title">
									<?php esc_html_e( 'Weekly Fulfilment Days', 'fuelchef-subscriptions' ); ?>
								</h2>
							</div>
							<div class="fcs-card__body">
								<p class="fcs-card__intro">
									<?php esc_html_e( 'Enable the days of the week when recurring orders can be fulfilled, and set the fulfilment hours for each active day.', 'fuelchef-subscriptions' ); ?>
								</p>
								<table class="fcs-weekday-table">
									<tbody id="weekdayRows"></tbody>
								</table>
							</div>
						</div>

						<div class="fcs-card">
							<div class="fcs-card__header">
								<h2 class="fcs-card__title">
									<?php esc_html_e( 'Local Closures', 'fuelchef-subscriptions' ); ?>
								</h2>
							</div>
							<div class="fcs-card__body">
								<p class="fcs-card__intro">
									<?php esc_html_e( 'Block specific dates for this schedule only (e.g. municipal events, local facility maintenance).', 'fuelchef-subscriptions' ); ?>
								</p>

								<div class="fcs-calendar-toolbar">
									<button
										type="button"
										class="fcs-btn fcs-btn--icon"
										id="calPrevMonth"
										aria-label="<?php esc_attr_e( 'Previous month', 'fuelchef-subscriptions' ); ?>"
										title="<?php esc_attr_e( 'Previous month', 'fuelchef-subscriptions' ); ?>"
									>
										<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
											<path
												d="M11 4.5 6.5 9l4.5 4.5"
												stroke="currentColor"
												stroke-width="1.75"
												stroke-linecap="round"
												stroke-linejoin="round"
											/>
										</svg>
									</button>
									<strong class="fcs-calendar-toolbar__title" id="localCalendarMonth"></strong>
									<button
										type="button"
										class="fcs-btn fcs-btn--icon"
										id="calNextMonth"
										aria-label="<?php esc_attr_e( 'Next month', 'fuelchef-subscriptions' ); ?>"
										title="<?php esc_attr_e( 'Next month', 'fuelchef-subscriptions' ); ?>"
									>
										<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
											<path
												d="M7 4.5 11.5 9 7 13.5"
												stroke="currentColor"
												stroke-width="1.75"
												stroke-linecap="round"
												stroke-linejoin="round"
											/>
										</svg>
									</button>
								</div>

								<div class="fcs-calendar" id="localCalendar"></div>

								<div class="fcs-calendar-legend">
									<span class="fcs-calendar-legend__dot"></span>
									<?php esc_html_e( 'Local closure', 'fuelchef-subscriptions' ); ?>
									<span>
							<?php esc_html_e( '(Global store closures will automatically apply in addition to these.)', 'fuelchef-subscriptions' ); ?>
								</span>
								</div>

								<div class="fcs-summary-list" id="localUnavailableList"></div>
							</div>
						</div>
					</section>

					<section class="fcs-tab-panel" id="tab-destinations">
						<div class="fcs-card">
							<div class="fcs-card__header">
								<h2 class="fcs-card__title">
									<?php esc_html_e( 'Assigned Destinations', 'fuelchef-subscriptions' ); ?>
								</h2>
							</div>
							<div class="fcs-card__body">
								<p class="fcs-card__intro">
									<?php esc_html_e( 'Assign the shipping zones and pickup locations this schedule fulfils.', 'fuelchef-subscriptions' ); ?>
								</p>

								<div class="fcs-dest-list" id="destinationList"></div>

								<div class="fcs-dest-add">
									<select class="fcs-select" id="destinationCatalog"></select>
									<button type="button" class="fcs-btn fcs-btn--add" id="addDestinationBtn">
										<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
											<path d="M7 2.5v9M2.5 7h9" stroke="currentColor" stroke-width="1.75"
													stroke-linecap="round"/>
										</svg>
										<?php esc_html_e( 'Add', 'fuelchef-subscriptions' ); ?>
									</button>
								</div>
							</div>
						</div>
					</section>

					<div class="fcs-submit-bar">
						<div class="fcs-submit-bar__actions">
							<button type="button" class="fcs-btn fcs-btn--primary" id="saveScheduleBtn">
								<?php esc_html_e( 'Save Schedule', 'fuelchef-subscriptions' ); ?>
							</button>
							<button type="button" class="fcs-btn fcs-btn--danger" id="deleteScheduleBtn">
								<?php esc_html_e( 'Delete Schedule', 'fuelchef-subscriptions' ); ?>
							</button>
						</div>
						<span class="fcs-save-status fcs-save-status--saved" id="fcsSaveStatus">
					&#10003;
					<?php esc_html_e( 'All changes saved', 'fuelchef-subscriptions' ); ?>
				</span>
					</div>
					<?php
				endif;
				?>
			</main>
		</div>

		<?php
		if ( null !== $selected ) :
			?>
			<div class="fcs-overlay" id="addScheduleModalOverlay">
				<div class="fcs-modal" role="dialog" aria-modal="true" aria-labelledby="addScheduleModalTitle">
					<h3 class="fcs-modal__title" id="addScheduleModalTitle">
						<?php esc_html_e( 'New schedule', 'fuelchef-subscriptions' ); ?>
					</h3>
					<div class="fcs-field">
						<label for="newScheduleName">
							<?php esc_html_e( 'Schedule name', 'fuelchef-subscriptions' ); ?>
						</label>
						<input class="fcs-input" id="newScheduleName" type="text" maxlength="190">
					</div>
					<div class="fcs-modal__actions">
						<button type="button" class="fcs-btn" id="cancelAddScheduleBtn">
							<?php esc_html_e( 'Cancel', 'fuelchef-subscriptions' ); ?>
						</button>
						<button type="button" class="fcs-btn fcs-btn--primary" id="confirmAddScheduleBtn">
							<?php esc_html_e( 'Create Schedule', 'fuelchef-subscriptions' ); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="fcs-overlay" id="deleteModalOverlay">
				<div class="fcs-modal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
					<h3 class="fcs-modal__title" id="deleteModalTitle">
						<?php esc_html_e( 'Delete this schedule?', 'fuelchef-subscriptions' ); ?>
					</h3>
					<p class="fcs-modal__body">
						<?php esc_html_e( 'Its weekdays, local closure dates and destination assignments are removed with it. This cannot be undone.', 'fuelchef-subscriptions' ); ?>
					</p>
					<div class="fcs-modal__actions">
						<button type="button" class="fcs-btn" id="cancelDeleteBtn">
							<?php esc_html_e( 'Cancel', 'fuelchef-subscriptions' ); ?>
						</button>
						<button type="button" class="fcs-btn fcs-btn--danger" id="confirmDeleteBtn">
							<?php esc_html_e( 'Delete Permanently', 'fuelchef-subscriptions' ); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="fcs-popover" id="datePopover" popover="auto" role="dialog" aria-labelledby="datePopoverTitle">
				<div class="fcs-popover__arrow"></div>
				<div class="fcs-popover__header">
					<div>
						<div class="fcs-popover__title" id="datePopoverTitle" data-pop-title></div>
						<div class="fcs-popover__subtitle">
							<?php esc_html_e( 'Local Closure', 'fuelchef-subscriptions' ); ?>
						</div>
					</div>
					<button type="button" class="fcs-popover__close" data-pop-close aria-label="<?php esc_attr_e( 'Close', 'fuelchef-subscriptions' ); ?>">
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
							<path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
				<label class="fcs-popover__label">
					<?php esc_html_e( 'Closure reason (optional)', 'fuelchef-subscriptions' ); ?>
				</label>
				<textarea
					class="fcs-textarea"
					data-pop-reason
					maxlength="255"
					placeholder="<?php esc_attr_e( 'e.g. Local Road Closure or Renovation', 'fuelchef-subscriptions' ); ?>"
				></textarea>
				<div class="fcs-popover__count"><span data-pop-count>0</span> / 255</div>
				<div class="fcs-popover__actions">
					<button type="button" class="fcs-btn fcs-btn--danger" data-pop-remove>
						<?php esc_html_e( 'Remove date', 'fuelchef-subscriptions' ); ?>
					</button>
					<button type="button" class="fcs-btn fcs-btn--primary" data-pop-save>
						<?php esc_html_e( 'Save note', 'fuelchef-subscriptions' ); ?>
					</button>
				</div>
			</div>
			<?php
		endif;
		?>

		<div class="fcs-toast" id="fcsToast" role="status" aria-live="polite"></div>
	</div>

</div>
