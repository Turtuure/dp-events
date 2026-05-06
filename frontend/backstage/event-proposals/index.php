<?php
/**
 * Backstage — Event Proposals admin page.
 *
 * Lists member-submitted event proposals with columns:
 *   Author | Title | Event date | Source locale | Status | Submitted | Review
 *
 * Approve creates an Event row (+ events_i18n row in source_locale) via
 *   POST /api/v1/backstage/event-proposals/{id}/approve
 * Reject sets status = 'rejected' via
 *   POST /api/v1/backstage/event-proposals/{id}/reject { note }
 */

declare(strict_types=1);

use Daems\Frontend\ApiClient;

$pageTitle   = 'Event Proposals';
$activePage  = 'events';
$breadcrumbs = [
    ['label' => 'Events',    'url' => '/backstage/events'],
    ['label' => 'Proposals'],
];

/**
 * Fetch the backstage envelope ({items,total}) via raw cURL — the backstage
 * list endpoints return that envelope directly, without the `data` wrapper
 * ApiClient::get expects.
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

$proposals = $fetchList('/backstage/event-proposals');
$esc       = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="event-proposals-admin">

<div class="page-header">
    <div>
        <h1 class="page-header__title">Event Proposals</h1>
        <p class="page-header__subtitle">Review, approve, or reject member-submitted event proposals.</p>
    </div>
</div>

<div class="card evt-props-card">
    <div class="card__body">
        <div class="proj-meta-row"><strong id="ep-count"><?= (int) ($proposals['total'] ?? count($proposals['items'])) ?> proposal(s)</strong></div>
        <?php if (empty($proposals['items'])): ?>
            <p class="proj-empty">No pending event proposals.</p>
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

</div><!-- /.event-proposals-admin -->

<link rel="stylesheet" href="/modules/events/assets/backstage/proposal-modal.css">
<script>
window.DAEMS_EVENT_PROPOSALS = <?= json_encode($proposals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/modules/events/assets/backstage/proposal-modal.js"></script>

<?php
$pageContent = ob_get_clean();
require DAEMS_SITE_PUBLIC . '/pages/layout.php';
