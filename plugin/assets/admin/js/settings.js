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
      FCS.setBusy(saveBtn, true);

      FCS.post('fcs_save_settings', {
        cutoff_days: document.getElementById('cutoffDays').value,
        cutoff_time: `${document.getElementById('cutoffTime').value}:00`,
        subscribe_discount_percent: document.getElementById('subscribeDiscountPercent').value,
        subscribe_applicability: document.getElementById('subscribeApplicability').value,
        max_fulfilment_window_days: document.getElementById('maxFulfilmentWindowDays').value,
        fulfilment_date_label: document.getElementById('fulfilmentDateLabel').value,
        fulfilment_date_description: document.getElementById('fulfilmentDateDescription').value,
        subscribe_save_label: document.getElementById('subscribeSaveLabel').value,
        subscribe_save_description: document.getElementById('subscribeSaveDescription').value,
        minimum_order_amount: document.getElementById('minimumOrderAmount').value,
        minimum_cart_quantity: document.getElementById('minimumCartQuantity').value,
        ineligible_message: document.getElementById('ineligibleMessage').value,
        logged_out_message: document.getElementById('loggedOutMessage').value,
        fulfilment_window_message: document.getElementById('fulfilmentWindowMessage').value
      }).done((response) => {
        if (!response.success) {
          FCS.toast(response.data && response.data.message ? response.data.message : window.fcsAdmin.i18n.couldNotSaveSettings, 'error');
          return;
        }
        FCS.State.markClean();
        FCS.toast(window.fcsAdmin.i18n.settingsSaved);
      }).always(() => FCS.setBusy(saveBtn, false));
    });
  }

  // Track inputs for changes
  document.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('change', () => FCS.State.markDirty());
  });
});

