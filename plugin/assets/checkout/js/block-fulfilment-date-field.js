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
 * The Checkout block re-renders its fields with React, not a full page/fragment replace,
 * so there is no `updated_checkout`-style event to hook; a MutationObserver is still the
 * right tool for noticing the select mount or remount. Re-fetching eligible dates,
 * though, reacts to the destination the customer's shipping actually resolved to -
 * address and chosen rate - read reactively from the Cart block's own `wc/store/cart`
 * data store (window.wp.data / window.wc.wcBlocksData), the same store the Checkout
 * block itself renders shipping and totals from, rather than guessing from a blanket
 * DOM listener.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined') {
    return;
  }

  const SELECTOR = '[data-fcs-block-fulfilment-date]';
  const WRAPPER_SELECTOR = '.wc-block-components-select-input';
  const i18n = window.fcsCheckout.i18n;

  let currentSelect = null;
  let currentWindows = {};
  let lastDestinationKey = null;
  let refetchTimer = null;

  function fieldWrapper($select) {
    const $wrapper = $select.closest(WRAPPER_SELECTOR);

    return $wrapper.length ? $wrapper : $select;
  }

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

  // Writes only when the caption's own text or visibility actually needs to change - a
  // MutationObserver elsewhere (block-subscribe-and-save.js) watches this same subtree,
  // and an unconditional .text() write is itself a mutation that would retrigger it on
  // every tick regardless of whether anything really changed.
  function updateWindowCaption($select) {
    const $caption = windowCaption($select);
    const fulfilmentWindow = currentWindows[$select.val()];

    if (!fulfilmentWindow) {
      if (!$caption.prop('hidden')) {
        $caption.prop('hidden', true);
      }
      return;
    }

    const text = i18n.fulfilmentWindow.replace('%1$s', fulfilmentWindow.start).replace('%2$s', fulfilmentWindow.end);

    if ($caption.prop('hidden') || $caption.text() !== text) {
      $caption.text(text).prop('hidden', false);
    }
  }

  // Every real date option already exists (registered server-side); this only enables
  // the ones the customer's current destination is actually eligible for and disables
  // the rest, since a submission is validated against the full registered set regardless
  // of which options this leaves enabled. Group-heading options (value starting with
  // "__group_") are always left disabled - they are never a real date. The whole field
  // hides, rather than merely disabling every option, once nothing is resolved yet or
  // the resolved destination has no schedule - matching classic checkout's own field,
  // which never renders at all in that case.
  function applyEligibility($select, hasSchedule, dates, windows) {
    fieldWrapper($select).toggle( !! hasSchedule );

    if ( ! hasSchedule ) {
      return;
    }

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
          applyEligibility($select, data.hasSchedule, data.dates, data.windows);
        }
      })
      .catch(function () {
        // Leave the select's current enabled/disabled state as-is; the next change retries.
      });
  }

  // The destination a chosen rate resolves to (see Chosen_Shipping_Destination on the
  // PHP side) is exactly what an address change or a different chosen rate can affect;
  // reading it from the cart store's own package data - rather than the raw, possibly
  // incomplete address fields - matches what the server actually keys eligibility on.
  function currentDestinationKey() {
    const wcData = window.wc && window.wc.wcBlocksData;
    const wpData = window.wp && window.wp.data;

    if (!wcData || !wpData) {
      return null;
    }

    const packages = wpData.select(wcData.cartStore).getShippingRates();
    const destination = packages[0] && packages[0].destination;
    const selectedRate = packages[0] && packages[0].shipping_rates
      ? packages[0].shipping_rates.find(function (rate) { return rate.selected; })
      : null;

    return JSON.stringify([
      destination ? [destination.country, destination.state, destination.city, destination.postcode] : null,
      selectedRate ? selectedRate.rate_id : null
    ]);
  }

  // The store fires on every action, not just a settled destination change, and a single
  // customer edit can produce several in quick succession while things settle - debounced
  // so a burst collapses into one check (and, if the destination actually differs, one
  // fetch) against the destination's final state, rather than one per tick.
  function refetchIfDestinationChanged() {
    if (!currentSelect) {
      return;
    }

    window.clearTimeout(refetchTimer);
    refetchTimer = window.setTimeout(function () {
      const key = currentDestinationKey();

      if (null === key || key === lastDestinationKey) {
        return;
      }

      lastDestinationKey = key;
      refetchEligibleDates(currentSelect);
    }, 300);
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
    lastDestinationKey = currentDestinationKey();

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

  if (window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function') {
    window.wp.data.subscribe(refetchIfDestinationChanged);
  }

  const $initial = $(SELECTOR);
  if ($initial.length) {
    attach($initial);
  }
});
