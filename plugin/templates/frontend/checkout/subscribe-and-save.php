<?php
/**
 * Classic checkout subscribe-and-save checkbox.
 *
 * Rendered by Frontend\Checkout\Subscribe_And_Save::render(), directly inside the order
 * review table's `<tfoot>`, right before the order total. $data carries `checked`
 * (bool) and `discount_percent` (int).
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save;

defined( 'ABSPATH' ) || exit;

/** @var bool $checked */
$checked = $data['checked'];
/** @var int $discount_percent */
$discount_percent = $data['discount_percent'];
?>
<tr class="fcs-subscribe-and-save-row">
	<th></th>
	<td class="update_totals_on_change">
		<label class="fcs-subscribe-and-save-label">
			<input
				type="checkbox"
				id="fcs_subscribe_and_save"
				name="<?php echo esc_attr( Subscribe_And_Save::FIELD_NAME ); ?>"
				value="1"
				<?php checked( $checked ); ?>
			/>
			<?php
			printf(
				/* translators: %d: subscribe-and-save discount percentage. */
				esc_html__( 'Subscribe & Save %d%%', 'fuelchef-subscriptions' ),
				absint( $discount_percent )
			);
			?>
		</label>
	</td>
</tr>
