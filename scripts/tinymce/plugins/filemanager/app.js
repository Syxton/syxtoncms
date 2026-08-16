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
  var TYPE = root.dataset.type; // '', 'image', 'media', 'file'
  // Only true when opened by the HTML feature's editor (see plugin.js /
  // tmp/pagelib.template) - the Gallery/Index folder-link choice only
  // means anything there, since it's that feature's photogallery filter
  // that looks for the resulting attribute (see htmllib.php).
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
    view: loadPref('view', 'grid'),       // 'grid' | 'list'
    sortBy: loadPref('sortBy', 'name'),   // 'name' | 'date' | 'size'
    sortDir: loadPref('sortDir', 'asc'),  // 'asc' | 'desc'
    query: '',                            // current search filter (matches file/folder name)
  };

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

  function api(action, params) {
    var body = new URLSearchParams(Object.assign({
      action: action, area: state.area, id: state.id, path: state.path, csrf: CSRF
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

    var body = el('div', { class: 'fm-body fm-dropzone' });
    root.appendChild(body);
    ['dragover', 'dragleave', 'drop'].forEach(function (evt) {
      body.addEventListener(evt, function (e) {
        if (state.area === 'old') return; // read-only area, no uploads
        if (dragging) return; // internal file/folder move, not an OS file drag - handled by item/crumb drop targets
        e.preventDefault();
        body.classList.toggle('dragover', evt === 'dragover');
        if (evt === 'drop' && e.dataTransfer.files.length) doUpload(e.dataTransfer.files);
      });
    });

    root.appendChild(el('div', { class: 'fm-footer', id: 'fm-footer' }));

    renderToolbar();
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
    toolbar.appendChild(newFolderBtn);

    var uploadInput = el('input', { type: 'file', multiple: 'multiple', style: 'display:none' });
    uploadInput.addEventListener('change', function () { doUpload(uploadInput.files); uploadInput.value = ''; });
    var uploadBtn = el('button', { class: 'fm-btn', text: 'Upload' });
    uploadBtn.addEventListener('click', function () { uploadInput.click(); });
    toolbar.appendChild(uploadBtn);
    toolbar.appendChild(uploadInput);
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
    updateTabHighlight();
    renderToolbar();
    load();
  }

  function buildBreadcrumb() {
    var crumb = root.querySelector('.fm-breadcrumb');
    if (!crumb) return;
    crumb.innerHTML = '';
    var rootBtn = el('button', { text: AREA_LABELS[state.area] });
    rootBtn.addEventListener('click', function () { state.path = ''; state.selected = null; state.query = ''; renderToolbar(); load(); });
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
      ? function () { state.path = (state.path ? state.path + '/' : '') + f.name; state.selected = null; state.query = ''; renderToolbar(); load(); }
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
    var wrap = el('div', { class: 'fm-list' });
    var header = el('div', { class: 'fm-list-row fm-list-header' });
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
    folders.concat(files).forEach(function (f) {
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
    renderFooter();
  }

  function renderFooter() {
    var footer = document.getElementById('fm-footer');
    if (!footer) return;
    footer.innerHTML = '';
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

        if (CAN_PUBLIC && CAN_PRIVATE) {
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

  /**
   * Action buttons (migrate / link / rename / delete) shared by the grid
   * tile and the list row. `container` is the item element itself, needed
   * so the "get a link" button can pass it along to onSelect for the
   * .selected highlight.
   */
  function buildItemActions(opts, container) {
    var actions = el('div', { class: 'fm-item-actions' });
    if (opts.readOnly) {
      var migrateBtn = el('button', { text: '\u21ea', title: 'Migrate to My files / Page files' });
      migrateBtn.addEventListener('click', function (e) { e.stopPropagation(); onMigrate(opts); });
      actions.appendChild(migrateBtn);
    } else {
      if (opts.isFolder && opts.onSelect) {
        var linkBtn = el('button', { text: '\uD83D\uDD17', title: 'Get a link to this folder' });
        linkBtn.addEventListener('click', function (e) { e.stopPropagation(); opts.onSelect(container); });
        actions.appendChild(linkBtn);
      }
      var renameBtn = el('button', { text: '\u270e', title: 'Rename' });
      renameBtn.addEventListener('click', function (e) { e.stopPropagation(); onRename(opts); });
      var deleteBtn = el('button', { text: '\u2715', title: 'Delete' });
      deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); onDelete(opts); });
      actions.appendChild(renameBtn);
      actions.appendChild(deleteBtn);
    }
    return actions;
  }

  /**
   * Makes `item` draggable (as the source of a move) when not read-only,
   * and, if it's a folder, wires it up as a drop target for other items
   * being dragged onto it (moves the dragged item into this folder).
   */
  function wireDragAndDrop(item, opts) {
    if (opts.readOnly) return;
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
    var item = el('div', { class: 'fm-item' });
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
    var row = el('div', { class: 'fm-list-row fm-item' });

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
    var name = prompt('New folder name:');
    if (!name) return;
    api('mkdir', { name: name }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Could not create folder'));
      else load();
    }).catch(reportError);
  }

  function onRename(opts) {
    var newName = prompt('Rename to:', opts.name);
    if (!newName || newName === opts.name) return;
    api('rename', { old: opts.name, new: newName, target: opts.isFolder ? 'folder' : 'file' }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Rename failed'));
      else load();
    }).catch(reportError);
  }

  function onDelete(opts) {
    if (!confirm('Delete "' + opts.name + '"?' + (opts.isFolder ? ' This deletes everything inside it.' : ''))) return;
    api('delete', { name: opts.name, target: opts.isFolder ? 'folder' : 'file' }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Delete failed'));
      else load();
    }).catch(reportError);
  }

  function onMove(opts) {
    var toArea = state.area === 'priv' ? 'pub' : 'priv';
    var toId = toArea === 'pub' ? PAGEID : USERID;
    var label = toArea === 'priv' ? 'My files' : 'Page files';
    if (!confirm('Move "' + opts.name + '" to ' + label + '?')) return;
    api('move', { name: opts.name, target: opts.isFolder ? 'folder' : 'file', toArea: toArea, toId: toId, toPath: '' }).then(function (res) {
      if (!res.ok) reportError(new Error(res.body.error || 'Move failed'));
      else { state.selected = null; load(); }
    }).catch(reportError);
  }

  function onMigrate(opts) {
    // Migrating out of the read-only "Old files" area into a real,
    // managed area. If both destinations are available, ask which.
    var toArea = 'priv';
    if (CAN_PUBLIC) {
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
    if (state.area === 'old') return; // read-only area
    var fd = new FormData();
    fd.append('action', 'upload');
    fd.append('area', state.area);
    fd.append('id', state.id);
    fd.append('path', state.path);
    fd.append('csrf', CSRF);
    Array.prototype.forEach.call(fileList, function (file) { fd.append('file[]', file); });
    fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (text) {
          var parsed;
          try { parsed = JSON.parse(text); }
          catch (e) { throw new Error('Upload failed: the server returned an unexpected response (' + r.status + ').'); }
          return parsed;
        });
      })
      .then(function (res) {
        var failed = (res.results || []).filter(function (r) { return !r.ok; });
        if (failed.length) {
          reportError(new Error('Some files failed to upload:\n' + failed.map(function (f) { return f.name + ': ' + f.error; }).join('\n')));
        }
        load();
      })
      .catch(function (err) {
        reportError(err);
        load(); // refresh anyway - the file(s) that did succeed should still show up
      });
  }

  render();
})();