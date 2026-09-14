/**
 * FuelChef Subscriptions - Checkout delivery date field
 *
 * This field lives alongside the other checkout fields, not inside the order review
 * table, so it is not replaced by WooCommerce's own `update_order_review` AJAX refresh.
 * The flatpickr instance is created once and kept; eligible dates are re-fetched from the
 * same REST route the block checkout field uses whenever the checkout form changes - that
 * refresh is also what shows or hides the field once the shipping destination resolves to
 * a schedule (or stops resolving to one).
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
  let currentWindows = {};

  function readJson($el, attr, fallback) {
    try {
      const parsed = JSON.parse($el.attr(attr) || '');
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (e) {
      return fallback;
    }
  }

  function updateWindowCaption(selectedDate) {
    const $caption = $('#fcsDeliveryDateWindow');
    const deliveryWindow = currentWindows[selectedDate];

    if (!$caption.length || !deliveryWindow) {
      $caption.attr('hidden', true);
      return;
    }

    $caption.text(i18n.deliveryWindow.replace('%1$s', deliveryWindow.start).replace('%2$s', deliveryWindow.end));
    $caption.removeAttr('hidden');
  }

  function applyEligibleDates(dates, windows) {
    currentWindows = windows && typeof windows === 'object' ? windows : {};

    if (instance) {
      instance.set('enable', Array.isArray(dates) ? dates : []);
      updateWindowCaption(instance.input.value);
    }
  }

  function refetchEligibleDates() {
    fetch(window.fcsCheckout.eligibleDatesUrl, { credentials: 'same-origin' })
      .then(function (response) {
        return response.ok ? response.json() : { hasSchedule: false, dates: [], windows: {} };
      })
      .then(function (data) {
        $('#fcsDeliveryDateFieldWrap').attr('hidden', !data.hasSchedule);
        applyEligibleDates(data.dates, data.windows);
      })
      .catch(function () {
        // Leave the field as it was; the next checkout change retries.
      });
  }

  function initDatePicker() {
    const $input = $('.fcs-delivery-date-input');

    if (!$input.length || instance) {
      return;
    }

    const dates = readJson($input, 'data-eligible-dates', []);
    const windows = readJson($input, 'data-windows', {});

    currentWindows = windows;

    instance = window.flatpickr($input[0], {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'F j, Y',
      altInputClass: 'fcs-delivery-date-input__display',
      enable: dates,
      locale,
      disableMobile: true,
      onChange: (selectedDates, dateStr) => updateWindowCaption(dateStr)
    });

    instance.altInput.setAttribute('placeholder', i18n.chooseDate);
  }

  $(document.body).on('init_checkout', initDatePicker);
  $(document.body).on('updated_checkout', refetchEligibleDates);
});
