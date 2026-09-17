/**
 * FuelChef Subscriptions - Checkout shared helpers
 *
 * Every checkout enhancement script depends on this one, since it is also where
 * window.fcsCheckout's localized data is attached (see Frontend\Assets) - the one handle
 * guaranteed to load before any of them, whether or not a given script calls a helper
 * below. Currently only fulfilment-date-field.js's Flatpickr locale does. There is no JS
 * build step for this plugin's assets (see the root package.json), so this is a plain
 * script, exposing itself as window.fcsCheckoutShared.
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
