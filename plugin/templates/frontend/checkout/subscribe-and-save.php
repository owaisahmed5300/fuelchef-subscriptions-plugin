<?php
/**
 * Classic checkout subscribe-and-save checkbox.
 *
 * This template can be overridden by copying it to
 * yourtheme/fuelchef-subscriptions/checkout/subscribe-and-save.php.
 *
 * @var bool $eligible Whether the cart currently meets the store's Subscribe & Save minimums.
 * @var bool $checked Whether the checkbox should render checked.
 * @var string $label The checkbox's label.
 * @var string $description The checkbox's help text, empty to show none.
 * @var string $ineligible_message Shown instead of the checkbox when the cart is not eligible.
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save;

defined( 'ABSPATH' ) || exit;

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
