<?php
/**
 * Backstage Events Admin
 *
 * Two KPI card-tabs (Events / Proposals) drive the panels below.
 * Clicking a card swaps the visible panel — no separate tab nav row.
 *   - events    : list + CRUD + status + registrations.
 *   - proposals : review pending event proposals, approve/reject (review modal).
 *
 * Create/edit moved to dedicated sub-pages:
 *   /backstage/events/new           → modules/events/frontend/backstage/new/index.php
 *   /backstage/events/edit?id=…     → modules/events/frontend/backstage/edit/index.php
 */

declare(strict_types=1);

use Daems\Frontend\ApiClient;

$pageTitle  = 'backstage.title.events';
$activePage = 'events';
$breadcrumbs = [];

$activeTab = $_GET['tab'] ?? 'events';
if (!in_array($activeTab, ['events', 'proposals'], true)) {
    $activeTab = 'events';
}

/**
 * Fetch {items, total} from backend via raw cURL — backstage list endpoints
 * return the envelope directly without a 'data' wrapper, so ApiClient::get
 * would return null.
 */
$fetchList = static function (string $path): array {
    $token   = (string) ($_SESSION['token'] ?? '');
    $headers = ['Accept: application/json', 'Host: daems-platform.local'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ch = curl_init('http://daems-platform.local/api/v1' . $path);
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
            return $decoded;
        }
    }
    return ['items' => [], 'total' => 0];
};

$initial   = $fetchList('/backstage/events');
$proposals = $fetchList('/backstage/event-proposals');

// Pending Event Proposals count — drives the Proposals card-tab value.
$eventProposalsPending = 0;
foreach (($proposals['items'] ?? []) as $item) {
    if (is_array($item) && isset($item['status']) && $item['status'] === 'pending') {
        $eventProposalsPending++;
    }
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
        <a href="/backstage/events/new" class="btn btn--primary" id="btn-new-event">+ New event</a>
    </div>
</div>

<?php
// SVG icons for the card-tabs.
$icon_calendar = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
$icon_inbox    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>';

// Server-rendered placeholders. events-stats.js refines the Events card
// subtitle ("X drafts • Y reg (30d)") once the stats endpoint responds.
$eventsTotal = (int) ($initial['total'] ?? count($initial['items'] ?? []));

$cardTabs = [
    [
        'tab'         => 'events',
        'label'       => 'Events',
        'value'       => $eventsTotal,
        'subtitle'    => 'all statuses',
        'icon_html'   => $icon_calendar,
        'icon_variant'=> 'blue',
    ],
    [
        'tab'         => 'proposals',
        'label'       => 'Proposals',
        'value'       => $eventProposalsPending,
        'subtitle'    => 'awaiting review',
        'icon_html'   => $icon_inbox,
        'icon_variant'=> 'amber',
    ],
];
?>
<div class="kpis-grid kpis-grid--tabs" role="tablist" aria-label="Events sections">
  <?php foreach ($cardTabs as $t): $isActive = $activeTab === $t['tab']; ?>
    <button type="button"
            class="kpi-card kpi-card--tab<?= $isActive ? ' is-active' : '' ?>"
            role="tab"
            id="evt-tab-<?= $esc($t['tab']) ?>"
            data-tab="<?= $esc($t['tab']) ?>"
            data-kpi="<?= $esc($t['tab']) ?>"
            aria-selected="<?= $isActive ? 'true' : 'false' ?>"
            aria-controls="evt-panel-<?= $esc($t['tab']) ?>">
      <div class="kpi-card__head">
        <div>
          <div class="kpi-card__label"><?= $esc($t['label']) ?></div>
          <div class="kpi-card__value"><?= $esc((string) $t['value']) ?></div>
          <div class="kpi-card__trend kpi-card__trend--muted" data-tab-subtitle="<?= $esc($t['tab']) ?>">
            <?= $esc($t['subtitle']) ?>
          </div>
        </div>
        <span class="kpi-card__icon kpi-card__icon--<?= $esc($t['icon_variant']) ?>"><?= $t['icon_html'] /* trusted inline SVG */ ?></span>
      </div>
    </button>
  <?php endforeach; ?>
</div>
<script src="/modules/events/assets/backstage/events-stats.js" defer></script>

<!-- Events panel -->
<section class="evt-tab-content <?= $activeTab === 'events' ? 'is-active' : '' ?>"
         data-tab-content="events" id="evt-panel-events"
         role="tabpanel" aria-labelledby="evt-tab-events">

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
</section>

<!-- Proposals panel -->
<section class="evt-tab-content <?= $activeTab === 'proposals' ? 'is-active' : '' ?>"
         data-tab-content="proposals" id="evt-panel-proposals"
         role="tabpanel" aria-labelledby="evt-tab-proposals">
    <div class="card evt-props-card">
        <div class="card__body">
            <div class="events-meta-row">
                <strong id="ep-count"><?= (int) ($proposals['total'] ?? count($proposals['items'])) ?> proposal(s)</strong>
            </div>
            <?php if (empty($proposals['items'])): ?>
                <p class="events-empty">No pending event proposals.</p>
            <?php else: ?>
                <table class="data-table evt-props-table" id="event-proposals-table">
                    <thead>
                        <tr>
                            <th>Author</th>
                            <th>Title</th>
                            <th>Event date</th>
                            <th>Locale</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proposals['items'] as $p): ?>
                            <tr id="ep-<?= $esc((string) ($p['id'] ?? '')) ?>">
                                <td><?= $esc((string) ($p['author_name'] ?? '')) ?></td>
                                <td><strong><?= $esc((string) ($p['title'] ?? '')) ?></strong></td>
                                <td><?= $esc((string) ($p['event_date'] ?? '')) ?></td>
                                <td><span class="locale-badge"><?= $esc((string) ($p['source_locale'] ?? 'fi_FI')) ?></span></td>
                                <td><span class="status-pill status-pill--<?= $esc((string) ($p['status'] ?? 'pending')) ?>"><?= $esc((string) ($p['status'] ?? 'pending')) ?></span></td>
                                <td><?= $esc(substr((string) ($p['created_at'] ?? ''), 0, 16)) ?></td>
                                <td class="evt-props-actions">
                                    <?php if (($p['status'] ?? 'pending') === 'pending'): ?>
                                        <button type="button" class="btn btn--ghost btn--sm"
                                                data-review='<?= $esc(json_encode($p, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>'>
                                            Review
                                        </button>
                                    <?php else: ?>
                                        <span class="evt-props-muted">Decided</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>

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

</div><!-- /.events-admin -->

<link rel="stylesheet" href="/modules/events/assets/backstage/event-modal.css">
<link rel="stylesheet" href="/modules/events/assets/backstage/proposal-modal.css">
<link rel="stylesheet" href="/modules/events/assets/backstage/events-admin.css">
<link rel="stylesheet" href="/backstage/pages/shared/locale-cards.css">
<script>
window.DAEMS_EVENTS = <?= json_encode($initial, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.DAEMS_EVENTS_TAB = <?= json_encode([
    'activeTab' => $activeTab,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/modules/events/assets/backstage/events-admin.js"></script>
<script src="/modules/events/assets/backstage/proposal-modal.js"></script>

<?php
$pageContent = ob_get_clean();
require DAEMS_SITE_PUBLIC . '/pages/layout.php';
