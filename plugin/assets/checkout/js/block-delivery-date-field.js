/**
 * FuelChef Subscriptions - Block checkout delivery date field
 *
 * The Checkout block re-renders its fields with React, not a full page/fragment
 * replace, so there is no `updated_checkout`-style event to hook. This watches the DOM
 * for the field's input appearing (or reappearing, if its visibility ever changes) and
 * attaches flatpickr to it, refetching eligible dates whenever anything in the checkout
 * form changes - shipping method and address fields included - since none of those are
 * reliably identifiable by a fixed selector under the block checkout's own markup.
 *
 * Deliberately does not use flatpickr's `altInput` mode here, unlike the classic
 * checkout field: altInput inserts a second, brand-new <input> next to the original and
 * hides the original - which works cleanly in classic checkout's own template, but here
 * the original input is one the Checkout block itself rendered and wraps with its own
 * label and sizing. Disconnecting that with a foreign sibling element is what caused the
 * field to render too narrow with its label overlapping the placeholder text. Attaching
 * directly to the block's own input, with no DOM changes around it, keeps its native
 * width and label behaviour intact.
 *
 * `appendTo: document.body` (below) is a second, separate fix for the calendar popup
 * itself: by default flatpickr inserts `.flatpickr-calendar` as a plain sibling of the
 * input, inside the exact subtree React renders and reconciles for this field. React does
 * not know about that foreign node - any re-render of this field's surroundings (which
 * WooCommerce Blocks triggers often, e.g. on totals or validation state changes elsewhere
 * on the page) can discard or detach it, which is what made the calendar fail to open, or
 * open and immediately vanish. Rendering it as a direct child of <body> instead keeps it
 * entirely outside any subtree the Checkout block manages.
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

  const SELECTOR = '[data-fcs-block-delivery-date]';
  const i18n = window.fcsCheckout.i18n;
  const locale = window.fcsCheckoutShared.buildFlatpickrLocale(i18n, window.fcsCheckout.startOfWeek);

  let instance = null;
  let refetchTimer = null;
  let currentWindows = {};

  function windowCaption($input) {
    let $caption = $input.next('.fcs-delivery-date-window');

    if (!$caption.length) {
      $caption = $('<p class="fcs-delivery-date-window" aria-live="polite" hidden></p>');
      $input.after($caption);
    }

    return $caption;
  }

  function addDescription($input) {
    const description = window.fcsCheckout.deliveryDateDescription;

    if (!description || $input.siblings('.fcs-delivery-date-description').length) {
      return;
    }

    $input.after($('<p class="fcs-delivery-date-description"></p>').text(description));
  }

  function updateWindowCaption() {
    if (!instance) {
      return;
    }

    const $caption = windowCaption($(instance.input));
    const deliveryWindow = currentWindows[instance.input.value];

    if (!deliveryWindow) {
      $caption.attr('hidden', true);
      return;
    }

    $caption.text(i18n.deliveryWindow.replace('%1$s', deliveryWindow.start).replace('%2$s', deliveryWindow.end));
    $caption.removeAttr('hidden');
  }

  function refetchEligibleDates() {
    if (!instance) {
      return;
    }

    fetch(window.fcsCheckout.eligibleDatesUrl, { credentials: 'same-origin' })
      .then(function (response) {
        return response.ok ? response.json() : { dates: [], windows: {} };
      })
      .then(function (data) {
        if (instance) {
          instance.set('enable', Array.isArray(data.dates) ? data.dates : []);
          currentWindows = data.windows && typeof data.windows === 'object' ? data.windows : {};
          updateWindowCaption();
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

    // No placeholder to set here: the field's own registered label already serves as
    // its placeholder, per the Additional Checkout Fields API's own documented
    // behaviour - setting a second one would fight the block's own rendering of it.
    instance = window.flatpickr($input[0], {
      dateFormat: 'Y-m-d',
      enable: [],
      locale,
      disableMobile: true,
      appendTo: document.body,
      onChange: updateWindowCaption
    });

    // Order matters: windowCaption() creates its element first so addDescription()'s
    // insertion (also right after the input) pushes it below, keeping the static
    // description above the per-date availability caption - matching classic checkout.
    windowCaption($input);
    addDescription($input);

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
