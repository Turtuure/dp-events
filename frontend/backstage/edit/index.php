<?php
declare(strict_types=1);

if (!class_exists('ApiClient')) {
    require_once DAEMS_SITE_PUBLIC . '/../src/ApiClient.php';
}

$u = $_SESSION['user'] ?? null;
$isAdmin = $u && (!empty($u['is_platform_admin']) || ($u['role'] ?? '') === 'admin'
               || ($u['role'] ?? '') === 'global_system_administrator');
if (!$isAdmin) { header('Location: /'); exit; }

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    header('Location: /backstage/events');
    exit;
}

// Server-side fetch so the form pre-fills synchronously (no flash of empty fields).
// GET /backstage/events/{id}/translations returns chrome + per-locale translations + coverage.
$event = ApiClient::get('/backstage/events/' . rawurlencode($id) . '/translations');
if (!is_array($event) || empty($event)) {
    http_response_code(404);
    require DAEMS_SITE_PUBLIC . '/pages/errors/404.php';
    exit;
}

$translations = is_array($event['translations'] ?? null) ? $event['translations'] : [];
$coverage     = is_array($event['coverage']     ?? null) ? $event['coverage']     : [];

// Title for the page subheader: prefer fi_FI, then en_GB, then sw_TZ.
$displayTitle = '(untitled)';
foreach (['fi_FI', 'en_GB', 'sw_TZ'] as $loc) {
    if (!empty($translations[$loc]['title'])) {
        $displayTitle = (string) $translations[$loc]['title'];
        break;
    }
}

$pageTitle   = 'Edit event';
$activePage  = 'events';
$breadcrumbs = [
    ['label' => 'Events', 'url' => '/backstage/events'],
    ['label' => 'Edit'],
];

$primary_label = 'Save';
$show_delete   = true;
$contentClass  = 'content--no-scroll';

$titleSafe = htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8');
$idSafe    = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">Edit event</h1>
        <p class="page-header__subtitle"><?= $titleSafe ?></p>
    </div>
    <div>
        <a href="/backstage/events" class="btn btn--outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back to events
        </a>
    </div>
</div>

<div class="event-form-panel event-form-panel--full-height" data-mode="edit" data-event-id="<?= $idSafe ?>">
    <?php include __DIR__ . '/../_form.php'; ?>
</div>

<link rel="stylesheet" href="/pages/backstage/shared/locale-cards.css">
<link rel="stylesheet" href="/modules/events/assets/backstage/event-form.css">
<script src="/pages/backstage/shared/locale-cards.js" defer></script>
<script src="/modules/events/assets/backstage/upload-widget.js" defer></script>
<script src="/modules/events/assets/backstage/event-form-page.js" defer></script>

<?php
$pageContent = ob_get_clean();
require DAEMS_SITE_PUBLIC . '/pages/backstage/layout.php';
