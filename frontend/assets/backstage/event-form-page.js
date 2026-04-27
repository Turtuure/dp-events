/**
 * Event create/edit sub-page handlers.
 *
 * Buttons:
 *   - Save     (#ef-save)     — POST chrome (type, event_date, event_time, is_online).
 *                                On create also seeds fi_FI title/location/description
 *                                from the locale-cards draft so the row can be created.
 *                                In edit, also patches hero_image / gallery_json from
 *                                the upload widgets so URLs get persisted.
 *   - Publish  (#ef-publish)  — only in edit mode: toggles status published <-> draft.
 *   - Archive  (#ef-archive)  — only in edit mode: sets status to 'archived' after confirm.
 *                                (Events have no hard-delete endpoint; archive is the
 *                                closest analogue and matches the Projects pattern.)
 *   - Cancel   (anchor)       — only in create mode, navigates back to list.
 *
 * Translations save themselves per-locale via the locale-cards component
 * (POST /api/v1/backstage/events/{id}/translations/{locale}).
 *
 * Image upload:
 *   - Hero image    — single, via UploadWidget, POST /api/backstage/event-upload?id={id}
 *                     Persisted on save via chrome update body { hero_image: <url> }.
 *   - Gallery       — multi, via _GalleryWidget (separate state from UploadWidget).
 *                     Persisted on save via chrome update body { gallery_json: [<url>, …] }.
 *   - In CREATE mode the widgets queue files locally; uploads happen after the row
 *     exists (post-create), then a follow-up update writes the URLs to the row.
 *
 * Reads mode + id from the parent .event-form-panel data attributes:
 *   data-mode      : 'create' | 'edit'
 *   data-event-id  : present only when mode === 'edit'
 *
 * Pre-fill values for translations + coverage + hero/gallery are bridged
 * through window.DAEMS_EVENT_FORM (set by _form.php from server-side state).
 */
