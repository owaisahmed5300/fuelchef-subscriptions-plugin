/**
 * FuelChef Subscriptions - Classic checkout recurring fulfilment day notice
 *
 * Once a customer has both chosen a fulfilment date and checked Subscribe & Save, tells
 * them which weekday future renewals will fall on. `#fcsRecurringDayNotice` is
 * server-rendered (see the subscribe-and-save template) inside the same order review
 * table `update_order_review` replaces wholesale, so this re-reads both fields' current
 * values on every refresh rather than tracking state itself.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined' || typeof window.fcsCheckoutShared === 'undefined') {
    return;
  }

  const i18n = window.fcsCheckout.i18n;

  function update() {
    const $notice = $('#fcsRecurringDayNotice');

    if (!$notice.length) {
      return;
    }

    const date = $('.fcs-fulfilment-date-input').val();
    const checked = $('#fcs_subscribe_and_save').is(':checked');

    if (!date || !checked) {
      $notice.attr('hidden', true);
      return;
    }

    const weekday = window.fcsCheckoutShared.weekdayNameForDate(date, i18n.dayNames);

    $notice.text(i18n.recurringDayNotice.replace('%s', weekday)).removeAttr('hidden');
  }

  $(document.body).on('init_checkout updated_checkout fcs:fulfilment-date-changed', update);
  $(document.body).on('change', '#fcs_subscribe_and_save', update);
});
