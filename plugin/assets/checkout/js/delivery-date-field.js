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

  function readWindows($input) {
    try {
      const windows = JSON.parse($input.attr('data-windows') || '{}');
      return windows && typeof windows === 'object' ? windows : {};
    } catch (e) {
      return {};
    }
  }

  function updateWindowCaption($input, windows, selectedDate) {
    const $caption = $('#fcsDeliveryDateWindow');
    const deliveryWindow = windows[selectedDate];

    if (!$caption.length || !deliveryWindow) {
      $caption.attr('hidden', true);
      return;
    }

    $caption.text(i18n.deliveryWindow.replace('%1$s', deliveryWindow.start).replace('%2$s', deliveryWindow.end));
    $caption.removeAttr('hidden');
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

    const windows = readWindows($input);

    instance = window.flatpickr($input[0], {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'F j, Y',
      altInputClass: 'fcs-delivery-date-input__display',
      enable: readEligibleDates($input),
      locale,
      disableMobile: true,
      onChange: (selectedDates, dateStr) => updateWindowCaption($input, windows, dateStr)
    });

    instance.altInput.setAttribute('placeholder', i18n.chooseDate);
    updateWindowCaption($input, windows, $input.val());
  }

  $(document.body).on('init_checkout updated_checkout', initDatePicker);
});
