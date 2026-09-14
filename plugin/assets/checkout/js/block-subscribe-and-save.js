/**
 * FuelChef Subscriptions - Block checkout subscribe-and-save description
 *
 * The Additional Checkout Fields API has no description slot for a checkbox field, so
 * this adds the store's configured help text as a plain sibling element next to the
 * checkbox once it appears - the same DOM-watching approach block-delivery-date-field.js
 * uses for the same reason.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined') {
    return;
  }

  const SELECTOR = '[data-fcs-block-subscribe-and-save]';
  const description = window.fcsCheckout.subscribeSaveDescription;

  if (!description) {
    return;
  }

  function addDescription($checkbox) {
    // A checkbox field is rendered inside its own clickable <label>; inserting the
    // description as a sibling of that label, not inside it, keeps it out of the
    // clickable area instead of becoming part of the checkbox's own click target.
    const $label = $checkbox.closest('label');
    const $anchor = $label.length ? $label : $checkbox;

    if ($anchor.next('.fcs-subscribe-and-save-description').length) {
      return;
    }

    $anchor.after($('<p class="fcs-subscribe-and-save-description"></p>').text(description));
  }

  const observer = new MutationObserver(function () {
    const $checkbox = $(SELECTOR);

    if ($checkbox.length) {
      addDescription($checkbox);
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });

  const $initial = $(SELECTOR);
  if ($initial.length) {
    addDescription($initial);
  }
});
