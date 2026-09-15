<?php
/**
 * Classic checkout fulfilment date field.
 *
 * A `<tr>`, not `woocommerce_form_field()`'s own `<p>` markup - only `<tr>` is valid
 * directly inside the `<tfoot>` this renders in.
 *
 * $data carries `eligible_dates` (list<string>, `Y-m-d`), `windows`
 * (array<string, array{start: string, end: string}>, keyed by date), `label` (string),
 * `description` (string, empty for none), `selected_date` (string|null) and
 * `selected_window` (array{start: string, end: string}|null).
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Frontend\Checkout\Fulfilment_Date_Field;

defined( 'ABSPATH' ) || exit;

/** @var list<string> $eligible_dates */
$eligible_dates = $data['eligible_dates'];
/** @var array<string, array{start: string, end: string}> $windows */
$windows = $data['windows'];
/** @var string $label */
$label = $data['label'];
/** @var string $description */
$description = $data['description'];
/** @var string|null $selected_date */
$selected_date = $data['selected_date'];
/** @var array{start: string, end: string}|null $selected_window */
$selected_window = $data['selected_window'];

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
		>
		<?php if ( '' !== $description ) : ?>
			<p class="fcs-fulfilment-date-description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<p class="fcs-fulfilment-date-window" id="fcsFulfilmentDateWindow" aria-live="polite" <?php echo null === $selected_window ? 'hidden' : ''; ?>>
			<?php if ( null !== $selected_window ) : ?>
				<?php
				printf(
					/* translators: 1: opening time, 2: closing time. */
					esc_html__( 'Fulfilment available between %1$s and %2$s.', 'fuelchef-subscriptions' ),
					esc_html( $selected_window['start'] ),
					esc_html( $selected_window['end'] )
				);
				?>
			<?php endif; ?>
		</p>
	</td>
</tr>
