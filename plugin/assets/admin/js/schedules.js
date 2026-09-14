/**
 * FuelChef Subscriptions - Schedules Page Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  FCS.State.init();
  FCS.initTabs();

  const data = window.fcsSchedulesData;
  const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  const tableBody = document.getElementById('weekdayRows');
  const titleInput = document.getElementById('scheduleTitle');

  function saveMessage(response, fallback) {
    return (response.data && response.data.message) ? response.data.message : fallback;
  }

  // Weekly fulfillment days
  function renderDays() {
    if (!tableBody) return;
    tableBody.innerHTML = '';

    data.weekdays.forEach(day => {
      const tr = document.createElement('tr');
      tr.className = 'fcs-weekday-row';
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
            ? '<span class="fcs-pill-badge fcs-pill-badge--success"><span class="fcs-status-dot"></span> Active</span>'
            : '<span class="fcs-pill-badge fcs-pill-badge--muted">Closed</span>'}
        </td>
        <td>
          <div class="fcs-weekday-time-ctrl" style="${day.enabled ? '' : 'display:none;'}">
            <label>Fulfillment start:</label>
            <input type="time" class="fcs-input fcs-day-time-input" value="${FCS.escapeHtml(day.start_time.slice(0, 5))}">
          </div>
        </td>
      `;

      const checkbox = tr.querySelector('.day-enable-check');
      const timeInput = tr.querySelector('.fcs-day-time-input');

      const saveDay = () => {
        FCS.post('fcs_save_schedule_weekday', {
          schedule_id: data.selectedId,
          day_of_week: day.day_of_week,
          enabled: checkbox.checked ? 1 : '',
          start_time: `${timeInput.value}:00`
        }).done((response) => {
          if (!response.success) {
            FCS.toast(saveMessage(response, 'Could not save that day'));
            return;
          }
          day.enabled = response.data.enabled;
          day.start_time = response.data.start_time;
          renderDays();
          FCS.toast('Schedule updated');
        });
      };

      checkbox.addEventListener('change', () => {
        day.enabled = checkbox.checked;
        renderDays();
        saveDay();
      });

      timeInput.addEventListener('change', saveDay);

      tableBody.appendChild(tr);
    });
  }

  function saveName(onSuccess) {
    FCS.post('fcs_save_schedule', { schedule_id: data.selectedId, name: titleInput.value }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, 'Could not save the schedule name'));
        return;
      }
      if (onSuccess) onSuccess();
    });
  }

  if (titleInput) {
    titleInput.addEventListener('change', () => saveName(() => {
      FCS.State.markClean();
      FCS.toast('Schedule name saved');
    }));
  }

  document.getElementById('saveScheduleBtn')?.addEventListener('click', () => {
    saveName(() => {
      FCS.State.markClean();
      FCS.toast('Schedule saved successfully');
    });
  });

  // Add a schedule
  document.getElementById('addScheduleBtn')?.addEventListener('click', () => {
    const name = window.prompt('Name the new schedule:');
    if (!name) return;

    FCS.post('fcs_save_schedule', { name }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, 'Could not create that schedule'));
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
        FCS.toast(saveMessage(response, 'Could not delete that schedule'));
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
    emptyMessage: 'No localized closure dates set for this schedule.',
    onCreate: (date) => FCS.post('fcs_save_blackout', { schedule_id: data.selectedId, date }).then((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, 'Could not add that date'));
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onSave: (id, reason) => FCS.post('fcs_save_blackout', { blackout_id: id, reason }).then((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, 'Could not save that note'));
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onRemove: (id) => FCS.post('fcs_delete_blackout', { blackout_id: id }).then((response) => response.success)
  });

  // Destinations
  const destList = document.getElementById('destinationList');
  let destinations = data.destinations.slice();

  function saveDestinations() {
    const payload = {};
    destinations.forEach((dest, i) => {
      payload[`destinations[${i}][type]`] = dest.type;
      payload[`destinations[${i}][key]`] = dest.key;
    });

    return FCS.post('fcs_save_schedule_destinations', Object.assign({ schedule_id: data.selectedId }, payload));
  }

  function renderDestinations() {
    if (!destList) return;

    if (!destinations.length) {
      destList.innerHTML = '<div class="fcs-summary-list__empty">No destinations assigned yet.</div>';
      return;
    }

    destList.innerHTML = destinations.map((dest, i) => `
      <div class="fcs-dest-item">
        <span class="fcs-dest-item__name">${FCS.escapeHtml(dest.type)}</span>
        <span class="fcs-dest-item__desc">${FCS.escapeHtml(dest.key)}</span>
        <button type="button" class="fcs-pill__remove" data-index="${i}" aria-label="Remove destination">×</button>
      </div>
    `).join('');

    destList.querySelectorAll('[data-index]').forEach(btn => {
      btn.onclick = () => {
        destinations.splice(Number(btn.dataset.index), 1);
        renderDestinations();
        saveDestinations().done(() => FCS.toast('Destination removed'));
      };
    });
  }

  document.getElementById('addDestinationBtn')?.addEventListener('click', () => {
    const type = document.getElementById('destinationType').value;
    const key = document.getElementById('destinationKey').value.trim();
    if (!key) return;

    destinations.push({ type, key });
    renderDestinations();
    document.getElementById('destinationKey').value = '';
    saveDestinations().done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, 'Could not save that destination'));
        return;
      }
      FCS.toast('Destination added');
    });
  });

  renderDays();
  renderDestinations();
});
