<?php
/**
 * Classic checkout fulfilment date field - shown once a shipping address or pickup
 * location resolves to a real destination, but no schedule covers it.
 *
 * A `<tr>`, not a `<p>` - see fulfilment-date-field.php for why.
 *
 * This template can be overridden by copying it to
 * yourtheme/fuelchef-subscriptions/checkout/fulfilment-date-no-match.php.
 *
 * @var string $label The field's label.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<tr class="fcs-fulfilment-date-row">
	<th>
		<?php echo esc_html( $label ); ?>
	</th>
	<td>
		<p class="fcs-fulfilment-date-no-match">
			<?php esc_html_e( 'No fulfilment dates are available for this location.', 'fuelchef-subscriptions' ); ?>
		</p>
	</td>
</tr>
