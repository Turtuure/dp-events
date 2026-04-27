<?php
/**
 * Event detail page — Description + meta card section.
 *
 * Expects $event array to be in scope (set by detail.php).
 *
 * @package DaemsPublic
 */
$date = new DateTime($event['event_date']);
$formattedDate = $date->format('l, F j, Y');
$typeLabel = ['upcoming' => 'Upcoming', 'online' => 'Online', 'past' => 'Past'][$event['type']] ?? ucfirst($event['type']);

$u            = $_SESSION['user'] ?? null;
$isRegistered = $event['is_registered'] ?? false;
$isPast       = $event['type'] === 'past';
$description  = (string) ($event['description'] ?? '');
$location     = (string) ($event['location'] ?? '');
$eventTime    = (string) ($event['event_time'] ?? '');
$isOnline     = (bool) ($event['is_online'] ?? false);
?>
<section class="event-detail-content">
    <div class="container">
        <div class="row g-3 g-md-5">

            <div class="col-lg-8">
                <p class="event-detail-description"><?= nl2br(htmlspecialchars($description)) ?></p>
            </div>

            <div class="col-lg-4">
                <div class="event-detail-meta-card event-detail-meta-card--<?= htmlspecialchars($event['type']) ?>">
                    <h4>Event details</h4>
                    <ul class="event-detail-meta-list event-detail-meta-list--<?= htmlspecialchars($event['type']) ?>">
                        <li>
                            <i class="bi bi-calendar3"></i>
                            <span><?= $formattedDate ?></span>
                        </li>
                        <?php if ($eventTime !== ''): ?>
                        <li>
                            <i class="bi bi-clock"></i>
                            <span><?= htmlspecialchars($eventTime) ?></span>
                        </li>
                        <?php endif; ?>
                        <li>
                            <?php if ($isOnline): ?>
                            <i class="bi bi-camera-video"></i>
                            <?php else: ?>
                            <i class="bi bi-geo-alt"></i>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($location) ?></span>
                        </li>
                        <li>
                            <i class="bi bi-tag"></i>
                            <span><?= $typeLabel ?></span>
                        </li>
                    </ul>

                    <?php if (!$isPast): ?>
                    <div class="event-detail-meta-actions mt-3">
                        <?php if ($u !== null && !isViewAsGuest()): ?>
                            <?php if ($isRegistered): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 js-event-unregister-btn"
                                data-slug="<?= htmlspecialchars($event['slug']) ?>">
                                <i class="bi bi-calendar-x me-1"></i> Cancel Registration
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-dark btn-sm w-100 js-event-register-btn"
                                data-slug="<?= htmlspecialchars($event['slug']) ?>">
                                <i class="bi bi-calendar-check me-1"></i> Register
                            </button>
                            <?php endif; ?>
                        <?php else: ?>
                        <a href="/?signin=1" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bi bi-lock me-1"></i> Sign in to register
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>
