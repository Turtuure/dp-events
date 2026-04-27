<?php
$date = new DateTime($event['event_date']);
$formattedDate = $date->format('F j, Y');
$typeLabel = ['upcoming' => 'Upcoming', 'online' => 'Online', 'past' => 'Past'][$event['type']] ?? ucfirst($event['type']);

$u              = $_SESSION['user'] ?? null;
$isRegistered   = $event['is_registered'] ?? false;
$participantCount = $event['participant_count'] ?? 0;
$isPast         = $event['type'] === 'past';
$title          = (string) ($event['title'] ?? '');
$location       = (string) ($event['location'] ?? '');
$eventTime      = (string) ($event['event_time'] ?? '');
$isOnline       = (bool) ($event['is_online'] ?? false);
?>
<section class="event-detail-hero event-detail-hero--<?= htmlspecialchars($event['type']) ?><?= $event['hero_image'] ? ' event-detail-hero--has-image' : '' ?>">
    <?php if ($event['hero_image']): ?>
    <img
        src="<?= htmlspecialchars($event['hero_image']) ?>"
        alt="<?= htmlspecialchars($title) ?>"
        class="event-detail-hero-bg"
        loading="eager"
    />
    <div class="event-detail-hero-overlay"></div>
    <?php endif; ?>

    <div class="container event-detail-hero-content">
        <a href="/events" class="event-detail-back">
            <i class="bi bi-arrow-left"></i> Back to Events
        </a>
        <span class="about-tag event-detail-tag"><?= $typeLabel ?></span>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p class="event-detail-hero-meta">
            <i class="bi bi-calendar3"></i> <?= $formattedDate ?>
            <?php if ($eventTime !== ''): ?>
            <span class="event-meta-sep">·</span>
            <i class="bi bi-clock"></i> <?= htmlspecialchars($eventTime) ?>
            <?php endif; ?>
            <span class="event-meta-sep">·</span>
            <?php if ($isOnline): ?>
            <i class="bi bi-camera-video"></i> <?= htmlspecialchars($location) ?>
            <?php else: ?>
            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($location) ?>
            <?php endif; ?>
            <span class="event-meta-sep ms-3">·</span>
            <i class="bi bi-people me-1"></i>
            <span id="event-participant-count"><?= $participantCount ?></span> registered
        </p>

        <?php if (!$isPast): ?>
        <div class="event-hero-actions mt-3 d-flex gap-2 flex-wrap">
            <?php if ($u !== null && !isViewAsGuest()): ?>
                <?php if ($isRegistered): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm px-4 js-event-unregister-btn"
                    data-slug="<?= htmlspecialchars($event['slug']) ?>">
                    <i class="bi bi-calendar-x me-1"></i> Cancel Registration
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-dark btn-sm px-4 js-event-register-btn"
                    data-slug="<?= htmlspecialchars($event['slug']) ?>">
                    <i class="bi bi-calendar-check me-1"></i> Register
                </button>
                <?php endif; ?>
            <?php else: ?>
            <a href="/?signin=1" class="btn btn-outline-secondary btn-sm px-4">
                <i class="bi bi-lock me-1"></i> Sign in to register
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
