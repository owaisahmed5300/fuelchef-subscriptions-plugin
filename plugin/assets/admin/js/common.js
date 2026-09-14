/**
 * FuelChef Subscriptions (FCS) - Core Shared Utilities
 */

window.FCS = window.FCS || {};

// Helper: Escape HTML
FCS.escapeHtml = function (str) {
  return String(str).replace(/[&<>'"]/g, tag => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;'
  }[tag] || tag));
};

// Ajax helper: posts to admin-ajax.php with the shared nonce, returns a jQuery promise.
FCS.post = function (action, data) {
  return jQuery.post(window.fcsAdmin.ajaxUrl, Object.assign({ action, nonce: window.fcsAdmin.nonce }, data));
};

// Dirty State Tracker
FCS.State = {
  isDirty: false,
  _label: null,
  _getLabel() {
    if (!this._label) this._label = document.getElementById('fcsSaveStatus');
    return this._label;
  },
  markDirty() {
    this.isDirty = true;
    const el = this._getLabel();
    if (el) {
      el.classList.remove('fcs-save-status--saved');
      el.classList.add('fcs-save-status--dirty');
      el.innerHTML = `&#9679; ${FCS.escapeHtml(window.fcsAdmin.i18n.unsavedChanges)}`;
    }
  },
  markClean() {
    this.isDirty = false;
    const el = this._getLabel();
    if (el) {
      el.classList.remove('fcs-save-status--dirty');
      el.classList.add('fcs-save-status--saved');
      el.innerHTML = `&#10003; ${FCS.escapeHtml(window.fcsAdmin.i18n.allChangesSaved)}`;
    }
  },
  init() {
    window.addEventListener('beforeunload', (e) => {
      if (this.isDirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
  }
};

// Toast Notifications
FCS.toast = function (msg) {
  const el = document.getElementById('fcsToast');
  if (!el) return;
  el.textContent = msg;
  el.classList.add('fcs-toast--show');
  clearTimeout(this._timer);
  this._timer = setTimeout(() => el.classList.remove('fcs-toast--show'), 2200);
};

// WP Tabs
FCS.initTabs = function () {
  document.querySelectorAll('.fcs-nav-tab').forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();
      const wrap = tab.closest('.fcs-wrap');
      wrap.querySelectorAll('.fcs-nav-tab').forEach(t => t.classList.remove('fcs-nav-tab--active'));
      wrap.querySelectorAll('.fcs-tab-panel').forEach(p => p.classList.remove('fcs-tab-panel--active'));

      tab.classList.add('fcs-nav-tab--active');
      const target = document.getElementById(tab.dataset.tab);
      if (target) target.classList.add('fcs-tab-panel--active');
    });
  });
};

/**
 * Interactive calendar with a create/edit/remove popover, backed by real blackout rows.
 *
 * `blackouts` is a flat list of { id, date: 'YYYY-MM-DD', label: 'Dec 25, 2026', reason }
 * seeded from the server. Every mutation calls the matching option (onCreate/onSave/
 * onRemove) and only updates the UI once the server confirms it.
 */
