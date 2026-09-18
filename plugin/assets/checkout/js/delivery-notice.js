/**
 * FuelChef Subscriptions - Classic checkout delivery-date notice
 *
 * Once a customer has chosen a fulfilment date, tells them when their order arrives -
 * a one-off delivery date normally, or which weekday it will recur on once the
 * subscribe-discount checkbox is also checked. Shown regardless of whether that checkbox
 * is even visible to this customer (a logged-out or ineligible customer never sees it at
 * all), since every customer deserves to know when their order is coming.
 * `#fcsDeliveryNotice` is server-rendered (see the delivery-notice template) inside the
 * same order review table `update_order_review` replaces wholesale, so this re-reads both
 * fields' current values on every refresh rather than tracking state itself.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined' || typeof window.fcsCheckoutShared === 'undefined') {
    return;
  }

  const i18n = window.fcsCheckout.i18n;

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

    const formattedDate = window.fcsCheckoutShared.formatDisplayDate(date, i18n.monthNames);
    const checked = $('#fcs_subscribe_and_save').is(':checked');

    const message = checked
      ? i18n.recurringDeliveryNotice
          .replace('%1$s', window.fcsCheckoutShared.weekdayNameForDate(date, i18n.dayNames))
          .replace('%2$s', formattedDate)
      : i18n.singleDeliveryNotice.replace('%s', formattedDate);

    $notice.text(message).removeAttr('hidden');
  }

  $(document.body).on('init_checkout updated_checkout fcs:fulfilment-date-changed', update);
  $(document.body).on('change', '#fcs_subscribe_and_save', update);
});
