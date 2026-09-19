/**
 * FuelChef Subscriptions - Closures Page Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  function saveMessage(response, fallback) {
    return (response.data && response.data.message) ? response.data.message : fallback;
  }

  FCS.createCalendar({
    calendarEl: document.getElementById('globalCalendar'),
    monthLabelEl: document.getElementById('globalCalendarMonth'),
    summaryEl: document.getElementById('globalUnavailableList'),
    prevBtn: document.getElementById('calPrevMonth'),
    nextBtn: document.getElementById('calNextMonth'),
    popoverEl: document.getElementById('datePopover'),
    blackouts: window.fcsClosures.blackouts,
    emptyMessage: window.fcsAdmin.i18n.noGlobalClosures,
    onCreate: (date) => FCS.post('fcs_save_blackout', { date }).then((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotAddDate), 'error');
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onSave: (id, reason) => FCS.post('fcs_save_blackout', { blackout_id: id, reason }).then((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveNote), 'error');
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onRemove: (id) => FCS.post('fcs_delete_blackout', { blackout_id: id }).then((response) => response.success)
  });
});
