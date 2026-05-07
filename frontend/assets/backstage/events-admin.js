/**
 * Events admin list page — card-tabs, table render, registration modal.
 *
 * Two card-tabs (Events / Proposals) drive the .evt-tab-content panels.
 * The proposal-modal.js sibling handles the Review button on the Proposals
 * tab; this file owns:
 *   - Tab switching (Events / Proposals)
 *   - Events table render + filters
 *   - Publish / Archive row actions
 *   - Registrations modal (per-event participant list + remove)
 *
 * Uses location.reload() after any mutation as an MVP simplification
 * (matching projects admin behaviour).
 */
(function (global) {
    'use strict';

    var events = (global.DAEMS_EVENTS && Array.isArray(global.DAEMS_EVENTS.items))
        ? global.DAEMS_EVENTS.items.slice()
        : [];

    var filterStatus = '';
    var filterType   = '';
    var filterSearch = '';

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // =========================================================================
    // Tab switching — clickable KPI cards (.kpi-card--tab[data-tab]) drive
    // the .evt-tab-content panels below.
    // =========================================================================
    function initTabs() {
        var buttons = document.querySelectorAll('.kpi-card--tab[data-tab]');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-tab');
                if (!tab) return;
                activateTab(tab);
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', tab);
                    window.history.replaceState({}, '', url.toString());
                } catch (e) { /* ignore */ }
            });
        });
    }

    function activateTab(tab) {
        document.querySelectorAll('.kpi-card--tab').forEach(function (btn) {
            var isActive = btn.getAttribute('data-tab') === tab;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        document.querySelectorAll('.evt-tab-content').forEach(function (sec) {
            sec.classList.toggle('is-active', sec.getAttribute('data-tab-content') === tab);
        });
    }

    // =========================================================================
    // Events table
    // =========================================================================
    var tbody;
    var countEl;

    function statusPill(status) {
        var cls = 'evt-pill evt-pill--' + status;
        return '<span class="' + cls + '">' + status + '</span>';
    }

    function typeLabel(type) {
        var map = { upcoming: 'Upcoming', past: 'Past', online: 'Online' };
        return map[type] || type;
    }

    function filtered() {
        return events.filter(function (e) {
            if (filterStatus && e.status !== filterStatus) return false;
            if (filterType && e.type !== filterType)     return false;
            if (filterSearch) {
                var q = filterSearch.toLowerCase();
                if ((e.title || '').toLowerCase().indexOf(q) === -1) return false;
            }
            return true;
        });
    }

    function coverageBadge(id, coverage) {
        coverage = coverage || {};
        var locales = ['fi_FI', 'en_GB', 'sw_TZ'];
        var dots = locales.map(function (loc) {
            var c = coverage[loc] || { filled: 0, total: 3 };
            var cls;
            if (c.total > 0 && c.filled >= c.total) cls = 'complete';
            else if (c.filled === 0)                cls = 'empty';
            else                                    cls = 'partial';
            return '<span class="coverage-dot ' + cls + '" data-loc="' + loc +
                '" title="' + loc + ' ' + c.filled + '/' + c.total + '"></span>';
        }).join('');
        return '<span class="coverage-badge" data-entity-id="' + escHtml(id) +
               '" title="fi_FI / en_GB / sw_TZ translations">' + dots + '</span>';
    }

    function renderTable() {
        var rows = filtered();
        if (countEl) countEl.textContent = 'Total: ' + rows.length;
        if (!tbody) return;
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="events-empty">No events found.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (e) {
            var id     = e.id || '';
            var canPub = e.status === 'draft' || e.status === 'archived';
            var canArc = e.status !== 'archived';
            var editHref = '/backstage/events/edit?id=' + encodeURIComponent(id);
            return '<tr data-row-id="' + escHtml(id) + '">' +
                '<td>' + (e.event_date || '-') + '</td>' +
                '<td><a class="evt-title-link" href="' + editHref + '">' + escHtml(e.title || '') + '</a></td>' +
                '<td>' + typeLabel(e.type || '') + '</td>' +
                '<td>' + statusPill(e.status || 'draft') + '</td>' +
                '<td class="evt-coverage-cell">' + coverageBadge(id, e.coverage) + '</td>' +
                '<td>' +
                    '<button type="button" class="evt-reg-count btn--link" data-id="' + escHtml(id) + '" data-title="' + escHtml(e.title || '') + '">' +
                        (e.registration_count || 0) + ' registrant' + ((e.registration_count || 0) !== 1 ? 's' : '') +
                    '</button>' +
                '</td>' +
                '<td class="evt-actions">' +
                    '<a class="evt-action" href="' + editHref + '" title="Edit" aria-label="Edit event">' +
                        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21a1 1 0 0 0 0-1.42l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.82z"/></svg>' +
                    '</a>' +
                    (canPub
                        ? '<button type="button" class="evt-action" data-action="publish" data-id="' + escHtml(id) + '" title="Publish" aria-label="Publish event">' +
                            '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm-1 14.5v-9l7 4.5z"/></svg>' +
                          '</button>'
                        : '') +
                    (canArc
                        ? '<button type="button" class="evt-action evt-action--warn" data-action="archive" data-id="' + escHtml(id) + '" title="Archive" aria-label="Archive event">' +
                            '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v3.01c0 .72.43 1.34 1 1.72V20c0 1.1 1.1 2 2 2h14c.9 0 2-.9 2-2V8.72c.57-.38 1-1 1-1.72V4c0-1.1-.9-2-2-2zm-5 12H9v-2h6v2zm5-8H4V4h16v2z"/></svg>' +
                          '</button>'
                        : '') +
                '</td>' +
            '</tr>';
        }).join('');

        // Wire action buttons (edit is a plain anchor — no JS needed)
        tbody.querySelectorAll('[data-action="publish"]').forEach(function (btn) {
            btn.addEventListener('click', function () { doPublish(btn.getAttribute('data-id')); });
        });
        tbody.querySelectorAll('[data-action="archive"]').forEach(function (btn) {
            btn.addEventListener('click', function () { doArchive(btn.getAttribute('data-id')); });
        });
        tbody.querySelectorAll('.evt-reg-count').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openParticipants(btn.getAttribute('data-id'), btn.getAttribute('data-title') || '');
            });
        });
    }

    function doPublish(id) {
        fetch('/api/backstage/events?op=publish&id=' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        }).then(function (r) {
            if (r.ok) { location.reload(); }
            else { alert('Failed to publish event.'); }
        });
    }

    function doArchive(id) {
        if (!confirm('Archive this event? It will no longer appear publicly.')) return;
        fetch('/api/backstage/events?op=archive&id=' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        }).then(function (r) {
            if (r.ok) { location.reload(); }
            else { alert('Failed to archive event.'); }
        });
    }

    // =========================================================================
    // Participants modal
    // =========================================================================
    var partBackdrop;
    var partBody;
    var partClose;

    function openParticipants(eventId, title) {
        if (!partBackdrop || !partBody) return;
        partBody.innerHTML = '<p>Loading&hellip;</p>';
        partBackdrop.hidden = false;
        partBackdrop.setAttribute('aria-hidden', 'false');
        var titleEl = document.getElementById('participants-modal-title');
        if (titleEl) titleEl.textContent = 'Registrations — ' + title;

        fetch('/api/backstage/events?op=registrations&id=' + encodeURIComponent(eventId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var items = (data && Array.isArray(data.items)) ? data.items : [];
                if (items.length === 0) {
                    partBody.innerHTML = '<p>No registrations yet.</p>';
                    return;
                }
                var rows = items.map(function (r) {
                    return '<tr>' +
                        '<td>' + escHtml(r.name || '') + '</td>' +
                        '<td>' + escHtml(r.email || '') + '</td>' +
                        '<td>' + escHtml((r.registered_at || '').substring(0, 10)) + '</td>' +
                        '<td>' +
                            '<button type="button" class="btn btn--sm evt-action--danger" data-remove-event="' + escHtml(eventId) + '" data-remove-user="' + escHtml(r.user_id || '') + '">Remove</button>' +
                        '</td>' +
                    '</tr>';
                }).join('');
                partBody.innerHTML =
                    '<table class="data-table evt-reg-table">' +
                        '<thead><tr><th>Name</th><th>Email</th><th>Registered</th><th></th></tr></thead>' +
                        '<tbody>' + rows + '</tbody>' +
                    '</table>';

                partBody.querySelectorAll('[data-remove-event]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var evId  = btn.getAttribute('data-remove-event');
                        var usrId = btn.getAttribute('data-remove-user');
                        if (!confirm('Remove this registration?')) return;
                        fetch('/api/backstage/events?op=remove_registration&id=' + encodeURIComponent(evId) + '&user_id=' + encodeURIComponent(usrId), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({}),
                        }).then(function (r) {
                            if (r.ok || r.status === 204) {
                                btn.closest('tr').remove();
                                var ev = events.find(function (e) { return e.id === evId; });
                                if (ev && typeof ev.registration_count === 'number') ev.registration_count--;
                                renderTable();
                            } else {
                                alert('Failed to remove registration.');
                            }
                        });
                    });
                });
            });
    }

    function initParticipantsModal() {
        partBackdrop = document.getElementById('participants-modal-backdrop');
        partBody     = document.getElementById('participants-modal-body');
        partClose    = document.getElementById('participants-modal-close');

        if (partClose) {
            partClose.addEventListener('click', function () {
                if (partBackdrop) { partBackdrop.hidden = true; partBackdrop.setAttribute('aria-hidden', 'true'); }
            });
        }
        if (partBackdrop) {
            partBackdrop.addEventListener('click', function (e) {
                if (e.target === partBackdrop) { partBackdrop.hidden = true; partBackdrop.setAttribute('aria-hidden', 'true'); }
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && partBackdrop && !partBackdrop.hidden) {
                partBackdrop.hidden = true;
                partBackdrop.setAttribute('aria-hidden', 'true');
            }
        });
    }

    // =========================================================================
    // Filters
    // =========================================================================
    function initFilters() {
        function bindFilter(id, setter) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', function () { setter(el.value); renderTable(); });
        }
        bindFilter('filter-status', function (v) { filterStatus = v; });
        bindFilter('filter-type',   function (v) { filterType   = v; });
        var searchEl = document.getElementById('filter-search');
        if (searchEl) {
            searchEl.addEventListener('input', function () { filterSearch = searchEl.value; renderTable(); });
        }
    }

    // =========================================================================
    // Public API — coverage update from edit sub-page bouncer.
    // =========================================================================
    global.DAEMS_EVENTS_COVERAGE_UPDATE = function (entityId, coverage) {
        if (!entityId || !coverage) return;
        var ev = events.find(function (e) { return e.id === entityId; });
        if (ev) ev.coverage = coverage;
        var cell = tbody && tbody.querySelector('tr[data-row-id="' + entityId + '"] .evt-coverage-cell');
        if (cell) cell.innerHTML = coverageBadge(entityId, coverage);
    };

    // =========================================================================
    // Boot
    // =========================================================================
    function boot() {
        tbody   = document.getElementById('events-tbody');
        countEl = document.getElementById('events-count');
        initTabs();
        initFilters();
        initParticipantsModal();
        renderTable();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}(window));
