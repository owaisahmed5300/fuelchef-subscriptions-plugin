<?php
/**
 * Classic checkout delivery date field.
 *
 * Rendered by Frontend\Checkout\Delivery_Date_Field::render(), directly inside the order
 * review table's `<tfoot>`. $data carries `eligible_dates` (list<string>, `Y-m-d`),
 * `selected_date` (?string), `windows` (array<string, array{start: string, end: string}>,
 * keyed by date), `label` (string) and `description` (string, empty for none - both
 * store-configured). The date picker itself is initialized by checkout.js, which reads the
 * eligible dates and windows back off this field's `data-eligible-dates`/`data-windows`
 * attributes.
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Frontend\Checkout\Delivery_Date_Field;

defined( 'ABSPATH' ) || exit;

/** @var list<string> $eligible_dates */
$eligible_dates = $data['eligible_dates'];
/** @var string|null $selected_date */
$selected_date = $data['selected_date'];
/** @var array<string, array{start: string, end: string}> $windows */
$windows = $data['windows'];
/** @var string $label */
$label = $data['label'];
/** @var string $description */
$description = $data['description'];
?>
<tr class="fcs-delivery-date-row">
	<th>
		<label for="fcs_delivery_date"><?php echo esc_html( $label ); ?></label>
	</th>
	<td>
		<input
			type="text"
			id="fcs_delivery_date"
			name="<?php echo esc_attr( Delivery_Date_Field::FIELD_NAME ); ?>"
			class="fcs-delivery-date-input"
			autocomplete="off"
			readonly="readonly"
			value="<?php echo esc_attr( $selected_date ?? '' ); ?>"
			placeholder="<?php esc_attr_e( 'Choose a date', 'fuelchef-subscriptions' ); ?>"
			data-eligible-dates="<?php echo esc_attr( (string) wp_json_encode( $eligible_dates ) ); ?>"
			data-windows="<?php echo esc_attr( (string) wp_json_encode( $windows ) ); ?>"
		/>
		<?php if ( '' !== $description ) : ?>
			<p class="fcs-delivery-date-description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<p class="fcs-delivery-date-window" id="fcsDeliveryDateWindow" aria-live="polite" hidden></p>
		<?php if ( [] === $eligible_dates ) : ?>
			<p class="fcs-delivery-date-empty">
				<?php esc_html_e( 'No delivery dates are currently available for this destination.', 'fuelchef-subscriptions' ); ?>
			</p>
		<?php endif; ?>
	</td>
</tr>
