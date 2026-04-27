/**
 * UploadWidget — drag-drop image upload widget.
 *
 * Usage:
 *   UploadWidget.init(containerEl, existingUrls, eventId, options)
 *     options: { single: bool } — if single:true, only one file allowed
 *   UploadWidget.uploadAll(eventId)  — Promise<string[]>  (new URLs)
 *   UploadWidget.getCurrentUrls()   — string[]  (existing + new)
 *   UploadWidget.getPending()       — File[]
 */
(function (global) {
    'use strict';

    var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    var MAX_BYTES     = 10 * 1024 * 1024; // 10 MiB

    var _container    = null;
    var _eventId      = null;
    var _existingUrls = [];   // already-saved, persisted
    var _pendingFiles = [];   // File objects waiting for upload
    var _pendingUrls  = [];   // object URLs for preview (index-aligned with _pendingFiles)
    var _single       = false;

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function validate(file) {
        if (!file || !file.type) return 'Unknown file type.';
        if (ALLOWED_TYPES.indexOf(file.type) === -1) return 'Unsupported type: ' + file.type + '. Use JPEG, PNG, WebP or GIF.';
        if (file.size > MAX_BYTES) return 'File too large (' + (file.size / 1024 / 1024).toFixed(1) + ' MiB). Max 10 MiB.';
        return null;
    }

    function addFile(file) {
        var err = validate(file);
        if (err) { showError(err); return; }

        if (_single) {
            // Clear previous pending
            _pendingFiles.forEach(function (_, i) {
                if (_pendingUrls[i]) URL.revokeObjectURL(_pendingUrls[i]);
            });
            _pendingFiles = [];
            _pendingUrls  = [];
        }

        var objUrl = URL.createObjectURL(file);
        _pendingFiles.push(file);
        _pendingUrls.push(objUrl);
        render();
    }

    function showError(msg) {
        var el = _container.querySelector('.upload-error');
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

        // Existing saved images
        _existingUrls.forEach(function (url, i) {
            html +=
                '<div class="upload-thumb" data-existing-idx="' + i + '">' +
                    '<img src="' + escHtml(url) + '" alt="Image ' + (i + 1) + '" loading="lazy">' +
                    '<button type="button" class="upload-thumb__del" data-del-existing="' + i + '" aria-label="Delete image">&times;</button>' +
                '</div>';
        });

        // Pending (not yet uploaded)
        _pendingFiles.forEach(function (file, i) {
            html +=
                '<div class="upload-thumb upload-thumb--pending" data-pending-idx="' + i + '">' +
                    '<img src="' + escHtml(_pendingUrls[i]) + '" alt="' + escHtml(file.name) + '">' +
                    '<button type="button" class="upload-thumb__del" data-del-pending="' + i + '" aria-label="Remove queued image">&times;</button>' +
                '</div>';
        });

        thumbsEl.innerHTML = html;

        // Wire delete buttons for existing
        thumbsEl.querySelectorAll('[data-del-existing]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var idx = parseInt(btn.getAttribute('data-del-existing'), 10);
                var url = _existingUrls[idx];
                if (!url) return;
                if (!_eventId) {
                    // No event yet (create mode) — just remove from list
                    _existingUrls.splice(idx, 1);
                    render();
                    return;
                }
                fetch('/api/backstage/events?op=delete_image&id=' + encodeURIComponent(_eventId), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: url }),
                }).then(function (r) {
                    if (r.ok || r.status === 204) {
                        _existingUrls.splice(idx, 1);
                        render();
                    } else {
                        showError('Failed to delete image.');
                    }
                }).catch(function () { showError('Network error deleting image.'); });
            });
        });

        // Wire delete buttons for pending
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

    function buildWidget(container) {
        container.innerHTML =
            '<div class="upload-drop-zone">' +
                '<div class="upload-drop-zone__icon">&#128444;</div>' +
                '<div class="upload-drop-zone__text">Drop images here or click to browse</div>' +
                '<div class="upload-drop-zone__hint">JPEG, PNG, WebP, GIF &mdash; max 10 MiB each</div>' +
                '<input type="file" accept="image/jpeg,image/png,image/webp,image/gif"' +
                    (_single ? '' : ' multiple') + ' tabindex="-1">' +
            '</div>' +
            '<div class="upload-error" style="display:none"></div>' +
            '<div class="upload-thumbs"></div>';

        var zone  = container.querySelector('.upload-drop-zone');
        var input = container.querySelector('input[type="file"]');

        input.addEventListener('change', function () {
            Array.from(input.files).forEach(addFile);
            input.value = '';
        });

        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('is-over');
        });
        zone.addEventListener('dragleave', function () { zone.classList.remove('is-over'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('is-over');
            Array.from(e.dataTransfer.files).forEach(addFile);
        });
    }

    var UploadWidget = {
        init: function (containerEl, existingUrls, eventId, opts) {
            _container    = containerEl;
            _existingUrls = Array.isArray(existingUrls) ? existingUrls.slice() : [];
            _eventId      = eventId || null;
            _single       = !!(opts && opts.single);
            _pendingFiles = [];
            _pendingUrls  = [];
            buildWidget(containerEl);
            render();
        },

        getPending: function () { return _pendingFiles.slice(); },

        getCurrentUrls: function () { return _existingUrls.slice(); },

        setEventId: function (id) { _eventId = id; },

        /**
         * Upload all pending files sequentially.
         * Returns Promise<string[]> — newly uploaded URLs.
         */
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
                    method: 'POST',
                    body: fd,
                }).then(function (r) {
                    if (!r.ok) {
                        return r.json().catch(function () { return {}; }).then(function (err) {
                            throw new Error(err.error || 'Upload failed (HTTP ' + r.status + ')');
                        });
                    }
                    return r.json();
                }).then(function (data) {
                    var url = (data && data.data && data.data.url) ? data.data.url : null;
                    if (url) {
                        newUrls.push(url);
                        _existingUrls.push(url);
                    }
                    // Always pop index 0 after the attempt, success or not — otherwise a
                    // failed upload leaves the same file at [0] and the next step retries forever.
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

    global.UploadWidget = UploadWidget;

}(window));
