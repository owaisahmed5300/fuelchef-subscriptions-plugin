/**
 * FuelChef Subscriptions - Schedules Page Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  FCS.State.init();
  FCS.initTabs();

  const data = window.fcsSchedulesData;
  const dayNames = window.fcsAdmin.i18n.dayNames;

  const tableBody = document.getElementById('weekdayRows');
  const titleInput = document.getElementById('scheduleTitle');

  function saveMessage(response, fallback) {
    return (response.data && response.data.message) ? response.data.message : fallback;
  }

  // Weekly fulfilment days
  const DOWN_ARROW_ICON = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3v9M4 8.5 8 12.5 12 8.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  function renderDays() {
    if (!tableBody) return;
    tableBody.innerHTML = '';

    data.weekdays.forEach((day, index) => {
      const isLast = index === data.weekdays.length - 1;

      const tr = document.createElement('tr');
      tr.className = 'fcs-weekday-row';
      tr.dataset.dayOfWeek = day.day_of_week;
      tr.innerHTML = `
        <td class="fcs-weekday-toggle">
          <label class="fcs-switch-wrap">
            <span class="fcs-switch">
              <input type="checkbox" class="day-enable-check" ${day.enabled ? 'checked' : ''}>
              <span class="fcs-switch-slider"></span>
            </span>
            <span class="fcs-weekday-name">${FCS.escapeHtml(dayNames[day.day_of_week])}</span>
          </label>
        </td>
        <td class="fcs-weekday-status">
          ${day.enabled
            ? `<span class="fcs-pill-badge fcs-pill-badge--success"><span class="fcs-status-dot"></span> ${FCS.escapeHtml(window.fcsAdmin.i18n.dayActive)}</span>`
            : `<span class="fcs-pill-badge fcs-pill-badge--muted">${FCS.escapeHtml(window.fcsAdmin.i18n.dayClosed)}</span>`}
        </td>
        <td>
          <div class="fcs-weekday-time-ctrl" style="${day.enabled ? '' : 'display:none;'}">
            <div class="fcs-weekday-time-field">
              <label for="weekday${day.day_of_week}StartTime">${FCS.escapeHtml(window.fcsAdmin.i18n.scheduleStart)}</label>
              <input id="weekday${day.day_of_week}StartTime" type="time" class="fcs-input fcs-day-time-input" data-role="start" value="${FCS.escapeHtml(day.start_time.slice(0, 5))}" aria-describedby="weekday${day.day_of_week}TimeError">
            </div>
            <span class="fcs-weekday-time-sep" aria-hidden="true">&ndash;</span>
            <div class="fcs-weekday-time-field">
              <label for="weekday${day.day_of_week}EndTime">${FCS.escapeHtml(window.fcsAdmin.i18n.scheduleEnd)}</label>
              <input id="weekday${day.day_of_week}EndTime" type="time" class="fcs-input fcs-day-time-input" data-role="end" value="${FCS.escapeHtml(day.end_time.slice(0, 5))}" aria-describedby="weekday${day.day_of_week}TimeError">
            </div>
            ${isLast ? '' : `
              <button type="button" class="fcs-btn fcs-btn--icon fcs-copy-down-btn" title="${FCS.escapeHtml(window.fcsAdmin.i18n.copyToDaysBelow)}" aria-label="${FCS.escapeHtml(window.fcsAdmin.i18n.copyToDaysBelow)}">
                ${DOWN_ARROW_ICON}
              </button>
            `}
          </div>
          <p class="fcs-weekday-time-error" id="weekday${day.day_of_week}TimeError" role="alert" hidden></p>
        </td>
      `;

      const checkbox = tr.querySelector('.day-enable-check');
      const startInput = tr.querySelector('[data-role="start"]');
      const endInput = tr.querySelector('[data-role="end"]');
      const errorEl = tr.querySelector('.fcs-weekday-time-error');
      const copyBtn = tr.querySelector('.fcs-copy-down-btn');

      const showError = (message) => {
        errorEl.textContent = message;
        errorEl.hidden = false;
        startInput.classList.add('fcs-input--error');
        endInput.classList.add('fcs-input--error');
        startInput.setAttribute('aria-invalid', 'true');
        endInput.setAttribute('aria-invalid', 'true');
      };

      const clearError = () => {
        errorEl.hidden = true;
        startInput.classList.remove('fcs-input--error');
        endInput.classList.remove('fcs-input--error');
        startInput.removeAttribute('aria-invalid');
        endInput.removeAttribute('aria-invalid');
      };

      // No AJAX here - a toggle or time change only updates the in-memory model and marks
      // the screen dirty. Every pending change across every day is only ever sent together,
      // when the admin clicks "Save Schedule" below, instead of one request per field.
      const validateTimes = () => {
        if (checkbox.checked && startInput.value && endInput.value && endInput.value <= startInput.value) {
          showError(window.fcsAdmin.i18n.endBeforeStart);
          return false;
        }

        clearError();
        return true;
      };

      checkbox.addEventListener('change', () => {
        day.enabled = checkbox.checked;
        renderDays();
        FCS.State.markDirty();
      });

      startInput.addEventListener('change', () => {
        day.start_time = `${startInput.value}:00`;
        validateTimes();
        FCS.State.markDirty();
      });

      endInput.addEventListener('change', () => {
        day.end_time = `${endInput.value}:00`;
        validateTimes();
        FCS.State.markDirty();
      });

      copyBtn?.addEventListener('click', () => {
        FCS.post('fcs_copy_schedule_weekday', {
          schedule_id: data.selectedId,
          day_of_week: day.day_of_week
        }).done((response) => {
          if (!response.success) {
            FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotCopySchedule), 'error');
            return;
          }

          const updatedDays = response.data.weekdays.map((updated) => updated.day_of_week);

          response.data.weekdays.forEach((updated) => {
            const target = data.weekdays.find((d) => d.day_of_week === updated.day_of_week);
            if (target) {
              target.enabled = updated.enabled;
              target.start_time = updated.start_time;
              target.end_time = updated.end_time;
            }
          });

          renderDays();

          updatedDays.forEach((dayOfWeek) => {
            const row = tableBody.querySelector(`tr[data-day-of-week="${dayOfWeek}"]`);
            row?.classList.add('fcs-weekday-row--copied');
            window.setTimeout(() => row?.classList.remove('fcs-weekday-row--copied'), 1200);
          });

          FCS.toast(window.fcsAdmin.i18n.scheduleCopied);
        });
      });

      tableBody.appendChild(tr);
    });
  }

  // No ajax here - a name edit only marks the screen dirty, like a weekday toggle or time
  // change. It is sent, along with every other pending edit, only when the admin clicks
  // "Save Schedule" below.
  if (titleInput) {
    titleInput.addEventListener('input', () => FCS.State.markDirty());
  }

  function weekdaysPayload() {
    const payload = { schedule_id: data.selectedId };

    data.weekdays.forEach((day, i) => {
      payload[`weekdays[${i}][day_of_week]`] = day.day_of_week;
      payload[`weekdays[${i}][enabled]`] = day.enabled ? 1 : '';
      payload[`weekdays[${i}][start_time]`] = day.start_time;
      payload[`weekdays[${i}][end_time]`] = day.end_time;
    });

    return payload;
  }

  document.getElementById('saveScheduleBtn')?.addEventListener('click', (event) => {
    if (tableBody?.querySelector('.fcs-weekday-time-error:not([hidden])')) {
      FCS.toast(window.fcsAdmin.i18n.endBeforeStart, 'error');
      return;
    }

    const trigger = event.currentTarget;
    FCS.setBusy(trigger, true);

    jQuery.when(
      FCS.post('fcs_save_schedule', { schedule_id: data.selectedId, name: titleInput.value }),
      FCS.post('fcs_save_schedule_weekdays', weekdaysPayload()),
      FCS.post('fcs_save_schedule_destinations', destinationsPayload())
    ).done((nameResult, weekdaysResult, destinationsResult) => {
      const [nameResponse] = nameResult;
      const [weekdaysResponse] = weekdaysResult;
      const [destinationsResponse] = destinationsResult;

      if (!nameResponse.success) {
        FCS.toast(saveMessage(nameResponse, window.fcsAdmin.i18n.couldNotSaveScheduleName), 'error');
        return;
      }

      if (!weekdaysResponse.success) {
        FCS.toast(saveMessage(weekdaysResponse, window.fcsAdmin.i18n.couldNotSaveDay), 'error');
        return;
      }

      if (!destinationsResponse.success) {
        FCS.toast(saveMessage(destinationsResponse, window.fcsAdmin.i18n.couldNotSaveDestinations), 'error');
        return;
      }

      weekdaysResponse.data.weekdays.forEach((updated) => {
        const target = data.weekdays.find((d) => d.day_of_week === updated.day_of_week);
        if (target) {
          target.enabled = updated.enabled;
          target.start_time = updated.start_time;
          target.end_time = updated.end_time;
        }
      });

      renderDays();
      FCS.State.markClean();
      FCS.toast(window.fcsAdmin.i18n.scheduleSaved);
    }).always(() => FCS.setBusy(trigger, false));
  });

  // Add a schedule
  const addOverlay = document.getElementById('addScheduleModalOverlay');
  const addModal = addOverlay?.querySelector('.fcs-modal');
  const newScheduleNameInput = document.getElementById('newScheduleName');
  let addModalTrigger = null;

  function openAddScheduleModal() {
    if (!addOverlay) return;
    addModalTrigger = document.activeElement;
    if (newScheduleNameInput) newScheduleNameInput.value = '';
    addOverlay.classList.add('fcs-overlay--show');
    newScheduleNameInput?.focus();
  }

  function closeAddScheduleModal() {
    if (!addOverlay) return;
    addOverlay.classList.remove('fcs-overlay--show');

    if (addModalTrigger && typeof addModalTrigger.focus === 'function') addModalTrigger.focus();
    addModalTrigger = null;
  }

  function submitAddSchedule() {
    const name = newScheduleNameInput?.value.trim();
    if (!name) return;

    FCS.post('fcs_save_schedule', { name }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotCreateSchedule), 'error');
        return;
      }
      window.location.href = `${data.baseUrl}&schedule_id=${response.data.id}`;
    });
  }

  if (addModal) {
    addModal.addEventListener('keydown', (event) => {
      // The document-level Escape handler (FCS.closeTransientUI) only hides the modal;
      // going through closeAddScheduleModal() here also returns focus to what opened it.
      if (event.key === 'Escape') {
        closeAddScheduleModal();
        return;
      }

      if (event.key === 'Enter' && event.target === newScheduleNameInput) {
        event.preventDefault();
        submitAddSchedule();
        return;
      }

      FCS.trapFocus(addModal, event);
    });
  }

  document.getElementById('addScheduleBtn')?.addEventListener('click', openAddScheduleModal);
  document.getElementById('cancelAddScheduleBtn')?.addEventListener('click', closeAddScheduleModal);
  document.getElementById('confirmAddScheduleBtn')?.addEventListener('click', submitAddSchedule);

  // Delete modal
  const overlay = document.getElementById('deleteModalOverlay');
  const deleteModal = overlay?.querySelector('.fcs-modal');
  const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
  let deleteModalTrigger = null;

  function openDeleteModal() {
    if (!overlay) return;
    deleteModalTrigger = document.activeElement;
    overlay.classList.add('fcs-overlay--show');
    cancelDeleteBtn?.focus();
  }

  function closeDeleteModal() {
    if (!overlay) return;
    overlay.classList.remove('fcs-overlay--show');

    if (deleteModalTrigger && typeof deleteModalTrigger.focus === 'function') deleteModalTrigger.focus();
    deleteModalTrigger = null;
  }

  if (deleteModal) {
    deleteModal.addEventListener('keydown', (event) => {
      // The document-level Escape handler (FCS.closeTransientUI) only hides the modal;
      // going through closeDeleteModal() here also returns focus to what opened it.
      if (event.key === 'Escape') {
        closeDeleteModal();
        return;
      }

      FCS.trapFocus(deleteModal, event);
    });
  }

  document.getElementById('deleteScheduleBtn')?.addEventListener('click', openDeleteModal);
  cancelDeleteBtn?.addEventListener('click', closeDeleteModal);
  document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
    FCS.post('fcs_delete_schedule', { schedule_id: data.selectedId }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotDeleteSchedule), 'error');
        return;
      }
      window.location.href = data.baseUrl;
    });
  });

  // Local blackout calendar
  FCS.createCalendar({
    calendarEl: document.getElementById('localCalendar'),
    monthLabelEl: document.getElementById('localCalendarMonth'),
    summaryEl: document.getElementById('localUnavailableList'),
    prevBtn: document.getElementById('calPrevMonth'),
    nextBtn: document.getElementById('calNextMonth'),
    popoverEl: document.getElementById('datePopover'),
    blackouts: data.blackouts,
    emptyMessage: window.fcsAdmin.i18n.noLocalClosures,
    onCreate: (date) => FCS.post('fcs_save_blackout', { schedule_id: data.selectedId, date }).then((response) => {
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

  // Destinations
  const destList = document.getElementById('destinationList');
  const catalogSelect = document.getElementById('destinationCatalog');
  const catalog = data.catalog || [];
  let destinations = data.destinations.slice();

  function destinationsPayload() {
    const payload = { schedule_id: data.selectedId };
    destinations.forEach((dest, i) => {
      payload[`destinations[${i}][type]`] = dest.type;
      payload[`destinations[${i}][key]`] = dest.key;
    });

    return payload;
  }

  function isAssigned(type, key) {
    return destinations.some(dest => dest.type === type && dest.key === key);
  }

  function renderCatalogOptions() {
    if (!catalogSelect) return;

    const groups = window.fcsAdmin.i18n.destinationTypeLabels || {};

    catalogSelect.innerHTML = Object.keys(groups).map(type => {
      const items = catalog.filter(option => option.type === type && option.enabled && !isAssigned(option.type, option.key));
      if (!items.length) return '';

      // An already-claimed destination stays visible, disabled, and names the schedule
      // that has it - hiding it outright would just leave an admin wondering where it went.
      const optionsHtml = items.map(option => {
        const label = option.assignedTo
          ? `${option.label} — ${window.fcsAdmin.i18n.destinationAlreadyAssigned.replace('{schedule}', option.assignedTo)}`
          : `${option.label}${option.description ? ` (${option.description})` : ''}`;

        return `
          <option value="${FCS.escapeHtml(option.type)}|${FCS.escapeHtml(option.key)}" data-type="${FCS.escapeHtml(option.type)}" data-key="${FCS.escapeHtml(option.key)}" ${option.assignedTo ? 'disabled' : ''}>
            ${FCS.escapeHtml(label)}
          </option>
        `;
      }).join('');

      return `<optgroup label="${FCS.escapeHtml(groups[type])}">${optionsHtml}</optgroup>`;
    }).join('');
  }

  function catalogDescription(dest) {
    const option = catalog.find(o => o.type === dest.type && o.key === dest.key);
    return option && option.description ? option.description : '';
  }

  function destinationItemHtml(dest, index) {
    const description = catalogDescription(dest);

    return `
      <div class="fcs-dest-item">
        <div class="fcs-dest-item__info">
          <span class="fcs-dest-item__name">${FCS.escapeHtml(dest.label)}</span>
          ${description ? `<span class="fcs-dest-item__desc">${FCS.escapeHtml(description)}</span>` : ''}
        </div>
        <div class="fcs-dest-item__actions">
          ${dest.available === false ? `<span class="fcs-pill-badge fcs-pill-badge--muted">${FCS.escapeHtml(window.fcsAdmin.i18n.destinationUnavailable)}</span>` : ''}
          <button type="button" class="fcs-pill__remove" data-index="${index}" aria-label="${FCS.escapeHtml(window.fcsAdmin.i18n.removeDestination)}">×</button>
        </div>
      </div>
    `;
  }

  function renderDestinations() {
    if (!destList) return;

    if (!destinations.length) {
      destList.innerHTML = `<div class="fcs-summary-list__empty">${FCS.escapeHtml(window.fcsAdmin.i18n.noDestinationsYet)}</div>`;
      return;
    }

    const groups = window.fcsAdmin.i18n.destinationTypeLabels || {};

    destList.innerHTML = Object.keys(groups).map(type => {
      const items = destinations
        .map((dest, i) => ({ dest, i }))
        .filter(({ dest }) => dest.type === type);

      if (!items.length) return '';

      return `
        <div class="fcs-dest-group">
          <div class="fcs-dest-group__title">
            ${FCS.escapeHtml(groups[type])}
            <span class="fcs-dest-group__count">${items.length}</span>
          </div>
          <div class="fcs-dest-group__items">
            ${items.map(({ dest, i }) => destinationItemHtml(dest, i)).join('')}
          </div>
        </div>
      `;
    }).join('');

    // No ajax here - removing a destination only updates the in-memory list and marks the
    // screen dirty, like a weekday toggle or a name edit. It is sent, along with every
    // other pending edit, only when the admin clicks "Save Schedule" below.
    destList.querySelectorAll('[data-index]').forEach(btn => {
      btn.onclick = () => {
        destinations.splice(Number(btn.dataset.index), 1);
        renderDestinations();
        renderCatalogOptions();
        FCS.State.markDirty();
      };
    });
  }

  document.getElementById('addDestinationBtn')?.addEventListener('click', () => {
    const selected = catalogSelect.selectedOptions[0];
    if (!selected) return;

    const option = catalog.find(o => o.type === selected.dataset.type && o.key === selected.dataset.key);
    if (!option || option.assignedTo) return;

    destinations.push({ type: option.type, key: option.key, label: option.label, available: true });
    renderDestinations();
    renderCatalogOptions();
    FCS.State.markDirty();
  });

  renderDays();
  renderCatalogOptions();
  renderDestinations();
});

