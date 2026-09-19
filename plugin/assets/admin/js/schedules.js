/**
 * FuelChef Subscriptions - Schedules Page Logic
 */

document.addEventListener('DOMContentLoaded', () => {
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

      const validateTimes = () => {
        if (checkbox.checked && startInput.value && endInput.value && endInput.value <= startInput.value) {
          showError(window.fcsAdmin.i18n.endBeforeStart);
          return false;
        }

        clearError();
        return true;
      };

      function weekdayPayload() {
        return {
          schedule_id: data.selectedId,
          day_of_week: day.day_of_week,
          enabled: day.enabled ? 1 : '',
          start_time: day.start_time,
          end_time: day.end_time
        };
      }

      // Every weekday change - a toggle or a time edit - saves itself immediately, the
      // moment it's made, instead of waiting behind a separate save action. A rejected
      // change reverts the field and toasts the error, mirroring how the closure
      // calendar below already behaves.
      function saveWeekday(revert) {
        FCS.post('fcs_save_schedule_weekday', weekdayPayload()).done((response) => {
          if (!response.success) {
            revert();
            renderDays();
            FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveDay), 'error');
            return;
          }
          FCS.toast(window.fcsAdmin.i18n.weekdaySaved);
        });
      }

      checkbox.addEventListener('change', () => {
        const previousEnabled = day.enabled;
        day.enabled = checkbox.checked;
        renderDays();
        saveWeekday(() => { day.enabled = previousEnabled; });
      });

      startInput.addEventListener('change', () => {
        const previous = day.start_time;
        day.start_time = `${startInput.value}:00`;
        if (!validateTimes()) return;
        saveWeekday(() => { day.start_time = previous; });
      });

      endInput.addEventListener('change', () => {
        const previous = day.end_time;
        day.end_time = `${endInput.value}:00`;
        if (!validateTimes()) return;
        saveWeekday(() => { day.end_time = previous; });
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

  // Saves on blur, not on every keystroke, and reverts to the last saved name if the
  // server rejects it (e.g. blank). Also keeps the sidebar's own copy of the name in
  // sync - it's a separate render of the same data and would otherwise go stale.
  if (titleInput) {
    let savedName = titleInput.value;

    titleInput.addEventListener('blur', () => {
      const name = titleInput.value.trim();
      if (name === savedName) return;

      FCS.post('fcs_save_schedule', { schedule_id: data.selectedId, name }).done((response) => {
        if (!response.success) {
          titleInput.value = savedName;
          FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveScheduleName), 'error');
          return;
        }

        savedName = response.data.name;
        titleInput.value = response.data.name;

        const sidebarTitle = document.querySelector(`a[href$="schedule_id=${data.selectedId}"] .fcs-schedule-nav__title`);
        if (sidebarTitle) sidebarTitle.textContent = response.data.name;
      });
    });
  }

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

  // Delete modal - opened either from the title row's own Delete Schedule button (the
  // currently open schedule) or from a sidebar row's "more actions" menu (any schedule,
  // without opening it first). pendingDeleteId tracks which one a confirm applies to.
  const overlay = document.getElementById('deleteModalOverlay');
  const deleteModal = overlay?.querySelector('.fcs-modal');
  const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
  let deleteModalTrigger = null;
  let pendingDeleteId = data.selectedId;

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

  // Sidebar "more actions" menu
  function closeAllScheduleMenus() {
    document.querySelectorAll('.fcs-schedule-nav__menu--show').forEach((menu) => menu.classList.remove('fcs-schedule-nav__menu--show'));
    document.querySelectorAll('.fcs-schedule-nav__more[aria-expanded="true"]').forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
  }

  document.querySelectorAll('.fcs-schedule-nav__more').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const menu = btn.nextElementSibling;
      const isOpen = menu.classList.contains('fcs-schedule-nav__menu--show');
      closeAllScheduleMenus();
      if (!isOpen) {
        menu.classList.add('fcs-schedule-nav__menu--show');
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  document.addEventListener('click', closeAllScheduleMenus);

  document.querySelectorAll('[data-action="delete-schedule"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      closeAllScheduleMenus();
      pendingDeleteId = Number(btn.dataset.scheduleId);
      openDeleteModal();
    });
  });

  document.getElementById('deleteScheduleBtn')?.addEventListener('click', () => {
    pendingDeleteId = data.selectedId;
    openDeleteModal();
  });

  cancelDeleteBtn?.addEventListener('click', closeDeleteModal);

  document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
    FCS.post('fcs_delete_schedule', { schedule_id: pendingDeleteId }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotDeleteSchedule), 'error');
        return;
      }

      closeDeleteModal();

      if (pendingDeleteId === data.selectedId) {
        window.location.href = data.baseUrl;
        return;
      }

      document.querySelector(`[data-schedule-id="${pendingDeleteId}"]`)?.closest('.fcs-schedule-nav__item')?.remove();
      FCS.toast(window.fcsAdmin.i18n.scheduleDeleted);
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

    // Removing a destination saves immediately, the same as adding one below.
    destList.querySelectorAll('[data-index]').forEach(btn => {
      btn.onclick = () => {
        const previous = destinations.slice();
        destinations.splice(Number(btn.dataset.index), 1);
        renderDestinations();
        renderCatalogOptions();

        FCS.post('fcs_save_schedule_destinations', destinationsPayload()).done((response) => {
          if (!response.success) {
            destinations = previous;
            renderDestinations();
            renderCatalogOptions();
            FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveDestinations), 'error');
            return;
          }
          FCS.toast(window.fcsAdmin.i18n.destinationRemoved);
        });
      };
    });
  }

  document.getElementById('addDestinationBtn')?.addEventListener('click', () => {
    const selected = catalogSelect.selectedOptions[0];
    if (!selected) return;

    const option = catalog.find(o => o.type === selected.dataset.type && o.key === selected.dataset.key);
    if (!option || option.assignedTo) return;

    const previous = destinations.slice();
    destinations.push({ type: option.type, key: option.key, label: option.label, available: true });
    renderDestinations();
    renderCatalogOptions();

    FCS.post('fcs_save_schedule_destinations', destinationsPayload()).done((response) => {
      if (!response.success) {
        destinations = previous;
        renderDestinations();
        renderCatalogOptions();
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveDestinations), 'error');
        return;
      }
      FCS.toast(window.fcsAdmin.i18n.destinationAdded);
    });
  });

  renderDays();
  renderCatalogOptions();
  renderDestinations();
});
