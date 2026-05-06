<?php
declare(strict_types=1);

$u = $_SESSION['user'] ?? null;
$isAdmin = $u && (!empty($u['is_platform_admin']) || ($u['role'] ?? '') === 'admin'
               || ($u['role'] ?? '') === 'global_system_administrator');
if (!$isAdmin) { header('Location: /'); exit; }

$pageTitle   = 'New event';
$activePage  = 'events';
$breadcrumbs = [
    ['label' => 'Events', 'url' => '/backstage/events'],
    ['label' => 'New'],
];

$event         = [];
$translations  = [];
$coverage      = [
    'fi_FI' => ['filled' => 0, 'total' => 3],
    'en_GB' => ['filled' => 0, 'total' => 3],
    'sw_TZ' => ['filled' => 0, 'total' => 3],
];
$primary_label = 'Create';
$show_delete   = false;
$contentClass  = 'content--no-scroll';

ob_start();
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">New event</h1>
        <p class="page-header__subtitle">Pick a type + date, then add the Finnish translation. English &amp; Swahili can be added afterwards.</p>
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

<div class="event-form-panel event-form-panel--full-height" data-mode="create">
    <?php include __DIR__ . '/../_form.php'; ?>
</div>

<link rel="stylesheet" href="/backstage/pages/shared/locale-cards.css">
<link rel="stylesheet" href="/modules/events/assets/backstage/event-form.css">
<script src="/backstage/pages/shared/locale-cards.js" defer></script>
<script src="/modules/events/assets/backstage/upload-widget.js" defer></script>
<script src="/modules/events/assets/backstage/event-form-page.js" defer></script>

<?php
$pageContent = ob_get_clean();
require DAEMS_SITE_PUBLIC . '/pages/layout.php';
