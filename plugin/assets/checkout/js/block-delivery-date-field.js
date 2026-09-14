/**
 * FuelChef Subscriptions - Block checkout delivery date field
 *
 * The Checkout block re-renders its fields with React, not a full page/fragment
 * replace, so there is no `updated_checkout`-style event to hook. This watches the DOM
 * for the field's input appearing (or reappearing, if its visibility ever changes) and
 * attaches flatpickr to it, refetching eligible dates whenever anything in the checkout
 * form changes - shipping method and address fields included - since none of those are
 * reliably identifiable by a fixed selector under the block checkout's own markup.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined' || typeof window.flatpickr === 'undefined') {
    return;
  }

  const SELECTOR = '[data-fcs-block-delivery-date]';
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
  let refetchTimer = null;

  function refetchEligibleDates() {
    if (!instance) {
      return;
    }

    fetch(window.fcsCheckout.eligibleDatesUrl, { credentials: 'same-origin' })
      .then(function (response) {
        return response.ok ? response.json() : { dates: [] };
      })
      .then(function (data) {
        if (instance) {
          instance.set('enable', Array.isArray(data.dates) ? data.dates : []);
        }
      })
      .catch(function () {
        // Leave the picker's current date list as-is; the next change event retries.
      });
  }

  function queueRefetch() {
    window.clearTimeout(refetchTimer);
    refetchTimer = window.setTimeout(refetchEligibleDates, 400);
  }

  function attach($input) {
    if (instance) {
      instance.destroy();
      instance = null;
    }

    instance = window.flatpickr($input[0], {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'F j, Y',
      altInputClass: 'fcs-delivery-date-input__display',
      enable: [],
      locale,
      disableMobile: true
    });

    instance.altInput.setAttribute('placeholder', i18n.chooseDate);
    refetchEligibleDates();
  }

  const observer = new MutationObserver(function () {
    const $input = $(SELECTOR);

    if ($input.length && $input[0] !== (instance ? instance.input : null)) {
      attach($input);
    } else if (!$input.length && instance) {
      instance.destroy();
      instance = null;
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });

  // The block checkout's own form/field class names aren't a stable contract to target,
  // so this listens broadly rather than risking a selector that silently never matches.
  $(document.body).on('change input', queueRefetch);

  const $initial = $(SELECTOR);
  if ($initial.length) {
    attach($initial);
  }
});
