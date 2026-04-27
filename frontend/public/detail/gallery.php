<?php
/**
 * Event detail page — Photo gallery + lightbox.
 *
 * Renders only when $event['gallery'] contains at least one photo.
 * Expects $event array to be in scope (set by detail.php).
 *
 * @package DaemsPublic
 */
if (empty($event['gallery'])) {
    return;
}
$count = count($event['gallery']);
?>
<section class="event-gallery">
    <div class="container">

        <div class="event-gallery-header">
            <h2>Photos</h2>
            <span class="event-gallery-count"><?= $count ?> <?= $count === 1 ? 'photo' : 'photos' ?></span>
        </div>

        <div class="row g-3">
            <?php foreach ($event['gallery'] as $i => $photo): ?>
            <?php $src = is_array($photo) ? $photo['src'] : $photo; ?>
            <?php $alt = is_array($photo) ? htmlspecialchars($photo['alt'] ?? '') : htmlspecialchars(((string) ($event['title'] ?? '')) . ' — photo ' . ($i + 1)); ?>
            <div class="col-6 col-md-4">
                <div
                    class="event-gallery-thumb"
                    data-src="<?= htmlspecialchars($src) ?>"
                    data-alt="<?= $alt ?>"
                    role="button"
                    tabindex="0"
                    aria-label="Open photo <?= $i + 1 ?> in full size"
                >
                    <img src="<?= htmlspecialchars($src) ?>" alt="<?= $alt ?>" loading="lazy" />
                    <div class="event-gallery-thumb-overlay">
                        <i class="bi bi-zoom-in"></i>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- Gallery lightbox modal -->
<div class="modal fade gallery-lightbox" id="galleryLightbox" tabindex="-1" aria-label="Photo viewer" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <button class="gallery-lightbox-nav gallery-lightbox-prev" id="gallery-prev" aria-label="Previous photo">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <img src="" alt="" class="gallery-lightbox-img" id="gallery-lightbox-img" />
                <button class="gallery-lightbox-nav gallery-lightbox-next" id="gallery-next" aria-label="Next photo">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>

        </div>
    </div>
</div>
