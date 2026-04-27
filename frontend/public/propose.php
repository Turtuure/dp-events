<?php
/**
 * Member-facing event proposal form.
 *
 * The `source_locale` is a hidden input populated from I18n::locale().
 * When an admin later approves the proposal, the backend creates the
 * event with its `events_i18n` row in that locale; other locales remain
 * empty until the admin translates them.
 *
 * Only authenticated members can submit.
 *
 * @package DaemsPublic
 */

$u = $_SESSION['user'] ?? null;
$memberRoles = ['member', 'supporter', 'administrator', 'system_administrator', 'global_system_administrator'];
if ($u === null || !in_array($u['role'] ?? '', $memberRoles, true)) {
    header('Location: /?signin=1');
    exit;
}

$locale = I18n::locale();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($locale, 0, 2)) ?>">
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?= I18n::e('events.propose.title') ?> — Daem Society</title>

        <link rel="shortcut icon" href="/assets/img/brand/daems-favicon.svg" />

        <link rel="stylesheet" href="/assets/css/bootstrap.min.css" />
        <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css" />
        <link rel="stylesheet" href="/assets/css/daems.css" />
        <link rel="stylesheet" href="/assets/css/daems-search.css" />
    </head>
    <body>

        <?php include DAEMS_SITE_PUBLIC . '/partials/top-nav.php'; ?>

        <main class="forum-subpage">
            <div class="container" style="max-width:720px">

                <div class="project-form-header">
                    <a href="/events" class="event-detail-back">
                        <i class="bi bi-arrow-left"></i> <?= I18n::e('btn.back') ?>
                    </a>
                    <h1><?= I18n::e('events.propose.heading') ?></h1>
                    <p class="text-muted" style="font-size:.95rem;">
                        <?= I18n::e('events.propose.intro', ['locale' => $locale]) ?>
                    </p>
                </div>

                <div class="project-form-error d-none" id="event-propose-error" role="alert"></div>

                <div class="forum-reply-card">
                    <form id="event-propose-form" autocomplete="off">
                        <input type="hidden" name="source_locale" value="<?= htmlspecialchars($locale, ENT_QUOTES) ?>">

                        <div class="mb-3">
                            <label for="ep-title" class="form-label">
                                <?= I18n::e('events.propose.field_title') ?>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="ep-title" name="title" class="form-control" required maxlength="255" />
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="ep-date" class="form-label">
                                    <?= I18n::e('events.propose.field_date') ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="date" id="ep-date" name="event_date" class="form-control" required />
                            </div>
                            <div class="col-sm-6">
                                <label for="ep-time" class="form-label">
                                    <?= I18n::e('events.propose.field_time') ?>
                                </label>
                                <input type="text" id="ep-time" name="event_time" class="form-control" placeholder="18:00" />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="ep-location" class="form-label">
                                <?= I18n::e('events.propose.field_location') ?>
                            </label>
                            <input type="text" id="ep-location" name="location" class="form-control" />
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="ep-online" name="is_online" value="1" />
                            <label class="form-check-label" for="ep-online">
                                <?= I18n::e('events.propose.field_online') ?>
                            </label>
                        </div>

                        <div class="mb-4">
                            <label for="ep-description" class="form-label">
                                <?= I18n::e('events.propose.field_description') ?>
                                <span class="text-danger">*</span>
                            </label>
                            <textarea id="ep-description" name="description" class="form-control" rows="8" required></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/events" class="btn btn-outline-secondary px-4"><?= I18n::e('btn.cancel') ?></a>
                            <button type="submit" class="btn btn-dark px-4">
                                <i class="bi bi-send me-1"></i> <?= I18n::e('events.propose.submit') ?>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </main>

        <?php include DAEMS_SITE_PUBLIC . '/partials/footer.php'; ?>

        <script src="/assets/js/bootstrap.bundle.min.js"></script>
        <script src="/assets/js/daems.js"></script>
        <script src="/assets/js/daems-search.js"></script>
        <script>
        (function () {
            const form = document.getElementById('event-propose-form');
            const err  = document.getElementById('event-propose-error');
            if (!form) return;

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                err.classList.add('d-none');
                err.textContent = '';

                const fd = new FormData(form);
                const params = new URLSearchParams();
                for (const [k, v] of fd.entries()) {
                    params.append(k, typeof v === 'string' ? v : String(v));
                }
                // Checkbox: send '1' when present, '0' otherwise.
                params.set('is_online', form.querySelector('#ep-online').checked ? '1' : '0');

                try {
                    const res = await fetch('/api/event/propose', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'application/json',
                        },
                        body: params.toString(),
                    });
                    const body = await res.json().catch(() => ({}));
                    if (res.ok) {
                        window.location.href = '/events?proposed=1';
                        return;
                    }
                    err.textContent = body.error || 'Submission failed.';
                    err.classList.remove('d-none');
                } catch (ex) {
                    err.textContent = 'Network error.';
                    err.classList.remove('d-none');
                }
            });
        })();
        </script>
    </body>
</html>
