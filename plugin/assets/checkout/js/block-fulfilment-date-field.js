/**
 * FuelChef Subscriptions - Block checkout fulfilment date field
 *
 * The field itself is registered as a native `select`, with every calendar date in the
 * store's lookahead window already a real, individually registered option (see
 * Frontend\Checkout\Block\Fulfilment_Date_Field for why: WooCommerce validates a
 * submission against exactly the registered `options` set, not against whatever
 * `<option>` elements a script injects afterwards - confirmed the hard way, a real order
 * placement failing with "is not one of ..." before this script settled on only ever
 * toggling `disabled` on pre-existing options, never adding, removing or relabelling one.
 * That is also why this script - unlike the classic field's own script - never attaches a
 * third-party widget to the field: an earlier version of this field used a
 * Flatpickr-enhanced text input here, and the calendar popup escaping React's own DOM
 * subtree, plus Flatpickr's `altInput` fighting the block's field wrapper, were real,
 * documented problems specific to this React-rendered surface. Toggling one boolean
 * attribute on an element React already rendered has none of that.
 *
 * The Checkout block re-renders its fields with React, not a full page/fragment
 * replace, so there is no `updated_checkout`-style event to hook. This watches the DOM
 * for the field's select appearing (or reappearing, if its visibility ever changes) and
 * fetches eligible dates whenever anything in the checkout form changes - shipping method
 * and address fields included - since none of those are reliably identifiable by a fixed
 * selector under the block checkout's own markup.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined') {
    return;
  }

  const SELECTOR = '[data-fcs-block-fulfilment-date]';
  const i18n = window.fcsCheckout.i18n;

  let currentSelect = null;
  let currentWindows = {};
  let refetchTimer = null;

  function windowCaption($select) {
    let $caption = $select.next('.fcs-fulfilment-date-window');

    if (!$caption.length) {
      $caption = $('<p class="fcs-fulfilment-date-window" aria-live="polite" hidden></p>');
      $select.after($caption);
    }

    return $caption;
  }

  function addDescription($select) {
    const description = window.fcsCheckout.fulfilmentDateDescription;

    if (!description || $select.siblings('.fcs-fulfilment-date-description').length) {
      return;
    }

    const descriptionId = 'fcsBlockFulfilmentDateDescription';
    $select.after($('<p class="fcs-fulfilment-date-description"></p>').attr('id', descriptionId).text(description));
    $select.attr('aria-describedby', descriptionId);
  }

  function updateWindowCaption($select) {
    const $caption = windowCaption($select);
    const fulfilmentWindow = currentWindows[$select.val()];

    if (!fulfilmentWindow) {
      $caption.attr('hidden', true);
      return;
    }

    $caption.text(i18n.fulfilmentWindow.replace('%1$s', fulfilmentWindow.start).replace('%2$s', fulfilmentWindow.end));
    $caption.removeAttr('hidden');
  }

  // Every real date option already exists (registered server-side); this only enables
  // the ones the customer's current destination is actually eligible for and disables
  // the rest, since a submission is validated against the full registered set regardless
  // of which options this leaves enabled. Group-heading options (value starting with
  // "__group_") are always left disabled - they are never a real date.
  function applyEligibility($select, dates, windows) {
    currentWindows = windows && typeof windows === 'object' ? windows : {};

    const eligible = {};
    (Array.isArray(dates) ? dates : []).forEach(function (date) {
      eligible[date] = true;
    });

    $select.find('option[value]').each(function () {
      const value = this.value;

      if (!value || value.indexOf('__group_') === 0) {
        return;
      }

      this.disabled = !eligible[value];
    });

    const $selectedOption = $select.find('option:selected');

    if ($selectedOption.length && $selectedOption.prop('disabled')) {
      $select.val('');
    }

    updateWindowCaption($select);
  }

  function refetchEligibleDates($select) {
    fetch(window.fcsCheckout.eligibleDatesUrl, { credentials: 'same-origin' })
      .then(function (response) {
        return response.ok ? response.json() : { hasSchedule: false, dates: [], windows: {} };
      })
      .then(function (data) {
        if (currentSelect && currentSelect.is($select)) {
          applyEligibility($select, data.dates, data.windows);
        }
      })
      .catch(function () {
        // Leave the select's current enabled/disabled state as-is; the next change retries.
      });
  }

  function queueRefetch() {
    if (!currentSelect) {
      return;
    }

    const $select = currentSelect;

    window.clearTimeout(refetchTimer);
    refetchTimer = window.setTimeout(function () {
      refetchEligibleDates($select);
    }, 400);
  }

  // Group-heading options have no way to register as `disabled` server-side - the
  // Additional Checkout Fields API's `select` options schema is only {value, label} - so
  // this is the one thing attach() must set once itself, before any eligibility data
  // exists, rather than leaving to applyEligibility().
  function disableGroupHeadings($select) {
    $select.find('option[value^="__group_"]').prop('disabled', true);
  }

  function attach($select) {
    currentSelect = $select;

    disableGroupHeadings($select);
    addDescription($select);
    $select.on('change', function () {
      updateWindowCaption($select);
    });

    refetchEligibleDates($select);
  }

  const observer = new MutationObserver(function () {
    const $select = $(SELECTOR);

    if ($select.length && (!currentSelect || $select[0] !== currentSelect[0])) {
      attach($select);
    } else if (!$select.length && currentSelect) {
      currentSelect = null;
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
