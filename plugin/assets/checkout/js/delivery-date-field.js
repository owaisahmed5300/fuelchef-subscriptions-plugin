/**
 * FuelChef Subscriptions - Checkout delivery date field
 *
 * The order review table is fully replaced on every checkout refresh (address or
 * shipping method change), so the field's DOM node - and any flatpickr instance bound to
 * it - never survives more than one refresh. This re-scans for the field and rebuilds
 * the picker from scratch each time, destroying the previous instance first.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined' || typeof window.flatpickr === 'undefined') {
    return;
  }

  const i18n = window.fcsCheckout.i18n;

  const locale = {
    firstDayOfWeek: window.fcsCheckout.startOfWeek,
    weekdays: {
      shorthand: i18n.dayNamesShort,
      longhand: i18n.dayNames
    },
    months: {
      shorthand: i18n.monthNamesShort,
      longhand: i18n.monthNames
    }
  };

  let instance = null;

  function readEligibleDates($input) {
    try {
      const dates = JSON.parse($input.attr('data-eligible-dates') || '[]');
      return Array.isArray(dates) ? dates : [];
    } catch (e) {
      return [];
    }
  }

  function initDatePicker() {
    if (instance) {
      instance.destroy();
      instance = null;
    }

    const $input = $('.fcs-delivery-date-input');

    if (!$input.length) {
      return;
    }

    instance = window.flatpickr($input[0], {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'F j, Y',
      altInputClass: 'fcs-delivery-date-input__display',
      enable: readEligibleDates($input),
      locale,
      disableMobile: true
    });

    instance.altInput.setAttribute('placeholder', i18n.chooseDate);
  }

  $(document.body).on('init_checkout updated_checkout', initDatePicker);
});
