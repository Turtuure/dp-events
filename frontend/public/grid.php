<?php
/**
 * Events page — Filter + event card grid.
 *
 * Consumes the locale-aware API. ApiClient sends Accept-Language derived
 * from I18n::locale(); the backend returns title/location/description in
 * the negotiated locale with per-field fallback to en_GB. `*_fallback`
 * and `*_missing` markers are present on the response but intentionally
 * NOT rendered to end users — admins see coverage in the backstage.
 *
 * @package DaemsPublic
 */
$events = ApiClient::get('/events') ?? [];
?>
<section class="events-grid">
    <div class="container">

        <div class="project-filters">
            <button class="project-filter-btn event-filter-btn active" data-filter="all">All</button>
            <button class="project-filter-btn event-filter-btn" data-filter="upcoming">Upcoming</button>
            <button class="project-filter-btn event-filter-btn" data-filter="past">Past</button>
            <button class="project-filter-btn event-filter-btn" data-filter="online">Online</button>
        </div>

        <div class="row g-4" id="events-list">

            <?php foreach ($events as $event):
                $d        = new DateTimeImmutable($event['event_date']);
                $hasImage = !empty($event['hero_image']);
                $typeClass = 'event-card-header--' . htmlspecialchars($event['type']);
                $typeLabel = ['upcoming' => 'Upcoming', 'past' => 'Past', 'online' => 'Online'][$event['type']] ?? ucfirst($event['type']);
                $title       = (string) ($event['title'] ?? '');
                $location    = (string) ($event['location'] ?? '');
                $description = (string) ($event['description'] ?? '');
                $eventTime   = (string) ($event['event_time'] ?? '');
                $isOnline    = (bool) ($event['is_online'] ?? false);
            ?>
            <div class="col-md-6 col-lg-4 event-card-wrap" data-category="<?= htmlspecialchars($event['type']) ?>">
                <a href="/events/<?= htmlspecialchars($event['slug']) ?>" class="event-card">
                    <div class="event-card-header <?= $typeClass ?><?= $hasImage ? ' event-card-header--has-image' : '' ?>">
                        <?php if ($hasImage): ?>
                        <img src="<?= htmlspecialchars($event['hero_image']) ?>" alt="<?= htmlspecialchars($title) ?>" class="event-card-img" />
                        <div class="event-card-overlay"></div>
                        <?php endif; ?>
                        <div class="event-date">
                            <span class="event-date-day"><?= $d->format('d') ?></span>
                            <span class="event-date-month"><?= $d->format('M') ?></span>
                            <span class="event-date-year"><?= $d->format('Y') ?></span>
                        </div>
                        <span class="event-type-badge"><?= $typeLabel ?></span>
                    </div>
                    <div class="event-card-body">
                        <h3><?= htmlspecialchars($title) ?></h3>
                        <p class="event-meta">
                            <?php if ($isOnline): ?>
                            <i class="bi bi-camera-video"></i>
                            <?php else: ?>
                            <i class="bi bi-geo-alt"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($location) ?>
                            <?php if ($eventTime !== ''): ?>
                            <span class="event-meta-sep">·</span>
                            <i class="bi bi-clock"></i> <?= htmlspecialchars($eventTime) ?>
                            <?php endif; ?>
                        </p>
                        <p class="event-desc"><?= htmlspecialchars(mb_strimwidth($description, 0, 140, '…')) ?></p>
                        <span class="project-link">View details <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</section>
