/**
 * EventModal — create/edit event modal with image upload support.
 *
 * Exposes: window.EventModal.open(mode, event?)
 *   mode  : 'create' | 'edit'
 *   event : existing event object (required for edit mode)
 *
 * Requires upload-widget.js loaded before this file.
 */
(function (global) {
    'use strict';

    var _backdrop = null;
    var _modal    = null;
    var _mode     = 'create';
    var _event    = null;

    var _heroContainer    = null;
    var _galleryContainer = null;

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function checked(id) {
        var el = document.getElementById(id);
        return el ? el.checked : false;
    }

    // ---- Validation ----
    // In i18n mode, title/location/description live inside the locale-cards
    // editor and are saved via dedicated translation endpoints. On _create_
    // we still collect a primary-locale (fi_FI) title/description from the
    // locale-cards container so the first POST can create the event row.
    // On _edit_ we skip title/description validation here — those fields
    // live in the locale cards and are saved per-locale independently.
    function readLocaleCardsDraft() {
        // Read whatever is currently entered in the locale-cards-fields
        // inputs. Only meaningful in create mode (no entity id yet) when we
        // need title/description to seed the initial event row.
        if (!_modal) return {};
        var container = _modal.querySelector('.locale-cards-container');
        if (!container) return {};
        var draft = {};
        container.querySelectorAll('.locale-cards-fields input, .locale-cards-fields textarea').forEach(function (i) {
            draft[i.name] = i.value;
        });
        return draft;
    }

    function validate() {
        var errors = {};
        var type = val('evt-f-type');
        if (!type) errors.type = 'Please select an event type.';

        var date = val('evt-f-date').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) errors.event_date = 'Date must be in YYYY-MM-DD format.';

        var online = checked('evt-f-online');

        if (_mode === 'create') {
            // For create we read the locale-cards draft (fi_FI by default)
            // because the server needs a title to create the event row.
            var draft = readLocaleCardsDraft();
            var title = (draft.title || '').trim();
            if (title.length < 3 || title.length > 200) {
                errors.title = 'Title must be 3–200 characters (enter it under the Suomi card).';
            }
            var location = (draft.location || '').trim();
            if (!online && location === '') {
                errors.location = 'Location is required for in-person events (enter it under the Suomi card).';
            }
            var desc = (draft.description || '').trim();
            if (desc.length < 20) {
                errors.description = 'Description must be at least 20 characters (enter it under the Suomi card).';
            }
        }
        // In edit mode, title/location/description are saved by the locale
        // cards via their per-locale endpoints — no need to validate here.

        return errors;
    }

    function showFieldErrors(errors) {
        // Clear previous
        _modal.querySelectorAll('.evt-error-msg').forEach(function (el) { el.textContent = ''; });
        _modal.querySelectorAll('.is-error').forEach(function (el) { el.classList.remove('is-error'); });

        Object.keys(errors).forEach(function (field) {
            // Translated fields (title/location/description) no longer have
            // dedicated inputs — the error is surfaced at the locale-cards
            // container level as a save-error message instead.
            var fieldMap = {
                type:       'evt-f-type',
                event_date: 'evt-f-date'
            };
            var inputId = fieldMap[field];
            if (inputId) {
                var input = document.getElementById(inputId);
                if (input) input.classList.add('is-error');
            }
            var errId  = 'evt-err-' + field;
            var errEl  = document.getElementById(errId);
            if (errEl) errEl.textContent = errors[field];
        });

        // Also surface locale-related errors at the bottom of the cards grid.
        if (errors.title || errors.location || errors.description) {
            var container = _modal.querySelector('.locale-cards-container');
            var status = container ? container.querySelector('.locale-cards-status') : null;
            if (status) {
                status.textContent = errors.title || errors.location || errors.description;
                status.classList.remove('is-success');
                status.classList.add('is-error');
            }
        }
    }

    // ---- Build modal DOM ----
    function buildModal() {
        if (_backdrop) return;

        _backdrop = document.createElement('div');
        _backdrop.className   = 'evt-modal-backdrop';
        _backdrop.id          = 'event-modal-backdrop';
        _backdrop.hidden      = true;
        _backdrop.setAttribute('aria-hidden', 'true');

        _modal = document.createElement('div');
        _modal.className = 'evt-modal evt-modal--wide';
        _modal.setAttribute('role', 'dialog');
        _modal.setAttribute('aria-modal', 'true');
        _modal.setAttribute('aria-labelledby', 'evt-modal-title');

        _modal.innerHTML =
            '<div class="evt-modal__header">' +
                '<h2 class="evt-modal__title" id="evt-modal-title">New Event</h2>' +
                '<button type="button" class="evt-modal__close" id="evt-modal-close" aria-label="Close">&times;</button>' +
            '</div>' +
            '<div class="evt-modal__body">' +
                '<form class="evt-form" id="evt-form" novalidate>' +

                    // Top: locale-cards (translated fields — title/location/description per locale).
                    '<div class="evt-form-row evt-form-row--full">' +
                        '<div class="evt-form-field">' +
                            '<div class="locale-cards-container"' +
                                 ' data-kind="event" data-entity-id="">' +
                                '<div class="locale-cards-grid" role="tablist" aria-label="Locale translations"></div>' +
                                '<div class="locale-cards-editor">' +
                                    '<div class="locale-cards-fields"></div>' +
                                    '<div class="locale-cards-actions">' +
                                        '<button type="button" class="btn btn--primary locale-cards-save">Save</button>' +
                                        '<span class="locale-cards-status" aria-live="polite"></span>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +

                    // Row: type + date + time (non-translated chrome)
                    '<div class="evt-form-row evt-form-row--3">' +
                        '<div class="evt-form-field">' +
                            '<label class="evt-form-label evt-form-label--required" for="evt-f-type">Type</label>' +
                            '<select id="evt-f-type" class="evt-form-select" required>' +
                                '<option value="">— select —</option>' +
                                '<option value="upcoming">Upcoming</option>' +
                                '<option value="past">Past</option>' +
                                '<option value="online">Online</option>' +
                            '</select>' +
                            '<span class="evt-error-msg" id="evt-err-type"></span>' +
                        '</div>' +
                        '<div class="evt-form-field">' +
                            '<label class="evt-form-label evt-form-label--required" for="evt-f-date">Date</label>' +
                            '<input type="date" id="evt-f-date" class="evt-form-input" required>' +
                            '<span class="evt-error-msg" id="evt-err-event_date"></span>' +
                        '</div>' +
                        '<div class="evt-form-field">' +
                            '<label class="evt-form-label" for="evt-f-time">Time (optional)</label>' +
                            '<input type="time" id="evt-f-time" class="evt-form-input">' +
                        '</div>' +
                    '</div>' +

                    // Row: online toggle (location is per-locale, lives in cards)
                    '<div class="evt-form-row">' +
                        '<div class="evt-form-field">' +
                            '<label class="evt-form-label">&nbsp;</label>' +
                            '<div class="evt-form-checkbox-row">' +
                                '<input type="checkbox" id="evt-f-online">' +
                                '<label for="evt-f-online">Online event (no physical location)</label>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +

                    // Hero image
                    '<div class="evt-form-row evt-form-row--full">' +
                        '<div class="evt-form-field">' +
                            '<p class="evt-section-title">Hero image <span style="font-weight:400;text-transform:none">(1 image)</span></p>' +
                            '<div id="evt-hero-container" class="upload-widget"></div>' +
                        '</div>' +
                    '</div>' +

                    // Gallery images
                    '<div class="evt-form-row evt-form-row--full">' +
                        '<div class="evt-form-field">' +
                            '<p class="evt-section-title">Gallery images <span style="font-weight:400;text-transform:none">(up to 14)</span></p>' +
                            '<div id="evt-gallery-container" class="upload-widget"></div>' +
                        '</div>' +
                    '</div>' +

                    // Publish toggle
                    '<div class="evt-form-row evt-form-row--full">' +
                        '<div class="evt-form-checkbox-row">' +
                            '<input type="checkbox" id="evt-f-publish">' +
                            '<label for="evt-f-publish">Publish immediately (otherwise saved as draft)</label>' +
                        '</div>' +
                    '</div>' +

                '</form>' +
            '</div>' +
            '<div class="evt-modal__footer">' +
                '<span class="evt-progress" id="evt-progress" style="display:none"></span>' +
                '<span class="evt-error-msg" id="evt-save-error"></span>' +
                '<button type="button" class="btn btn--ghost" id="evt-modal-cancel">Cancel</button>' +
                '<button type="button" class="btn btn--primary" id="evt-modal-save">Save</button>' +
            '</div>';

        _backdrop.appendChild(_modal);
        document.body.appendChild(_backdrop);

        // Close handlers
        document.getElementById('evt-modal-close').addEventListener('click', close);
        document.getElementById('evt-modal-cancel').addEventListener('click', close);
        _backdrop.addEventListener('click', function (e) { if (e.target === _backdrop) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !_backdrop.hidden) close(); });

        // Save handler
        document.getElementById('evt-modal-save').addEventListener('click', handleSave);
    }

    function close() {
        if (_backdrop) {
            _backdrop.hidden = true;
            _backdrop.setAttribute('aria-hidden', 'true');
        }
        setProgress(null);
        setSaveError('');
    }

    function setProgress(msg) {
        var el = document.getElementById('evt-progress');
        if (!el) return;
        if (msg) { el.textContent = msg; el.style.display = ''; }
        else     { el.textContent = ''; el.style.display = 'none'; }
    }

    function setSaveError(msg) {
        var el = document.getElementById('evt-save-error');
        if (el) el.textContent = msg || '';
    }

    function setSaving(saving) {
        var btn = document.getElementById('evt-modal-save');
        if (btn) { btn.disabled = saving; btn.textContent = saving ? 'Saving…' : 'Save'; }
    }

    // ---- Populate form for edit mode ----
    function populateForm(event) {
        document.getElementById('evt-f-type').value      = event.type || '';
        document.getElementById('evt-f-date').value      = event.event_date || '';
        document.getElementById('evt-f-time').value      = event.event_time || '';
        document.getElementById('evt-f-online').checked  = !!(event.is_online);
        document.getElementById('evt-f-publish').checked = event.status === 'published';

        // Initialize upload widgets with existing images
        var heroUrl    = event.hero_image ? [event.hero_image] : [];
        var galleryUrl = Array.isArray(event.gallery_json) ? event.gallery_json : [];

        global.UploadWidget.init(
            document.getElementById('evt-hero-container'),
            heroUrl,
            event.id || null,
            { single: true }
        );

        // Create a second widget instance for gallery — we use a namespaced clone approach
        if (!global._GalleryWidget) global._GalleryWidget = buildSecondWidget();
        global._GalleryWidget.init(
            document.getElementById('evt-gallery-container'),
            galleryUrl,
            event.id || null,
            { single: false }
        );

        // Mount the locale cards using the admin payload shape.
        // event.translations = { fi_FI: {...}, en_GB: {...}|null, sw_TZ: null }
        // event.coverage     = { fi_FI: { filled, total }, ... }
        mountLocaleCardsFromEvent(event);
    }

    function mountLocaleCardsFromEvent(event) {
        var container = _modal.querySelector('.locale-cards-container');
        if (!container || !global.LocaleCards) return;
        var translations = (event && event.translations) ? event.translations : {};
        // Legacy payload fallback: if the backend still ships a single title/
        // location/description at the top level (pre-i18n), seed fi_FI with it
        // so admins aren't staring at empty cards while A is still rolling out.
        if (!translations.fi_FI && (event.title || event.location || event.description)) {
            translations = Object.assign({}, translations, {
                fi_FI: {
                    title:       event.title || '',
                    location:    event.location || '',
                    description: event.description || ''
                }
            });
        }
        var coverage = (event && event.coverage) ? event.coverage : {};
        global.LocaleCards.mount(container, {
            kind:         'event',
            entityId:     event && event.id ? event.id : '',
            translations: translations,
            coverage:     coverage
        });
    }

    function initEmpty() {
        global.UploadWidget.init(
            document.getElementById('evt-hero-container'),
            [],
            null,
            { single: true }
        );
        if (!global._GalleryWidget) global._GalleryWidget = buildSecondWidget();
        global._GalleryWidget.init(
            document.getElementById('evt-gallery-container'),
            [],
            null,
            { single: false }
        );
        // Mount empty locale cards — fi_FI active, no translations, 0/3 coverage.
        var container = _modal.querySelector('.locale-cards-container');
        if (container && global.LocaleCards) {
            global.LocaleCards.mount(container, {
                kind:         'event',
                entityId:     '',
                translations: {},
                coverage:     { fi_FI: { filled: 0, total: 3 }, en_GB: { filled: 0, total: 3 }, sw_TZ: { filled: 0, total: 3 } }
            });
        }
    }

    /**
     * Build a second UploadWidget instance (gallery slot uses same API but separate state).
     * We clone the UploadWidget factory to get isolated state.
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
        function validate(file) {
            if (!file || !file.type) return 'Unknown file type.';
            if (ALLOWED_TYPES.indexOf(file.type) === -1) return 'Unsupported type: ' + file.type;
            if (file.size > MAX_BYTES) return 'File too large (max 10 MiB).';
            return null;
        }
        function showError(msg) {
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
                    '<button type="button" class="upload-thumb__del" data-del-existing="' + i + '">&times;</button>' +
                '</div>';
            });
            _pendingFiles.forEach(function (file, i) {
                html += '<div class="upload-thumb upload-thumb--pending" data-pending-idx="' + i + '">' +
                    '<img src="' + escHtml(_pendingUrls[i]) + '" alt="' + escHtml(file.name) + '">' +
                    '<button type="button" class="upload-thumb__del" data-del-pending="' + i + '">&times;</button>' +
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
                        else showError('Failed to delete.');
                    });
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
            var err = validate(file);
            if (err) { showError(err); return; }
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
            init: function (containerEl, existingUrls, eventId) {
                _container    = containerEl;
                _existingUrls = Array.isArray(existingUrls) ? existingUrls.slice() : [];
                _eventId      = eventId || null;
                _pendingFiles = [];
                _pendingUrls  = [];
                buildWidgetDom(containerEl);
                render();
            },
            setEventId: function (id) { _eventId = id; },
            getPending: function () { return _pendingFiles.slice(); },
            getCurrentUrls: function () { return _existingUrls.slice(); },
            uploadAll: function (eventId, progressCallback) {
                if (eventId) _eventId = eventId;
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
                        return r.json().then(function (data) {
                            if (!r.ok) throw new Error((data && data.error) || 'Upload failed');
                            return data;
                        });
                    }).then(function (data) {
                        var url = data && data.data && data.data.url;
                        if (url) {
                            newUrls.push(url);
                            _existingUrls.push(url);
                        }
                        // Always pop [0] so a failing or url-less response does not loop.
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

    // ---- Save logic ----
    function handleSave() {
        var errors = validate();
        if (Object.keys(errors).length > 0) {
            showFieldErrors(errors);
            return;
        }
        showFieldErrors({});
        setSaving(true);
        setSaveError('');

        var type        = val('evt-f-type');
        var eventDate   = val('evt-f-date').trim();
        var eventTime   = val('evt-f-time').trim() || null;
        var isOnline    = checked('evt-f-online');
        var publishNow  = checked('evt-f-publish');

        var eventId = (_mode === 'edit' && _event) ? _event.id : null;

        // For create: seed the primary-locale (fi_FI) translation from the
        // locale-cards draft. The backend expects the event to exist before
        // translations can be addressed via /translations/{locale} — so we
        // pass title/location/description alongside create to create row +
        // initial fi_FI translation in one round-trip (matches backend
        // create-event contract which still accepts these fields as the
        // source-locale translation per spec §6).
        var draft    = readLocaleCardsDraft();
        var titleFi  = (draft.title       || '').trim();
        var locFi    = (draft.location    || '').trim();
        var descFi   = (draft.description || '').trim();

        var baseBody = {
            type:       type,
            event_date: eventDate,
            event_time: eventTime,
            is_online:  isOnline
        };
        if (_mode === 'create') {
            baseBody.title       = titleFi;
            baseBody.location    = locFi || null;
            baseBody.description = descFi;
            baseBody.source_locale = 'fi_FI';
        }

        var totalPending =
            global.UploadWidget.getPending().length +
            (global._GalleryWidget ? global._GalleryWidget.getPending().length : 0);
        var uploadsDone = 0;

        function updateProgress(done, total) {
            uploadsDone = done;
            setProgress('Uploading ' + done + '/' + totalPending + '...');
        }

        function doCreate() {
            return fetch('/api/backstage/events?op=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({}, baseBody, { publish_immediately: publishNow })),
            }).then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) throw new Error((data && data.error) || 'Create failed');
                    return data;
                });
            }).then(function (data) {
                var id   = data && data.data && data.data.id;
                var slug = data && data.data && data.data.slug;
                if (!id) throw new Error('No event ID returned from create.');
                eventId = id;
                // Set event IDs on widgets
                global.UploadWidget.setEventId(id);
                if (global._GalleryWidget) global._GalleryWidget.setEventId(id);
                return { id: id, slug: slug };
            });
        }

        function doUploadAndUpdate(createdId) {
            var id = createdId || eventId;
            var heroWidgetPending    = global.UploadWidget.getPending().length;
            var galleryWidgetPending = global._GalleryWidget ? global._GalleryWidget.getPending().length : 0;
            totalPending = heroWidgetPending + galleryWidgetPending;

            if (totalPending === 0) {
                return Promise.resolve({ heroUrls: [], galleryUrls: [] });
            }

            setProgress('Uploading 0/' + totalPending + '...');
            var heroCount = 0;
            var galCount  = 0;

            return global.UploadWidget.uploadAll(id, function (done, total) {
                heroCount = done;
                setProgress('Uploading ' + (heroCount + galCount) + '/' + totalPending + '...');
            }).then(function (heroUrls) {
                return (global._GalleryWidget
                    ? global._GalleryWidget.uploadAll(id, function (done, total) {
                        galCount = done;
                        setProgress('Uploading ' + (heroCount + galCount) + '/' + totalPending + '...');
                    })
                    : Promise.resolve([])
                ).then(function (galleryUrls) {
                    return { heroUrls: heroUrls, galleryUrls: galleryUrls };
                });
            });
        }

        function doUpdate(id) {
            // Collect current URLs (existing + newly uploaded)
            var heroUrls    = global.UploadWidget.getCurrentUrls();
            var galleryUrls = global._GalleryWidget ? global._GalleryWidget.getCurrentUrls() : [];

            var updateBody = Object.assign({}, baseBody, {
                hero_image:   heroUrls.length > 0 ? heroUrls[0] : null,
                gallery_json: galleryUrls,
            });

            return fetch('/api/backstage/events?op=update&id=' + encodeURIComponent(id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updateBody),
            }).then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) throw new Error((data && data.error) || 'Update failed');
                    return data;
                });
            });
        }

        var promise;
        if (_mode === 'create') {
            promise = doCreate()
                .then(function (created) {
                    return doUploadAndUpdate(created.id);
                })
                .then(function () {
                    return doUpdate(eventId);
                });
        } else {
            // Edit: upload first, then update
            promise = doUploadAndUpdate(eventId)
                .then(function () {
                    return doUpdate(eventId);
                });
        }

        promise
            .then(function () {
                setProgress(null);
                close();
                if (typeof global.DAEMS_EVENTS_RELOAD === 'function') {
                    global.DAEMS_EVENTS_RELOAD();
                } else {
                    location.reload();
                }
            })
            .catch(function (err) {
                setSaving(false);
                setProgress(null);
                setSaveError(err && err.message ? err.message : 'Save failed. Please try again.');
            });
    }

    /**
     * Fetch the admin-view payload for a single event so we get translations
     * and coverage. Falls back to the list-row shape when the endpoint is
     * unreachable (e.g. backend not yet deployed) — the legacy fields get
     * mapped into a synthetic fi_FI translation by mountLocaleCardsFromEvent.
     */
    function fetchAdminEvent(id) {
        return fetch('/api/v1/backstage/events/' + encodeURIComponent(id), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json().catch(function () { return null; });
        }).then(function (data) {
            // Response may be either the event object directly or wrapped in
            // { data: {...} } depending on backend convention.
            if (!data) return null;
            if (data.id && (data.translations || data.coverage)) return data;
            if (data.data && data.data.id) return data.data;
            return null;
        }).catch(function () { return null; });
    }

    var EventModal = {
        open: function (mode, event) {
            buildModal();
            _mode  = mode || 'create';
            _event = event || null;

            var titleEl = document.getElementById('evt-modal-title');
            if (titleEl) titleEl.textContent = _mode === 'edit' ? 'Edit Event' : 'New Event';

            // Reset form
            var form = document.getElementById('evt-form');
            if (form) form.reset();

            // Hide publish checkbox in edit mode (use publish button instead)
            var publishRow = document.getElementById('evt-f-publish');
            if (publishRow && publishRow.closest('.evt-form-row')) {
                publishRow.closest('.evt-form-row').style.display = _mode === 'edit' ? 'none' : '';
            }

            setSaveError('');
            setProgress(null);
            _modal.querySelectorAll('.evt-error-msg').forEach(function (el) { el.textContent = ''; });
            _modal.querySelectorAll('.is-error').forEach(function (el) { el.classList.remove('is-error'); });

            var saveBtn = document.getElementById('evt-modal-save');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }

            if (_mode === 'edit' && _event) {
                // Seed with the list-row payload immediately so the modal
                // renders without a network blip, then enrich with the admin
                // endpoint (translations + coverage) asynchronously.
                populateForm(_event);
                if (_event.id) {
                    fetchAdminEvent(_event.id).then(function (full) {
                        if (full) {
                            _event = full;
                            mountLocaleCardsFromEvent(full);
                        }
                    });
                }
            } else {
                initEmpty();
            }

            _backdrop.hidden = false;
            _backdrop.setAttribute('aria-hidden', 'false');

            var first = _modal.querySelector('input, select, textarea');
            if (first) setTimeout(function () { first.focus(); }, 50);
        },
    };

    global.EventModal = EventModal;

    // Listen once for locale-cards saves so the list view can refresh badges
    // (Task B4 reads this event from index.php to repaint coverage dots).
    // The handler lives here because the modal is the only producer for events.
    document.addEventListener('locale-cards:saved', function (e) {
        if (!e || !e.detail || e.detail.kind !== 'event') return;
        if (typeof global.DAEMS_EVENTS_COVERAGE_UPDATE === 'function') {
            global.DAEMS_EVENTS_COVERAGE_UPDATE(e.detail.entityId, e.detail.coverage);
        }
    });

}(window));
