/**
 * FuelChef Subscriptions - Checkout shared helpers
 *
 * Small, DOM-independent helpers reused by both the classic and block checkout
 * enhancement scripts. There is no JS build step for this plugin's assets (see the root
 * package.json), so this is a plain script, enqueued as a dependency of both, exposing
 * itself as window.fcsCheckoutShared the same way window.fcsCheckout already carries this
 * plugin's localized data.
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

  return {
    buildFlatpickrLocale: buildFlatpickrLocale
  };
})();
