<?php
/**
 * Classic checkout subscribe-and-save checkbox.
 *
 * $data carries `eligible` (bool), `checked` (bool), `label` (string), `description`
 * (string, empty for none) and `ineligible_message` (string).
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save;

defined( 'ABSPATH' ) || exit;

/** @var bool $eligible */
$eligible = $data['eligible'];
/** @var bool $checked */
$checked = $data['checked'];
/** @var string $label */
$label = $data['label'];
/** @var string $description */
$description = $data['description'];
/** @var string $ineligible_message */
$ineligible_message = $data['ineligible_message'];

if ( ! $eligible ) :
	?>
	<p class="fcs-subscribe-and-save-ineligible"><?php echo esc_html( $ineligible_message ); ?></p>
	<?php
	return;
endif;

woocommerce_form_field(
	Subscribe_And_Save::FIELD_NAME,
	[
		'type'        => 'checkbox',
		'label'       => $label,
		'description' => $description,
		// update_totals_on_change is what makes WooCommerce's own checkout.js trigger a
		// totals refresh when this checkbox changes - the same class core fields use.
		'class'       => [ 'form-row-wide', 'fcs-subscribe-and-save-row', 'update_totals_on_change' ],
	],
	$checked ? '1' : ''
);
?>
<p id="fcsRecurringDayNotice" class="fcs-recurring-day-notice" aria-live="polite" hidden></p>
