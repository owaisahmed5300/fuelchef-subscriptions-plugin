<?php
/**
 * Classic checkout fulfilment date field.
 *
 * A `<tr>`, not `woocommerce_form_field()`'s own `<p>` markup - only `<tr>` is valid
 * directly inside the `<tfoot>` this renders in.
 *
 * @var list<string> $eligible_dates Eligible dates for the chosen destination, `Y-m-d`.
 * @var array<string, array{start: string, end: string}> $windows Fulfilment windows, keyed by date.
 * @var string $label The field's label.
 * @var string $description The field's help text, empty to show none.
 * @var string|null $selected_date The currently selected date, if any.
 * @var array{start: string, end: string}|null $selected_window The selected date's fulfilment window, if any.
 * @var string $window_message The resolved fulfilment-window message for the selected date, empty when none is selected.
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Frontend\Checkout\Fulfilment_Date_Field;

defined( 'ABSPATH' ) || exit;

$description = ( [] === $eligible_dates )
	? __( 'No fulfilment dates are currently available for this destination.', 'fuelchef-subscriptions' )
	: $description;
?>
<tr class="fcs-fulfilment-date-row">
	<th>
		<label for="fcs_fulfilment_date"><?php echo esc_html( $label ); ?></label>
	</th>
	<td>
		<input
			type="text"
			id="fcs_fulfilment_date"
			name="<?php echo esc_attr( Fulfilment_Date_Field::FIELD_NAME ); ?>"
			class="fcs-fulfilment-date-input"
			placeholder="<?php esc_attr_e( 'Choose a date', 'fuelchef-subscriptions' ); ?>"
			readonly="readonly"
			autocomplete="off"
			value="<?php echo esc_attr( $selected_date ?? '' ); ?>"
			data-eligible-dates="<?php echo esc_attr( (string) wp_json_encode( $eligible_dates ) ); ?>"
			data-windows="<?php echo esc_attr( (string) wp_json_encode( $windows ) ); ?>"
			<?php echo '' !== $description ? 'aria-describedby="fcsFulfilmentDateDescription fcsFulfilmentDateWindow"' : 'aria-describedby="fcsFulfilmentDateWindow"'; ?>
		>
		<?php if ( '' !== $description ) : ?>
			<p class="fcs-fulfilment-date-description" id="fcsFulfilmentDateDescription"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<p class="fcs-fulfilment-date-window" id="fcsFulfilmentDateWindow" aria-live="polite" <?php echo null === $selected_window ? 'hidden' : ''; ?>>
			<?php echo esc_html( $window_message ); ?>
		</p>
	</td>
</tr>
