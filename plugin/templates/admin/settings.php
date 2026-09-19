<?php
/**
 * Settings admin screen.
 *
 * Rendered by Settings_Controller::render(). The blackout calendar reads its own data
 * from the `fcsSettings` script localization instead - see the controller.
 *
 * Template Variables
 *
 * @var Settings $settings
 * @var list<string> $applicabilities
 * @var string $currency_symbol
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap">
	<div class="fcs-admin fcs-wrap fcs-editor-shell">
		<div class="fcs-page-header fcs-editor-header">
			<h1 class="fcs-page-title">
				<?php esc_html_e( 'FuelChef Subscriptions', 'fuelchef-subscriptions' ); ?>
				&rsaquo;
				<?php esc_html_e( 'Settings', 'fuelchef-subscriptions' ); ?>
			</h1>
			<p class="fcs-page-subtitle">
				<?php
				esc_html_e(
					'Configure store-wide recurring rules, global closure dates, and lock cutoff windows.',
					'fuelchef-subscriptions'
				);
				?>
			</p>
		</div>

		<nav class="fcs-nav-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings Tabs', 'fuelchef-subscriptions' ); ?>">
			<button type="button" id="tab-cutoff-trigger" class="fcs-nav-tab fcs-nav-tab--active" role="tab" aria-selected="true" aria-controls="tab-cutoff" data-tab="tab-cutoff">
				<?php esc_html_e( 'Order Cutoff', 'fuelchef-subscriptions' ); ?>
			</button>
			<button type="button" id="tab-checkout-fields-trigger" class="fcs-nav-tab" role="tab" aria-selected="false" aria-controls="tab-checkout-fields" data-tab="tab-checkout-fields">
				<?php esc_html_e( 'Checkout Fields', 'fuelchef-subscriptions' ); ?>
			</button>
		</nav>

		<form id="settingsForm" onsubmit="return false;">
			<section class="fcs-tab-panel fcs-tab-panel--active" id="tab-cutoff" role="tabpanel" aria-labelledby="tab-cutoff-trigger" tabindex="0">
				<div class="fcs-card">
					<div class="fcs-card__header">
						<h2 class="fcs-card__title">
							<?php esc_html_e( 'Order Cutoff Time', 'fuelchef-subscriptions' ); ?>
						</h2>
					</div>
					<div class="fcs-card__body">
						<p class="fcs-card__intro">
							<?php
							esc_html_e(
								'Customers can no longer place, change or cancel an order for a delivery/pickup date once that date\'s cutoff has passed.',
								'fuelchef-subscriptions'
							);
							?>
						</p>

						<div class="fcs-inline-field">
							<label for="cutoffDays">
								<?php esc_html_e( 'Lock orders', 'fuelchef-subscriptions' ); ?>
							</label>
							<input
								class="fcs-input fcs-input--number"
								id="cutoffDays"
								type="number"
								min="0"
								value="<?php echo esc_attr( (string) $settings->cutoff_days() ); ?>"
							>
							<span>
								<?php esc_html_e( 'day(s) before the delivery/pickup date, at', 'fuelchef-subscriptions' ); ?>
							</span>
							<input
								class="fcs-input fcs-input--time"
								id="cutoffTime"
								type="time"
								value="<?php echo esc_attr( substr( $settings->cutoff_time(), 0, 5 ) ); ?>"
							>
						</div>

						<div class="fcs-notice">
							<strong>
								<?php esc_html_e( 'Example:', 'fuelchef-subscriptions' ); ?>
							</strong>
							<?php
							esc_html_e(
								'With a 1-day cutoff at 11:30 PM, a Friday order must be placed before 11:30 PM on Thursday. After that, Saturday becomes the earliest available date.',
								'fuelchef-subscriptions'
							);
							?>
						</div>
					</div>
				</div>
			</section>

			<section class="fcs-tab-panel" id="tab-checkout-fields" role="tabpanel" aria-labelledby="tab-checkout-fields-trigger" tabindex="0">
				<div class="fcs-card">
					<div class="fcs-card__header">
						<h2 class="fcs-card__title">
							<?php esc_html_e( 'Delivery/Pickup Date Field', 'fuelchef-subscriptions' ); ?>
						</h2>
					</div>
					<div class="fcs-card__body">
						<p class="fcs-card__intro">
							<?php
							esc_html_e(
								'Customize how the delivery/pickup date field appears to customers at checkout.',
								'fuelchef-subscriptions'
							);
							?>
						</p>

						<div class="fcs-field">
							<label for="maxFulfilmentWindowDays">
								<?php esc_html_e( 'Maximum delivery/pickup window', 'fuelchef-subscriptions' ); ?>
							</label>
							<div class="fcs-inline-value">
								<input
									class="fcs-input fcs-input--number"
									id="maxFulfilmentWindowDays"
									type="number"
									min="1"
									max="<?php echo esc_attr( (string) Settings::MAX_FULFILMENT_WINDOW_DAYS ); ?>"
									value="<?php echo esc_attr( (string) $settings->max_fulfilment_window_days() ); ?>"
									aria-describedby="maxFulfilmentWindowDaysHint"
								>
								<span>
									<?php esc_html_e( 'days into the future', 'fuelchef-subscriptions' ); ?>
								</span>
							</div>
							<p class="fcs-field__hint" id="maxFulfilmentWindowDaysHint">
								<?php
								esc_html_e(
									'How far ahead customers can choose a delivery/pickup date, still subject to schedules, closure dates and the order cutoff.',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>

						<div class="fcs-field">
							<label for="fulfilmentDateLabel">
								<?php esc_html_e( 'Field label', 'fuelchef-subscriptions' ); ?>
							</label>
							<input
								class="fcs-input"
								id="fulfilmentDateLabel"
								type="text"
								maxlength="190"
								value="<?php echo esc_attr( $settings->fulfilment_date_label() ); ?>"
							>
						</div>

						<div class="fcs-field">
							<label for="fulfilmentDateDescription">
								<?php esc_html_e( 'Help text (optional)', 'fuelchef-subscriptions' ); ?>
							</label>
							<?php
							$fulfilment_date_description_placeholder = __(
								'e.g. Choose the day you\'d like to receive this order.',
								'fuelchef-subscriptions'
							);
							?>
							<textarea
								class="fcs-textarea"
								id="fulfilmentDateDescription"
								maxlength="300"
								placeholder="<?php echo esc_attr( $fulfilment_date_description_placeholder ); ?>"
								aria-describedby="fulfilmentDateDescriptionHint"
							><?php echo esc_textarea( $settings->fulfilment_date_description() ); ?></textarea>
							<p class="fcs-field__hint" id="fulfilmentDateDescriptionHint">
								<?php
								esc_html_e(
									'Shown under the field. Leave blank to show none.',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>

						<div class="fcs-field">
							<label for="fulfilmentWindowMessage">
								<?php esc_html_e( 'Delivery/pickup window message (optional)', 'fuelchef-subscriptions' ); ?>
							</label>
							<?php
							$fulfilment_window_message_placeholder = __(
								'Available between {start} and {end}.',
								'fuelchef-subscriptions'
							);
							?>
							<textarea
								class="fcs-textarea"
								id="fulfilmentWindowMessage"
								maxlength="300"
								placeholder="<?php echo esc_attr( $fulfilment_window_message_placeholder ); ?>"
								aria-describedby="fulfilmentWindowMessageHint"
							><?php echo esc_textarea( $settings->fulfilment_window_message() ); ?></textarea>
							<p class="fcs-field__hint" id="fulfilmentWindowMessageHint">
								<?php
								esc_html_e(
									'Shown once a date is chosen. Use {start} and {end} anywhere you want the delivery/pickup hours to appear. Leave blank to use the default wording.',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>
					</div>
				</div>

				<div class="fcs-card">
					<div class="fcs-card__header">
						<h2 class="fcs-card__title">
							<?php esc_html_e( 'Subscribe Discount', 'fuelchef-subscriptions' ); ?>
						</h2>
					</div>
					<div class="fcs-card__body">
						<p class="fcs-card__intro">
							<?php
							esc_html_e(
								'Configure the discount a customer gets for subscribing to recurring orders at checkout, and the wording shown next to the checkbox.',
								'fuelchef-subscriptions'
							);
							?>
						</p>

						<div class="fcs-field">
							<label for="subscribeDiscountPercent">
								<?php esc_html_e( 'Discount', 'fuelchef-subscriptions' ); ?>
							</label>
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
							<label for="subscribeApplicability">
								<?php esc_html_e( 'Applies to', 'fuelchef-subscriptions' ); ?>
							</label>
							<select class="fcs-select" id="subscribeApplicability">
								<?php foreach ( $applicabilities as $value ) : ?>
									<option
										value="<?php echo esc_attr( $value ); ?>"
										<?php selected( $settings->subscribe_applicability(), $value ); ?>
									>
										<?php echo esc_html( Subscribe_Applicability::label( $value ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="fcs-field">
							<label for="subscribeSaveLabel">
								<?php esc_html_e( 'Checkbox label', 'fuelchef-subscriptions' ); ?>
							</label>
							<input
								class="fcs-input"
								id="subscribeSaveLabel"
								type="text"
								maxlength="190"
								value="<?php echo esc_attr( $settings->subscribe_save_label() ); ?>"
								aria-describedby="subscribeSaveLabelHint"
							>
							<p class="fcs-field__hint" id="subscribeSaveLabelHint">
								<?php
								esc_html_e(
									'Use {percent} anywhere you want the current discount to appear, e.g. "Subscribe for {percent}% off every order".',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>

						<div class="fcs-field">
							<label for="subscribeSaveDescription">
								<?php esc_html_e( 'Help text (optional)', 'fuelchef-subscriptions' ); ?>
							</label>
							<?php
							$subscribe_save_description_placeholder = __(
								'e.g. Get {percent}% off every scheduled order.',
								'fuelchef-subscriptions'
							);
							?>
							<textarea
								class="fcs-textarea"
								id="subscribeSaveDescription"
								maxlength="300"
								placeholder="<?php echo esc_attr( $subscribe_save_description_placeholder ); ?>"
								aria-describedby="subscribeSaveDescriptionHint"
							><?php echo esc_textarea( $settings->subscribe_save_description() ); ?></textarea>
							<p class="fcs-field__hint" id="subscribeSaveDescriptionHint">
								<?php
								esc_html_e(
									'Shown under the checkbox. Also accepts {percent}. Leave blank to show none.',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>

						<div class="fcs-field">
							<label for="minimumOrderAmount">
								<?php esc_html_e( 'Minimum order amount', 'fuelchef-subscriptions' ); ?>
							</label>
							<div class="fcs-currency-input">
								<span class="fcs-currency-input__prefix"><?php echo esc_html( $currency_symbol ); ?></span>
								<input
									class="fcs-input fcs-input--number"
									id="minimumOrderAmount"
									type="number"
									min="0"
									step="0.01"
									value="<?php echo esc_attr( (string) $settings->minimum_order_amount() ); ?>"
									aria-describedby="minimumOrderAmountHint"
								>
							</div>
							<p class="fcs-field__hint" id="minimumOrderAmountHint">
								<?php
								esc_html_e(
									'Cart subtotal required before the subscribe discount is offered. 0 means no restriction.',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>

						<div class="fcs-field">
							<label for="minimumCartQuantity">
								<?php esc_html_e( 'Minimum items in cart', 'fuelchef-subscriptions' ); ?>
							</label>
							<input
								class="fcs-input fcs-input--number"
								id="minimumCartQuantity"
								type="number"
								min="0"
								value="<?php echo esc_attr( (string) $settings->minimum_cart_quantity() ); ?>"
								aria-describedby="minimumCartQuantityHint"
							>
							<p class="fcs-field__hint" id="minimumCartQuantityHint">
								<?php
								esc_html_e(
									'Cart items required before the subscribe discount is offered. 0 means no restriction.',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>

						<div class="fcs-field">
							<label for="ineligibleMessage">
								<?php esc_html_e( 'Ineligible message (optional)', 'fuelchef-subscriptions' ); ?>
							</label>
							<textarea
								class="fcs-textarea"
								id="ineligibleMessage"
								maxlength="300"
								placeholder="<?php echo esc_attr( $settings->ineligible_message_resolved() ); ?>"
								aria-describedby="ineligibleMessageHint"
							><?php echo esc_textarea( $settings->ineligible_message() ); ?></textarea>
							<p class="fcs-field__hint" id="ineligibleMessageHint">
								<?php
								esc_html_e(
									'Shown instead of the subscribe discount when the cart doesn\'t qualify. Leave blank to use the default wording.',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>

						<div class="fcs-field">
							<label for="loggedOutMessage">
								<?php esc_html_e( 'Logged-out message (optional)', 'fuelchef-subscriptions' ); ?>
							</label>
							<textarea
								class="fcs-textarea"
								id="loggedOutMessage"
								maxlength="300"
								placeholder="<?php echo esc_attr( $settings->logged_out_message_resolved() ); ?>"
								aria-describedby="loggedOutMessageHint"
							><?php echo esc_textarea( $settings->logged_out_message() ); ?></textarea>
							<p class="fcs-field__hint" id="loggedOutMessageHint">
								<?php
								esc_html_e(
									'Shown instead of the subscribe discount when the customer isn\'t logged in, next to a Log in link. Leave blank to use the default wording.',
									'fuelchef-subscriptions'
								);
								?>
							</p>
						</div>
					</div>
				</div>
			</section>

			<div class="fcs-submit-bar">
				<button type="button" class="fcs-btn fcs-btn--primary" id="saveSettingsBtn">
					<?php esc_html_e( 'Save Changes', 'fuelchef-subscriptions' ); ?>
				</button>
				<span class="fcs-save-status fcs-save-status--saved" id="fcsSaveStatus" role="status" aria-live="polite">
					&#10003;
					<?php esc_html_e( 'All changes saved', 'fuelchef-subscriptions' ); ?>
				</span>
			</div>
		</form>

		<div class="fcs-toast" id="fcsToast" role="status" aria-live="polite">
			<span class="fcs-toast__icon" aria-hidden="true"></span>
			<span class="fcs-toast__message" data-toast-message></span>
		</div>
	</div>
</div>
