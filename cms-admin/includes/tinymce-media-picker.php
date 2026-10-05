<?php
declare(strict_types=1);

/**
 * "Select from Media Library" picker modal — shared partial.
 *
 * Used by pages.php (TinyMCE image dialog + Featured/OG image fields) and
 * ads.php (banner / video / poster fields). Include it once, near the end of
 * the page. For TinyMCE add to tinymce.init():
 *
 *   file_picker_types: 'image',
 *   file_picker_callback: window.wpmMlPicker,
 *
 * Consumers that open the modal themselves (the path-picker buttons) rely on
 * this contract, which must not change:
 *   - element ids  #mce-ml-modal, #mce-ml-search, #mce-ml-backdrop, #mce-ml-close
 *   - they open it by setting modal.hidden = false after dispatching an
 *     'input' event on #mce-ml-search (that event (re)loads the list)
 *   - they react to clicks on .mce-ml-item and read its data-path attribute
 *   - optional: they set modal.dataset.current to the field's current value so
 *     the matching card shows as selected
 *
 * Data comes from api/media-list.php (server-side search + paging, so it no
 * longer renders — and getimagesize()s — every image into the page at load)
 * and uploads go to api/media-upload.php. Styling uses only admin.css tokens.
 *
 * Needs no PHP variables from the including page (no $pdo).
 */
?>
<style>
/* ---- Media Library picker modal (prefix mce-ml-*) ---- */
#mce-ml-modal {
    position: fixed; inset: 0;
    z-index: 1400;                    /* above the TinyMCE dialog (~1300) */
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
#mce-ml-modal[hidden] { display: none !important; }
#mce-ml-backdrop { position: absolute; inset: 0; background: var(--modal-overlay); }
#mce-ml-dialog {
    position: relative;
    display: flex; flex-direction: column;
    width: min(980px, 100%);
    height: min(760px, 92vh);
    background: var(--surface);
    border: 1px solid var(--modal-border);
    border-radius: var(--radius);
    box-shadow: var(--modal-shadow);
    overflow: hidden;
    color: var(--text);
}
#mce-ml-head {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 16px 18px 12px;
    flex-shrink: 0;
}
#mce-ml-head-text { flex: 1; min-width: 0; }
#mce-ml-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--text); }
#mce-ml-count { margin: 2px 0 0; font-size: 12px; color: var(--muted); }
#mce-ml-close {
    flex-shrink: 0;
    width: 34px; height: 34px;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--surface-soft);
    border: 1px solid var(--line);
    border-radius: var(--radius-sm);
    color: var(--muted);
    cursor: pointer;
    transition: background .14s ease, border-color .14s ease, color .14s ease;
}
#mce-ml-close:hover { background: var(--navlink-hover-bg); border-color: var(--navlink-hover-border); color: var(--text); }
#mce-ml-close:focus-visible, #mce-ml-search:focus-visible, #mce-ml-dropzone:focus-visible,
.mce-ml-item:focus-visible { outline: 2px solid var(--navlink-active-border); outline-offset: 2px; }

.mce-ml-searchrow { position: relative; padding: 0 18px 12px; flex-shrink: 0; }
.mce-ml-searchrow svg { position: absolute; left: 30px; top: 11px; color: var(--muted); pointer-events: none; }
#mce-ml-search {
    width: 100%;
    padding: 9px 12px 9px 36px;
    border: 1px solid var(--line);
    border-radius: var(--radius-sm);
    background: var(--input-bg);
    color: var(--text);
    font-size: 13.5px; font-family: inherit;
}

