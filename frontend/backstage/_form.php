<?php
/**
 * Shared event create/edit form.
 *
 * Renders a 2-column layout:
 *   LEFT  — translation locale-cards grid (3 locales × title/location/description)
 *           per-locale fields. Save is per-locale via locale-cards component.
 *   RIGHT — chrome metadata: status, type, date+time, online toggle, slug,
 *           publish toggle, hero image upload, gallery image upload.
 *           Action buttons (Save, Publish/Set-to-Draft, Archive) sit at the bottom.
 *
 * Variables expected before include:
 * @var array  $event          Pre-fill values, or [] for an empty form.
 *                              Keys: id, slug, type, status, event_date, event_time,
 *                              is_online, hero_image, gallery_json.
 * @var array  $translations   Per-locale translations map (e.g. ['fi_FI' => ['title' => ..., 'location' => ..., 'description' => ...]]).
 *                              Empty array on create; populated on edit.
 * @var array  $coverage       Per-locale coverage map (e.g. ['fi_FI' => ['filled' => 3, 'total' => 3]]).
 * @var string $primary_label  Submit button label ('Create' for new, 'Save' for edit).
 * @var bool   $show_delete    If true, render the Archive button (event has no hard-delete; archive is the closest analogue).
 */
declare(strict_types=1);

$event         = $event         ?? [];
$translations  = $translations  ?? [];
$coverage      = $coverage      ?? [];
$primary_label = $primary_label ?? 'Create';
$show_delete   = $show_delete   ?? false;

