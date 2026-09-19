<?php
/**
 * Closures admin screen.
 *
 * Rendered by Closures_Controller::render(). The blackout calendar reads its own data
 * from the `fcsClosures` script localization instead - see the controller.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap">
	<div class="fcs-admin fcs-wrap fcs-editor-shell">
		<header class="fcs-page-header fcs-editor-header">
			<h1 class="fcs-page-title">
				<?php esc_html_e( 'FuelChef Subscriptions', 'fuelchef-subscriptions' ); ?>
				&rsaquo;
				<?php esc_html_e( 'Closures', 'fuelchef-subscriptions' ); ?>
			</h1>
			<p class="fcs-page-subtitle">
				<?php esc_html_e( 'Block dates that close recurring orders for the entire store.', 'fuelchef-subscriptions' ); ?>
			</p>
		</header>

		<div class="fcs-card">
			<div class="fcs-card__header">
				<h2 class="fcs-card__title">
					<?php esc_html_e( 'Global Store Closures', 'fuelchef-subscriptions' ); ?>
				</h2>
			</div>
			<div class="fcs-card__body">
				<p class="fcs-card__intro">
					<?php
					esc_html_e(
						'Dates specified here close recurring orders for the entire store regardless of location. Use this for statutory holidays or full warehouse shutdowns.',
						'fuelchef-subscriptions'
					);
					?>
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
					<strong class="fcs-calendar-toolbar__title" id="globalCalendarMonth"></strong>
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

				<div class="fcs-calendar" id="globalCalendar"></div>

				<div class="fcs-calendar-legend">
					<span class="fcs-calendar-legend__dot"></span>
					<?php esc_html_e( 'Closed store-wide', 'fuelchef-subscriptions' ); ?>
					<span>
						<?php
						esc_html_e(
							'(Click any date to close. Click an existing closed date to edit note.)',
							'fuelchef-subscriptions'
						);
						?>
					</span>
				</div>

				<div class="fcs-summary-list" id="globalUnavailableList"></div>
			</div>
		</div>

		<div class="fcs-popover" id="datePopover" popover="auto" role="dialog" aria-labelledby="datePopoverTitle">
			<div class="fcs-popover__arrow" data-popper-arrow></div>
			<div class="fcs-popover__header">
				<div>
					<div class="fcs-popover__title" id="datePopoverTitle" data-pop-title></div>
					<div class="fcs-popover__subtitle">
						<?php esc_html_e( 'Global Store Closure', 'fuelchef-subscriptions' ); ?>
					</div>
				</div>
				<button
					type="button"
					class="fcs-popover__close"
					data-pop-close
					aria-label="<?php esc_attr_e( 'Close', 'fuelchef-subscriptions' ); ?>"
				>
					<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
						<path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
					</svg>
				</button>
			</div>
			<label class="fcs-popover__label" for="globalClosureReason">
				<?php esc_html_e( 'Closure note (optional)', 'fuelchef-subscriptions' ); ?>
			</label>
			<textarea
				class="fcs-textarea"
				id="globalClosureReason"
				data-pop-reason
				maxlength="255"
				placeholder="<?php esc_attr_e( 'e.g. National Holiday', 'fuelchef-subscriptions' ); ?>"
				aria-describedby="globalClosureReasonCount"
			></textarea>
			<div class="fcs-popover__count" id="globalClosureReasonCount"><span data-pop-count>0</span> / 255</div>
			<div class="fcs-popover__actions">
				<button type="button" class="fcs-btn fcs-btn--danger" data-pop-remove>
					<?php esc_html_e( 'Remove date', 'fuelchef-subscriptions' ); ?>
				</button>
				<button type="button" class="fcs-btn fcs-btn--primary" data-pop-save>
					<?php esc_html_e( 'Save note', 'fuelchef-subscriptions' ); ?>
				</button>
			</div>
		</div>

		<div class="fcs-toast" id="fcsToast" role="status" aria-live="polite">
			<span class="fcs-toast__icon" aria-hidden="true"></span>
			<span class="fcs-toast__message" data-toast-message></span>
		</div>
	</div>
</div>