/* ---- upload drop zone ---- */
#mce-ml-dropzone {
    flex-shrink: 0;
    margin: 0 18px 14px;
    padding: 16px 14px;
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    border: 1.5px dashed var(--line);
    border-radius: var(--radius-sm);
    background: var(--surface-soft);
    text-align: center;
    font-size: 13px; color: var(--muted);
    cursor: pointer;
    transition: border-color .14s ease, background .14s ease, color .14s ease;
}
#mce-ml-dropzone:hover, #mce-ml-dropzone.is-dragover {
    border-color: var(--navlink-active-border);
    background: var(--accent-soft);
    color: var(--text);
}
#mce-ml-dropzone svg { color: var(--accent); }
#mce-ml-dropzone strong { color: var(--text); font-weight: 700; }
#mce-ml-dropzone small { font-size: 11.5px; color: var(--muted); }
#mce-ml-dropzone.is-busy { cursor: progress; }
#mce-ml-dz-busy { display: none; width: 100%; max-width: 360px; flex-direction: column; gap: 6px; align-items: stretch; }
#mce-ml-dropzone.is-busy #mce-ml-dz-idle { display: none; }
#mce-ml-dropzone.is-busy #mce-ml-dz-busy { display: flex; }
#mce-ml-dz-busy-text { font-size: 12.5px; color: var(--text); }
.mce-ml-progress { height: 5px; border-radius: 99px; background: var(--line); overflow: hidden; }
.mce-ml-progress > span { display: block; height: 100%; width: 0; background: var(--accent); transition: width .12s linear; }
#mce-ml-error {
    display: none;
    margin: -4px 18px 12px;
    padding: 9px 12px;
    border-radius: var(--radius-sm);
    background: var(--danger-bg);
    border: 1px solid var(--danger-border);
    color: var(--danger-text);
    font-size: 12.5px; line-height: 1.45;
}
#mce-ml-error.is-visible { display: block; }

