/**
 * FuelChef Subscriptions - Block checkout subscribe-and-save
 *
 * Three responsibilities, all because the Additional Checkout Fields API's checkbox type
 * is a plain field with no support for any of them:
 *
 * - Adds the store's configured help text as a plain sibling element next to the
 *   checkbox once it appears - the same DOM-watching approach block-fulfilment-date-field.js
 *   uses for the same reason, since there is no description slot to fill.
 * - Reports the checkbox's value to the server through the Store API's own documented
 *   `extensionCartUpdate()` mechanism (see Block\Subscribe_And_Save::
 *   register_update_callback()), so checking or unchecking it updates the order summary
 *   totals immediately - the same way changing the shipping method already does, and
 *   without which the discount would only ever show up after placing the order.
 * - Swaps the checkbox for the store's ineligible message when the live cart falls
 *   below the configured minimums, read reactively from the official `wc/store/cart`
 *   data store (window.wp.data / window.wc.wcBlocksData) rather than a REST round trip -
 *   the cart total and item count are already there on every store update.
 *   `Block\Subscribe_And_Save::apply_discount()` is the authoritative gate; this is only
 *   a live preview of the same rule.
 */

jQuery(function ($) {
  'use strict';

  if (typeof window.fcsCheckout === 'undefined') {
    return;
  }

  const SELECTOR = '[data-fcs-block-subscribe-and-save]';
  const NAMESPACE = 'fuelchef-subscriptions/subscribe-and-save';
  const description = window.fcsCheckout.subscribeSaveDescription;
  const minimumOrderAmount = window.fcsCheckout.minimumOrderAmount;
  const minimumCartQuantity = window.fcsCheckout.minimumCartQuantity;
  const ineligibleMessage = window.fcsCheckout.ineligibleMessage;

  let hasReset = false;
  let lastEligible = null;

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

  // Reads the live cart subtotal and item quantity straight from the Cart block's own
  // data store - the same one the block Checkout itself renders totals from - so this
  // stays in sync with every cart change without polling or a REST fetch of its own.
  function isCartEligible() {
    const wcData = window.wc && window.wc.wcBlocksData;
    const wpData = window.wp && window.wp.data;

    if (!wcData || !wpData) {
      return true;
    }

    const store = wpData.select(wcData.cartStore);
    const totals = store.getCartTotals();
    const minorUnit = Math.pow(10, totals.currency_minor_unit || 0);
    const subtotal = parseInt(totals.total_items, 10) / minorUnit;
    const quantity = store.getCartData().itemsCount;

    if (minimumOrderAmount > 0 && subtotal < minimumOrderAmount) {
      return false;
    }

    if (minimumCartQuantity > 0 && quantity < minimumCartQuantity) {
      return false;
    }

    return true;
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

  function ineligibleMessageElement($checkbox) {
    const $anchor = $checkbox.closest('label').length ? $checkbox.closest('label') : $checkbox;
    let $message = $anchor.siblings('.fcs-subscribe-and-save-ineligible');

    if ($message.length) {
      return $message;
    }

    $message = $('<p class="fcs-subscribe-and-save-ineligible" hidden></p>').text(ineligibleMessage);
    $anchor.after($message);

    return $message;
  }

  function applyEligibility($checkbox, eligible) {
    const $anchor = $checkbox.closest('label').length ? $checkbox.closest('label') : $checkbox;
    const $description = $anchor.siblings('.fcs-subscribe-and-save-description');
    const $message = ineligibleMessageElement($checkbox);

    $anchor.toggle(eligible);
    $description.toggle(eligible);
    $message.toggle(!eligible);

    // A real click, not a direct .prop('checked', false) + synthetic change event:
    // this checkbox is a React-controlled element, and setting the DOM property
    // directly leaves React's own state still "checked" - confirmed empirically, it
    // gets silently restored on the next re-render. A native click reaches React's
    // event listener the same way a real user's click would.
    if (!eligible && $checkbox.is(':checked')) {
      $checkbox[0].click();
    }
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

    lastEligible = isCartEligible();
    applyEligibility($checkbox, lastEligible);

    if ($checkbox.data('fcsBound')) {
      return;
    }

    $checkbox.data('fcsBound', true).on('change', function () {
      syncChecked(this.checked);
    });
  }

  // The subscribe callback fires on every store action, not just a cart-total change -
  // recomputing eligibility is cheap, but re-touching the DOM on every keystroke
  // elsewhere on the page is not, so this only acts when the eligibility verdict itself
  // actually flips.
  function onCartStoreChange() {
    const $checkbox = $(SELECTOR);

    if (!$checkbox.length) {
      return;
    }

    const eligible = isCartEligible();

    if (eligible === lastEligible) {
      return;
    }

    lastEligible = eligible;
    applyEligibility($checkbox, eligible);
  }

  if (window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function') {
    window.wp.data.subscribe(onCartStoreChange);
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
