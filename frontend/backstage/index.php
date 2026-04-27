<?php
/**
 * Backstage Events Admin
 *
 * CRUD for events: list, create, edit, publish, archive, manage registrations.
 */

declare(strict_types=1);

if (!class_exists('ApiClient')) {
    require_once DAEMS_SITE_PUBLIC . '/../src/ApiClient.php';
}

$pageTitle  = 'Events';
$activePage = 'events';
$breadcrumbs = [];

// Server-side initial list — use direct curl because listEvents endpoint returns
// {items,total} without a 'data' envelope, so ApiClient::get would return null.
$initial = ['items' => [], 'total' => 0];
try {
    $token   = (string) ($_SESSION['token'] ?? '');
    $headers = ['Accept: application/json', 'Host: daems-platform.local'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ch = curl_init('http://daems-platform.local/api/v1/backstage/events');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300 && is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['items'])) {
            $initial = $decoded;
        }
    }
} catch (\Throwable $e) {
    // Silent — JS will show empty state.
}

// Pending Event Proposals count for the sub-page card.
$eventProposalsPending = 0;
try {
    $token   = (string) ($_SESSION['token'] ?? '');
    $headers = ['Accept: application/json', 'Host: daems-platform.local'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ch = curl_init('http://daems-platform.local/api/v1/backstage/event-proposals');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300 && is_string($raw)) {
        $decoded = json_decode($raw, true);
        $items = [];
        if (is_array($decoded)) {
            if (isset($decoded['items']) && is_array($decoded['items'])) {
                $items = $decoded['items'];
            } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
                $items = $decoded['data'];
            }
        }
        foreach ($items as $item) {
            if (is_array($item) && isset($item['status']) && $item['status'] === 'pending') {
                $eventProposalsPending++;
            }
        }
    }
} catch (\Throwable $e) {
    // Silent — card just renders without a badge.
}

$esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="events-admin">

<div class="page-header">
    <div>
        <h1 class="page-header__title">Events</h1>
        <p class="page-header__subtitle">Create, edit, publish, and manage event registrations.</p>
    </div>
    <div>
        <button type="button" class="btn btn--primary" id="btn-new-event">+ New event</button>
    </div>
</div>

<?php
$icon_calendar = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
$icon_pencil   = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
$icon_users    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$icon_inbox    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>';

$kpis = [
    ['kpi_id' => 'upcoming',          'label' => 'Upcoming',           'value' => '—', 'icon_html' => $icon_calendar, 'icon_variant' => 'blue',  'trend_label' => 'next 30 days',     'trend_direction' => 'muted'],
    ['kpi_id' => 'drafts',            'label' => 'Drafts',             'value' => '—', 'icon_html' => $icon_pencil,   'icon_variant' => 'gray',  'trend_label' => 'unpublished',      'trend_direction' => 'muted'],
    ['kpi_id' => 'registrations_30d', 'label' => 'Registrations (30d)','value' => '—', 'icon_html' => $icon_users,    'icon_variant' => 'green', 'trend_label' => 'last 30 days',     'trend_direction' => 'muted'],
    ['kpi_id' => 'pending_proposals', 'label' => 'Pending proposals',  'value' => '—', 'icon_html' => $icon_inbox,    'icon_variant' => 'amber', 'trend_label' => 'awaiting review',  'trend_direction' => 'warn'],
];
?>
<div class="kpis-grid">
  <?php foreach ($kpis as $kpi): daems_shared_partial('components/cards/kpi-card/kpi-card', $kpi); endforeach; ?>
</div>
<script src="/modules/events/assets/backstage/events-stats.js" defer></script>

<?php
    $cardTitle        = 'Event Proposals';
    $cardHref         = '/backstage/event-proposals';
    $cardPendingCount = $eventProposalsPending;
    $cardSubtitle     = 'Member-submitted events awaiting review';
    include DAEMS_SITE_PUBLIC . '/pages/backstage/partials/sub-page-card.php';
?>

<!-- Filter bar -->
<div class="card events-filters-card">
    <div class="card__body">
        <div class="events-filters-row">
            <label class="events-field">
                <span class="events-label">Status</span>
                <select id="filter-status">
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
            </label>
            <label class="events-field">
                <span class="events-label">Type</span>
                <select id="filter-type">
                    <option value="">All types</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="past">Past</option>
                    <option value="online">Online</option>
                </select>
            </label>
            <label class="events-field events-field--search">
                <span class="events-label">Search</span>
                <input type="text" id="filter-search" placeholder="Title&hellip;">
            </label>
        </div>
    </div>
</div>

<!-- Events table -->
<div class="card events-table-card">
    <div class="card__body events-table-body">
        <div id="events-total-row" class="events-meta-row">
            <strong id="events-count">Loading&hellip;</strong>
        </div>
        <table class="data-table events-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Translations</th>
                    <th>Registrations</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="events-tbody">
                <tr><td colspan="7" class="events-empty">Loading&hellip;</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Participants modal (hidden, populated by JS) -->
