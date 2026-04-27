/**
 * Events KPI strip — fetches /api/backstage/events.php?op=stats and refines
 * the two card-tabs (Events / Proposals).
 *
 * The Events card displays the total event count in the value slot, and a
 * "X drafts • Y reg (30d)" subtitle once stats arrive. The Proposals value
 * is also refreshed against the canonical pending count from the stats
 * endpoint (server-rendered initial value comes from the proposals list).
 */
(function () {
  'use strict';

  if (!document.querySelector('.kpi-card--tab[data-tab="events"]')) return;

  fetch('/api/backstage/events.php?op=stats')
    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(function (j) { render(j && j.data ? j.data : null); })
    .catch(function (e) { console.error('events stats failed', e); });

  function render(data) {
    if (!data) return;
    document.querySelectorAll('.kpi-card--tab').forEach(function (c) { c.classList.remove('is-loading'); });

    var upcoming = (data.upcoming          && typeof data.upcoming.value          === 'number') ? data.upcoming.value          : null;
    var drafts   = (data.drafts            && typeof data.drafts.value            === 'number') ? data.drafts.value            : null;
    var regs30d  = (data.registrations_30d && typeof data.registrations_30d.value === 'number') ? data.registrations_30d.value : null;
    var pending  = (data.pending_proposals && typeof data.pending_proposals.value === 'number') ? data.pending_proposals.value : null;

    // Events card subtitle: "X drafts • Y reg (30d)"
    if (drafts !== null || regs30d !== null) {
      var parts = [];
      if (drafts  !== null) parts.push(drafts  + ' draft' + (drafts === 1 ? '' : 's'));
      if (regs30d !== null) parts.push(regs30d + ' reg (30d)');
      var sub = document.querySelector('[data-tab-subtitle="events"]');
      if (sub && parts.length) sub.textContent = parts.join(' • ');
    }

    // Proposals value — refresh from canonical stats count.
    if (pending !== null) {
      setVal('proposals', pending);
    }

    // Upcoming count is informational; we keep the total event count in the
    // Events card value slot. Reserved if we ever want to flip the Events
    // value to "upcoming only".
    void upcoming;
  }

  function setVal(tab, value) {
    var el = document.querySelector('.kpi-card--tab[data-tab="' + tab + '"] .kpi-card__value');
    if (el) el.textContent = String(value);
  }
})();
