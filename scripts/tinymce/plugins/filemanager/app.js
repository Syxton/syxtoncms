(function () {
  'use strict';

  var root = document.getElementById('fm-root');
  var API = root.dataset.api;
  var CSRF = root.dataset.csrf;
  var PAGEID = root.dataset.pageid;
  var USERID = root.dataset.userid;
  var CAN_PUBLIC = root.dataset.canPublic === '1';
  var CAN_PRIVATE = root.dataset.canPrivate === '1';
  var CAN_OLD = root.dataset.canOld === '1';
  // Per-action abilities - only meaningful for Page files (area='pub').
  // My files has no gate at all. Use the pubAreaOK()/moveAllowed()/
  // copyAllowed()/migrateAllowed() helpers below rather than reading
  // these directly.
  var PERM = {
    delete: root.dataset.canDelete === '1',
    upload: root.dataset.canUpload === '1',
    move: root.dataset.canMove === '1',
    copy: root.dataset.canCopy === '1',
    createfolder: root.dataset.canCreatefolder === '1',
    migrate: root.dataset.canMigrate === '1',
    edit: root.dataset.canEdit === '1',
  };
  var TYPE = root.dataset.type; // '', 'image', 'media', 'file'
  // Set only when opened by the HTML feature - gates the Gallery/Index
  // folder-link choice (see filter_photogallery() in htmllib.php).
  var ALLOW_GALLERY = root.dataset.allowGallery === '1';

  // Opened inside the picker's overlay iframe (appended directly to the
  // host page's <body> by plugin.js - see its file header for why it's
  // not a window.open() popup nor a nested TinyMCE dialog) vs. linked to
  // directly elsewhere in the app.
  //
  // EMBEDDED (which shows Insert/Cancel) requires BOTH window.parent to
  // be a different window AND the embed=1 marker plugin.js puts on the
  // URL. window.parent alone isn't proof - nothing stops some other part
  // of the app from iframing this page for an unrelated reason - and the
  // marker alone isn't proof either, since a URL's query string can be
  // copied into a plain link. Together they reliably mean "this really is
  // the picker overlay, with the editor's page on the other end to post
  // messages to".
  var EMBED_FLAG = root.dataset.embed === '1';
  var TARGET = (function () {
    try { return (window.parent && window.parent !== window) ? window.parent : null; }
    catch (e) { return null; }
  })();
  var EMBEDDED = EMBED_FLAG && !!TARGET;

  var IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
  var MEDIA_EXT = ['mp4', 'webm', 'mp3'];

  // Distinct icon per filetype - colored "badge" (label + color) rather
  // than emoji, which renders inconsistently across OS/browsers and can't
  // tell a .doc from a .zip at a glance. Only covers non-image types since
  // images (including svg) already get an actual thumbnail preview, which
  // is more informative than a badge. Anything not listed falls back to a
  // generic gray "FILE" badge. Keep in sync with $GLOBALS['FM_ALLOWED_EXT']
  // in fmconfig.php - fine for this to be a subset.
  var FILE_TYPE_STYLES = {
    pdf:  { label: 'PDF',  color: '#d93025' },
    doc:  { label: 'DOC',  color: '#2b579a' },
    docx: { label: 'DOC',  color: '#2b579a' },
    xls:  { label: 'XLS',  color: '#217346' },
    xlsx: { label: 'XLS',  color: '#217346' },
    ppt:  { label: 'PPT',  color: '#d24726' },
    pptx: { label: 'PPT',  color: '#d24726' },
    txt:  { label: 'TXT',  color: '#5f6368' },
    csv:  { label: 'CSV',  color: '#188038' },
    zip:  { label: 'ZIP',  color: '#8a6d3b' },
    mp3:  { label: 'MP3',  color: '#8430ce' },
    mp4:  { label: 'MP4',  color: '#1967d2' },
    webm: { label: 'WEBM', color: '#1967d2' },
  };
  var GENERIC_FILE_STYLE = { label: 'FILE', color: '#5f6368' };

  var LEVEL_LABELS = {
    link: 'Anyone with this link (no login required)',
    page: 'Can view page (may require login)',
    private: 'My eyes only (only for My files)',
  };

  var AREA_LABELS = { priv: 'My files', pub: 'Page files', old: 'Old files' };

  // Single-area actions (delete/upload/createfolder) are gated only when
  // that area is Page files - My files is always allowed.
  function pubAreaOK(perm) { return state.area !== 'pub' || perm; }
  // Move/copy touch two areas - gated as soon as Page files is on either
  // end. Migrating out of Old files always needs filemanager_migrate,
  // regardless of destination.
  function moveAllowed(toArea) { return !((state.area === 'pub' || toArea === 'pub') && !PERM.move); }
  function copyAllowed(toArea) { return !((state.area === 'pub' || toArea === 'pub') && !PERM.copy); }
  function migrateAllowed() { return PERM.migrate; }

  // Areas available AND permitted as a move/copy/migrate destination -
  // drives both the relevant button's visibility and the picker's tabs.
  function destinationAreasFor(kind) {
    var allowedFn = kind === 'move' ? moveAllowed : (kind === 'copy' ? copyAllowed : migrateAllowed);
    var areas = [];
    if (CAN_PRIVATE && allowedFn('priv')) areas.push('priv');
    if (CAN_PUBLIC && allowedFn('pub')) areas.push('pub');
    return areas;
  }

  // Whether ANY bulk action (move/copy/delete/migrate) is possible right
  // now - if not, don't show multi-select checkboxes at all.
  function bulkActionsAvailable() {
    if (state.area === 'old') return destinationAreasFor('migrate').length > 0;
    return destinationAreasFor('move').length > 0 || destinationAreasFor('copy').length > 0 || pubAreaOK(PERM.delete);
  }

  function loadPref(key, fallback) {
    try {
      var v = window.localStorage.getItem('fm_' + key);
      return v === null ? fallback : v;
    } catch (e) { return fallback; }
  }
  function savePref(key, value) {
    try { window.localStorage.setItem('fm_' + key, value); } catch (e) { /* ignore (e.g. private mode) */ }
  }

  var state = {
    // "My files" first per product decision - private area is the default
    // landing tab whenever it's available.
    area: CAN_PRIVATE ? 'priv' : (CAN_PUBLIC ? 'pub' : 'old'),
    id: CAN_PRIVATE ? USERID : (CAN_PUBLIC ? PAGEID : USERID),
    path: '',
    selected: null,   // {name, ext, isFolder, previewUrl, mtime, size}
    level: null,      // chosen access level for the current selection (pub/priv only)
    mode: 'index',    // chosen link mode for a selected folder: 'index' | 'gallery'
    view: loadPref('view', 'list'),       // 'grid' | 'list'
    sortBy: loadPref('sortBy', 'date'),   // 'name' | 'date' | 'size'
    sortDir: loadPref('sortDir', 'desc'), // 'asc' | 'desc'
    query: '',                            // current search filter (matches file/folder name)
  };

  // Tracks the single in-flight upload batch, or null when idle. See
  // doUpload()/renderUploadProgress() - only one batch runs at a time.
  var uploadState = null; // { xhr, total, loaded, files: [{name, status, error}] }

  // Multi-select for bulk move/copy/delete, keyed within the current
  // folder only (cleared on navigation) - independent of state.selected.
  var multiSelected = {}; // key -> {name, isFolder}
  function multiKey(name, isFolder) { return (isFolder ? 'd:' : 'f:') + name; }
  function multiCount() { return Object.keys(multiSelected).length; }
  function clearMultiSelect() { multiSelected = {}; }

  // Last successful 'list' response for the current area/path, cached so
  // typing in the search box can re-filter instantly without re-hitting
  // the API. Cleared/replaced by load().
  var currentData = null;
  var currentReadOnly = false;

  // Name of the item currently being dragged (drag-and-drop move), so drop
  // targets can refuse to drop a folder onto itself. Cleared on dragend.
  var dragging = null; // {name, isFolder}

  var tabEls = []; // [{el, area}] kept around so clicking a tab can update
                    // every tab's active class, not just re-derive it once.

  function el(tag, attrs, children) {
    var e = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) {
      if (k === 'class') e.className = attrs[k];
      else if (k === 'text') e.textContent = attrs[k];
      else e.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }

  function apiFor(area, id, path, action, params) {
    var body = new URLSearchParams(Object.assign({
      action: action, area: area, id: id, path: path, pageid: PAGEID, csrf: CSRF
    }, params || {}));
    return fetch(API, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (text) {
          var parsed;
          try { parsed = JSON.parse(text); }
          catch (e) { throw new Error('Server returned an unexpected response (' + r.status + ')'); }
          return { ok: r.ok, body: parsed };
        });
      });
  }

  function api(action, params) {
    return apiFor(state.area, state.id, state.path, action, params);
  }

  function reportError(err) {
    var msg = (err && err.message) ? err.message : 'Something went wrong. Please try again.';
    alert(msg);
  }

  function buildFileBadge(ext) {
    var style = FILE_TYPE_STYLES[ext] || GENERIC_FILE_STYLE;
    return el('div', { class: 'fm-badge', text: style.label, style: 'background:' + style.color + ';' });
  }

  function typeAllowed(ext) {
    if (!TYPE) return true;
    if (TYPE === 'image') return IMAGE_EXT.indexOf(ext) !== -1;
    if (TYPE === 'media') return MEDIA_EXT.indexOf(ext) !== -1;
    return true;
  }

  function availableLevels() {
    var levels = ['link'];
    if (PAGEID) levels.push('page');
    if (state.area === 'priv') levels.push('private');
    return levels; // already least -> most restrictive
  }

  function defaultLevel() {
    var levels = availableLevels();
    if (state.area === 'priv' && levels.indexOf('private') !== -1) return 'private';
    if (state.area === 'pub' && levels.indexOf('page') !== -1) return 'page';
    return levels[0];
  }

  function render() {
    root.innerHTML = '';
    tabEls = [];

    var tabs = el('div', { class: 'fm-tabs' });
    // "My files" first, "Page files" second, "Old files" last - least to
    // most rarely used.
    [['priv', CAN_PRIVATE], ['pub', CAN_PUBLIC], ['old', CAN_OLD]].forEach(function (pair) {
      var area = pair[0], can = pair[1];
      if (!can) return;
      var tabEl = el('div', { class: 'fm-tab', text: AREA_LABELS[area] });
      tabEl.addEventListener('click', function () { switchArea(area); });
      tabs.appendChild(tabEl);
      tabEls.push({ el: tabEl, area: area });
    });
    root.appendChild(tabs);
    updateTabHighlight();

    root.appendChild(el('div', { class: 'fm-toolbar', id: 'fm-toolbar' }));
    root.appendChild(el('div', { class: 'fm-upload-progress', id: 'fm-upload-progress' }));

    var body = el('div', { class: 'fm-body fm-dropzone' });
    root.appendChild(body);
    ['dragover', 'dragleave', 'drop'].forEach(function (evt) {
      body.addEventListener(evt, function (e) {
        if (state.area === 'old' || !pubAreaOK(PERM.upload)) return; // read-only, or no upload permission
        if (uploadState) return; // one batch at a time - already uploading
        if (dragging) return; // internal file/folder move, not an OS file drag - handled by item/crumb drop targets
        e.preventDefault();
        body.classList.toggle('dragover', evt === 'dragover');
        if (evt === 'drop' && e.dataTransfer.files.length) doUpload(e.dataTransfer.files);
      });
    });

    root.appendChild(el('div', { class: 'fm-footer', id: 'fm-footer' }));

    renderToolbar();
    renderUploadProgress();
    load();
  }

  function renderToolbar() {
    var toolbar = document.getElementById('fm-toolbar');
    if (!toolbar) return;
    toolbar.innerHTML = '';

    var crumb = el('div', { class: 'fm-breadcrumb' });
    toolbar.appendChild(crumb);
    buildBreadcrumb();

    toolbar.appendChild(buildSearchBox());
    toolbar.appendChild(buildViewSortControls());

    if (state.area === 'old') {
      toolbar.appendChild(el('div', { class: 'fm-readonly-note', text: 'Read-only - use Migrate to move items into My files or Page files.' }));
      return;
    }

    var newFolderBtn = el('button', { class: 'fm-btn secondary', text: 'New folder' });
    newFolderBtn.addEventListener('click', onNewFolder);
    if (pubAreaOK(PERM.createfolder)) toolbar.appendChild(newFolderBtn);

    var uploadInput = el('input', { type: 'file', multiple: 'multiple', style: 'display:none' });
    uploadInput.addEventListener('change', function () { doUpload(uploadInput.files); uploadInput.value = ''; });
    var uploadBtn = el('button', { class: 'fm-btn', text: uploadState ? 'Uploading\u2026' : 'Upload' });
    if (uploadState) uploadBtn.disabled = true;
    uploadBtn.addEventListener('click', function () { uploadInput.click(); });
    if (pubAreaOK(PERM.upload)) {
      toolbar.appendChild(uploadBtn);
      toolbar.appendChild(uploadInput);
    }
  }

  /**
   * Search box: filters the current folder's files/folders by name as the
   * user types. Purely client-side against the already-loaded listing
   * (currentData), so it doesn't hit the API - re-rendering is instant.
   */
  function buildSearchBox() {
    var wrap = el('div', { class: 'fm-search' });

    var input = el('input', {
      type: 'search',
      class: 'fm-search-input',
      placeholder: 'Search this folder\u2026',
      value: state.query,
      'aria-label': 'Search files and folders',
    });
    var debounceTimer = null;
    input.addEventListener('input', function () {
      var value = input.value;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () { setQuery(value); }, 150);
    });
    // Esc clears the box without waiting for the debounce.
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && input.value) {
        e.stopPropagation();
        clearTimeout(debounceTimer);
        input.value = '';
        setQuery('');
      }
    });
    wrap.appendChild(input);

    return wrap;
  }

  function setQuery(query) {
    if (query === state.query) return;
    state.query = query;
    renderBody();
  }

  function matchesQuery(name) {
    if (!state.query) return true;
    return name.toLowerCase().indexOf(state.query.toLowerCase()) !== -1;
  }

  var SORT_LABELS = { name: 'Name', date: 'Date modified', size: 'Size' };

  function buildViewSortControls() {
    var wrap = el('div', { class: 'fm-view-sort' });

    var sortSelect = el('select', { class: 'fm-sort-select', title: 'Sort by' });
    ['name', 'date', 'size'].forEach(function (key) {
      var opt = el('option', { value: key, text: SORT_LABELS[key] });
      if (key === state.sortBy) opt.setAttribute('selected', 'selected');
      sortSelect.appendChild(opt);
    });
    sortSelect.addEventListener('change', function () { setSort(sortSelect.value, state.sortDir); });
    wrap.appendChild(sortSelect);

    var dirBtn = el('button', {
      class: 'fm-btn secondary fm-sort-dir',
      text: state.sortDir === 'asc' ? '\u2191' : '\u2193',
      title: state.sortDir === 'asc' ? 'Ascending' : 'Descending',
    });
    dirBtn.addEventListener('click', function () { setSort(state.sortBy, state.sortDir === 'asc' ? 'desc' : 'asc'); });
    wrap.appendChild(dirBtn);

    var viewToggle = el('div', { class: 'fm-view-toggle' });
    [['grid', '\u25a6 Grid'], ['list', '\u2261 List']].forEach(function (pair) {
      var btn = el('button', { class: 'fm-btn secondary' + (state.view === pair[0] ? ' active' : ''), text: pair[1] });
      btn.addEventListener('click', function () { setView(pair[0]); });
      viewToggle.appendChild(btn);
    });
    wrap.appendChild(viewToggle);

    return wrap;
  }

  function setView(view) {
    if (view === state.view) return;
    state.view = view;
    savePref('view', view);
    renderToolbar();
    load();
  }

  function setSort(sortBy, sortDir) {
    if (sortBy === state.sortBy && sortDir === state.sortDir) return;
    state.sortBy = sortBy;
    state.sortDir = sortDir;
    savePref('sortBy', sortBy);
    savePref('sortDir', sortDir);
    renderToolbar();
    load();
  }

  function updateTabHighlight() {
    tabEls.forEach(function (t) {
      t.el.classList.toggle('active', t.area === state.area);
    });
  }

  function switchArea(area) {
    if (area === state.area) return;
    state.area = area;
    state.id = area === 'pub' ? PAGEID : USERID; // priv and old are both keyed by userid
    state.path = '';
    state.selected = null;
    state.query = '';
    clearMultiSelect();
    updateTabHighlight();
    renderToolbar();
    load();
  }

  function buildBreadcrumb() {
    var crumb = root.querySelector('.fm-breadcrumb');
    if (!crumb) return;
    crumb.innerHTML = '';
    var rootBtn = el('button', { text: AREA_LABELS[state.area] });
    rootBtn.addEventListener('click', function () { state.path = ''; state.selected = null; state.query = ''; clearMultiSelect(); renderToolbar(); load(); });
    crumb.appendChild(rootBtn);
    makeDropTarget(rootBtn, '');
    if (state.path) {
      state.path.split('/').forEach(function (seg, idx, arr) {
        crumb.appendChild(document.createTextNode(' / '));
        var b = el('button', { text: seg });
        var segPath = arr.slice(0, idx + 1).join('/');
        b.addEventListener('click', function () {
          state.path = segPath;
          state.selected = null;
          state.query = '';
          clearMultiSelect();
          renderToolbar();
          load();
        });
        crumb.appendChild(b);
        makeDropTarget(b, segPath);
      });
    }
  }

  /**
   * Wire up `target` (a breadcrumb button, or a folder tile/row) as a
   * drop target for drag-and-drop moves: dropping a dragged file/folder
   * on it moves that item into `toPath` (a folder path within the
   * current area/id). No-op (not even highlighted) in the read-only
   * "Old files" area or while nothing is being dragged.
   */
  function makeDropTarget(target, toPath) {
    target.addEventListener('dragover', function (e) {
      if (!dragging || state.area === 'old') return;
      if (dragging.isFolder && (state.path ? state.path + '/' : '') + dragging.name === toPath) return; // no dropping a folder onto itself
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      target.classList.add('fm-drop-target');
    });
    target.addEventListener('dragleave', function () { target.classList.remove('fm-drop-target'); });
    target.addEventListener('drop', function (e) {
      target.classList.remove('fm-drop-target');
      if (!dragging || state.area === 'old') return;
      e.preventDefault();
      var moved = dragging;
      if (toPath === state.path) return; // already here
      if (moved.isFolder && (state.path ? state.path + '/' : '') + moved.name === toPath) return; // folder onto itself
      api('move', { name: moved.name, target: moved.isFolder ? 'folder' : 'file', toArea: state.area, toId: state.id, toPath: toPath }).then(function (res) {
        if (!res.ok) { reportError(new Error(res.body.error || 'Move failed')); return; }
        if (state.selected && state.selected.name === moved.name) state.selected = null;
        load();
      }).catch(reportError);
    });
  }

  /**
   * Sort comparator for the current state.sortBy/sortDir. Folders and
   * files are sorted separately (see load()) and always grouped with
   * folders first, so this only needs to order within one group.
   * Folders have no 'size', so a size sort falls back to name for them.
   */
  function compareEntries(a, b) {
    var dir = state.sortDir === 'desc' ? -1 : 1;
    var av, bv;
    if (state.sortBy === 'date') { av = a.mtime; bv = b.mtime; }
    else if (state.sortBy === 'size' && !a.isFolder) { av = a.size; bv = b.size; }
    else { av = a.name.toLowerCase(); bv = b.name.toLowerCase(); }
    if (av < bv) return -1 * dir;
    if (av > bv) return 1 * dir;
    return 0;
  }

  function load() {
    api('list', {}).then(function (res) {
      buildBreadcrumb();
      if (!res.ok) {
        currentData = null;
        var body = root.querySelector('.fm-body');
        body.innerHTML = '';
        body.appendChild(el('div', { class: 'fm-empty', text: res.body.error || 'Error loading folder' }));
        renderFooter();
        return;
      }
      currentData = res.body;
      currentReadOnly = state.area === 'old';
      renderBody();
      renderFooter();
    }).catch(function (err) {
      currentData = null;
      var body = root.querySelector('.fm-body');
      body.innerHTML = '';
      body.appendChild(el('div', { class: 'fm-empty', text: err.message || 'Could not load this folder.' }));
    });
  }

  /**
   * Renders the current folder's contents from the cached currentData,
   * applying the type filter (typeAllowed), the search filter
   * (matchesQuery) and the current sort. Called after every fetch, and
   * again whenever the search box or sort/view controls change - none of
   * which need a fresh API call.
   */
  function renderBody() {
    var body = root.querySelector('.fm-body');
    if (!body) return;
    body.innerHTML = '';
    if (!currentData) return;
    var data = currentData;
    var readOnly = currentReadOnly;

    var folders = data.folders.filter(function (f) { return matchesQuery(f.name); }).map(function (f) {
      return { isFolder: true, name: f.name, mtime: f.mtime, readOnly: readOnly };
    });
    var files = data.files.filter(function (f) { return typeAllowed(f.ext) && matchesQuery(f.name); }).map(function (f) {
      return { isFolder: false, name: f.name, ext: f.ext, previewUrl: f.previewUrl, mtime: f.mtime, size: f.size, readOnly: readOnly };
    });
    folders.sort(compareEntries);
    files.sort(compareEntries);

    if (!folders.length && !files.length) {
      body.appendChild(el('div', {
        class: 'fm-empty',
        text: state.query
          ? 'No files or folders match \u201c' + state.query + '\u201d.'
          : (readOnly ? 'No files found here.' : 'This folder is empty. Drag files here or click Upload.')
      }));
    } else if (state.view === 'list') {
      body.appendChild(renderList(folders, files));
    } else {
      body.appendChild(renderGrid(folders, files));
    }
  }

  function onOpenFor(f) {
    return f.isFolder
      ? function () { state.path = (state.path ? state.path + '/' : '') + f.name; state.selected = null; state.query = ''; clearMultiSelect(); renderToolbar(); load(); }
      : function (itemEl) { selectItem({ name: f.name, isFolder: false, ext: f.ext, previewUrl: f.previewUrl }, itemEl); };
  }
  function onSelectFor(f) {
    if (f.readOnly || !f.isFolder) return null;
    return function (itemEl) { selectItem({ name: f.name, isFolder: true }, itemEl); };
  }

  function renderGrid(folders, files) {
    var grid = el('div', { class: 'fm-grid' });
    folders.concat(files).forEach(function (f) {
      grid.appendChild(makeItem(Object.assign({}, f, { onOpen: onOpenFor(f), onSelect: onSelectFor(f) })));
    });
    return grid;
  }

  function renderList(folders, files) {
    var showChecks = bulkActionsAvailable();
    var wrap = el('div', { class: 'fm-list' + (showChecks ? '' : ' no-check') });
    var all = folders.concat(files);
    var header = el('div', { class: 'fm-list-row fm-list-header' });

    if (showChecks) {
      var checkHeaderCell = el('div', { class: 'fm-list-cell fm-list-check' });
      var selectAll = el('input', { type: 'checkbox', class: 'fm-multi-check' });
      selectAll.checked = all.length > 0 && all.every(isMultiSelected);
      selectAll.addEventListener('change', function () {
        if (selectAll.checked) {
          all.forEach(function (f) { multiSelected[multiKey(f.name, f.isFolder)] = { name: f.name, isFolder: f.isFolder }; });
          state.selected = null;
        } else {
          clearMultiSelect();
        }
        renderBody();
        renderFooter();
      });
      checkHeaderCell.appendChild(selectAll);
      header.appendChild(checkHeaderCell);
    }

    [['name', 'Name'], ['date', 'Date modified'], ['size', 'Size']].forEach(function (pair) {
      var th = el('button', { class: 'fm-list-th' + (state.sortBy === pair[0] ? ' active' : '') });
      th.appendChild(document.createTextNode(pair[1] + ' '));
      if (state.sortBy === pair[0]) {
        th.appendChild(document.createTextNode(state.sortDir === 'asc' ? '\u2191' : '\u2193'));
      }
      th.addEventListener('click', function () {
        setSort(pair[0], state.sortBy === pair[0] && state.sortDir === 'asc' ? 'desc' : 'asc');
      });
      header.appendChild(th);
    });
    header.appendChild(el('span', { class: 'fm-list-th fm-list-th-actions' }));
    wrap.appendChild(header);
    all.forEach(function (f) {
      wrap.appendChild(makeListRow(Object.assign({}, f, { onOpen: onOpenFor(f), onSelect: onSelectFor(f) })));
    });
    return wrap;
  }

  function selectItem(f, itemEl) {
    document.querySelectorAll('.fm-item.selected').forEach(function (n) { n.classList.remove('selected'); });
    if (itemEl) itemEl.classList.add('selected');
    state.selected = f;
    state.level = state.area === 'old' ? null : defaultLevel();
    state.mode = 'index';
    if (multiCount()) { clearMultiSelect(); renderBody(); }
    renderFooter();
  }

  function isMultiSelected(f) {
    return !!multiSelected[multiKey(f.name, f.isFolder)];
  }

  function toggleMultiSelect(f) {
    var key = multiKey(f.name, f.isFolder);
    if (multiSelected[key]) delete multiSelected[key];
    else multiSelected[key] = { name: f.name, isFolder: f.isFolder };
    state.selected = null; // checkbox pick supersedes the single-item panel
    renderBody();
    renderFooter();
  }

  function renderFooter() {
    var footer = document.getElementById('fm-footer');
    if (!footer) return;
    footer.innerHTML = '';

    if (multiCount() > 0) {
      footer.appendChild(buildBulkBar());
      return;
    }

    var sel = state.selected;

    if (sel) {
      var selRow = el('div', { class: 'fm-selection' });
      selRow.appendChild(el('div', { class: 'fm-selection-name', text: sel.name }));

      if (state.area === 'old') {
        // Old files bypass filegate entirely - the direct URL IS the link,
        // no access level to choose.
        var copyBtnOld = el('button', { class: 'fm-btn secondary', text: 'Copy Link' });
        copyBtnOld.addEventListener('click', function () { copyToClipboard(sel.previewUrl, copyBtnOld); });
        selRow.appendChild(copyBtnOld);
      } else {
        var levels = availableLevels();
        var select = el('select', { class: 'fm-level-select' });
        levels.forEach(function (lvl) {
          var opt = el('option', { value: lvl, text: LEVEL_LABELS[lvl] });
          if (lvl === state.level) opt.setAttribute('selected', 'selected');
          select.appendChild(opt);
        });
        select.addEventListener('change', function () { state.level = select.value; });
        selRow.appendChild(select);

        if (sel.isFolder && ALLOW_GALLERY) {
          var modeSelect = el('select', { class: 'fm-level-select' });
          [['index', 'Index (list files)'], ['gallery', 'Gallery']].forEach(function (pair) {
            var opt = el('option', { value: pair[0], text: pair[1] });
            if (pair[0] === state.mode) opt.setAttribute('selected', 'selected');
            modeSelect.appendChild(opt);
          });
          modeSelect.addEventListener('change', function () { state.mode = modeSelect.value; });
          selRow.appendChild(modeSelect);
        }

        var copyBtn = el('button', { class: 'fm-btn secondary', text: 'Copy Link' });
        copyBtn.addEventListener('click', function () {
          getShareUrl(function (url) { copyToClipboard(url, copyBtn); });
        });
        selRow.appendChild(copyBtn);

        if (destinationAreasFor('move').indexOf(state.area === 'priv' ? 'pub' : 'priv') !== -1) {
          var moveBtn = el('button', { class: 'fm-btn secondary', text: 'Move to ' + (state.area === 'priv' ? 'Page files' : 'My files') });
          moveBtn.addEventListener('click', function () { onMove(sel); });
          selRow.appendChild(moveBtn);
        }
      }

      footer.appendChild(selRow);
    }

    var actions = el('div', { class: 'fm-footer-actions' });
    if (EMBEDDED) {
      var cancelBtn = el('button', { class: 'fm-btn secondary', text: 'Cancel' });
      cancelBtn.addEventListener('click', function () {
        TARGET.postMessage({ mceAction: 'cancel' }, '*');
      });
      actions.appendChild(cancelBtn);

      var insertBtn = el('button', { class: 'fm-btn', text: 'Insert' });
      insertBtn.disabled = !sel;
      insertBtn.addEventListener('click', function () {
        if (!sel) return;
        if (state.area === 'old') {
          TARGET.postMessage({
            mceAction: 'insert',
            file: { name: sel.name, ext: sel.ext || '', url: sel.previewUrl, isFolder: !!sel.isFolder }
          }, '*');
          return;
        }
        getShareUrl(function (url) {
          TARGET.postMessage({
            mceAction: 'insert',
            file: { name: sel.name, ext: sel.ext || '', url: url, isFolder: !!sel.isFolder, mode: state.mode }
          }, '*');
        });
      });
      actions.appendChild(insertBtn);
    }
    footer.appendChild(actions);
  }

  // Shown instead of the single-item panel when items are checkbox-
  // selected. Move/Copy open the destination picker; Delete/Migrate act
  // directly (mirroring onDelete/onMigrate).
  function buildBulkBar() {
    var bar = el('div', { class: 'fm-selection' });
    var items = Object.keys(multiSelected).map(function (k) { return multiSelected[k]; });
    bar.appendChild(el('div', { class: 'fm-selection-name', text: items.length + ' selected' }));

    var clearBtn = el('button', { class: 'fm-btn secondary', text: 'Clear' });
    clearBtn.addEventListener('click', function () { clearMultiSelect(); renderBody(); renderFooter(); });
    bar.appendChild(clearBtn);

    if (state.area === 'old') {
      if (destinationAreasFor('migrate').length) {
        var migrateBtn = el('button', { class: 'fm-btn secondary', text: 'Migrate to\u2026' });
        migrateBtn.addEventListener('click', function () { openDestinationPicker('migrate', items); });
        bar.appendChild(migrateBtn);
      }
    } else {
      if (destinationAreasFor('move').length) {
        var moveBtn = el('button', { class: 'fm-btn secondary', text: 'Move to\u2026' });
        moveBtn.addEventListener('click', function () { openDestinationPicker('move', items); });
        bar.appendChild(moveBtn);
      }
      if (destinationAreasFor('copy').length) {
        var copyBtn = el('button', { class: 'fm-btn secondary', text: 'Copy to\u2026' });
        copyBtn.addEventListener('click', function () { openDestinationPicker('copy', items); });
        bar.appendChild(copyBtn);
      }
      if (pubAreaOK(PERM.delete)) {
        var delBtn = el('button', { class: 'fm-btn danger', text: 'Delete selected' });
        delBtn.addEventListener('click', function () { onBulkDelete(items); });
        bar.appendChild(delBtn);
      }
    }
    return bar;
  }

  function onBulkDelete(items) {
    if (!pubAreaOK(PERM.delete)) return;
    var names = items.map(function (it) { return it.name; }).join(', ');
    if (!confirm('Delete ' + items.length + ' item' + (items.length > 1 ? 's' : '') + '?\n\n' + names
      + '\n\nThis deletes everything inside any selected folders.')) return;
    Promise.all(items.map(function (it) {
      return api('delete', { name: it.name, target: it.isFolder ? 'folder' : 'file' });
    })).then(function (results) {
      var failed = results.filter(function (r) { return !r.ok; });
      clearMultiSelect();
      state.selected = null;
      if (failed.length) {
        reportError(new Error(failed.length + ' item(s) could not be deleted.'));
      }
      load();
    }).catch(reportError);
  }

  // Move/Copy/Migrate `items` to a folder the user navigates to and
  // picks, in any area destinationAreasFor allows for `kind`. 'migrate' is
  // just 'move' server-side - kept separate here for labels/destinations.
  function openDestinationPicker(kind, items) {
    var apiAction = kind === 'migrate' ? 'move' : kind;
    var areas = destinationAreasFor(kind);
    if (!items.length || !areas.length) return;

    var pick = { area: areas[0], id: areas[0] === 'pub' ? PAGEID : USERID, path: '' };

    var overlay = el('div', { class: 'fm-modal-overlay' });
    var modal = el('div', { class: 'fm-modal' });
    var verb = kind === 'copy' ? 'Copy' : (kind === 'migrate' ? 'Migrate' : 'Move');
    modal.appendChild(el('div', {
      class: 'fm-modal-title',
      text: verb + ' ' + items.length + ' item' + (items.length > 1 ? 's' : '') + ' to\u2026'
    }));

    var tabsRow = el('div', { class: 'fm-modal-tabs' });
    var crumbRow = el('div', { class: 'fm-modal-crumb' });
    var listEl = el('div', { class: 'fm-modal-list' });
    modal.appendChild(tabsRow);
    modal.appendChild(crumbRow);
    modal.appendChild(listEl);

    var actionsRow = el('div', { class: 'fm-modal-actions' });
    var cancelBtn = el('button', { class: 'fm-btn secondary', text: 'Cancel' });
    cancelBtn.addEventListener('click', close);
    var confirmBtn = el('button', { class: 'fm-btn', text: verb + ' here' });
    confirmBtn.addEventListener('click', doConfirm);
    actionsRow.appendChild(cancelBtn);
    actionsRow.appendChild(confirmBtn);
    modal.appendChild(actionsRow);
    overlay.appendChild(modal);

    function close() { overlay.remove(); }

    function renderTabs() {
      tabsRow.innerHTML = '';
      if (areas.length < 2) {
        // Only one destination area is available - still show which one,
        // rather than leaving the picker with no area indicator at all.
        tabsRow.appendChild(el('span', { class: 'fm-tab-single', text: AREA_LABELS[areas[0]] }));
        return;
      }
      areas.forEach(function (a) {
        var btn = el('button', { class: 'fm-btn secondary' + (a === pick.area ? ' active' : ''), text: AREA_LABELS[a] });
        btn.addEventListener('click', function () {
          pick.area = a; pick.id = a === 'pub' ? PAGEID : USERID; pick.path = '';
          renderTabs(); refresh();
        });
        tabsRow.appendChild(btn);
      });
    }

    function renderCrumb() {
      crumbRow.innerHTML = '';
      var rootBtn = el('button', { text: AREA_LABELS[pick.area] });
      rootBtn.addEventListener('click', function () { pick.path = ''; refresh(); });
      crumbRow.appendChild(rootBtn);
      if (pick.path) {
        pick.path.split('/').forEach(function (seg, idx, arr) {
          crumbRow.appendChild(document.createTextNode(' / '));
          var segPath = arr.slice(0, idx + 1).join('/');
          var b = el('button', { text: seg });
          b.addEventListener('click', function () { pick.path = segPath; refresh(); });
          crumbRow.appendChild(b);
        });
      }
    }

    function refresh() {
      renderCrumb();
      listEl.innerHTML = '';
      listEl.appendChild(el('div', { class: 'fm-empty', text: 'Loading\u2026' }));
      apiFor(pick.area, pick.id, pick.path, 'list', {}).then(function (res) {
        listEl.innerHTML = '';
        if (!res.ok) {
          listEl.appendChild(el('div', { class: 'fm-empty', text: res.body.error || 'Could not load this folder.' }));
          return;
        }
        var folders = res.body.folders || [];
        if (!folders.length) {
          listEl.appendChild(el('div', { class: 'fm-empty', text: 'No subfolders here.' }));
          return;
        }
        folders.forEach(function (f) {
          var row = el('div', { class: 'fm-modal-folder' });
          row.appendChild(el('span', { class: 'fm-list-icon', text: '\uD83D\uDCC1' }));
          row.appendChild(el('span', { text: f.name }));
          row.addEventListener('click', function () {
            pick.path = (pick.path ? pick.path + '/' : '') + f.name;
            refresh();
          });
          listEl.appendChild(row);
        });
      }).catch(function (err) {
        listEl.innerHTML = '';
        listEl.appendChild(el('div', { class: 'fm-empty', text: err.message || 'Could not load this folder.' }));
      });
    }

    function doConfirm() {
      confirmBtn.disabled = true;
      var toArea = pick.area, toId = pick.id, toPath = pick.path;
      Promise.all(items.map(function (it) {
        return api(apiAction, { name: it.name, target: it.isFolder ? 'folder' : 'file', toArea: toArea, toId: toId, toPath: toPath });
      })).then(function (results) {
        var failed = results.filter(function (r) { return !r.ok; });
        close();
        clearMultiSelect();
        state.selected = null;
        if (failed.length) {
          reportError(new Error(failed.length + ' item(s) could not be ' + (kind === 'copy' ? 'copied' : 'moved') + '.'));
        }
        load();
      }).catch(function (err) {
        confirmBtn.disabled = false;
        reportError(err);
      });
    }

    renderTabs();
    refresh();
    root.appendChild(overlay);
  }

  function copyToClipboard(url, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function () {
        var original = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = original; }, 1500);
      });
    } else {
      prompt('Copy this link:', url);
    }
  }

  function getShareUrl(cb) {
    if (!state.selected) return;
    var params = { name: state.selected.name, level: state.level, pageid: PAGEID };
    if (state.selected.isFolder) {
      params.target = 'folder';
      params.mode = state.mode;
    }
    api('geturl', params).then(function (res) {
      if (!res.ok) { reportError(new Error(res.body.error || 'Could not generate a link')); return; }
      cb(res.body.url);
    }).catch(reportError);
  }

  // Action buttons (migrate/link/rename/delete) shared by the grid tile
  // and list row. `container` lets the link button pass itself to onSelect.
  function buildItemActions(opts, container) {
    var actions = el('div', { class: 'fm-item-actions' });
    if (opts.readOnly) {
      if (destinationAreasFor('migrate').length) {
        var migrateBtn = el('button', { text: '\u21ea', title: 'Migrate to My files / Page files' });
        migrateBtn.addEventListener('click', function (e) { e.stopPropagation(); onMigrate(opts); });
        actions.appendChild(migrateBtn);
      }
    } else {
      if (opts.isFolder && opts.onSelect) {
        var linkBtn = el('button', { text: '\uD83D\uDD17', title: 'Get a link to this folder' });
        linkBtn.addEventListener('click', function (e) { e.stopPropagation(); opts.onSelect(container); });
        actions.appendChild(linkBtn);
      }
      if (pubAreaOK(PERM.edit)) {
        var renameBtn = el('button', { text: '\u270e', title: 'Rename' });
        renameBtn.addEventListener('click', function (e) { e.stopPropagation(); onRename(opts); });
        actions.appendChild(renameBtn);
      }
      if (pubAreaOK(PERM.delete)) {
        var deleteBtn = el('button', { text: '\u2715', title: 'Delete' });
        deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); onDelete(opts); });
        actions.appendChild(deleteBtn);
      }
    }
    return actions;
  }

  // Checkbox for bulk move/copy/delete/migrate. Shown even when readOnly -
  // Old files items can still be bulk-migrated.
  function buildMultiCheckbox(opts) {
    var box = el('input', { type: 'checkbox', class: 'fm-multi-check' });
    box.checked = isMultiSelected(opts);
    box.addEventListener('click', function (e) { e.stopPropagation(); });
    box.addEventListener('change', function () { toggleMultiSelect(opts); });
    return box;
  }

  // Makes `item` draggable when not read-only and move is permitted, and
  // wires folders as drop targets for other dragged items.
  function wireDragAndDrop(item, opts) {
    if (opts.readOnly || !moveAllowed(state.area)) return;
    item.setAttribute('draggable', 'true');
    item.addEventListener('dragstart', function (e) {
      dragging = { name: opts.name, isFolder: !!opts.isFolder };
      item.classList.add('fm-dragging');
      e.dataTransfer.effectAllowed = 'move';
      // Needed for some browsers (notably Firefox) to allow the drag at all.
      try { e.dataTransfer.setData('text/plain', opts.name); } catch (e2) { /* ignore */ }
    });
    item.addEventListener('dragend', function () {
      item.classList.remove('fm-dragging');
      dragging = null;
    });
    if (opts.isFolder) {
      makeDropTarget(item, (state.path ? state.path + '/' : '') + opts.name);
    }
  }

  function makeItem(opts) {
    var item = el('div', { class: 'fm-item' + (isMultiSelected(opts) ? ' multi-selected' : '') });
    if (bulkActionsAvailable()) item.appendChild(buildMultiCheckbox(opts));
    var thumb = el('div', { class: 'fm-thumb' });
    if (!opts.isFolder && IMAGE_EXT.indexOf(opts.ext) !== -1) {
      thumb.appendChild(el('img', { src: opts.previewUrl, alt: opts.name }));
    } else if (opts.isFolder) {
      thumb.appendChild(el('div', { class: 'fm-badge fm-badge-folder', text: '\uD83D\uDCC1' }));
    } else {
      thumb.appendChild(buildFileBadge(opts.ext));
    }
    item.appendChild(thumb);
    item.appendChild(el('div', { class: 'fm-name', text: opts.name }));
    if (!opts.isFolder) {
      item.appendChild(el('div', {
        class: 'fm-meta',
        text: humanSize(opts.size) + ' \u00b7 ' + new Date(opts.mtime * 1000).toLocaleDateString()
      }));
    }

    item.appendChild(buildItemActions(opts, item));

    item.addEventListener('click', function () {
      if (opts.isFolder) opts.onOpen();
      else opts.onOpen(item);
    });
    item.addEventListener('dblclick', function () {
      if (opts.isFolder) opts.onOpen();
    });
    wireDragAndDrop(item, opts);
    return item;
  }

  function makeListRow(opts) {
    var row = el('div', { class: 'fm-list-row fm-item' + (isMultiSelected(opts) ? ' multi-selected' : '') });

    if (bulkActionsAvailable()) {
      var checkCell = el('div', { class: 'fm-list-cell fm-list-check' });
      checkCell.appendChild(buildMultiCheckbox(opts));
      row.appendChild(checkCell);
    }

    var nameCell = el('div', { class: 'fm-list-cell fm-list-name' });
    if (!opts.isFolder && IMAGE_EXT.indexOf(opts.ext) !== -1) {
      nameCell.appendChild(el('img', { class: 'fm-list-thumb', src: opts.previewUrl, alt: opts.name }));
    } else if (opts.isFolder) {
      nameCell.appendChild(el('span', { class: 'fm-list-icon', text: '\uD83D\uDCC1' }));
    } else {
      var style = FILE_TYPE_STYLES[opts.ext] || GENERIC_FILE_STYLE;
      nameCell.appendChild(el('span', { class: 'fm-list-icon fm-list-icon-badge', text: style.label, style: 'background:' + style.color + ';' }));
    }
    nameCell.appendChild(el('span', { class: 'fm-name', text: opts.name }));
    row.appendChild(nameCell);

    row.appendChild(el('div', {
      class: 'fm-list-cell fm-list-date',
      text: new Date(opts.mtime * 1000).toLocaleDateString()
    }));
    row.appendChild(el('div', {
      class: 'fm-list-cell fm-list-size',
      text: opts.isFolder ? '\u2014' : humanSize(opts.size)
    }));

    var actionsCell = el('div', { class: 'fm-list-cell fm-list-actions' });
    actionsCell.appendChild(buildItemActions(opts, row));
    row.appendChild(actionsCell);

    row.addEventListener('click', function () {
      if (opts.isFolder) opts.onOpen();
      else opts.onOpen(row);
    });
    row.addEventListener('dblclick', function () {
      if (opts.isFolder) opts.onOpen();
    });
    wireDragAndDrop(row, opts);
    return row;
  }

  function humanSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function onNewFolder() {
    if (!pubAreaOK(PERM.createfolder)) return;
    var name = prompt('New folder name:');
    if (!name) return;
    api('mkdir', { name: name }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Could not create folder'));
      else load();
    }).catch(reportError);
  }

  function onRename(opts) {
    if (!pubAreaOK(PERM.edit)) return;
    var newName = prompt('Rename to:', opts.name);
    if (!newName || newName === opts.name) return;
    api('rename', { old: opts.name, new: newName, target: opts.isFolder ? 'folder' : 'file' }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Rename failed'));
      else load();
    }).catch(reportError);
  }

  function onDelete(opts) {
    if (!pubAreaOK(PERM.delete)) return;
    if (!confirm('Delete "' + opts.name + '"?' + (opts.isFolder ? ' This deletes everything inside it.' : ''))) return;
    api('delete', { name: opts.name, target: opts.isFolder ? 'folder' : 'file' }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Delete failed'));
      else load();
    }).catch(reportError);
  }

  function onMove(opts) {
    var toArea = state.area === 'priv' ? 'pub' : 'priv';
    if (!moveAllowed(toArea)) return;
    var toId = toArea === 'pub' ? PAGEID : USERID;
    var label = toArea === 'priv' ? 'My files' : 'Page files';
    if (!confirm('Move "' + opts.name + '" to ' + label + '?')) return;
    api('move', { name: opts.name, target: opts.isFolder ? 'folder' : 'file', toArea: toArea, toId: toId, toPath: '' }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Move failed'));
      else { state.selected = null; load(); }
    }).catch(reportError);
  }

  function onMigrate(opts) {
    if (!migrateAllowed()) return; // requires filemanager_migrate regardless of destination
    var offerPublic = CAN_PUBLIC;
    var toArea = 'priv';
    if (offerPublic) {
      toArea = confirm('Move "' + opts.name + '" to Page files?\n\nOK = Page files, Cancel = My files') ? 'pub' : 'priv';
    } else if (!confirm('Move "' + opts.name + '" to My files?')) {
      return;
    }
    var toId = toArea === 'pub' ? PAGEID : USERID;
    api('move', { name: opts.name, target: opts.isFolder ? 'folder' : 'file', toArea: toArea, toId: toId, toPath: '' }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Move failed'));
      else { state.selected = null; load(); }
    }).catch(reportError);
  }

  function doUpload(fileList) {
    if (state.area === 'old' || !pubAreaOK(PERM.upload)) return;
    if (uploadState) return; // one batch at a time - toolbar button/dropzone are disabled while uploading

    var files = Array.prototype.slice.call(fileList);
    if (!files.length) return;

    var fd = new FormData();
    fd.append('action', 'upload');
    fd.append('area', state.area);
    fd.append('id', state.id);
    fd.append('path', state.path);
    fd.append('pageid', PAGEID);
    fd.append('csrf', CSRF);
    files.forEach(function (file) { fd.append('file[]', file); });

    var xhr = new XMLHttpRequest();
    uploadState = {
      xhr: xhr,
      total: files.reduce(function (sum, f) { return sum + f.size; }, 0),
      loaded: 0,
      files: files.map(function (f) { return { name: f.name, status: 'pending' }; }),
    };
    renderToolbar();
    renderUploadProgress();

    xhr.upload.addEventListener('progress', function (e) {
      if (!uploadState) return;
      if (e.lengthComputable) uploadState.loaded = e.loaded;
      renderUploadProgress();
    });

    xhr.addEventListener('load', function () {
      if (!uploadState) return; // cleared already (e.g. abort raced the load event)
      var parsed = null;
      try { parsed = JSON.parse(xhr.responseText); } catch (e) { /* handled below */ }
      if (!parsed) {
        uploadState = null;
        renderToolbar();
        renderUploadProgress();
        reportError(new Error('Upload failed: the server returned an unexpected response (' + xhr.status + ').'));
        load(); // refresh anyway - the file(s) that did succeed should still show up
        return;
      }
      var results = parsed.results || [];
      uploadState.loaded = uploadState.total; // in case the final progress event never fired
      uploadState.files.forEach(function (f, i) {
        var r = results[i];
        f.status = r && r.ok ? 'ok' : 'error';
        f.error = r ? r.error : 'No response';
      });
      var hadFailure = uploadState.files.some(function (f) { return f.status === 'error'; });
      renderUploadProgress();
      load();
      // Leave the summary on screen briefly (longer if something failed) before clearing it.
      setTimeout(function () {
        uploadState = null;
        renderToolbar();
        renderUploadProgress();
      }, hadFailure ? 3000 : 800);
    });

    xhr.addEventListener('error', function () {
      uploadState = null;
      renderToolbar();
      renderUploadProgress();
      reportError(new Error('Upload failed - network error.'));
      load(); // refresh anyway - the file(s) that did succeed should still show up
    });

    xhr.addEventListener('abort', function () {
      uploadState = null;
      renderToolbar();
      renderUploadProgress();
      load();
    });

    xhr.open('POST', API, true);
    xhr.withCredentials = true;
    xhr.send(fd);
  }

  function cancelUpload() {
    if (uploadState && uploadState.xhr) uploadState.xhr.abort();
  }

  function renderUploadProgress() {
    var wrap = document.getElementById('fm-upload-progress');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!uploadState) { wrap.classList.remove('active'); return; }
    wrap.classList.add('active');

    var pct = uploadState.total > 0 ? Math.min(100, Math.round(uploadState.loaded / uploadState.total * 100)) : 100;
    var settled = uploadState.files.every(function (f) { return f.status !== 'pending'; });

    var head = el('div', { class: 'fm-upload-head' });
    head.appendChild(el('div', {
      class: 'fm-upload-label',
      text: settled
        ? 'Upload complete'
        : 'Uploading ' + uploadState.files.length + (uploadState.files.length === 1 ? ' file' : ' files') +
          '\u2026 ' + humanSize(uploadState.loaded) + ' of ' + humanSize(uploadState.total) + ' (' + pct + '%)'
    }));
    if (!settled) {
      var cancelBtn = el('button', { class: 'fm-upload-cancel', text: 'Cancel', title: 'Cancel upload' });
      cancelBtn.addEventListener('click', cancelUpload);
      head.appendChild(cancelBtn);
    }
    wrap.appendChild(head);

    var track = el('div', { class: 'fm-upload-bar-track' });
    track.appendChild(el('div', { class: 'fm-upload-bar-fill' + (settled ? ' done' : ''), style: 'width:' + pct + '%;' }));
    wrap.appendChild(track);

    if (uploadState.files.length > 1 || settled) {
      var list = el('div', { class: 'fm-upload-files' });
      uploadState.files.forEach(function (f) {
        var row = el('div', { class: 'fm-upload-file fm-upload-file-' + f.status });
        var icon = f.status === 'ok' ? '\u2713' : f.status === 'error' ? '\u2715' : '\u2026';
        row.appendChild(el('span', { class: 'fm-upload-file-icon', text: icon }));
        row.appendChild(el('span', { class: 'fm-upload-file-name', text: f.name }));
        if (f.status === 'error' && f.error) row.appendChild(el('span', { class: 'fm-upload-file-msg', text: f.error }));
        list.appendChild(row);
      });
      wrap.appendChild(list);
    }
  }

  render();
})();