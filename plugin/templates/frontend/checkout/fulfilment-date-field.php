<?php
/**
 * Classic checkout fulfilment date field.
 *
 * Rendered by Frontend\Checkout\Fulfilment_Date_Field::render(), alongside the other
 * checkout fields via `woocommerce_form_field()` so it looks and behaves like a native
 * WooCommerce field. $data carries `has_schedule` (bool - whether a schedule currently
 * applies, at the time of this page load), `eligible_dates` (list<string>, `Y-m-d`),
 * `windows` (array<string, array{start: string, end: string}>, keyed by date), `label`
 * (string) and `description` (string, empty for none - both store-configured).
 *
 * The date picker is initialized by fulfilment-date-field.js, which also re-fetches
 * eligible dates and toggles this field's visibility whenever the checkout form changes,
 * since this part of checkout is never re-rendered server-side after this first paint.
 */

declare(strict_types=1);

use FuelChef\Subscriptions\Frontend\Checkout\Fulfilment_Date_Field;

defined( 'ABSPATH' ) || exit;

/** @var bool $has_schedule */
$has_schedule = $data['has_schedule'];
/** @var list<string> $eligible_dates */
$eligible_dates = $data['eligible_dates'];
/** @var array<string, array{start: string, end: string}> $windows */
$windows = $data['windows'];
/** @var string $label */
$label = $data['label'];
/** @var string $description */
$description = $data['description'];

$description = ( $has_schedule && [] === $eligible_dates )
	? __( 'No fulfilment dates are currently available for this destination.', 'fuelchef-subscriptions' )
	: $description;
?>
<div id="fcsFulfilmentDateFieldWrap" <?php echo $has_schedule ? '' : 'hidden'; ?>>
	<?php
	woocommerce_form_field(
		Fulfilment_Date_Field::FIELD_NAME,
		[
			'type'              => 'text',
			'label'             => $label,
			'description'       => $description,
			'placeholder'       => __( 'Choose a date', 'fuelchef-subscriptions' ),
			'class'             => [ 'form-row-wide', 'fcs-fulfilment-date-row' ],
			'input_class'       => [ 'fcs-fulfilment-date-input' ],
			'custom_attributes' => [
				'readonly'            => 'readonly',
				'autocomplete'        => 'off',
				'data-eligible-dates' => (string) wp_json_encode( $eligible_dates ),
				'data-windows'        => (string) wp_json_encode( $windows ),
			],
		],
		''
	);
	?>
	<p class="fcs-fulfilment-date-window" id="fcsFulfilmentDateWindow" aria-live="polite" hidden></p>
</div>