/* ---- grid + cards ---- */
#mce-ml-body { flex: 1; overflow-y: auto; padding: 2px 18px 18px; }
.mce-ml-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 14px;
}
.mce-ml-item {
    position: relative;
    display: flex; flex-direction: column;
    background: var(--surface);
    border: 1.5px solid var(--line);
    border-radius: var(--radius-sm);
    overflow: hidden;
    cursor: pointer;
    outline: none;
    transition: border-color .14s ease, box-shadow .14s ease, transform .13s ease;
}
.mce-ml-item:hover, .mce-ml-item:focus-visible {
    border-color: var(--navlink-active-border);
    box-shadow: var(--shadow-sm);
    transform: translateY(-2px);
}
.mce-ml-item__thumb { position: relative; aspect-ratio: 4 / 3; background: var(--surface-soft); overflow: hidden; }
.mce-ml-item__img { display: block; width: 100%; height: 100%; object-fit: cover; }
.mce-ml-item__check {
    position: absolute; top: 8px; right: 8px;
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    border: 2px solid var(--accent);
    background: var(--surface);
    color: var(--accent-text);
    opacity: 0;
    transform: scale(.85);
    transition: opacity .14s ease, transform .14s ease, background .14s ease;
}
.mce-ml-item__check svg { opacity: 0; }
.mce-ml-item:hover .mce-ml-item__check, .mce-ml-item:focus-visible .mce-ml-item__check { opacity: 1; transform: scale(1); }
.mce-ml-item.is-selected { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent), var(--shadow-sm); }
.mce-ml-item.is-selected .mce-ml-item__check { opacity: 1; transform: scale(1); background: var(--accent); }
.mce-ml-item.is-selected .mce-ml-item__check svg { opacity: 1; }
.mce-ml-item.is-new { animation: mce-ml-pop .35s ease; }
@keyframes mce-ml-pop { from { transform: scale(.94); opacity: .4; } to { transform: scale(1); opacity: 1; } }
.mce-ml-item__info { padding: 9px 11px 10px; min-width: 0; }
.mce-ml-item__name {
    display: block;
    font-size: 12.5px; font-weight: 600; color: var(--text);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mce-ml-item__meta { display: block; margin-top: 2px; font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mce-ml-status { grid-column: 1 / -1; padding: 36px 16px; text-align: center; color: var(--muted); font-size: 14px; }
.mce-ml-status--error { color: var(--danger-text); }
#mce-ml-more { display: none; justify-content: center; padding-top: 16px; }
#mce-ml-more.is-visible { display: flex; }

@media (max-width: 640px) {
    #mce-ml-modal { padding: 0; align-items: flex-end; }
    #mce-ml-dialog { width: 100%; height: 94vh; border-radius: var(--radius) var(--radius) 0 0; }
    .mce-ml-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    #mce-ml-body, .mce-ml-searchrow, #mce-ml-head { padding-left: 14px; padding-right: 14px; }
    #mce-ml-dropzone, #mce-ml-error { margin-left: 14px; margin-right: 14px; }
    .mce-ml-searchrow svg { left: 26px; }
}
</style>

<!-- Media Library picker modal -->
<div id="mce-ml-modal" hidden role="dialog" aria-modal="true" aria-labelledby="mce-ml-title">
    <div id="mce-ml-backdrop"></div>
    <div id="mce-ml-dialog">

        <div id="mce-ml-head">
            <div id="mce-ml-head-text">
                <h3 id="mce-ml-title">Select from Media Library</h3>
                <p id="mce-ml-count" aria-live="polite"></p>
            </div>
            <button type="button" id="mce-ml-close" aria-label="Close">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <div class="mce-ml-searchrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" id="mce-ml-search" placeholder="Search images by file name…" autocomplete="off" aria-label="Search images">
        </div>

        <div id="mce-ml-dropzone" role="button" tabindex="0" aria-label="Upload new images">
            <span id="mce-ml-dz-idle">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/></svg><br>
                <strong>Click to upload</strong> or drag &amp; drop an image here (max 5 MB)<br>
                <small>JPG, PNG, WebP or GIF</small>
            </span>
            <span id="mce-ml-dz-busy">
                <span id="mce-ml-dz-busy-text">Uploading…</span>
                <span class="mce-ml-progress"><span id="mce-ml-dz-bar"></span></span>
            </span>
        </div>
        <input type="file" id="mce-ml-file-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
        <div id="mce-ml-error" role="alert"></div>

        <div id="mce-ml-body">
            <div class="mce-ml-grid" id="mce-ml-grid"></div>
            <div id="mce-ml-more"><button type="button" class="admin-btn admin-btn--secondary" id="mce-ml-more-btn">Load more</button></div>
        </div>

    </div>
</div>

<script>
(function () {
    var modal     = document.getElementById('mce-ml-modal');
    if (!modal) return;
    var backdrop  = document.getElementById('mce-ml-backdrop');
    var search    = document.getElementById('mce-ml-search');
    var closeBtn  = document.getElementById('mce-ml-close');
    var grid      = document.getElementById('mce-ml-grid');
    var countEl   = document.getElementById('mce-ml-count');
    var moreWrap  = document.getElementById('mce-ml-more');
    var moreBtn   = document.getElementById('mce-ml-more-btn');
    var dropzone  = document.getElementById('mce-ml-dropzone');
    var fileInput = document.getElementById('mce-ml-file-input');
    var errorBox  = document.getElementById('mce-ml-error');
    var busyText  = document.getElementById('mce-ml-dz-busy-text');
    var busyBar   = document.getElementById('mce-ml-dz-bar');

    var LIST_URL   = <?= json_encode(cms_api_href('media-list.php'), JSON_UNESCAPED_SLASHES) ?>;
    var UPLOAD_URL = <?= json_encode(cms_api_href('media-upload.php'), JSON_UNESCAPED_SLASHES) ?>;
    var CSRF_TOKEN = <?= json_encode(cms_csrf_token()) ?>;
    var PAGE_SIZE  = 48;
    var MAX_BYTES  = 5 * 1024 * 1024;                 // same as the server's image limit
    var OK_TYPES   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    var currentCallback = null;                       // set only while TinyMCE opened the modal
    var state = { q: '', offset: 0, total: 0, seq: 0, ctrl: null, timer: null, seen: {}, uploading: false };

    var CHECK_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

    function fmtSize(kb) {
        if (kb === null || kb === undefined || kb === '') return '';
        kb = Number(kb);
        return kb >= 1024 ? (kb / 1024).toFixed(kb >= 10240 ? 0 : 1) + ' MB' : kb + ' KB';
    }
    function fmtBytes(b) { return fmtSize(Math.ceil(b / 1024)); }

    /* ---- open / close ---- */
    function openModal() {
        modal.hidden = false;
        search.value = '';
        state.q = '';
        loadList(true);
        search.focus();
    }
    function closeModal() {
        modal.hidden = true;
        currentCallback = null;
        if (state.ctrl) { state.ctrl.abort(); }
    }

    /* ---- list (server-side search + paging) ---- */
    function isCurrent(d) {
        var c = modal.dataset.current || '';
        if (!c) return false;
        return c === d.file_path || c === d.thumb_url ||
               (d.file_path && c.slice(-d.file_path.length) === d.file_path);
    }

    // One card. Same data-* contract the page-level pickers read (data-path)
    // and TinyMCE's selectItem() reads (data-src/alt/width/height).
    function buildItem(d, isNew) {
        var item = document.createElement('div');
        item.className = 'mce-ml-item' + (isCurrent(d) ? ' is-selected' : '') + (isNew ? ' is-new' : '');
        item.setAttribute('role', 'button');
        item.setAttribute('tabindex', '0');
        item.setAttribute('data-src', d.thumb_url || '');
        item.setAttribute('data-path', d.file_path || '');
        item.setAttribute('data-alt', d.alt_text || '');
        item.setAttribute('data-name', (d.file_name || '').toLowerCase());
        item.setAttribute('data-width', String(d.width || 0));
        item.setAttribute('data-height', String(d.height || 0));

        var dims = (d.width > 0 && d.height > 0) ? d.width + ' × ' + d.height : '';
        var meta = [dims, fmtSize(d.size_kb)].filter(Boolean).join(' · ');
        item.title = (d.file_name || '') + (meta ? '\n' + meta : '');

        var thumb = document.createElement('div');
        thumb.className = 'mce-ml-item__thumb';
        var img = document.createElement('img');
        img.className = 'mce-ml-item__img';
        img.src = d.thumb_url || '';
        img.alt = d.alt_text || d.file_name || '';
        img.loading = 'lazy';
        img.onerror = function () { img.style.visibility = 'hidden'; };
        thumb.appendChild(img);
        var check = document.createElement('span');
        check.className = 'mce-ml-item__check';
        check.innerHTML = CHECK_SVG;
        thumb.appendChild(check);
        item.appendChild(thumb);

        var info = document.createElement('div');
        info.className = 'mce-ml-item__info';
        var name = document.createElement('span');
        name.className = 'mce-ml-item__name';
        name.textContent = d.file_name || '';
        info.appendChild(name);
        if (meta) {
            var m = document.createElement('span');
            m.className = 'mce-ml-item__meta';
            m.textContent = meta;
            info.appendChild(m);
        }
        item.appendChild(info);
        return item;
    }

    function setStatus(text, isError) {
        var node = grid.querySelector('.mce-ml-status');
        if (!text) { if (node) node.remove(); return; }
        if (!node) { node = document.createElement('p'); grid.appendChild(node); }
        node.className = 'mce-ml-status' + (isError ? ' mce-ml-status--error' : '');
        node.textContent = text;
    }

    function updateCount() {
        if (state.q) {
            countEl.textContent = state.total + ' result' + (state.total === 1 ? '' : 's') + ' for “' + state.q + '”';
        } else {
            countEl.textContent = state.total + ' image' + (state.total === 1 ? '' : 's') + ' in the library';
        }
    }

    function loadList(reset) {
        if (reset) {
            state.offset = 0;
            state.seen = {};
            grid.innerHTML = '';
            moreWrap.classList.remove('is-visible');
        }
        var seq = ++state.seq;
        if (state.ctrl) { state.ctrl.abort(); }
        state.ctrl = new AbortController();
        if (reset) { setStatus('Loading…'); }
        moreBtn.disabled = true;

        var url = LIST_URL + '?q=' + encodeURIComponent(state.q) + '&offset=' + state.offset + '&limit=' + PAGE_SIZE;
        fetch(url, { credentials: 'same-origin', signal: state.ctrl.signal, headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (seq !== state.seq) return;                      // a newer search superseded this one
                setStatus('');
                moreBtn.disabled = false;
                if (!data || !data.success) {
                    setStatus((data && data.error) || 'Could not load the media library.', true);
                    return;
                }
                state.total = data.total;
                // Items added by an upload shift later pages by one; skip
                // anything already on screen instead of showing it twice.
                data.items.forEach(function (d) {
                    if (state.seen[d.id]) return;
                    state.seen[d.id] = true;
                    grid.appendChild(buildItem(d, false));
                });
                state.offset = data.offset + data.items.length;
                updateCount();
                if (state.total === 0) {
                    setStatus(state.q ? 'No images match “' + state.q + '”.' : 'No images yet — upload one above.');
                }
                moreWrap.classList.toggle('is-visible', !!data.has_more);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                if (seq !== state.seq) return;
                moreBtn.disabled = false;
                setStatus('Could not load the media library. Check your connection and try again.', true);
            });
    }

    // Typing searches the whole library on the server; short debounce so a
    // burst of keystrokes is one request. An empty box (which is also how the
    // page-level pickers "reset" the modal before opening it) loads at once.
    search.addEventListener('input', function () {
        state.q = search.value.trim();
        clearTimeout(state.timer);
        if (state.q === '') { loadList(true); return; }
        state.timer = setTimeout(function () { loadList(true); }, 220);
    });
    moreBtn.addEventListener('click', function () { loadList(false); });

    /* ---- select (TinyMCE flow; page-level pickers listen for the same click) ---- */
    function selectItem(item) {
        Array.prototype.forEach.call(grid.querySelectorAll('.mce-ml-item.is-selected'), function (n) { n.classList.remove('is-selected'); });
        item.classList.add('is-selected');
        if (!currentCallback) return;

        var src = item.getAttribute('data-src') || item.getAttribute('data-path') || '';
        var alt = item.getAttribute('data-alt') || '';
        var w   = parseInt(item.getAttribute('data-width')  || '0', 10);
        var h   = parseInt(item.getAttribute('data-height') || '0', 10);

        var MAX_W = 600;                                // cap display width, keep aspect ratio
        if (w > MAX_W) { h = h > 0 ? Math.round(h * MAX_W / w) : 0; w = MAX_W; }

        var imgMeta = { title: alt };
        if (w > 0) { imgMeta.width  = String(w); }
        if (h > 0) { imgMeta.height = String(h); }

        currentCallback(src, imgMeta);
        closeModal();
    }

    // One delegated listener for every card (including ones added later by
    // upload / "Load more"). Deliberately no stopPropagation: the page-level
    // delegates in pages.php / ads.php must still see the click.
    grid.addEventListener('click', function (e) {
        var item = e.target.closest('.mce-ml-item');
        if (item) selectItem(item);
    });
    grid.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var item = e.target.closest('.mce-ml-item');
        if (item) { e.preventDefault(); item.click(); }
    });

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (!modal.hidden && (e.key === 'Escape' || e.key === 'Esc')) closeModal();
    });

    /* ---- upload: click or drag & drop ---- */
    function showError(msg) { errorBox.textContent = msg; errorBox.classList.add('is-visible'); }
    function clearError()   { errorBox.textContent = ''; errorBox.classList.remove('is-visible'); }

    // XHR (not fetch) so the drop zone can show real upload progress.
    function uploadOne(file, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', UPLOAD_URL);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded / e.total); };
            xhr.onerror = function () { reject('Upload failed — check your connection and try again.'); };
            xhr.onload = function () {
                var data = null;
                try { data = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
                if (data && data.success) { resolve(data); return; }
                if (data && data.error)   { reject(data.error); return; }
                reject(xhr.status === 403 || xhr.status === 200
                    ? 'Upload failed — your session may have expired. Reload the page and log in again.'
                    : 'Upload failed — unexpected server response (HTTP ' + xhr.status + ').');
            };
            var fd = new FormData();
            fd.append('media_file', file);
            fd.append('csrf_token', CSRF_TOKEN);
            xhr.send(fd);
        });
    }

    function addUploaded(d, autoSelect) {
        state.seen[d.id] = true;
        var node = buildItem(d, true);
        setStatus('');
        grid.insertBefore(node, grid.firstChild);
        state.total += 1;
        updateCount();
        node.scrollIntoView({ block: 'nearest' });
        // Selecting a single upload right away is the point of uploading from
        // inside the picker. A real click, so the page-level delegates act too.
        if (autoSelect) node.click();
    }

    function handleFiles(fileList) {
        if (state.uploading) return;
        clearError();
        var errors = [], ok = [];
        Array.prototype.forEach.call(fileList, function (f) {
            if (OK_TYPES.indexOf(f.type) === -1) {
                errors.push(f.name + ': only JPG, PNG, WebP or GIF images can be uploaded.');
            } else if (f.size > MAX_BYTES) {
                errors.push(f.name + ': ' + (f.size / 1048576).toFixed(2) + ' MB is over the 5 MB limit.');
            } else {
                ok.push(f);
            }
        });
        if (errors.length) showError(errors.join(' '));
        if (!ok.length) return;

        state.uploading = true;
        dropzone.classList.add('is-busy');
        var autoSelect = ok.length === 1, i = 0, failed = [];
        (function next() {
            if (i >= ok.length) {
                state.uploading = false;
                dropzone.classList.remove('is-busy');
                if (failed.length) showError((errors.length ? errors.join(' ') + ' ' : '') + failed.join(' '));
                return;
            }
            var file = ok[i];
            busyText.textContent = 'Uploading ' + (ok.length > 1 ? (i + 1) + '/' + ok.length + ' — ' : '') + file.name + '…';
            busyBar.style.width = '0%';
            uploadOne(file, function (frac) { busyBar.style.width = Math.round(frac * 100) + '%'; })
                .then(function (d) { addUploaded(d, autoSelect); })
                .catch(function (msg) { failed.push(file.name + ': ' + msg); })
                .then(function () { i++; next(); });
        })();
    }

    dropzone.addEventListener('click', function () { if (!state.uploading) fileInput.click(); });
    dropzone.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && !state.uploading) { e.preventDefault(); fileInput.click(); }
    });
    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files.length) handleFiles(fileInput.files);
        fileInput.value = '';                           // allow picking the same file again
    });
    ['dragenter', 'dragover'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); dropzone.classList.add('is-dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('is-dragover'); });
    });
    dropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) handleFiles(e.dataTransfer.files);
    });

    /**
     * TinyMCE file_picker_callback — register as:
     *   file_picker_callback: window.wpmMlPicker
     * `value` is the Source field's current value; used to mark that card selected.
     */
    window.wpmMlPicker = function (callback, value, meta) {
        if (!meta || meta.filetype !== 'image') return;
        currentCallback = callback;
        modal.dataset.current = value || '';
        openModal();
    };
})();