<div class="evt-modal-backdrop" id="participants-modal-backdrop" hidden aria-hidden="true">
    <div class="evt-modal" role="dialog" aria-modal="true" aria-labelledby="participants-modal-title">
        <div class="evt-modal__header">
            <h2 class="evt-modal__title" id="participants-modal-title">Registrations</h2>
            <button type="button" class="evt-modal__close" id="participants-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="evt-modal__body" id="participants-modal-body">
            <p>Loading&hellip;</p>
        </div>
    </div>
</div>

<!-- Create/Edit modal (built by event-modal.js) -->
<div id="event-modal-mount"></div>

</div><!-- /.events-admin -->

<link rel="stylesheet" href="/modules/events/assets/backstage/event-modal.css">
<link rel="stylesheet" href="/pages/backstage/shared/sub-page-card.css">
<link rel="stylesheet" href="/pages/backstage/shared/locale-cards.css">
<script>
window.DAEMS_EVENTS = <?= json_encode($initial, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/pages/backstage/shared/locale-cards.js"></script>
<script src="/modules/events/assets/backstage/upload-widget.js"></script>
<script src="/modules/events/assets/backstage/event-modal.js"></script>
<script>
(function () {
    'use strict';

    var events  = (window.DAEMS_EVENTS && Array.isArray(window.DAEMS_EVENTS.items))
        ? window.DAEMS_EVENTS.items.slice()
        : [];

    var filterStatus = '';
    var filterType   = '';
    var filterSearch = '';

    var tbody   = document.getElementById('events-tbody');
    var countEl = document.getElementById('events-count');

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
            return '<tr data-row-id="' + escHtml(id) + '">' +
                '<td>' + (e.event_date || '-') + '</td>' +
                '<td>' + escHtml(e.title || '') + '</td>' +
                '<td>' + typeLabel(e.type || '') + '</td>' +
                '<td>' + statusPill(e.status || 'draft') + '</td>' +
                '<td class="evt-coverage-cell">' + coverageBadge(id, e.coverage) + '</td>' +
                '<td>' +
                    '<button type="button" class="evt-reg-count btn--link" data-id="' + escHtml(id) + '" data-title="' + escHtml(e.title || '') + '">' +
                        (e.registration_count || 0) + ' registrant' + ((e.registration_count || 0) !== 1 ? 's' : '') +
                    '</button>' +
                '</td>' +
                '<td class="evt-actions">' +
                    '<button type="button" class="evt-action" data-action="edit" data-id="' + escHtml(id) + '" title="Edit">' +
                        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21a1 1 0 0 0 0-1.42l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.82z"/></svg>' +
                    '</button>' +
                    (canPub
                        ? '<button type="button" class="evt-action" data-action="publish" data-id="' + escHtml(id) + '" title="Publish">' +
                            '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm-1 14.5v-9l7 4.5z"/></svg>' +
                          '</button>'
                        : '') +
                    (canArc
                        ? '<button type="button" class="evt-action evt-action--warn" data-action="archive" data-id="' + escHtml(id) + '" title="Archive">' +
                            '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v3.01c0 .72.43 1.34 1 1.72V20c0 1.1 1.1 2 2 2h14c.9 0 2-.9 2-2V8.72c.57-.38 1-1 1-1.72V4c0-1.1-.9-2-2-2zm-5 12H9v-2h6v2zm5-8H4V4h16v2z"/></svg>' +
                          '</button>'
                        : '') +
                '</td>' +
            '</tr>';
        }).join('');

        // Wire action buttons
        tbody.querySelectorAll('[data-action="edit"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                var ev = events.find(function (e) { return e.id === id; });
                if (ev) window.EventModal.open('edit', ev);
            });
        });
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

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
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

    // Participants modal
    var partBackdrop = document.getElementById('participants-modal-backdrop');
    var partBody     = document.getElementById('participants-modal-body');
    var partClose    = document.getElementById('participants-modal-close');

    function openParticipants(eventId, title) {
        if (!partBackdrop || !partBody) return;
        partBody.innerHTML = '<p>Loading&hellip;</p>';
        partBackdrop.hidden = false;
        partBackdrop.setAttribute('aria-hidden', 'false');
        document.getElementById('participants-modal-title').textContent = 'Registrations — ' + title;

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
                                // Update cached count
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

    // New event button
    var btnNew = document.getElementById('btn-new-event');
    if (btnNew) {
        btnNew.addEventListener('click', function () { window.EventModal.open('create'); });
    }

    // Filter wiring
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

    // EventModal callback — reload events list after save
    window.DAEMS_EVENTS_RELOAD = function () { location.reload(); };

    // Coverage update — called by event-modal.js after a locale-cards save.
    // Updates the cached event's coverage map and repaints just that row's
    // badge without a full page reload.
    window.DAEMS_EVENTS_COVERAGE_UPDATE = function (entityId, coverage) {
        if (!entityId || !coverage) return;
        var ev = events.find(function (e) { return e.id === entityId; });
        if (ev) ev.coverage = coverage;
        var cell = tbody && tbody.querySelector('tr[data-row-id="' + entityId + '"] .evt-coverage-cell');
        if (cell) cell.innerHTML = coverageBadge(entityId, coverage);
    };

    // Initial render
    renderTable();
})();
</script>

<?php
$pageContent = ob_get_clean();
require DAEMS_SITE_PUBLIC . '/pages/backstage/layout.php';
