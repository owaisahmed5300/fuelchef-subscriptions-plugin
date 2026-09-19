/**
 * FuelChef Subscriptions - Classic checkout delivery-date notice
 *
 * Once a customer has chosen a fulfilment date, tells them when their order will be
 * delivered or ready for pickup - depending on which the currently chosen shipping rate
 * actually is - as a one-off date normally, or which weekday it will recur on once the
 * subscribe-discount checkbox is also checked. Shown regardless of whether that checkbox
 * is even visible to this customer (a logged-out or ineligible customer never sees it at
 * all), since every customer deserves to know when their order is coming.
 * `#fcsDeliveryNotice` is server-rendered (see the delivery-notice template) inside the
 * same order review table `update_order_review` replaces wholesale, so this re-reads both
 * fields' current values, and the chosen shipping rate, on every refresh rather than
 * tracking state itself.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined' || typeof window.fcsCheckoutShared === 'undefined') {
    return;
  }

  const i18n = window.fcsCheckout.i18n;

  // WooCommerce renders either a radio per rate (multiple options) or a single hidden
  // input carrying the only one available - matching either finds whichever rate is
  // actually chosen.
  function currentShippingRateId() {
    const $chosen = $('input[name^="shipping_method"]:checked, input[name^="shipping_method"][type="hidden"]');

    return $chosen.length ? $chosen.val() : null;
  }

  function update() {
    const $notice = $('#fcsDeliveryNotice');

    if (!$notice.length) {
      return;
    }

    const date = $('.fcs-fulfilment-date-input').val();

    if (!date) {
      $notice.attr('hidden', true);
      return;
    }

    const checked = $('#fcs_subscribe_and_save').is(':checked');
    const isPickup = window.fcsCheckoutShared.isPickupRateId(currentShippingRateId());
    const message = window.fcsCheckoutShared.deliveryNoticeMessage(isPickup, checked, date, i18n);

    $notice.text(message).removeAttr('hidden');
  }

  $(document.body).on('init_checkout updated_checkout fcs:fulfilment-date-changed', update);
  $(document.body).on('change', '#fcs_subscribe_and_save', update);
});
