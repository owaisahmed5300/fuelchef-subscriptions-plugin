/**
 * FuelChef Subscriptions - Checkout shared helpers
 *
 * Every checkout enhancement script depends on this one, since it is also where
 * window.fcsCheckout's localized data is attached (see Frontend\Assets) - the one handle
 * guaranteed to load before any of them, whether or not a given script calls a helper
 * below. There is no JS build step for this plugin's assets (see the root package.json),
 * so this is a plain script, exposing itself as window.fcsCheckoutShared.
 */

window.fcsCheckoutShared = (function () {
  'use strict';

  /**
   * Builds the flatpickr `locale` option from this plugin's own localized strings -
   * identical for classic and block checkout's own date pickers.
   */
  function buildFlatpickrLocale(i18n, startOfWeek) {
    return {
      firstDayOfWeek: startOfWeek,
      weekdays: {
        shorthand: i18n.dayNamesShort,
        longhand: i18n.dayNames
      },
      months: {
        shorthand: i18n.monthNamesShort,
        longhand: i18n.monthNames
      }
    };
  }

  /**
   * The full weekday name a `Y-m-d` date string falls on, using the site's translated
   * day names. Parses the parts directly rather than through `Date.parse()`, which reads
   * a bare `Y-m-d` string as UTC midnight and can land on the wrong local day.
   */
  function weekdayNameForDate(dateStr, dayNames) {
    const parts = dateStr.split('-').map(Number);
    const date = new Date(parts[0], parts[1] - 1, parts[2]);

    return dayNames[date.getDay()];
  }

  /**
   * A `Y-m-d` date string as a readable "day Month year" string (e.g. "14 October 2026"),
   * using the site's translated month names - the same parsing approach as
   * weekdayNameForDate() above, for the same reason.
   */
  function formatDisplayDate(dateStr, monthNames) {
    const parts = dateStr.split('-').map(Number);

    return `${parts[2]} ${monthNames[parts[1] - 1]} ${parts[0]}`;
  }

  /**
   * Whether a WooCommerce shipping rate ID belongs to a pickup location rather than a
   * shipping zone - the same rule `Chosen_Shipping_Destination_Service` uses server-side,
   * via the prefix it localizes as `fcsCheckout.pickupRatePrefix`.
   */
  function isPickupRateId(rateId) {
    const prefix = window.fcsCheckout && window.fcsCheckout.pickupRatePrefix;

    return typeof rateId === 'string' && typeof prefix === 'string' && rateId.indexOf(prefix) === 0;
  }

  /**
   * The rate the Checkout block's own cart store currently shows as selected, or null
   * before one is chosen or outside block checkout entirely. Shared by
   * block-fulfilment-date-field.js and block-subscribe-and-save.js, which both need to
   * know the customer's current destination.
   */
  function currentSelectedRateId() {
    const wcData = window.wc && window.wc.wcBlocksData;
    const wpData = window.wp && window.wp.data;

    if (!wcData || !wpData) {
      return null;
    }

    const packages = wpData.select(wcData.cartStore).getShippingRates();
    const selectedRate = packages[0] && packages[0].shipping_rates
      ? packages[0].shipping_rates.find(function (rate) { return rate.selected; })
      : null;

    return selectedRate ? selectedRate.rate_id : null;
  }

  /**
   * The "your order will be delivered/ready for pickup" notice text for a chosen date,
   * picking the delivery or pickup wording and the one-off or recurring form. Shared by
   * classic checkout's delivery-notice.js and block checkout's block-subscribe-and-save.js,
   * which otherwise built this same message independently.
   */
  function deliveryNoticeMessage(isPickup, isRecurring, dateStr, i18n) {
    const formattedDate = formatDisplayDate(dateStr, i18n.monthNames);

    if (isRecurring) {
      const template = isPickup ? i18n.recurringPickupNotice : i18n.recurringDeliveryNotice;

      return template
        .replace('%1$s', weekdayNameForDate(dateStr, i18n.dayNames))
        .replace('%2$s', formattedDate);
    }

    const template = isPickup ? i18n.singlePickupNotice : i18n.singleDeliveryNotice;

    return template.replace('%s', formattedDate);
  }

  return {
    buildFlatpickrLocale: buildFlatpickrLocale,
    weekdayNameForDate: weekdayNameForDate,
    formatDisplayDate: formatDisplayDate,
    isPickupRateId: isPickupRateId,
    currentSelectedRateId: currentSelectedRateId,
    deliveryNoticeMessage: deliveryNoticeMessage
  };
})();
