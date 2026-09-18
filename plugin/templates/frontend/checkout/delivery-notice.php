<?php
/**
 * Classic checkout delivery-date notice marker.
 *
 * Always rendered, regardless of login state or subscribe-discount eligibility - every
 * customer who chooses a fulfilment date is told when their order arrives. Empty until
 * `assets/checkout/js/delivery-notice.js` fills it in client-side: the fulfilment date is
 * rarely known yet at render time, and even once it is, the discount checkbox it also
 * reads can change after this template has already run.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<p id="fcsDeliveryNotice" class="fcs-delivery-notice" aria-live="polite" hidden></p>