FCS.createCalendar = function (options) {
  const {
    calendarEl,
    monthLabelEl,
    summaryEl,
    prevBtn,
    nextBtn,
    blackouts = [],
    popoverEl,
    emptyMessage = '',
    onCreate,
    onSave,
    onRemove
  } = options;

  const monthNames = window.fcsAdmin.i18n.monthNames;
  const weekdayNamesShort = window.fcsAdmin.i18n.weekdayNamesShort;
  let viewDate = new Date();
  let items = blackouts.slice();
  let activeId = null;

  const isoDate = (y, m, d) => `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  const dateLabel = (y, m, d) => `${monthNames[m].slice(0, 3)} ${String(d).padStart(2, '0')}, ${y}`;

  function findByDate(iso) {
    return items.find(i => i.date === iso) || null;
  }

  function findById(id) {
    return items.find(i => i.id === id) || null;
  }

  function monthKey(y, m) {
    return `${monthNames[m]} ${y}`;
  }

  function itemsByMonth() {
    const grouped = {};
    items.forEach(item => {
      const [y, m] = item.date.split('-').map(Number);
      const key = monthKey(y, m - 1);
      (grouped[key] = grouped[key] || []).push(item);
    });
    Object.values(grouped).forEach(list => list.sort((a, b) => a.date.localeCompare(b.date)));
    return grouped;
  }

  function renderSummary() {
    if (!summaryEl) return;
    const grouped = itemsByMonth();
    const entries = Object.entries(grouped);
    if (!entries.length) {
      summaryEl.innerHTML = `<div class="fcs-summary-list__empty">${FCS.escapeHtml(emptyMessage)}</div>`;
      return;
    }

    const currentKey = monthKey(viewDate.getFullYear(), viewDate.getMonth());
    summaryEl.innerHTML = entries.map(([m, list]) => {
      const isOpen = m === currentKey;
      return `
        <details class="fcs-summary-month" ${isOpen ? 'open' : ''}>
          <summary class="fcs-summary-month__summary">
            <span class="fcs-summary-month__label">
              <svg class="fcs-summary-month__chevron" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M4 5.5 7 8.5l3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              ${FCS.escapeHtml(m)}
            </span>
            <span class="fcs-summary-month__count">${list.length} date${list.length === 1 ? '' : 's'}</span>
          </summary>
          <div class="fcs-summary-month__body">
            <div class="fcs-pills">
              ${list.map(item => `
                <div class="fcs-pill" data-id="${item.id}">
                  <span>${FCS.escapeHtml(item.label)}</span>
                  <button type="button" class="fcs-pill__remove" data-remove-id="${item.id}" aria-label="Remove date">×</button>
                </div>
              `).join('')}
            </div>
          </div>
        </details>
      `;
    }).join('');

    summaryEl.querySelectorAll('.fcs-pill').forEach(pill => {
      pill.onclick = (e) => {
        if (!e.target.closest('.fcs-pill__remove')) {
          const item = findById(Number(pill.dataset.id));
          if (item) openPopover(item, pill);
        }
      };
    });

    summaryEl.querySelectorAll('.fcs-pill__remove').forEach(btn => {
      btn.onclick = (e) => {
        e.stopPropagation();
        removeItem(Number(btn.dataset.removeId));
      };
    });
  }

  function render() {
    const y = viewDate.getFullYear();
    const m = viewDate.getMonth();
    if (monthLabelEl) monthLabelEl.textContent = `${monthNames[m]} ${y}`;

    const firstDay = new Date(y, m, 1).getDay();
    const totalDays = new Date(y, m + 1, 0).getDate();
    const today = new Date();

    let html = `
      <div class="fcs-calendar__head">
        ${weekdayNamesShort.map(d => `<div class="fcs-calendar__th">${FCS.escapeHtml(d)}</div>`).join('')}
      </div>
      <div class="fcs-calendar__grid">
    `;

    for (let i = 0; i < firstDay; i++) {
      html += '<div class="fcs-calendar__cell fcs-calendar__cell--empty"></div>';
    }

    for (let d = 1; d <= totalDays; d++) {
      const iso = isoDate(y, m, d);
      const item = findByDate(iso);
      const curDate = new Date(y, m, d);
      const isToday = curDate.toDateString() === today.toDateString();

      html += `
        <div class="fcs-calendar__cell ${item ? 'fcs-calendar__cell--unavailable ' : ''}${isToday ? 'fcs-calendar__cell--today' : ''}">
          <button type="button" class="fcs-calendar__date-btn" data-date="${iso}">${d}</button>
          ${item && item.reason ? `<div class="fcs-calendar__reason">${FCS.escapeHtml(item.reason)}</div>` : ''}
        </div>
      `;
    }

    const trailing = (7 - ((firstDay + totalDays) % 7)) % 7;
    for (let i = 0; i < trailing; i++) {
      html += '<div class="fcs-calendar__cell fcs-calendar__cell--empty"></div>';
    }

    html += '</div>';
    calendarEl.innerHTML = html;

    const gridEl = calendarEl.querySelector('.fcs-calendar__grid');
    if (gridEl) {
      const cellCount = gridEl.children.length;
      const lastRowStart = cellCount - 7;
      for (let i = Math.max(0, lastRowStart); i < cellCount; i++) {
        gridEl.children[i].classList.add('fcs-calendar__cell--lastrow');
      }
    }

    calendarEl.querySelectorAll('[data-date]').forEach(btn => {
      btn.onclick = () => {
        const iso = btn.dataset.date;
        const existing = findByDate(iso);

        if (existing) {
          openPopover(existing, btn.closest('.fcs-calendar__cell'));
          return;
        }

        createItem(iso, btn);
      };
    });

    renderSummary();
  }

  function createItem(iso, anchor) {
    if (!onCreate) return;

    const [y, m, d] = iso.split('-').map(Number);

    onCreate(iso).then(item => {
      if (!item) return;
      item.label = item.label || dateLabel(y, m - 1, d);
      items.push(item);
      render();
      FCS.toast(window.fcsAdmin.i18n.dateMarkedUnavailable);
      // render() just rebuilt the calendar's markup, so the original anchor button is a
      // detached node - its getBoundingClientRect() would return an all-zero rect, which
      // is what put the popover at the top-left of the page. Re-query the same date's
      // button from the fresh DOM instead of reusing the stale reference.
      openPopover(item, calendarEl.querySelector(`[data-date="${iso}"]`));
    });
  }

  function removeItem(id) {
    if (!onRemove) return;

    onRemove(id).then(ok => {
      if (!ok) return;
      items = items.filter(i => i.id !== id);
      closePopover();
      render();
      FCS.toast(window.fcsAdmin.i18n.dateRemoved);
    });
  }

  function positionPopover(anchor) {
    if (!popoverEl || !anchor) return;
    const rect = anchor.getBoundingClientRect();
    let left = rect.left;
    let top = rect.bottom + 6;
    if (left + 300 > window.innerWidth - 12) left = window.innerWidth - 312;
    if (top + 260 > window.innerHeight) top = rect.top - 268;
    popoverEl.style.left = `${Math.max(12, left)}px`;
    popoverEl.style.top = `${Math.max(12, top)}px`;
  }

  function openPopover(item, anchor) {
    if (!popoverEl) return;
    activeId = item.id;

    const title = popoverEl.querySelector('[data-pop-title]');
    const reason = popoverEl.querySelector('[data-pop-reason]');
    const count = popoverEl.querySelector('[data-pop-count]');

    if (title) title.textContent = item.label;
    if (reason) {
      reason.value = item.reason || '';
      if (count) count.textContent = reason.value.length;
    }

    popoverEl.classList.add('fcs-popover--show');
    positionPopover(anchor);
    if (reason) reason.focus();
  }

  function closePopover() {
    if (!popoverEl) return;
    popoverEl.classList.remove('fcs-popover--show');
    activeId = null;
  }

  if (popoverEl) {
    const reasonInput = popoverEl.querySelector('[data-pop-reason]');
    const countEl = popoverEl.querySelector('[data-pop-count]');
    const saveBtn = popoverEl.querySelector('[data-pop-save]');
    const removeBtn = popoverEl.querySelector('[data-pop-remove]');

    if (reasonInput && countEl) {
      reasonInput.oninput = (e) => countEl.textContent = e.target.value.length;
    }

    if (saveBtn) {
      saveBtn.onclick = () => {
        if (null === activeId || !onSave) return;

        onSave(activeId, reasonInput.value).then(item => {
          if (!item) return;
          const existing = findById(activeId);
          if (existing) existing.reason = item.reason;
          closePopover();
          render();
          FCS.toast(window.fcsAdmin.i18n.noteSaved);
        });
      };
    }

    if (removeBtn) {
      removeBtn.onclick = () => {
        if (null !== activeId) removeItem(activeId);
      };
    }

    document.addEventListener('click', (e) => {
      if (popoverEl.classList.contains('fcs-popover--show') &&
          !e.target.closest('.fcs-popover') &&
          !e.target.closest('.fcs-pill') &&
          !e.target.closest('.fcs-calendar__cell')) {
        closePopover();
      }
    });
  }

  if (prevBtn) prevBtn.onclick = () => { viewDate.setMonth(viewDate.getMonth() - 1); render(); };
  if (nextBtn) nextBtn.onclick = () => { viewDate.setMonth(viewDate.getMonth() + 1); render(); };

  render();

  return { render };
};
