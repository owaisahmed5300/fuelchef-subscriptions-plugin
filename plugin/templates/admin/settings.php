<?php
/**
 * Settings admin screen.
 *
 * Rendered by Settings_Controller::render(). $data carries `settings`
 * (Settings\Settings), `cutoff_units` (list<string>) and `applicabilities`
 * (list<string>). The blackout calendar reads its own data from the
 * `fcsSettings` script localization instead - see the controller.
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Values\Cutoff_Unit;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;

defined( 'ABSPATH' ) || exit;

$settings = $data['settings'];
?>
<div class="fcs-wrap">
	<div class="fcs-page-header">
		<h1 class="fcs-page-title">
			<?php esc_html_e( 'FuelChef Subscriptions', 'fuelchef-subscriptions' ); ?>
			&rsaquo; <?php esc_html_e( 'Settings', 'fuelchef-subscriptions' ); ?>
		</h1>
		<p class="fcs-page-subtitle">
			<?php esc_html_e( 'Configure store-wide recurring rules, global blackout dates, and lock cutoff windows.', 'fuelchef-subscriptions' ); ?>
		</p>
	</div>

	<nav class="fcs-nav-tabs" aria-label="<?php esc_attr_e( 'Settings Tabs', 'fuelchef-subscriptions' ); ?>">
		<button type="button" class="fcs-nav-tab fcs-nav-tab--active" data-tab="tab-blackouts">
			<?php esc_html_e( 'Global Blackouts', 'fuelchef-subscriptions' ); ?>
		</button>
		<button type="button" class="fcs-nav-tab" data-tab="tab-cutoff">
			<?php esc_html_e( 'Order Cutoff', 'fuelchef-subscriptions' ); ?>
		</button>
		<button type="button" class="fcs-nav-tab" data-tab="tab-subscribe">
			<?php esc_html_e( 'Subscribe & Save', 'fuelchef-subscriptions' ); ?>
		</button>
	</nav>

	<form id="settingsForm" onsubmit="return false;">
		<section class="fcs-tab-panel fcs-tab-panel--active" id="tab-blackouts">
			<div class="fcs-card">
				<div class="fcs-card__header">
					<h2 class="fcs-card__title"><?php esc_html_e( 'Global Store Closures', 'fuelchef-subscriptions' ); ?></h2>
				</div>
				<div class="fcs-card__body">
					<p style="margin-top:0; color:var(--fcs-color-text-muted);">
						<?php esc_html_e( 'Dates specified here close recurring orders for the entire store regardless of location. Use this for statutory holidays or full warehouse shutdowns.', 'fuelchef-subscriptions' ); ?>
					</p>

					<div class="fcs-calendar-toolbar">
						<button type="button" class="fcs-btn fcs-btn--icon" id="calPrevMonth" aria-label="<?php esc_attr_e( 'Previous Month', 'fuelchef-subscriptions' ); ?>">&lsaquo;</button>
						<strong class="fcs-calendar-toolbar__title" id="globalCalendarMonth"></strong>
						<button type="button" class="fcs-btn fcs-btn--icon" id="calNextMonth" aria-label="<?php esc_attr_e( 'Next Month', 'fuelchef-subscriptions' ); ?>">&rsaquo;</button>
					</div>

					<div class="fcs-calendar" id="globalCalendar"></div>

					<div class="fcs-calendar-legend">
						<span class="fcs-calendar-legend__dot"></span>
						<?php esc_html_e( 'Closed store-wide', 'fuelchef-subscriptions' ); ?>
						<span><?php esc_html_e( '(Click any date to close. Click an existing closed date to edit note.)', 'fuelchef-subscriptions' ); ?></span>
					</div>

					<div class="fcs-summary-list" id="globalUnavailableList"></div>
				</div>
			</div>
		</section>

		<section class="fcs-tab-panel" id="tab-cutoff">
			<div class="fcs-card">
				<div class="fcs-card__header">
					<h2 class="fcs-card__title"><?php esc_html_e( 'Upcoming Order Lock Window', 'fuelchef-subscriptions' ); ?></h2>
				</div>
				<div class="fcs-card__body">
					<p style="margin-top:0; color:var(--fcs-color-text-muted);">
						<?php esc_html_e( 'Define how much advance notice is required before recurring orders are locked and sent for fulfillment preparation.', 'fuelchef-subscriptions' ); ?>
					</p>

					<div class="fcs-inline-field">
						<label for="cutoffAmount"><?php esc_html_e( 'Lock the order', 'fuelchef-subscriptions' ); ?></label>
						<input
							class="fcs-input fcs-input--number"
							id="cutoffAmount"
							type="number"
							min="0"
							value="<?php echo esc_attr( (string) $settings->cutoff_amount() ); ?>"
						>
						<select class="fcs-select" id="cutoffUnit">
							<?php foreach ( $data['cutoff_units'] as $unit ) : ?>
								<option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $settings->cutoff_unit(), $unit ); ?>>
									<?php echo esc_html( Cutoff_Unit::label( $unit ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<span><?php esc_html_e( 'before fulfillment', 'fuelchef-subscriptions' ); ?></span>
					</div>

					<div class="fcs-notice">
						<strong><?php esc_html_e( 'At cutoff time:', 'fuelchef-subscriptions' ); ?></strong>
						<?php esc_html_e( 'Upcoming subscription renewals process payment, and order contents can no longer be edited by customers.', 'fuelchef-subscriptions' ); ?>
					</div>
				</div>
			</div>
		</section>

		<section class="fcs-tab-panel" id="tab-subscribe">
			<div class="fcs-card">
				<div class="fcs-card__header">
					<h2 class="fcs-card__title"><?php esc_html_e( 'Subscribe & Save', 'fuelchef-subscriptions' ); ?></h2>
				</div>
				<div class="fcs-card__body">
					<p style="margin-top:0; color:var(--fcs-color-text-muted);">
						<?php esc_html_e( 'Configure the discount a customer gets for choosing to subscribe at checkout.', 'fuelchef-subscriptions' ); ?>
					</p>

					<div class="fcs-inline-field">
						<label for="subscribeDiscountPercent"><?php esc_html_e( 'Discount', 'fuelchef-subscriptions' ); ?></label>
						<input
							class="fcs-input fcs-input--number"
							id="subscribeDiscountPercent"
							type="number"
							min="0"
							max="100"
							value="<?php echo esc_attr( (string) $settings->subscribe_discount_percent() ); ?>"
						>
						<span>%</span>
					</div>

					<div class="fcs-inline-field">
						<label for="subscribeApplicability"><?php esc_html_e( 'Applies to', 'fuelchef-subscriptions' ); ?></label>
						<select class="fcs-select" id="subscribeApplicability">
							<?php foreach ( $data['applicabilities'] as $value ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings->subscribe_applicability(), $value ); ?>>
									<?php echo esc_html( Subscribe_Applicability::label( $value ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>
		</section>

		<div class="fcs-submit-bar">
			<button type="button" class="fcs-btn fcs-btn--primary" id="saveSettingsBtn">
				<?php esc_html_e( 'Save Changes', 'fuelchef-subscriptions' ); ?>
			</button>
			<span class="fcs-save-status fcs-save-status--saved" id="fcsSaveStatus">
				&#10003; <?php esc_html_e( 'All changes saved', 'fuelchef-subscriptions' ); ?>
			</span>
		</div>
	</form>
</div>

<div class="fcs-popover" id="datePopover">
	<div class="fcs-popover__title" data-pop-title></div>
	<div class="fcs-popover__subtitle"><?php esc_html_e( 'Global Store Closure', 'fuelchef-subscriptions' ); ?></div>
	<label class="fcs-popover__label"><?php esc_html_e( 'Closure note (optional)', 'fuelchef-subscriptions' ); ?></label>
	<textarea class="fcs-textarea" data-pop-reason maxlength="255" placeholder="<?php esc_attr_e( 'e.g. National Holiday', 'fuelchef-subscriptions' ); ?>"></textarea>
	<div class="fcs-popover__count"><span data-pop-count>0</span> / 255</div>
	<div class="fcs-popover__actions">
		<button type="button" class="fcs-btn fcs-btn--danger" data-pop-remove><?php esc_html_e( 'Remove date', 'fuelchef-subscriptions' ); ?></button>
		<button type="button" class="fcs-btn fcs-btn--primary" data-pop-save><?php esc_html_e( 'Save note', 'fuelchef-subscriptions' ); ?></button>
	</div>
</div>

<div class="fcs-toast" id="fcsToast"></div>
