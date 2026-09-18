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
 * - Swaps the checkbox for the store's logged-out message (with a Log in link) when the
 *   customer has no account, or its ineligible message when the live cart falls below the
 *   configured minimums - the latter read reactively from the official `wc/store/cart`
 *   data store (window.wp.data / window.wc.wcBlocksData) rather than a REST round trip,
 *   since the cart total and item count are already there on every store update. Login
 *   state itself never changes without a full page reload, so it is only ever applied
 *   once, at bind time - `Block\Subscribe_And_Save::apply_discount()` is the authoritative
 *   gate either way; this is only a live preview of the same rule.
 * - Tells the customer which weekday future renewals will fall on, once both this
 *   checkbox and the fulfilment date field (block-fulfilment-date-field.js) have a
 *   value - the two fields are otherwise unaware of each other.
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
  const isLoggedIn = window.fcsCheckout.isLoggedIn;
  const loggedOutMessage = window.fcsCheckout.loggedOutMessage;
  const loginUrl = window.fcsCheckout.loginUrl;

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

    // .siblings(), not .next(): the ineligible/logged-out message elements can end up
    // inserted between the anchor and this description (each is added via the same
    // $anchor.after(), so insertion order determines final sibling order) - an
    // immediate-next-only check would miss an already-inserted description and duplicate
    // it on every MutationObserver tick.
    if ($anchor.siblings('.fcs-subscribe-and-save-description').length) {
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

  function loggedOutMessageElement($checkbox) {
    const $anchor = $checkbox.closest('label').length ? $checkbox.closest('label') : $checkbox;
    let $message = $anchor.siblings('.fcs-subscribe-and-save-logged-out');

    if ($message.length) {
      return $message;
    }

    $message = $('<p class="fcs-subscribe-and-save-logged-out" hidden></p>').text(`${loggedOutMessage} `);
    $message.append(
      $('<a class="fcs-subscribe-and-save-logged-out__link"></a>').attr('href', loginUrl).text(window.fcsCheckout.i18n.logIn)
    );
    $anchor.after($message);

    return $message;
  }

  // A real click, not a direct .prop('checked', false) + synthetic change event: this
  // checkbox is a React-controlled element, and setting the DOM property directly leaves
  // React's own state still "checked" - confirmed empirically, it gets silently restored
  // on the next re-render. A native click reaches React's event listener the same way a
  // real user's click would.
  function uncheckIfChecked($checkbox) {
    if ($checkbox.is(':checked')) {
      $checkbox[0].click();
    }
  }

  function applyEligibility($checkbox, eligible) {
    const $anchor = $checkbox.closest('label').length ? $checkbox.closest('label') : $checkbox;
    const $description = $anchor.siblings('.fcs-subscribe-and-save-description');
    const $message = ineligibleMessageElement($checkbox);

    $anchor.toggle(eligible);
    $description.toggle(eligible);
    $message.toggle(!eligible);

    if (!eligible) {
      uncheckIfChecked($checkbox);
    }
  }

  // Logged-out takes priority over cart eligibility - a guest doesn't need to know
  // whether their cart would otherwise qualify, only that they need an account first.
  function applyState($checkbox) {
    const $anchor = $checkbox.closest('label').length ? $checkbox.closest('label') : $checkbox;
    const $loggedOut = loggedOutMessageElement($checkbox);

    if (!isLoggedIn) {
      $anchor.hide();
      $anchor.siblings('.fcs-subscribe-and-save-description').hide();
      ineligibleMessageElement($checkbox).hide();
      $loggedOut.show();
      uncheckIfChecked($checkbox);
      return;
    }

    $loggedOut.hide();
    lastEligible = isCartEligible();
    applyEligibility($checkbox, lastEligible);
  }

  function recurringDayNoticeElement($checkbox) {
    const $anchor = ineligibleMessageElement($checkbox);
    let $notice = $anchor.siblings('.fcs-recurring-day-notice');

    if ($notice.length) {
      return $notice;
    }

    $notice = $('<p class="fcs-recurring-day-notice" aria-live="polite" hidden></p>');
    $anchor.after($notice);

    return $notice;
  }

  // Writes only when the notice's own text or visibility actually needs to change: this
  // runs on every bind() call, which itself runs on every MutationObserver tick, and an
  // unconditional .text() write is itself a childList mutation - one that would retrigger
  // the very observer that called this, looping forever. Confirmed the hard way, a real
  // browser tab crash once a date and the checkbox were both set.
  function updateRecurringDayNotice($checkbox) {
    if (typeof window.fcsCheckoutShared === 'undefined') {
      return;
    }

    const $notice = recurringDayNoticeElement($checkbox);
    const $dateField = $('[data-fcs-block-fulfilment-date]');
    const date = $dateField.length ? $dateField.val() : '';

    if (!date || !$checkbox.is(':checked')) {
      if (!$notice.prop('hidden')) {
        $notice.prop('hidden', true);
      }
      return;
    }

    const weekday = window.fcsCheckoutShared.weekdayNameForDate(date, window.fcsCheckout.i18n.dayNames);
    const message = window.fcsCheckout.i18n.recurringDayNotice.replace('%s', weekday);

    if ($notice.prop('hidden') || $notice.text() !== message) {
      $notice.text(message).prop('hidden', false);
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

    applyState($checkbox);
    updateRecurringDayNotice($checkbox);

    if ($checkbox.data('fcsBound')) {
      return;
    }

    $checkbox.data('fcsBound', true).on('change', function () {
      syncChecked(this.checked);
      updateRecurringDayNotice($checkbox);
    });

    // The fulfilment date field mounts and changes independently of this one; a
    // delegated listener catches it whether it appears before or after this checkbox.
    $(document.body).on('change', '[data-fcs-block-fulfilment-date]', function () {
      updateRecurringDayNotice($checkbox);
    });
  }

  // The subscribe callback fires on every store action, not just a cart-total change -
  // recomputing eligibility is cheap, but re-touching the DOM on every keystroke
  // elsewhere on the page is not, so this only acts when the eligibility verdict itself
  // actually flips. Login state is checked once at bind time and never changes without a
  // full page reload, so a logged-out customer's cart-total changes are ignored entirely.
  function onCartStoreChange() {
    const $checkbox = $(SELECTOR);

    if (!$checkbox.length || !isLoggedIn) {
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
