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

  // Weekly fulfillment days
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
              <label>${FCS.escapeHtml(window.fcsAdmin.i18n.scheduleStart)}</label>
              <input type="time" class="fcs-input fcs-day-time-input" data-role="start" value="${FCS.escapeHtml(day.start_time.slice(0, 5))}">
            </div>
            <span class="fcs-weekday-time-sep" aria-hidden="true">&ndash;</span>
            <div class="fcs-weekday-time-field">
              <label>${FCS.escapeHtml(window.fcsAdmin.i18n.scheduleEnd)}</label>
              <input type="time" class="fcs-input fcs-day-time-input" data-role="end" value="${FCS.escapeHtml(day.end_time.slice(0, 5))}">
            </div>
            ${isLast ? '' : `
              <button type="button" class="fcs-btn fcs-btn--icon fcs-copy-down-btn" title="${FCS.escapeHtml(window.fcsAdmin.i18n.copyToDaysBelow)}" aria-label="${FCS.escapeHtml(window.fcsAdmin.i18n.copyToDaysBelow)}">
                ${DOWN_ARROW_ICON}
              </button>
            `}
          </div>
          <p class="fcs-weekday-time-error" hidden></p>
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
      };

      const clearError = () => {
        errorEl.hidden = true;
        startInput.classList.remove('fcs-input--error');
        endInput.classList.remove('fcs-input--error');
      };

      const saveDay = () => {
        if (checkbox.checked && startInput.value && endInput.value && endInput.value <= startInput.value) {
          showError(window.fcsAdmin.i18n.endBeforeStart);
          return;
        }

        clearError();

        FCS.post('fcs_save_schedule_weekday', {
          schedule_id: data.selectedId,
          day_of_week: day.day_of_week,
          enabled: checkbox.checked ? 1 : '',
          start_time: `${startInput.value}:00`,
          end_time: `${endInput.value}:00`
        }).done((response) => {
          if (!response.success) {
            showError(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveDay));
            return;
          }
          day.enabled = response.data.enabled;
          day.start_time = response.data.start_time;
          day.end_time = response.data.end_time;
          renderDays();
          FCS.toast(window.fcsAdmin.i18n.scheduleUpdated);
        });
      };

      checkbox.addEventListener('change', () => {
        day.enabled = checkbox.checked;
        renderDays();
        saveDay();
      });

      startInput.addEventListener('change', saveDay);
      endInput.addEventListener('change', saveDay);

      copyBtn?.addEventListener('click', () => {
        FCS.post('fcs_copy_schedule_weekday', {
          schedule_id: data.selectedId,
          day_of_week: day.day_of_week
        }).done((response) => {
          if (!response.success) {
            FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotCopySchedule));
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

  function saveName(onSuccess) {
    FCS.post('fcs_save_schedule', { schedule_id: data.selectedId, name: titleInput.value }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveScheduleName));
        return;
      }
      if (onSuccess) onSuccess();
    });
  }

  if (titleInput) {
    titleInput.addEventListener('change', () => saveName(() => {
      FCS.State.markClean();
      FCS.toast(window.fcsAdmin.i18n.scheduleNameSaved);
    }));
  }

  document.getElementById('saveScheduleBtn')?.addEventListener('click', () => {
    saveName(() => {
      FCS.State.markClean();
      FCS.toast(window.fcsAdmin.i18n.scheduleSaved);
    });
  });

  // Add a schedule
  document.getElementById('addScheduleBtn')?.addEventListener('click', () => {
    const name = window.prompt(window.fcsAdmin.i18n.promptNewScheduleName);
    if (!name) return;

    FCS.post('fcs_save_schedule', { name }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotCreateSchedule));
        return;
      }
      window.location.href = `${data.baseUrl}&schedule_id=${response.data.id}`;
    });
  });

  // Delete modal
  const overlay = document.getElementById('deleteModalOverlay');
  document.getElementById('deleteScheduleBtn')?.addEventListener('click', () => overlay.classList.add('fcs-overlay--show'));
  document.getElementById('cancelDeleteBtn')?.addEventListener('click', () => overlay.classList.remove('fcs-overlay--show'));
  document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
    FCS.post('fcs_delete_schedule', { schedule_id: data.selectedId }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotDeleteSchedule));
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
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotAddDate));
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onSave: (id, reason) => FCS.post('fcs_save_blackout', { blackout_id: id, reason }).then((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveNote));
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

  function saveDestinations() {
    const payload = {};
    destinations.forEach((dest, i) => {
      payload[`destinations[${i}][type]`] = dest.type;
      payload[`destinations[${i}][key]`] = dest.key;
    });

    return FCS.post('fcs_save_schedule_destinations', Object.assign({ schedule_id: data.selectedId }, payload));
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

      const optionsHtml = items.map(option => `
        <option value="${FCS.escapeHtml(option.type)}|${FCS.escapeHtml(option.key)}" data-type="${FCS.escapeHtml(option.type)}" data-key="${FCS.escapeHtml(option.key)}">
          ${FCS.escapeHtml(option.label)}${option.description ? ` (${FCS.escapeHtml(option.description)})` : ''}
        </option>
      `).join('');

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

    destList.querySelectorAll('[data-index]').forEach(btn => {
      btn.onclick = () => {
        destinations.splice(Number(btn.dataset.index), 1);
        renderDestinations();
        renderCatalogOptions();
        saveDestinations().done(() => FCS.toast(window.fcsAdmin.i18n.destinationRemoved));
      };
    });
  }

  document.getElementById('addDestinationBtn')?.addEventListener('click', () => {
    const selected = catalogSelect.selectedOptions[0];
    if (!selected) return;

    const option = catalog.find(o => o.type === selected.dataset.type && o.key === selected.dataset.key);
    if (!option) return;

    destinations.push({ type: option.type, key: option.key, label: option.label, available: true });
    renderDestinations();
    renderCatalogOptions();
    saveDestinations().done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveDestination));
        return;
      }
      FCS.toast(window.fcsAdmin.i18n.destinationAdded);
    });
  });

  renderDays();
  renderCatalogOptions();
  renderDestinations();
});