(function () {
    'use strict';

    var panel = document.querySelector('.event-form-panel');
    if (!panel) return;

    var mode    = panel.getAttribute('data-mode')     || 'create';
    var eventId = panel.getAttribute('data-event-id') || '';

    var bridge = window.DAEMS_EVENT_FORM || {
        id: eventId, translations: {}, coverage: {}, heroImage: '', gallery: []
    };

    var statusEl   = document.getElementById('ef-status');
    var typeEl     = document.getElementById('ef-type');
    var dateEl     = document.getElementById('ef-date');
    var timeEl     = document.getElementById('ef-time');
    var slugEl     = document.getElementById('ef-slug');
    var onlineEl   = document.getElementById('ef-online');
    var publishNowEl = document.getElementById('ef-publish-now');
    var saveBtn    = document.getElementById('ef-save');
    var publishBtn = document.getElementById('ef-publish');
    var archiveBtn = document.getElementById('ef-archive');
    var errorEl    = document.getElementById('ef-error-mount');

    var heroContainer    = document.getElementById('ef-hero-container');
    var galleryContainer = document.getElementById('ef-gallery-container');

    // Snapshot starting status so edit-mode can decide whether to hit the
    // publish/archive endpoints alongside the chrome POST.
    var initialStatus = statusEl ? statusEl.value : 'draft';

    var labels = {
        save:    saveBtn    ? saveBtn.textContent.trim()    : '',
        publish: publishBtn ? publishBtn.textContent.trim() : '',
        archive: archiveBtn ? archiveBtn.textContent.trim() : '',
    };

    // ── Toggle helper-label swap (Online + Publish-now) ──────────────────
    function wireToggleLabel(toggleEl) {
        if (!toggleEl) return;
        var label = toggleEl.closest('.event-form__field')
            ? toggleEl.closest('.event-form__field').querySelector('.toggle-switch__label')
            : null;
        if (!label) return;
        var onText  = label.getAttribute('data-on')  || label.textContent;
        var offText = label.getAttribute('data-off') || label.textContent;
        var sync = function () { label.textContent = toggleEl.checked ? onText : offText; };
        toggleEl.addEventListener('change', sync);
        sync();
    }
    wireToggleLabel(onlineEl);
    wireToggleLabel(publishNowEl);

    // ── Locale-cards mount ──────────────────────────────────────────────
    function mountLocaleCards() {
        var container = panel.querySelector('.locale-cards-container');
        if (!container || !window.LocaleCards) return;
        window.LocaleCards.mount(container, {
            kind:         'event',
            entityId:     bridge.id || '',
            translations: bridge.translations || {},
            coverage:     bridge.coverage || {
                fi_FI: { filled: 0, total: 3 },
                en_GB: { filled: 0, total: 3 },
                sw_TZ: { filled: 0, total: 3 }
            }
        });
    }

    if (window.LocaleCards) {
        mountLocaleCards();
    } else {
        document.addEventListener('DOMContentLoaded', mountLocaleCards);
        window.addEventListener('load', mountLocaleCards);
    }

    // ── Upload widgets: hero (single) + gallery (multi) ─────────────────
    // UploadWidget is a singleton — for two independent panes we need a
    // namespaced second instance. The original modal cloned the factory at
    // runtime; the cloning logic lives below in buildSecondWidget(). The
    // primary instance manages the hero, the clone manages the gallery.
    function mountUploadWidgets() {
        if (!window.UploadWidget) {
            // upload-widget.js loads with `defer`, so we may run first. Retry on load.
            window.addEventListener('load', mountUploadWidgets);
            return;
        }
        var heroExisting = bridge.heroImage ? [bridge.heroImage] : [];
        var galleryExisting = Array.isArray(bridge.gallery) ? bridge.gallery : [];

        if (heroContainer) {
            window.UploadWidget.init(
                heroContainer,
                heroExisting,
                bridge.id || null,
                { single: true }
            );
        }
        if (galleryContainer) {
            if (!window._EventGalleryWidget) window._EventGalleryWidget = buildSecondWidget();
            window._EventGalleryWidget.init(
                galleryContainer,
                galleryExisting,
                bridge.id || null,
                { single: false }
            );
        }
    }
    mountUploadWidgets();

    /**
     * Independent UploadWidget clone for the gallery slot — same behaviour as
     * window.UploadWidget but isolated state. Posts to the same upload
     * endpoint but accepts multiple files. Mirrors the structure used by the
     * legacy event-modal.js so the contract on /api/backstage/event-upload
     * stays unchanged.
     */
    function buildSecondWidget() {
        var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        var MAX_BYTES     = 10 * 1024 * 1024;

        var _container    = null;
        var _eventId      = null;
        var _existingUrls = [];
        var _pendingFiles = [];
        var _pendingUrls  = [];

        function escHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function validateFile(file) {
            if (!file || !file.type) return 'Unknown file type.';
            if (ALLOWED_TYPES.indexOf(file.type) === -1) return 'Unsupported type: ' + file.type;
            if (file.size > MAX_BYTES) return 'File too large (max 10 MiB).';
            return null;
        }
        function showWidgetError(msg) {
            var el = _container && _container.querySelector('.upload-error');
            if (!el) return;
            el.textContent = msg;
            el.style.display = 'block';
            setTimeout(function () { el.style.display = 'none'; }, 5000);
        }
        function render() {
            if (!_container) return;
            var thumbsEl = _container.querySelector('.upload-thumbs');
            if (!thumbsEl) return;
            var html = '';
            _existingUrls.forEach(function (url, i) {
                html += '<div class="upload-thumb" data-existing-idx="' + i + '">' +
                    '<img src="' + escHtml(url) + '" alt="img" loading="lazy">' +
                    '<button type="button" class="upload-thumb__del" data-del-existing="' + i + '" aria-label="Delete image">&times;</button>' +
                '</div>';
            });
            _pendingFiles.forEach(function (file, i) {
                html += '<div class="upload-thumb upload-thumb--pending" data-pending-idx="' + i + '">' +
                    '<img src="' + escHtml(_pendingUrls[i]) + '" alt="' + escHtml(file.name) + '">' +
                    '<button type="button" class="upload-thumb__del" data-del-pending="' + i + '" aria-label="Remove queued image">&times;</button>' +
                '</div>';
            });
            thumbsEl.innerHTML = html;
            thumbsEl.querySelectorAll('[data-del-existing]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var idx = parseInt(btn.getAttribute('data-del-existing'), 10);
                    var url = _existingUrls[idx];
                    if (!url) return;
                    if (!_eventId) { _existingUrls.splice(idx, 1); render(); return; }
                    fetch('/api/backstage/events?op=delete_image&id=' + encodeURIComponent(_eventId), {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ url: url }),
                    }).then(function (r) {
                        if (r.ok || r.status === 204) { _existingUrls.splice(idx, 1); render(); }
                        else showWidgetError('Failed to delete.');
                    }).catch(function () { showWidgetError('Network error deleting image.'); });
                });
            });
            thumbsEl.querySelectorAll('[data-del-pending]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var idx = parseInt(btn.getAttribute('data-del-pending'), 10);
                    if (_pendingUrls[idx]) URL.revokeObjectURL(_pendingUrls[idx]);
                    _pendingFiles.splice(idx, 1);
                    _pendingUrls.splice(idx, 1);
                    render();
                });
            });
        }
        function addFile(file) {
            var err = validateFile(file);
            if (err) { showWidgetError(err); return; }
            _pendingFiles.push(file);
            _pendingUrls.push(URL.createObjectURL(file));
            render();
        }
        function buildWidgetDom(container) {
            container.innerHTML =
                '<div class="upload-drop-zone">' +
                    '<div class="upload-drop-zone__icon">&#128444;</div>' +
                    '<div class="upload-drop-zone__text">Drop images here or click to browse</div>' +
                    '<div class="upload-drop-zone__hint">JPEG, PNG, WebP, GIF &mdash; max 10 MiB each</div>' +
                    '<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple tabindex="-1">' +
                '</div>' +
                '<div class="upload-error" style="display:none"></div>' +
                '<div class="upload-thumbs"></div>';
            var zone  = container.querySelector('.upload-drop-zone');
            var input = container.querySelector('input[type="file"]');
            input.addEventListener('change', function () {
                Array.from(input.files).forEach(addFile);
                input.value = '';
            });
            zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('is-over'); });
            zone.addEventListener('dragleave', function () { zone.classList.remove('is-over'); });
            zone.addEventListener('drop', function (e) {
                e.preventDefault(); zone.classList.remove('is-over');
                Array.from(e.dataTransfer.files).forEach(addFile);
            });
        }

        return {
            init: function (containerEl, existingUrls, evId) {
                _container    = containerEl;
                _existingUrls = Array.isArray(existingUrls) ? existingUrls.slice() : [];
                _eventId      = evId || null;
                _pendingFiles = [];
                _pendingUrls  = [];
                buildWidgetDom(containerEl);
                render();
            },
            setEventId: function (id) { _eventId = id; },
            getPending: function () { return _pendingFiles.slice(); },
            getCurrentUrls: function () { return _existingUrls.slice(); },
            uploadAll: function (evId, progressCallback) {
                if (evId) _eventId = evId;
                var total   = _pendingFiles.length;
                var newUrls = [];
                if (total === 0) return Promise.resolve([]);
                function step(done) {
                    if (done >= total) return Promise.resolve();
                    var file = _pendingFiles[0];
                    if (!file) return Promise.resolve();
                    var fd = new FormData();
                    fd.append('file', file);
                    return fetch('/api/backstage/event-upload?id=' + encodeURIComponent(_eventId), {
                        method: 'POST', body: fd,
                    }).then(function (r) {
                        return r.json().catch(function () { return {}; }).then(function (data) {
                            if (!r.ok) throw new Error((data && data.error) || 'Upload failed');
                            return data;
                        });
                    }).then(function (data) {
                        var url = data && data.data && data.data.url;
                        if (url) {
                            newUrls.push(url);
                            _existingUrls.push(url);
                        }
                        if (_pendingUrls[0]) URL.revokeObjectURL(_pendingUrls[0]);
                        _pendingFiles.splice(0, 1);
                        _pendingUrls.splice(0, 1);
                        render();
                        if (progressCallback) progressCallback(done + 1, total);
                        return step(done + 1);
                    });
                }
                return step(0).then(function () { return newUrls; });
            },
        };
    }

    // ── Read fi_FI draft for create-mode seed ───────────────────────────
    function readFiFIDraft() {
        var container = panel.querySelector('.locale-cards-container');
        if (!container) return { title: '', location: '', description: '' };
        // The locale-cards component renders only the ACTIVE locale's fields
        // in the DOM. On a fresh create page the active locale defaults to
        // fi_FI, so reading the inputs gives us the seed we need.
        var draft = { title: '', location: '', description: '' };
        container.querySelectorAll('.locale-cards-fields input, .locale-cards-fields textarea').forEach(function (i) {
            draft[i.name] = i.value;
        });
        return draft;
    }

    // ── Error helpers ──────────────────────────────────────────────────
    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.style.display = '';
        errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function clearError() {
        if (!errorEl) return;
        errorEl.textContent = '';
        errorEl.style.display = 'none';
    }
    function showFieldError(field, msg) {
        var el = document.getElementById('ef-err-' + field);
        if (el) el.textContent = msg || '';
        var input = document.getElementById('ef-' + field);
        if (input) {
            if (msg) input.classList.add('is-error'); else input.classList.remove('is-error');
        }
    }
    function clearFieldErrors() {
        ['type', 'event_date'].forEach(function (f) { showFieldError(f, ''); });
    }

    // ── Busy state ─────────────────────────────────────────────────────
    function setAllBusy(busy) {
        [saveBtn, publishBtn, archiveBtn].forEach(function (b) {
            if (b) b.disabled = busy;
        });
    }
    function restoreLabels() {
        if (saveBtn)    saveBtn.textContent    = labels.save;
        if (publishBtn) publishBtn.textContent = labels.publish;
        if (archiveBtn) archiveBtn.textContent = labels.archive;
    }
    function setSaving(text) {
        if (saveBtn) { saveBtn.textContent = text || 'Saving…'; }
    }

    // ── Save flow ──────────────────────────────────────────────────────
    if (saveBtn) {
        saveBtn.addEventListener('click', function () { handleSave(); });
    }
    if (publishBtn) {
        publishBtn.addEventListener('click', handlePublishToggle);
    }
    if (archiveBtn) {
        archiveBtn.addEventListener('click', handleArchive);
    }

    function handleSave() {
        clearError();
        clearFieldErrors();

        // Chrome validation
        var type = typeEl ? typeEl.value : '';
        if (!type) {
            showFieldError('type', 'Please select an event type.');
            return;
        }
        var date = (dateEl ? dateEl.value : '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            showFieldError('event_date', 'Date must be in YYYY-MM-DD format.');
            return;
        }

        // Create mode also needs at least the fi_FI title/description seed.
        if (mode === 'create') {
            var draft = readFiFIDraft();
            var title = (draft.title || '').trim();
            if (title.length < 3 || title.length > 200) {
                showError('Title must be 3–200 characters (enter it under the Suomi card).');
                return;
            }
            var online = !!(onlineEl && onlineEl.checked);
            var location = (draft.location || '').trim();
            if (!online && location === '') {
                showError('Location is required for in-person events (enter it under the Suomi card).');
                return;
            }
            var desc = (draft.description || '').trim();
            if (desc.length < 20) {
                showError('Description must be at least 20 characters (enter it under the Suomi card).');
                return;
            }
        }

        setAllBusy(true);
        setSaving('Saving…');

        if (mode === 'create') {
            doCreate();
        } else {
            doUpdate();
        }
    }

    function doCreate() {
        var draft = readFiFIDraft();
        var body = {
            type:       typeEl ? typeEl.value : '',
            event_date: dateEl ? dateEl.value.trim() : '',
            event_time: timeEl && timeEl.value.trim() !== '' ? timeEl.value.trim() : null,
            is_online:  !!(onlineEl && onlineEl.checked),
            title:         (draft.title       || '').trim(),
            location:      (draft.location    || '').trim() || null,
            description:   (draft.description || '').trim(),
            source_locale: 'fi_FI',
            publish_immediately: !!(publishNowEl && publishNowEl.checked)
        };

        postJson('/api/backstage/events?op=create', body)
            .then(function (res) {
                if (!res.ok) { handleSaveError(res); return null; }
                var newId = (res.data && (res.data.id || (res.data.data && res.data.data.id))) || '';
                if (!newId) {
                    showError('Save succeeded but no event ID was returned.');
                    setAllBusy(false);
                    restoreLabels();
                    return null;
                }
                // Now upload pending files (if any) against the new id, then
                // patch hero_image / gallery_json so the URLs are persisted.
                eventId = newId;
                if (window.UploadWidget)         window.UploadWidget.setEventId(newId);
                if (window._EventGalleryWidget)  window._EventGalleryWidget.setEventId(newId);
                return uploadAllPending().then(function () {
                    return persistImageRefs(newId);
                });
            })
            .then(function (final) {
                if (final === undefined) return;
                if (final === null) return;
                if (final && final.ok === false) { handleSaveError(final); return; }
                window.location.href = '/backstage/events';
            })
            .catch(function (e) {
                setAllBusy(false);
                restoreLabels();
                showError('Save failed: ' + (e && e.message || 'unknown error'));
            });
    }

    function doUpdate() {
        // Edit mode: upload any pending files first, then PATCH chrome with
        // the resulting hero_image + gallery_json so the row references match.
        setSaving('Uploading…');
        uploadAllPending()
            .then(function () {
                setSaving('Saving…');
                var body = buildUpdateBody();
                return postJson('/api/backstage/events?op=update&id=' + encodeURIComponent(eventId), body);
            })
            .then(function (res) {
                if (!res.ok) { handleSaveError(res); return null; }

                // Status change?
                var nextStatus = statusEl ? statusEl.value : initialStatus;
                if (nextStatus === initialStatus) return res;
                if (nextStatus === 'published') {
                    return postJson('/api/backstage/events?op=publish&id=' + encodeURIComponent(eventId), {});
                }
                if (nextStatus === 'archived') {
                    return postJson('/api/backstage/events?op=archive&id=' + encodeURIComponent(eventId), {});
                }
                if (nextStatus === 'draft' && initialStatus === 'published') {
                    // No dedicated 'draft' endpoint exists — archive then explain.
                    // Realistically the user should use the explicit Set-to-Draft
                    // button, but we honour the dropdown change for parity.
                    showError('To revert a published event to draft, use the "Set to Draft" button above.');
                    return res;
                }
                return res;
            })
            .then(function (final) {
                if (!final) return; // already handled error
                if (final.ok === false) { handleSaveError(final); return; }
                window.location.href = '/backstage/events';
            })
            .catch(function (e) {
                setAllBusy(false);
                restoreLabels();
                showError('Save failed: ' + (e && e.message || 'unknown error'));
            });
    }

    function buildUpdateBody() {
        var heroUrls    = window.UploadWidget         ? window.UploadWidget.getCurrentUrls()        : [];
        var galleryUrls = window._EventGalleryWidget ? window._EventGalleryWidget.getCurrentUrls() : [];
        return {
            type:       typeEl ? typeEl.value : '',
            event_date: dateEl ? dateEl.value.trim() : '',
            event_time: timeEl && timeEl.value.trim() !== '' ? timeEl.value.trim() : null,
            is_online:  !!(onlineEl && onlineEl.checked),
            hero_image: heroUrls.length > 0 ? heroUrls[0] : null,
            gallery_json: galleryUrls
        };
    }

    function persistImageRefs(id) {
        // Used after create-mode upload — we already own the row, just need
        // hero_image / gallery_json to point at the new URLs.
        var pendingHero = window.UploadWidget ? window.UploadWidget.getCurrentUrls() : [];
        var pendingGal  = window._EventGalleryWidget ? window._EventGalleryWidget.getCurrentUrls() : [];
        if (pendingHero.length === 0 && pendingGal.length === 0) {
            return Promise.resolve({ ok: true, status: 200, data: {} });
        }
        return postJson('/api/backstage/events?op=update&id=' + encodeURIComponent(id), {
            type:       typeEl ? typeEl.value : '',
            event_date: dateEl ? dateEl.value.trim() : '',
            event_time: timeEl && timeEl.value.trim() !== '' ? timeEl.value.trim() : null,
            is_online:  !!(onlineEl && onlineEl.checked),
            hero_image: pendingHero.length > 0 ? pendingHero[0] : null,
            gallery_json: pendingGal
        });
    }

    function uploadAllPending() {
        var heroP = (window.UploadWidget && eventId)
            ? window.UploadWidget.uploadAll(eventId)
            : Promise.resolve([]);
        return heroP.then(function () {
            return (window._EventGalleryWidget && eventId)
                ? window._EventGalleryWidget.uploadAll(eventId)
                : Promise.resolve([]);
        });
    }

    function handleSaveError(res) {
        setAllBusy(false);
        restoreLabels();
        var msg = (res && res.data && (res.data.error || res.data.message)) || 'Save failed.';
        if (res && res.data && res.data.errors) {
            Object.keys(res.data.errors).forEach(function (k) {
                var v = res.data.errors[k];
                showFieldError(k, Array.isArray(v) ? v.join(' ') : String(v));
            });
        }
        showError(msg);
    }

    // ── Publish/draft toggle (edit mode only) ──────────────────────────
    function handlePublishToggle() {
        if (!eventId) return;
        var current = statusEl ? statusEl.value : initialStatus;
        var goingToDraft = current === 'published';

        clearError();
        setAllBusy(true);
        if (publishBtn) publishBtn.textContent = goingToDraft ? 'Reverting…' : 'Publishing…';

        if (goingToDraft) {
            // No dedicated draft endpoint — archive is the closest equivalent.
            // For now, surface a hint instead of pretending to revert.
            setAllBusy(false);
            restoreLabels();
            showError('To revert a published event back to draft, please archive it. Re-publishing later restores it.');
            return;
        }

        // Going from draft (or archived) to published.
        postJson('/api/backstage/events?op=publish&id=' + encodeURIComponent(eventId), {})
            .then(function (res) {
                if (!res.ok) { handleSaveError(res); return; }
                if (statusEl) statusEl.value = 'published';
                initialStatus = 'published';
                setAllBusy(false);
                restoreLabels();
                if (publishBtn) publishBtn.textContent = 'Set to Draft';
                labels.publish = publishBtn ? publishBtn.textContent.trim() : labels.publish;
            })
            .catch(function (e) {
                setAllBusy(false);
                restoreLabels();
                showError('Publish failed: ' + (e && e.message || 'unknown error'));
            });
    }

    // ── Archive (edit mode only) ──────────────────────────────────────
    function handleArchive() {
        if (!eventId) return;
        if (!confirm('Archive this event? It will no longer appear publicly. You can restore it later by changing the status back.')) {
            return;
        }

        clearError();
        setAllBusy(true);
        if (archiveBtn) archiveBtn.textContent = 'Archiving…';

        postJson('/api/backstage/events?op=archive&id=' + encodeURIComponent(eventId), {})
            .then(function (res) {
                if (!res.ok) { handleSaveError(res); return; }
                window.location.href = '/backstage/events';
            })
            .catch(function (e) {
                setAllBusy(false);
                restoreLabels();
                showError('Archive failed: ' + (e && e.message || 'unknown error'));
            });
    }

    // ── Thin fetch wrapper that always returns { ok, status, data } ────
    function postJson(url, body) {
        return fetch(url, {
            method:  'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body:    JSON.stringify(body || {})
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                return { ok: r.ok, status: r.status, data: data };
            });
        });
    }
})();
