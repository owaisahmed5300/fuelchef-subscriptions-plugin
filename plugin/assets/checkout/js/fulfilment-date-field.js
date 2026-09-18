/**
 * FuelChef Subscriptions - Checkout fulfilment date field
 *
 * This field lives inside the order review table, alongside the shipping options -
 * WooCommerce's own `update_order_review` AJAX refresh replaces that table's markup
 * wholesale on every address or shipping method change, so this script's job is only to
 * (re-)attach Flatpickr to whichever `<input>` the latest refresh rendered, using the
 * eligible-dates list and window data that refresh's own server-rendered `data-*`
 * attributes already carry. Unlike an earlier version of this field, there is no
 * separate REST fetch here: the server already recomputes both on every render.
 */

jQuery(function ($) {
  'use strict';

  if (
    typeof window.fcsCheckout === 'undefined' ||
    typeof window.fcsCheckoutShared === 'undefined' ||
    typeof window.flatpickr === 'undefined'
  ) {
    return;
  }

  const i18n = window.fcsCheckout.i18n;
  const locale = window.fcsCheckoutShared.buildFlatpickrLocale(i18n, window.fcsCheckout.startOfWeek);

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
    const $caption = $('#fcsFulfilmentDateWindow');
    const fulfilmentWindow = currentWindows[selectedDate];

    if (!$caption.length || !fulfilmentWindow) {
      $caption.attr('hidden', true);
      return;
    }

    $caption.text(i18n.fulfilmentWindow.replace('{start}', fulfilmentWindow.start).replace('{end}', fulfilmentWindow.end));
    $caption.removeAttr('hidden');
  }

  // Runs on both `init_checkout` (first paint) and `updated_checkout` (every order
  // review refresh) - the row this field lives in is fully replaced on each refresh, so
  // any instance already attached is bound to a now-removed DOM node and must be
  // recreated, never merely updated.
  function initDatePicker() {
    const $input = $('.fcs-fulfilment-date-input');

    if (instance) {
      instance.destroy();
      instance = null;
    }

    if (!$input.length) {
      return;
    }

    const dates = readJson($input, 'data-eligible-dates', []);
    const windows = readJson($input, 'data-windows', {});

    currentWindows = windows;

    instance = window.flatpickr($input[0], {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'F j, Y',
      altInputClass: 'fcs-fulfilment-date-input__display',
      enable: dates,
      locale,
      disableMobile: true,
      // Flatpickr manages this input's value itself and never dispatches a native
      // `change` event on it, so recurring-day-notice.js - which needs to know the
      // moment the date changes - listens for this custom event instead of one.
      onChange: (selectedDates, dateStr) => {
        updateWindowCaption(dateStr);
        $(document.body).trigger('fcs:fulfilment-date-changed', [dateStr]);
      }
    });

    instance.altInput.setAttribute('placeholder', i18n.chooseDate);

    // <label for="fcs_fulfilment_date"> in the template targets this input, but
    // altInput: true swaps in a separate visible input (this one keeps the raw value,
    // hidden) - move the id and its description association to the one the customer
    // actually sees, focuses and interacts with.
    instance.altInput.id = $input.attr('id');
    instance.altInput.setAttribute('aria-describedby', $input.attr('aria-describedby') || '');
    $input.removeAttr('id aria-describedby');

    updateWindowCaption($input.val());
    $(document.body).trigger('fcs:fulfilment-date-changed', [$input.val()]);
  }

  $(document.body).on('init_checkout updated_checkout', initDatePicker);
});
