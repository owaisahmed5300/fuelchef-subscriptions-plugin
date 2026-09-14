/**
 * FuelChef Subscriptions - Settings Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  FCS.State.init();
  FCS.initTabs();

  // Save Settings
  const saveBtn = document.getElementById('saveSettingsBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', (e) => {
      e.preventDefault();

      FCS.post('fcs_save_settings', {
        cutoff_days: document.getElementById('cutoffDays').value,
        cutoff_time: `${document.getElementById('cutoffTime').value}:00`,
        subscribe_discount_percent: document.getElementById('subscribeDiscountPercent').value,
        subscribe_applicability: document.getElementById('subscribeApplicability').value,
        max_delivery_window_days: document.getElementById('maxDeliveryWindowDays').value,
        delivery_date_label: document.getElementById('deliveryDateLabel').value,
        delivery_date_description: document.getElementById('deliveryDateDescription').value,
        subscribe_save_label: document.getElementById('subscribeSaveLabel').value,
        subscribe_save_description: document.getElementById('subscribeSaveDescription').value
      }).done((response) => {
        if (!response.success) {
          FCS.toast(response.data && response.data.message ? response.data.message : window.fcsAdmin.i18n.couldNotSaveSettings);
          return;
        }
        FCS.State.markClean();
        FCS.toast(window.fcsAdmin.i18n.settingsSaved);
      });
    });
  }

  // Global Blackouts Calendar
  FCS.createCalendar({
    calendarEl: document.getElementById('globalCalendar'),
    monthLabelEl: document.getElementById('globalCalendarMonth'),
    summaryEl: document.getElementById('globalUnavailableList'),
    prevBtn: document.getElementById('calPrevMonth'),
    nextBtn: document.getElementById('calNextMonth'),
    popoverEl: document.getElementById('datePopover'),
    blackouts: window.fcsSettings.blackouts,
    emptyMessage: window.fcsAdmin.i18n.noGlobalClosures,
    onCreate: (date) => FCS.post('fcs_save_blackout', { date }).then((response) => {
      if (!response.success) {
        FCS.toast(response.data && response.data.message ? response.data.message : window.fcsAdmin.i18n.couldNotAddDate);
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onSave: (id, reason) => FCS.post('fcs_save_blackout', { blackout_id: id, reason }).then((response) => {
      if (!response.success) {
        FCS.toast(response.data && response.data.message ? response.data.message : window.fcsAdmin.i18n.couldNotSaveNote);
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onRemove: (id) => FCS.post('fcs_delete_blackout', { blackout_id: id }).then((response) => response.success)
  });

  // Track inputs for changes
  document.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('change', () => FCS.State.markDirty());
  });
});