$v = static function (string $key) use ($event): string {
    $val = $event[$key] ?? '';
    return is_string($val) ? $val : (string) $val;
};
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$eventId   = (string) ($event['id'] ?? '');
$status    = $v('status') !== '' ? $v('status') : 'draft';
$type      = $v('type');
$eventDate = $v('event_date');
$eventTime = $v('event_time');
$isOnline  = !empty($event['is_online']);
$heroImage = $v('hero_image');
$gallery   = isset($event['gallery_json']) && is_array($event['gallery_json']) ? $event['gallery_json'] : [];
?>
<div class="event-form" id="event-form">
    <div class="event-form__cols">

        <!-- LEFT COLUMN — Translations (locale-cards) -->
        <div class="event-form__col event-form__col--left">

            <div class="event-form__field event-form__field--grow">
                <span class="event-form__label">
                    Translations
                    <span class="event-form__hint">title · location · description per locale</span>
                </span>

                <div class="locale-cards-container event-form__locales"
                     data-kind="event"
                     data-entity-id="<?= $esc($eventId) ?>">
                    <div class="locale-cards-grid" role="tablist" aria-label="Locale translations"></div>
                    <div class="locale-cards-editor">
                        <div class="locale-cards-fields"></div>
                        <div class="locale-cards-actions">
                            <button type="button" class="btn btn--outline locale-cards-save">Save</button>
                            <span class="locale-cards-status" aria-live="polite"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN — chrome metadata + actions -->
        <div class="event-form__col event-form__col--right">

            <div class="event-form__col-scroll">

                <div class="event-form__field">
                    <label class="event-form__label" for="ef-status">Status</label>
                    <select id="ef-status" name="status" class="event-form__input">
                        <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $val => $lbl): ?>
                            <option value="<?= $esc($val) ?>"<?= $val === $status ? ' selected' : '' ?>><?= $esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="event-form__field">
                    <label class="event-form__label event-form__label--required" for="ef-type">Type</label>
                    <select id="ef-type" name="type" class="event-form__input" required>
                        <option value="">— select —</option>
                        <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'online' => 'Online'] as $val => $lbl): ?>
                            <option value="<?= $esc($val) ?>"<?= $val === $type ? ' selected' : '' ?>><?= $esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="event-form__error" id="ef-err-type"></span>
                </div>

                <div class="event-form__field">
                    <label class="event-form__label event-form__label--required" for="ef-date">
                        Date
                        <span class="event-form__hint">YYYY-MM-DD</span>
                    </label>
                    <div class="event-form__datetime-row">
                        <input type="date" id="ef-date" name="event_date"
                               class="event-form__input"
                               value="<?= $esc($eventDate) ?>" required>
                        <input type="time" id="ef-time" name="event_time"
                               class="event-form__input event-form__input--time"
                               value="<?= $esc($eventTime) ?>"
                               aria-label="Time (optional)">
                    </div>
                    <span class="event-form__error" id="ef-err-event_date"></span>
                </div>

                <div class="event-form__field">
                    <label class="event-form__label" for="ef-slug">
                        Slug
                        <span class="event-form__hint">read-only · derived from title</span>
                    </label>
                    <input type="text" id="ef-slug" name="slug"
                           class="event-form__input"
                           value="<?= $esc($v('slug')) ?>"
                           readonly>
                </div>

                <div class="event-form__field">
                    <span class="event-form__label">Online event</span>
                    <label class="toggle-switch" for="ef-online">
                        <input type="checkbox" id="ef-online" name="is_online"
                               <?= $isOnline ? 'checked' : '' ?>>
                        <span class="toggle-switch__track" aria-hidden="true">
                            <span class="toggle-switch__thumb"></span>
                        </span>
                        <span class="toggle-switch__label" data-on="Online (no physical location)" data-off="In-person (location required)">
                            <?= $isOnline ? 'Online (no physical location)' : 'In-person (location required)' ?>
                        </span>
                    </label>
                </div>

                <?php if (!$show_delete): ?>
                <div class="event-form__field">
                    <span class="event-form__label">Publish on save</span>
                    <label class="toggle-switch" for="ef-publish-now">
                        <input type="checkbox" id="ef-publish-now" name="publish_immediately">
                        <span class="toggle-switch__track" aria-hidden="true">
                            <span class="toggle-switch__thumb"></span>
                        </span>
                        <span class="toggle-switch__label" data-on="Publish immediately" data-off="Save as draft">
                            Save as draft
                        </span>
                    </label>
                </div>
                <?php endif; ?>

                <div class="event-form__field">
                    <span class="event-form__label">
                        Hero image
                        <span class="event-form__hint">single image · JPEG/PNG/WebP/GIF · max 10 MiB</span>
                    </span>
                    <div id="ef-hero-container" class="upload-widget"></div>
                </div>

                <div class="event-form__field">
                    <span class="event-form__label">
                        Gallery images
                        <span class="event-form__hint">up to 14 images</span>
                    </span>
                    <div id="ef-gallery-container" class="upload-widget"></div>
                </div>
            </div>

            <div class="event-form__actions">
                <?php if ($show_delete): ?>
                    <button type="button" class="btn btn--danger-outline event-form__delete" id="ef-archive">Archive</button>
                <?php else: ?>
                    <a href="/backstage/events" class="btn btn--danger-outline">Cancel</a>
                <?php endif; ?>
                <button type="button" class="btn btn--outline" id="ef-save"><?= $esc($primary_label) ?></button>
                <?php if ($show_delete): ?>
                    <button type="button" class="btn btn--success-outline" id="ef-publish">
                        <?= $status === 'published' ? 'Set to Draft' : 'Publish' ?>
                    </button>
                <?php endif; ?>
            </div>

            <div id="ef-error-mount" class="event-form__error-banner" style="display:none;"></div>
        </div>

    </div>
</div>

<script>
window.DAEMS_EVENT_FORM = {
    id:           <?= json_encode($eventId, JSON_UNESCAPED_SLASHES) ?>,
    translations: <?= json_encode($translations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    coverage:     <?= json_encode($coverage, JSON_UNESCAPED_SLASHES) ?>,
    heroImage:    <?= json_encode($heroImage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    gallery:      <?= json_encode($gallery, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
};
</script>
