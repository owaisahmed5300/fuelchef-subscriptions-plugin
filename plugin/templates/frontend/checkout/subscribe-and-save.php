<?php
/**
 * Classic checkout subscribe-and-save checkbox.
 *
 * $data carries `checked` (bool), `label` (string) and `description` (string, empty for
 * none).
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
