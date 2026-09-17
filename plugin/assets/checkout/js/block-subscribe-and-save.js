/**
 * FuelChef Subscriptions - Block checkout subscribe-and-save
 *
 * Two responsibilities, both because the Additional Checkout Fields API's checkbox type
 * is a plain field with no support for either:
 *
 * - Adds the store's configured help text as a plain sibling element next to the
 *   checkbox once it appears - the same DOM-watching approach block-fulfilment-date-field.js
 *   uses for the same reason, since there is no description slot to fill.
 * - Reports the checkbox's value to the server through the Store API's own documented
 *   `extensionCartUpdate()` mechanism (see Block\Subscribe_And_Save::
 *   register_update_callback()), so checking or unchecking it updates the order summary
 *   totals immediately - the same way changing the shipping method already does, and
 *   without which the discount would only ever show up after placing the order.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined') {
    return;
  }

  const SELECTOR = '[data-fcs-block-subscribe-and-save]';
  const NAMESPACE = 'fuelchef-subscriptions/subscribe-and-save';
  const description = window.fcsCheckout.subscribeSaveDescription;

  let hasReset = false;

  function syncChecked(checked) {
    if (
      !window.wc ||
      !window.wc.blocksCheckout ||
      typeof window.wc.blocksCheckout.extensionCartUpdate !== 'function'
    ) {
      return;
    }

    window.wc.blocksCheckout.extensionCartUpdate({
      namespace: NAMESPACE,
      data: { checked }
    });
  }

  function addDescription($checkbox) {
    if (!description) {
      return;
    }

    // A checkbox field is rendered inside its own clickable <label>; inserting the
    // description as a sibling of that label, not inside it, keeps it out of the
    // clickable area instead of becoming part of the checkbox's own click target.
    const $label = $checkbox.closest('label');
    const $anchor = $label.length ? $label : $checkbox;

    if ($anchor.next('.fcs-subscribe-and-save-description').length) {
      return;
    }

    const descriptionId = 'fcsBlockSubscribeAndSaveDescription';
    $anchor.after($('<p class="fcs-subscribe-and-save-description"></p>').attr('id', descriptionId).text(description));
    $checkbox.attr('aria-describedby', descriptionId);
  }

  function bind($checkbox) {
    addDescription($checkbox);

    // Subscribe & Save must always start unchecked, with no discount applied, on every
    // fresh page view. This runs once, the first time the checkbox appears - not at
    // script load, since window.wc.blocksCheckout may not exist yet at that point - so
    // a session value left over from an earlier, abandoned attempt at the same cart
    // never lingers into a totals preview the customer never asked for.
    if (!hasReset) {
      hasReset = true;
      syncChecked(false);
    }

    if ($checkbox.data('fcsBound')) {
      return;
    }

    $checkbox.data('fcsBound', true).on('change', function () {
      syncChecked(this.checked);
    });
  }

  const observer = new MutationObserver(function () {
    const $checkbox = $(SELECTOR);

    if ($checkbox.length) {
      bind($checkbox);
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });

  const $initial = $(SELECTOR);
  if ($initial.length) {
    bind($initial);
  }
});
