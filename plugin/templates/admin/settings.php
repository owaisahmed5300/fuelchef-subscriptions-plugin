<?php
/**
 * Settings admin screen.
 *
 * Rendered by Settings_Controller::render(). $data carries `settings`
 * (Values\Settings) and `applicabilities` (list<string>). The blackout
 * calendar reads its own data from the
 * `fcsSettings` script localization instead - see the controller.
 */

declare(strict_types=1);

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
		<button type="button" class="fcs-nav-tab" data-tab="tab-checkout-fields">
			<?php esc_html_e( 'Checkout Fields', 'fuelchef-subscriptions' ); ?>
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
					<p class="fcs-card__intro">
						<?php esc_html_e( 'Dates specified here close recurring orders for the entire store regardless of location. Use this for statutory holidays or full warehouse shutdowns.', 'fuelchef-subscriptions' ); ?>
					</p>

					<div class="fcs-calendar-toolbar">
						<button type="button" class="fcs-btn fcs-btn--icon" id="calPrevMonth" aria-label="<?php esc_attr_e( 'Previous month', 'fuelchef-subscriptions' ); ?>" title="<?php esc_attr_e( 'Previous month', 'fuelchef-subscriptions' ); ?>">
							<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M11 4.5 6.5 9l4.5 4.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<strong class="fcs-calendar-toolbar__title" id="globalCalendarMonth"></strong>
						<button type="button" class="fcs-btn fcs-btn--icon" id="calNextMonth" aria-label="<?php esc_attr_e( 'Next month', 'fuelchef-subscriptions' ); ?>" title="<?php esc_attr_e( 'Next month', 'fuelchef-subscriptions' ); ?>">
							<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M7 4.5 11.5 9 7 13.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
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
					<h2 class="fcs-card__title"><?php esc_html_e( 'Order Cutoff Time', 'fuelchef-subscriptions' ); ?></h2>
				</div>
				<div class="fcs-card__body">
					<p class="fcs-card__intro">
						<?php esc_html_e( 'Customers can no longer place, change or cancel an order for a fulfilment date once that date\'s cutoff has passed.', 'fuelchef-subscriptions' ); ?>
					</p>

					<div class="fcs-inline-field">
						<label for="cutoffDays"><?php esc_html_e( 'Lock orders', 'fuelchef-subscriptions' ); ?></label>
						<input
							class="fcs-input fcs-input--number"
							id="cutoffDays"
							type="number"
							min="0"
							value="<?php echo esc_attr( (string) $settings->cutoff_days() ); ?>"
						>
						<span><?php esc_html_e( 'day(s) before fulfilment, at', 'fuelchef-subscriptions' ); ?></span>
						<input
							class="fcs-input"
							id="cutoffTime"
							type="time"
							value="<?php echo esc_attr( substr( $settings->cutoff_time(), 0, 5 ) ); ?>"
						>
					</div>

					<div class="fcs-notice">
						<strong><?php esc_html_e( 'Example:', 'fuelchef-subscriptions' ); ?></strong>
						<?php esc_html_e( 'With a 1-day cutoff at 11:30 PM, a Friday order must be placed before 11:30 PM on Thursday. After that, Saturday becomes the earliest available date.', 'fuelchef-subscriptions' ); ?>
					</div>
				</div>
			</div>
		</section>

		<section class="fcs-tab-panel" id="tab-checkout-fields">
			<div class="fcs-card">
				<div class="fcs-card__header">
					<h2 class="fcs-card__title"><?php esc_html_e( 'Fulfilment Date Field', 'fuelchef-subscriptions' ); ?></h2>
				</div>
				<div class="fcs-card__body">
					<p class="fcs-card__intro">
						<?php esc_html_e( 'Customize how the fulfilment date field appears to customers at checkout.', 'fuelchef-subscriptions' ); ?>
					</p>

					<div class="fcs-field">
						<label for="fulfilmentDateLabel"><?php esc_html_e( 'Field label', 'fuelchef-subscriptions' ); ?></label>
						<input
							class="fcs-input"
							id="fulfilmentDateLabel"
							type="text"
							maxlength="190"
							value="<?php echo esc_attr( $settings->fulfilment_date_label() ); ?>"
						>
					</div>

					<div class="fcs-field">
						<label for="fulfilmentDateDescription"><?php esc_html_e( 'Help text (optional)', 'fuelchef-subscriptions' ); ?></label>
						<textarea
							class="fcs-textarea"
							id="fulfilmentDateDescription"
							maxlength="300"
							placeholder="<?php esc_attr_e( 'e.g. Choose the day you\'d like this order fulfilled.', 'fuelchef-subscriptions' ); ?>"
						><?php echo esc_textarea( $settings->fulfilment_date_description() ); ?></textarea>
						<p class="fcs-field__hint"><?php esc_html_e( 'Shown under the field. Leave blank to show none.', 'fuelchef-subscriptions' ); ?></p>
					</div>

					<div class="fcs-field">
						<label for="maxFulfilmentWindowDays"><?php esc_html_e( 'Maximum fulfilment window', 'fuelchef-subscriptions' ); ?></label>
						<div class="fcs-inline-field">
							<input
								class="fcs-input fcs-input--number"
								id="maxFulfilmentWindowDays"
								type="number"
								min="1"
								value="<?php echo esc_attr( (string) $settings->max_fulfilment_window_days() ); ?>"
							>
							<span><?php esc_html_e( 'days into the future', 'fuelchef-subscriptions' ); ?></span>
						</div>
						<p class="fcs-field__hint"><?php esc_html_e( 'How far ahead customers can choose a fulfilment date, still subject to schedules, blackout dates and the order cutoff.', 'fuelchef-subscriptions' ); ?></p>
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
					<p class="fcs-card__intro">
						<?php esc_html_e( 'Configure the discount a customer gets for choosing to subscribe at checkout.', 'fuelchef-subscriptions' ); ?>
					</p>

					<div class="fcs-field">
						<label for="subscribeDiscountPercent"><?php esc_html_e( 'Discount', 'fuelchef-subscriptions' ); ?></label>
						<div class="fcs-percent-input">
							<input
								class="fcs-input fcs-input--number"
								id="subscribeDiscountPercent"
								type="number"
								min="0"
								max="100"
								value="<?php echo esc_attr( (string) $settings->subscribe_discount_percent() ); ?>"
							>
							<span class="fcs-percent-input__suffix">%</span>
						</div>
					</div>

					<div class="fcs-field">
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

			<div class="fcs-card">
				<div class="fcs-card__header">
					<h2 class="fcs-card__title"><?php esc_html_e( 'Checkout Wording', 'fuelchef-subscriptions' ); ?></h2>
				</div>
				<div class="fcs-card__body">
					<p class="fcs-card__intro">
						<?php esc_html_e( 'Customize the wording shown next to the Subscribe & Save checkbox at checkout.', 'fuelchef-subscriptions' ); ?>
					</p>

					<div class="fcs-field">
						<label for="subscribeSaveLabel"><?php esc_html_e( 'Checkbox label', 'fuelchef-subscriptions' ); ?></label>
						<input
							class="fcs-input"
							id="subscribeSaveLabel"
							type="text"
							maxlength="190"
							value="<?php echo esc_attr( $settings->subscribe_save_label() ); ?>"
						>
						<p class="fcs-field__hint"><?php esc_html_e( 'Use {percent} anywhere you want the current discount to appear, e.g. "Subscribe & Save {percent}%".', 'fuelchef-subscriptions' ); ?></p>
					</div>

					<div class="fcs-field">
						<label for="subscribeSaveDescription"><?php esc_html_e( 'Help text (optional)', 'fuelchef-subscriptions' ); ?></label>
						<textarea
							class="fcs-textarea"
							id="subscribeSaveDescription"
							maxlength="300"
							placeholder="<?php esc_attr_e( 'e.g. Get {percent}% off this order and every renewal.', 'fuelchef-subscriptions' ); ?>"
						><?php echo esc_textarea( $settings->subscribe_save_description() ); ?></textarea>
						<p class="fcs-field__hint"><?php esc_html_e( 'Shown under the checkbox. Also accepts {percent}. Leave blank to show none.', 'fuelchef-subscriptions' ); ?></p>
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
