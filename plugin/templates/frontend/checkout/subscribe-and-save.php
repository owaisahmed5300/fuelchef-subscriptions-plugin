<?php
/**
 * Classic checkout subscribe-and-save checkbox.
 *
 * Rendered by Frontend\Checkout\Subscribe_And_Save::render(), directly inside the order
 * review table's `<tfoot>`, right before the order total. $data carries `checked` (bool),
 * `label` (string) and `description` (string, empty for none - both store-configured,
 * with the {percent} placeholder already resolved).
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save;

defined( 'ABSPATH' ) || exit;

/** @var bool $checked */
$checked = $data['checked'];
/** @var string $label */
$label = $data['label'];
/** @var string $description */
$description = $data['description'];
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
			<?php echo esc_html( $label ); ?>
		</label>
		<?php if ( '' !== $description ) : ?>
			<p class="fcs-subscribe-and-save-description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
	</td>
</tr>
