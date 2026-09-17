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

  return {
    buildFlatpickrLocale: buildFlatpickrLocale,
    weekdayNameForDate: weekdayNameForDate
  };
})();