// ---- Shared TinyMCE content styles — consumed by each page's tinymce.init() ----
// These styles apply inside the TinyMCE iframe so images look correct while editing.
window.wpmMlContentStyle =
    'body { display: flow-root; }' +
    // Base: unclassified images get centred-ish appearance in the editor
    'img { display:block; max-width:600px; width:auto; height:auto; border-radius:12px; margin:24px auto; }' +
    // Alignment classes — must mirror assets/css/style.css exactly
    '.img-center { display:block; float:none; width:auto; max-width:700px; height:auto; margin:24px auto; border-radius:12px; }' +
    '.img-full   { display:block; float:none; width:100%; max-width:900px; height:auto; margin:24px auto; border-radius:12px; }' +
    '.img-left   { float:left;  width:280px; max-width:40%; height:auto; margin:0 24px 16px 0; border-radius:12px; }' +
    '.img-right  { float:right; width:280px; max-width:40%; height:auto; margin:0 0 16px 24px; border-radius:12px; }' +
    '.img-left~.img-left,.img-left~.img-right,.img-right~.img-left,.img-right~.img-right{clear:both}' +
    '@media (max-width:640px){' +
    '  .img-center,.img-full,.img-left,.img-right{float:none;display:block;width:100%;max-width:100%;margin:20px 0;}' +
    '}';

// ---- Editor setup hook — add to tinymce.init() setup function ----
// Adds an inline style attribute to newly inserted/selected images that do not
// already have one, so the styled appearance is preserved in the saved HTML.
window.wpmMlSetupEditor = function (editor) {
    // Only applied to images that carry NO alignment class (fallback insurance).
    // Images with img-center/img-full/img-left/img-right get their appearance
    // entirely from the CSS class — adding inline styles would override the class.
    var BASE_STYLE = 'height:auto;border-radius:12px';
    var ALIGN_RE   = /\bimg-(center|full|left|right)\b/;
    var ready = false;

    editor.on('init', function () { ready = true; });

    editor.on('NodeChange', function (e) {
        if (!ready) return;
        var el = e.element;
        if (el && el.nodeName === 'IMG' && !el.getAttribute('style')) {
            if (!ALIGN_RE.test(el.getAttribute('class') || '')) {
                editor.dom.setAttrib(el, 'style', BASE_STYLE);
            }
        }
    });
};
</script>
