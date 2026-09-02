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
  var PDF_EXT = 'pdf';

  function isPreviewable(ext) {
    return IMAGE_EXT.indexOf(ext) !== -1 || MEDIA_EXT.indexOf(ext) !== -1 || ext === PDF_EXT;
  }

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
    link: 'Anyone with this link',
    page: 'Can view page',
    private: 'My eyes only',
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

  // True while doUpload()'s conflict precheck/choice modal is in flight,
  // i.e. the window between picking files and uploadState actually being
  // set - guards the Upload button/dropzone against a second upload
  // starting while that's still being decided.
  var uploadPending = false;

  // Tracks the single reversible action available for undo (rename/move/
  // delete), or null when none is pending. See showUndo()/performUndo() -
  // area/id/path context is captured in the closure at action time, not
  // read live off `state`, so undo stays correct even if the person has
  // since navigated elsewhere.
  var undoState = null; // { message, action: () => Promise<{ok, body}>, timeoutId }

  // Multi-select for bulk move/copy/delete, keyed within the current
  // folder only (cleared on navigation) - independent of state.selected.
  var multiSelected = {}; // key -> {name, isFolder}
  function multiKey(name, isFolder) { return (isFolder ? 'd:' : 'f:') + name; }
  function multiCount() { return Object.keys(multiSelected).length; }
  function clearMultiSelect() { multiSelected = {}; }

  // --- Keyboard navigation / range-select state -----------------------
  // The flat, in-display-order list of {name, isFolder, ...} currently
  // shown (post filter/sort), set by renderBody() each time it runs -
  // this is what arrow keys, Home/End, and Ctrl+A operate over.
  var visibleItems = [];
  // multiKey() of the item that currently owns the roving tabindex (see
  // rovingKey()) - independent of selection, though a plain click/arrow
  // move sets both together.
  var focusedKey = null;
  // multiKey() of the item a Shift+click/Shift+arrow range extends from.
  // Deliberately not reset by Ctrl+click/Ctrl+arrow, matching the usual
  // desktop convention that only a *plain* selection redefines the anchor.
  var selectionAnchorKey = null;

  /**
   * Pure index math for arrow-key navigation over a flat, row-major list
   * of `total` items arranged in `columns` columns (columns=1 for the
   * list view, which has no horizontal axis). Arrow keys clamp at the
   * edges rather than wrapping - Home/End are the explicit way to jump to
   * the absolute ends - matching most desktop file managers.
   */
  function computeNavIndex(current, total, columns, key) {
    if (total <= 0) return -1;
    if (key === 'Home') return 0;
    if (key === 'End') return total - 1;
    var next = current;
    if (key === 'ArrowRight') next = current + 1;
    else if (key === 'ArrowLeft') next = current - 1;
    else if (key === 'ArrowDown') next = current + columns;
    else if (key === 'ArrowUp') next = current - columns;
    else return current;
    if (next < 0 || next >= total) return current;
    return next;
  }

  /** Inclusive range between anchorIndex and targetIndex, in list order, regardless of which is larger. */
  function computeRangeSelection(items, anchorIndex, targetIndex) {
    var lo = Math.min(anchorIndex, targetIndex);
    var hi = Math.max(anchorIndex, targetIndex);
    return items.slice(lo, hi + 1);
  }

  function findVisibleIndex(key) {
    for (var i = 0; i < visibleItems.length; i++) {
      if (multiKey(visibleItems[i].name, visibleItems[i].isFolder) === key) return i;
    }
    return -1;
  }

  /** Which item's multiKey should currently have tabindex=0 - the tracked focus if it's still visible, else the first item. */
  function rovingKey() {
    if (focusedKey && findVisibleIndex(focusedKey) !== -1) return focusedKey;
    return visibleItems.length ? multiKey(visibleItems[0].name, visibleItems[0].isFolder) : null;
  }

  /** Re-selects the multi-select range between the anchor and target, tolerating a stale/missing anchor by falling back to a single-item range. */
  function applyRangeSelection(anchorKey, targetKey) {
    var anchorIndex = anchorKey ? findVisibleIndex(anchorKey) : -1;
    var targetIndex = findVisibleIndex(targetKey);
    if (anchorIndex === -1) anchorIndex = targetIndex;
    var range = computeRangeSelection(visibleItems, anchorIndex, targetIndex);
    var sel = {};
    range.forEach(function (it) { sel[multiKey(it.name, it.isFolder)] = { name: it.name, isFolder: it.isFolder }; });
    multiSelected = sel;
    state.selected = null;
  }

  /** Finds the current DOM node for `key` post re-render and focuses it - a plain reference to the old node would be stale/detached. */
  function focusItemByKey(key) {
    if (!key) return;
    var items = document.querySelectorAll('.fm-item');
    for (var i = 0; i < items.length; i++) {
      if (items[i].dataset.key === key) { items[i].focus(); return; }
    }
  }

  /**
   * How many items make up one row of the grid view, for Up/Down arrow
   * purposes - measured from actual layout (items sharing the first
   * item's offsetTop) rather than computed from CSS, since the grid's
   * column count is responsive (repeat(auto-fill, ...)). Always 1 for
   * the list view, which has no horizontal axis.
   */
  function gridColumnCount() {
    if (state.view !== 'grid') return 1;
    var items = document.querySelectorAll('.fm-grid > .fm-item');
    if (!items.length) return 1;
    var firstTop = items[0].offsetTop;
    var count = 0;
    for (var i = 0; i < items.length; i++) {
      if (items[i].offsetTop === firstTop) count++;
      else break;
    }
    return count || 1;
  }

  // Last successful 'list' response for the current area/path, cached so
  // typing in the search box can re-filter instantly without re-hitting
  // the API. Cleared/replaced by load().
  var currentData = null;
  var currentReadOnly = false;

  // Soft-delete trash entry count for the current area+id (from list /
  // trash_list). Drives the recycle button color, badge, and title.
  var trashCount = 0;

  // Name of the item currently being dragged (drag-and-drop move), so drop
  // targets can refuse to drop a folder onto itself. Cleared on dragend.
  var dragging = null; // {name, isFolder}

  var tabEls = []; // [{el, area}] kept around so clicking a tab can update
                    // every tab's active class, not just re-derive it once.

  // The currently-open per-item "more actions" dropdown (see
  // buildItemActions), or null. Only one open at a time.
  var openItemMenu = null;

  // Reference to the toolbar's hidden upload <input>, set by
  // renderToolbar() - lets the background right-click menu (see
  // showBackgroundMenu) trigger the same file picker without building a
  // second one.
  var uploadInputEl = null;

  function closeItemMenu() {
    if (openItemMenu) {
      openItemMenu.classList.remove('open');
      if (openItemMenu.onCloseExtra) openItemMenu.onCloseExtra();
      openItemMenu = null;
    }
  }

  /**
   * Places a fixed-position item menu against the button that opened it,
   * clamped to the viewport. Fixed (rather than absolute-inside-the-item)
   * is what lets this escape .fm-list-cell's overflow:hidden and the
   * .fm-list-row.fm-item position:static override - it positions purely
   * off btn's on-screen rect, not any ancestor. Prefers hanging
   * below-and-right-aligned to the button (the old fixed CSS default),
   * but slides left if that would run off the right edge (grid's
   * leftmost column, where the item itself is narrower than the menu)
   * and flips above the button if there's no room below.
   */
  function positionItemMenu(menu, btn) {
    var margin = 8;
    var btnRect = btn.getBoundingClientRect();
    var menuW = menu.offsetWidth;
    var menuH = menu.offsetHeight;

    var left = btnRect.right - menuW; // right-align to the button by default
    left = Math.max(margin, Math.min(left, window.innerWidth - menuW - margin));

    var top = btnRect.bottom + 4;
    if (top + menuH > window.innerHeight - margin) {
      top = btnRect.top - menuH - 4; // no room below - open upward instead
    }

    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }

  /**
   * Same clamping as positionItemMenu, but anchored to a raw point
   * (the cursor) rather than a button's rect - used for right-click.
   */
  function positionItemMenuAtPoint(menu, x, y) {
    var margin = 8;
    var menuW = menu.offsetWidth;
    var menuH = menu.offsetHeight;
    menu.style.left = Math.max(margin, Math.min(x, window.innerWidth - menuW - margin)) + 'px';
    menu.style.top = Math.max(margin, Math.min(y, window.innerHeight - menuH - margin)) + 'px';
  }

  /**
   * Right-click on empty space in the file list (not on an item - those
   * stop propagation in buildItemActions' own contextmenu handler above)
   * offers New folder/Upload, mirroring the toolbar. Built fresh each
   * time and thrown away on close, rather than kept around like the
   * per-item menus, since there's no single persistent "background item"
   * to hang it off.
   */
  function showBackgroundMenu(x, y) {
    if (state.area === 'old') return false; // read-only - same early-out as the toolbar

    var menuItems = [];
    if (pubAreaOK(PERM.createfolder)) {
      menuItems.push({ icon: '\uD83D\uDCC1', label: 'New folder', handler: onNewFolder });
    }
    if (pubAreaOK(PERM.upload)) {
      menuItems.push({ icon: '\u2B06', label: 'Upload', handler: function () { if (uploadInputEl) uploadInputEl.click(); } });
    }
    if (!menuItems.length) return false;

    var menu = el('div', { class: 'fm-item-menu' });
    menuItems.forEach(function (mi) {
      var itemBtn = el('button', {}, [
        el('span', { class: 'fm-item-menu-icon', text: mi.icon }),
        el('span', { text: mi.label })
      ]);
      itemBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        closeItemMenu();
        mi.handler();
      });
      menu.appendChild(itemBtn);
    });
    wireMenuKeyboardNav(menu);

    root.appendChild(menu);
    closeItemMenu();
    menu.classList.add('open');
    openItemMenu = menu;
    openItemMenu.onCloseExtra = function () { menu.remove(); };
    positionItemMenuAtPoint(menu, x, y);
    var firstItem = focusableIn(menu)[0];
    if (firstItem) firstItem.focus();
    return true;
  }

  // Closes the open item menu on any click outside it, or on Escape.
  // Registered once - this whole file is a single IIFE run once per
  // dialog load, so there's no risk of stacking duplicate listeners.
  document.addEventListener('click', function (e) {
    if (openItemMenu && !openItemMenu.contains(e.target)) closeItemMenu();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeItemMenu();
  });
  window.addEventListener('resize', closeItemMenu);

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

  /**
   * Icon+label button used throughout the toolbar/footer/bulk bar (New
   * folder, Download, Move to..., etc). Renders both an icon and a text
   * label; CSS hides the label below a width breakpoint so narrow/mobile
   * layouts stay icon-only while wide layouts show the label too, since
   * an icon alone is often ambiguous once there's room to just say what
   * it does. `title` still carries the label for the icon-only case
   * (tooltip, and screen readers via the button's accessible name).
   */
  function iconBtn(icon, label, extraClass) {
    var btn = el('button', { class: 'fm-icon-btn' + (extraClass ? ' ' + extraClass : ''), title: label, 'aria-label': label });
    btn.appendChild(el('span', { class: 'fm-icon-btn-icon', text: icon }));
    btn.appendChild(el('span', { class: 'fm-icon-btn-label', text: label }));
    return btn;
  }

  var modalIdCounter = 0;

  // Elements a keyboard user could reasonably land on inside a modal or
  // menu - used both for the modal focus trap below and for arrow-key
  // navigation within item/background menus.
  function focusableIn(container) {
    return Array.prototype.slice.call(
      container.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
    );
  }

  /**
   * Standard role="menu" keyboard conventions for a dropdown that's
   * already open: ArrowUp/Down move between items, Home/End jump to the
   * ends. Escape is already handled globally (see closeItemMenu's
   * document-level listener), so it isn't duplicated here. Shared by the
   * per-item kebab/right-click menu and the background New folder/Upload
   * menu - both are flat lists of button children.
   */
  function wireMenuKeyboardNav(menu) {
    menu.setAttribute('role', 'menu');
    focusableIn(menu).forEach(function (it) { it.setAttribute('role', 'menuitem'); });
    menu.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Home' && e.key !== 'End') return;
      var items = focusableIn(menu);
      if (!items.length) return;
      var idx = items.indexOf(document.activeElement);
      e.preventDefault();
      if (e.key === 'ArrowDown') items[(idx + 1) % items.length].focus();
      else if (e.key === 'ArrowUp') items[(idx - 1 + items.length) % items.length].focus();
      else if (e.key === 'Home') items[0].focus();
      else if (e.key === 'End') items[items.length - 1].focus();
    });
  }

  /**
   * Wraps `modal` in the standard .fm-modal-overlay, appends it to the
   * DOM, and wires up the two dismissal paths every modal here should
   * support: clicking the backdrop, and Escape. Returns a close()
   * function the caller can also invoke itself (e.g. after a successful
   * action), so it doesn't need to remember to remove its own listener.
   * `onDismiss`, if given, runs on every close regardless of how it was
   * triggered - for modals that resolve a Promise (see
   * askConflictChoice), this is how Escape/backdrop-click still resolve
   * it (as cancelled) instead of leaving it hanging forever.
   *
   * Also handles the accessibility groundwork every modal here wants:
   * role="dialog"/aria-modal so assistive tech announces it as such,
   * aria-labelledby pointing at whatever .fm-modal-title it finds (all
   * four callers already have one), a Tab/Shift+Tab focus trap so
   * keyboard focus can't silently leave the dialog into the page behind
   * it, moving focus into the dialog on open, and restoring it to
   * whatever triggered the dialog once it closes.
   */
  function showModal(modal, onDismiss) {
    var overlay = el('div', { class: 'fm-modal-overlay' });

    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    if (!modal.hasAttribute('tabindex')) modal.setAttribute('tabindex', '-1');
    var titleEl = modal.querySelector('.fm-modal-title');
    if (titleEl) {
      if (!titleEl.id) titleEl.id = 'fm-modal-title-' + (++modalIdCounter);
      modal.setAttribute('aria-labelledby', titleEl.id);
    }

    overlay.appendChild(modal);

    var previouslyFocused = document.activeElement;
    function close() {
      overlay.remove();
      document.removeEventListener('keydown', onKey);
      if (onDismiss) onDismiss();
      if (previouslyFocused && typeof previouslyFocused.focus === 'function') previouslyFocused.focus();
    }
    function onKey(e) {
      if (e.key === 'Escape') { close(); return; }
      if (e.key !== 'Tab') return;
      var focusables = focusableIn(modal);
      if (!focusables.length) { e.preventDefault(); return; }
      var first = focusables[0], last = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', onKey);
    root.appendChild(overlay);

    var initialFocusables = focusableIn(modal);
    (initialFocusables[0] || modal).focus();

    return close;
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

    var tabs = el('div', { class: 'fm-tabs', role: 'tablist', 'aria-label': 'File areas' });
    // "My files" first, "Page files" second, "Old files" last - least to
    // most rarely used. Buttons (not divs) so they participate in Tab
    // order and are activatable with Enter/Space.
    [['priv', CAN_PRIVATE], ['pub', CAN_PUBLIC], ['old', CAN_OLD]].forEach(function (pair) {
      var area = pair[0], can = pair[1];
      if (!can) return;
      var tabEl = el('button', {
        type: 'button',
        class: 'fm-tab',
        text: AREA_LABELS[area],
        role: 'tab',
        'aria-selected': 'false',
        id: 'fm-tab-' + area
      });
      tabEl.addEventListener('click', function () { switchArea(area); });
      tabEl.addEventListener('keydown', function (e) {
        // Left/Right move focus (and activation) across the area tabs;
        // stopPropagation so the root list navigator does not steal them.
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Home' && e.key !== 'End') return;
        e.preventDefault();
        e.stopPropagation();
        if (!tabEls.length) return;
        var idx = -1;
        for (var i = 0; i < tabEls.length; i++) {
          if (tabEls[i].el === tabEl) { idx = i; break; }
        }
        if (idx === -1) return;
        var next = idx;
        if (e.key === 'ArrowRight') next = (idx + 1) % tabEls.length;
        else if (e.key === 'ArrowLeft') next = (idx - 1 + tabEls.length) % tabEls.length;
        else if (e.key === 'Home') next = 0;
        else if (e.key === 'End') next = tabEls.length - 1;
        switchArea(tabEls[next].area);
        tabEls[next].el.focus();
      });
      tabs.appendChild(tabEl);
      tabEls.push({ el: tabEl, area: area });
    });
    root.appendChild(tabs);
    updateTabHighlight();

    root.appendChild(el('div', { class: 'fm-toolbar', id: 'fm-toolbar' }));
    root.appendChild(el('div', { class: 'fm-upload-progress', id: 'fm-upload-progress' }));
    root.appendChild(el('div', { class: 'fm-breadcrumb-bar', id: 'fm-breadcrumb-bar' }));

    var body = el('div', { class: 'fm-body fm-dropzone' });
    root.appendChild(body);
    // The item menu is position:fixed (see positionItemMenu) so it isn't
    // clipped by .fm-list-cell's overflow:hidden - but that also means it
    // won't scroll along with the row that opened it. Simplest fix: just
    // close it if this scrolls, same as clicking outside would.
    body.addEventListener('scroll', closeItemMenu);
    // Arrow/Home/End/Ctrl+A for the file list are handled on the root so
    // they work even when focus is still on a toolbar button (not yet
    // tabbed into an .fm-item). handleItemKeydown ignores form controls,
    // open menus, and modals so it won't steal keys from those.
    root.addEventListener('keydown', handleItemKeydown);
    // Click on empty space (grid/list background, not an item) clears the
    // selection and parks keyboard focus on the first visible item so the
    // next arrow key works immediately - same idea as the arrow bootstrap.
    body.addEventListener('click', function (e) {
      if (!visibleItems.length) return;
      if (e.target.closest && e.target.closest('.fm-item')) return;
      // Ignore clicks that originated on nested controls outside items
      // (e.g. future chrome inside the body).
      if (e.target.closest && e.target.closest('button, input, select, textarea, a')) return;
      clearMultiSelect();
      state.selected = null;
      selectionAnchorKey = null;
      focusedKey = multiKey(visibleItems[0].name, visibleItems[0].isFolder);
      renderBody();
      renderFooter();
      focusItemByKey(focusedKey);
    });
    body.addEventListener('contextmenu', function (e) {
      // Item rows stop propagation in their own contextmenu handler (see
      // buildItemActions), so this only ever fires for genuine empty
      // space - the grid/list background, or the "this folder is empty"
      // message. Only suppress the browser's own menu if we actually have
      // something to offer instead (showBackgroundMenu reports that).
      if (showBackgroundMenu(e.clientX, e.clientY)) e.preventDefault();
    });
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
    root.appendChild(el('div', { class: 'fm-undo-toast', id: 'fm-undo-toast' }));

    renderToolbar();
    renderUploadProgress();
    load();
  }

  // Kept as a single entry point since call sites throughout the file
  // expect one function to refresh "everything above the file list" -
  // internally it's the two rows: search/sort, and the breadcrumb bar.
  function renderToolbar() {
    renderSearchSortBar();
    renderBreadcrumbBar();
  }

  function renderSearchSortBar() {
    var toolbar = document.getElementById('fm-toolbar');
    if (!toolbar) return;
    toolbar.innerHTML = '';

    if (state.area !== 'old') {
      var newFolderBtn = iconBtn('\uD83D\uDCC1', 'New folder');
      newFolderBtn.addEventListener('click', onNewFolder);
      if (pubAreaOK(PERM.createfolder)) toolbar.appendChild(newFolderBtn);

      var uploadInput = el('input', { type: 'file', multiple: 'multiple', style: 'display:none' });
      uploadInput.addEventListener('change', function () { doUpload(uploadInput.files); uploadInput.value = ''; });
      uploadInputEl = uploadInput;
      var uploadBtn = iconBtn('\u2B06', uploadState ? 'Uploading\u2026' : 'Upload');
      if (uploadState || uploadPending) uploadBtn.disabled = true;
      uploadBtn.addEventListener('click', function () { uploadInput.click(); });
      if (pubAreaOK(PERM.upload)) {
        toolbar.appendChild(uploadBtn);
        toolbar.appendChild(uploadInput);
      }

      var trashLabel = trashCount > 0
        ? ('Trash (' + trashCount + ' item' + (trashCount === 1 ? '' : 's') + ')')
        : 'Trash';
      var trashBtn = iconBtn('\u267B', trashLabel, trashCount > 0 ? 'has-items' : '');
      if (trashCount > 0) {
        var badgeText = trashCount > 99 ? '99+' : String(trashCount);
        trashBtn.appendChild(el('span', { class: 'fm-trash-badge', text: badgeText }));
      }
      trashBtn.addEventListener('click', openTrash);
      if (pubAreaOK(PERM.delete)) toolbar.appendChild(trashBtn);
    }

    toolbar.appendChild(buildSearchBox());
    toolbar.appendChild(buildViewSortControls());

    if (state.area === 'old') {
      toolbar.appendChild(el('div', { class: 'fm-readonly-note', text: 'Read-only - use Migrate to move items into My files or Page files.' }));
    }
  }

  // Full-width row directly above the file list, so long paths get room
  // to breathe instead of competing with the toolbar controls above.
  // Leading back button (Windows Explorer style) goes up one folder.
  function renderBreadcrumbBar() {
    var bar = document.getElementById('fm-breadcrumb-bar');
    if (!bar) return;
    bar.innerHTML = '';

    var backBtn = el('button', {
      class: 'fm-back-btn',
      text: '\u2190',
      title: 'Up one folder',
      'aria-label': 'Up one folder',
    });
    if (!state.path) {
      backBtn.disabled = true;
    } else {
      backBtn.addEventListener('click', navigateUp);
    }
    bar.appendChild(backBtn);

    var crumb = el('div', { class: 'fm-breadcrumb' });
    bar.appendChild(crumb);
    buildBreadcrumb();
  }

  /** Navigate to the parent of state.path (no-op at area root). */
  function navigateUp() {
    if (!state.path) return;
    var parts = state.path.split('/');
    parts.pop();
    state.path = parts.join('/');
    state.selected = null;
    state.query = '';
    clearMultiSelect();
    renderToolbar();
    load();
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
      'aria-label': state.sortDir === 'asc' ? 'Ascending' : 'Descending',
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
      var active = t.area === state.area;
      t.el.classList.toggle('active', active);
      t.el.setAttribute('aria-selected', active ? 'true' : 'false');
      // Roving tabindex within the tablist: only the active tab is in the
      // sequential Tab order; Left/Right move between tabs once focused.
      t.el.tabIndex = active ? 0 : -1;
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
      var fromArea = state.area, fromId = state.id, fromPath = state.path;
      checkConflicts([moved], state.area, state.id, toPath).then(function (conflicts) {
        var choicePromise = conflicts.length ? askConflictChoice(conflicts) : Promise.resolve('rename');
        return choicePromise.then(function (onConflict) {
          if (!onConflict) return; // cancelled
          return api('move', { name: moved.name, target: moved.isFolder ? 'folder' : 'file', toArea: state.area, toId: state.id, toPath: toPath, onConflict: onConflict }).then(function (res) {
            if (!res.ok) { reportError(new Error(res.body.error || 'Move failed')); return; }
            var finalName = res.body.name || moved.name;
            if (state.selected && state.selected.name === moved.name) state.selected = null;
            load();
            showUndo('Moved "' + moved.name + '"', function () {
              return apiFor(fromArea, fromId, toPath, 'move', { name: finalName, target: moved.isFolder ? 'folder' : 'file', toArea: fromArea, toId: fromId, toPath: fromPath });
            });
          });
        });
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
        trashCount = 0;
        renderSearchSortBar();
        var body = root.querySelector('.fm-body');
        body.innerHTML = '';
        body.appendChild(el('div', { class: 'fm-empty', text: res.body.error || 'Error loading folder' }));
        renderFooter();
        return;
      }
      currentData = res.body;
      currentReadOnly = state.area === 'old';
      trashCount = (typeof res.body.trashCount === 'number') ? res.body.trashCount : 0;
      renderSearchSortBar();
      renderBody();
      renderFooter();
    }).catch(function (err) {
      currentData = null;
      trashCount = 0;
      renderSearchSortBar();
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
    if (!currentData) { visibleItems = []; return; }
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
    visibleItems = folders.concat(files);

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
    var grid = el('div', { class: 'fm-grid', role: 'listbox', 'aria-multiselectable': 'true' });
    folders.concat(files).forEach(function (f) {
      grid.appendChild(makeItem(Object.assign({}, f, { onOpen: onOpenFor(f), onSelect: onSelectFor(f) })));
    });
    return grid;
  }

  function renderList(folders, files) {
    var showChecks = bulkActionsAvailable();
    var wrap = el('div', { class: 'fm-list' + (showChecks ? '' : ' no-check'), role: 'listbox', 'aria-multiselectable': 'true' });
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

    [['name', 'Name', ''], ['date', 'Date modified', 'fm-list-date'], ['size', 'Size', 'fm-list-size']].forEach(function (pair) {
      var th = el('button', { class: 'fm-list-th' + (pair[2] ? ' ' + pair[2] : '') + (state.sortBy === pair[0] ? ' active' : '') });
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
    if (multiSelected[key]) {
      delete multiSelected[key];
    } else {
      multiSelected[key] = { name: f.name, isFolder: f.isFolder };
      // Starting or extending a multi-selection via checkbox should establish
      // the range-select anchor, otherwise Shift+click has nothing to range from.
      if (!selectionAnchorKey) selectionAnchorKey = key;
    }
    state.selected = null; // checkbox pick supersedes the single-item panel
    focusedKey = key;
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

      if (state.area === 'old') {
        var topRowOld = el('div', { class: 'fm-selection-top' });
        topRowOld.appendChild(el('div', { class: 'fm-selection-name', text: sel.name }));

        // Old files bypass filegate entirely - the direct URL IS the link,
        // no access level to choose.
        var shareRowOld = el('div', { class: 'fm-selection-share' });
        var copyBtnOld = iconBtn('\uD83D\uDD17', 'Copy link');
        copyBtnOld.addEventListener('click', function () { copyToClipboard(sel.previewUrl, copyBtnOld); });
        shareRowOld.appendChild(copyBtnOld);
        if (!sel.isFolder) {
          var downloadBtnOld = iconBtn('\u2B07', 'Download');
          downloadBtnOld.addEventListener('click', function () { onDownload(sel); });
          shareRowOld.appendChild(downloadBtnOld);
        } else {
          var zipBtnOld = iconBtn('\u2B07', 'Download zip');
          zipBtnOld.addEventListener('click', function () { downloadZip([sel], zipBtnOld); });
          shareRowOld.appendChild(zipBtnOld);
        }
        topRowOld.appendChild(shareRowOld);
        selRow.appendChild(topRowOld);
        if (destinationAreasFor('migrate').length) {
          var actionsRowOld = el('div', { class: 'fm-selection-actions' });
          var migrateBtn = iconBtn('\u21ea', 'Migrate to\u2026');
          migrateBtn.addEventListener('click', function () { openDestinationPicker('migrate', [sel]); });
          actionsRowOld.appendChild(migrateBtn);
          selRow.appendChild(actionsRowOld);
        }
      } else {
        var topRow = el('div', { class: 'fm-selection-top' });
        topRow.appendChild(el('div', { class: 'fm-selection-name', text: sel.name }));

        var shareRow = el('div', { class: 'fm-selection-share' });
        var actionsRow = el('div', { class: 'fm-selection-actions' });

        var levels = availableLevels();
        var select = el('select', { class: 'fm-level-select' });
        levels.forEach(function (lvl) {
          var opt = el('option', { value: lvl, text: LEVEL_LABELS[lvl] });
          if (lvl === state.level) opt.setAttribute('selected', 'selected');
          select.appendChild(opt);
        });
        select.addEventListener('change', function () { state.level = select.value; });
        shareRow.appendChild(select);

        if (sel.isFolder && ALLOW_GALLERY) {
          var modeSelect = el('select', { class: 'fm-level-select' });
          [['index', 'Index'], ['gallery', 'Gallery']].forEach(function (pair) {
            var opt = el('option', { value: pair[0], text: pair[1] });
            if (pair[0] === state.mode) opt.setAttribute('selected', 'selected');
            modeSelect.appendChild(opt);
          });
          modeSelect.addEventListener('change', function () { state.mode = modeSelect.value; });
          shareRow.appendChild(modeSelect);
        }

        var copyBtn = iconBtn('\uD83D\uDD17', 'Copy link');
        copyBtn.addEventListener('click', function () {
          getShareUrl(function (url) { copyToClipboard(url, copyBtn); });
        });
        shareRow.appendChild(copyBtn);
        topRow.appendChild(shareRow);
        selRow.appendChild(topRow);

        if (!sel.isFolder) {
          var downloadBtn = iconBtn('\u2B07', 'Download');
          downloadBtn.addEventListener('click', function () { onDownload(sel); });
          actionsRow.appendChild(downloadBtn);
        } else {
          var zipBtn = iconBtn('\u2B07', 'Download zip');
          zipBtn.addEventListener('click', function () { downloadZip([sel], zipBtn); });
          actionsRow.appendChild(zipBtn);
        }

        if (destinationAreasFor('move').length) {
          var moveBtn = iconBtn('\uD83D\uDCE4', 'Move to\u2026');
          moveBtn.addEventListener('click', function () { openDestinationPicker('move', [sel]); });
          actionsRow.appendChild(moveBtn);
        }
        if (destinationAreasFor('copy').length) {
          var copyToBtn = iconBtn('\u29C9', 'Copy to\u2026');
          copyToBtn.addEventListener('click', function () { openDestinationPicker('copy', [sel]); });
          actionsRow.appendChild(copyToBtn);
        }

        selRow.appendChild(actionsRow);
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
  // selected. Move/Copy/Migrate open the destination picker; Delete acts directly.
  function buildBulkBar() {
    var bar = el('div', { class: 'fm-bulk-bar' });
    var items = Object.keys(multiSelected).map(function (k) { return multiSelected[k]; });
    bar.appendChild(el('div', { class: 'fm-selection-name', text: items.length + ' selected' }));

    var clearBtn = iconBtn('\u2610', 'Clear selection');
    clearBtn.addEventListener('click', function () { clearMultiSelect(); renderBody(); renderFooter(); });
    bar.appendChild(clearBtn);

    var zipBtn = iconBtn('\u2B07', 'Download zip');
    zipBtn.addEventListener('click', function () { downloadZip(items, zipBtn); });
    bar.appendChild(zipBtn);

    if (state.area === 'old') {
      if (destinationAreasFor('migrate').length) {
        var migrateBtn = iconBtn('\u21ea', 'Migrate to\u2026');
        migrateBtn.addEventListener('click', function () { openDestinationPicker('migrate', items); });
        bar.appendChild(migrateBtn);
      }
    } else {
      if (destinationAreasFor('move').length) {
        var moveBtn = iconBtn('\uD83D\uDCE4', 'Move to\u2026');
        moveBtn.addEventListener('click', function () { openDestinationPicker('move', items); });
        bar.appendChild(moveBtn);
      }
      if (destinationAreasFor('copy').length) {
        var copyBtn = iconBtn('\u29C9', 'Copy to\u2026');
        copyBtn.addEventListener('click', function () { openDestinationPicker('copy', items); });
        bar.appendChild(copyBtn);
      }
      if (pubAreaOK(PERM.delete)) {
        var delBtn = iconBtn('\u2715', 'Delete selected', 'danger');
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
    var area = state.area, id = state.id, path = state.path;
    Promise.all(items.map(function (it) {
      return api('delete', { name: it.name, target: it.isFolder ? 'folder' : 'file' })
        .then(function (res) { return { item: it, res: res }; });
    })).then(function (outcomes) {
      var failed = outcomes.filter(function (o) { return !o.res.ok; });
      var trashed = outcomes
        .filter(function (o) { return o.res.ok && o.res.body.trashId; })
        .map(function (o) { return { trashId: o.res.body.trashId }; });
      clearMultiSelect();
      state.selected = null;
      if (failed.length) {
        reportError(new Error(failed.length + ' item(s) could not be deleted.'));
      }
      load();
      if (trashed.length) {
        showUndo('Deleted ' + trashed.length + ' item' + (trashed.length > 1 ? 's' : ''), function () {
          return Promise.all(trashed.map(function (t) {
            return apiFor(area, id, path, 'restore', { trashId: t.trashId });
          })).then(function (results) {
            var restoreFailed = results.filter(function (r) { return !r.ok; });
            return {
              ok: restoreFailed.length === 0,
              body: { error: restoreFailed.length ? restoreFailed.length + ' item(s) could not be restored.' : '' }
            };
          });
        });
      }
    }).catch(reportError);
  }

  // Move/Copy/Migrate `items` to a folder the user navigates to and
  // picks, in any area destinationAreasFor allows for `kind`. 'migrate' is
  // just 'move' server-side - kept separate here for labels/destinations.
  //
  // Page files destinations are no longer locked to the editor's current
  // page: when the Page files tab is active the picker first offers a
  // search-as-you-type page list (api search_pages), then folder browsing
  // inside the chosen page. My files still keys off USERID as before.
  function openDestinationPicker(kind, items) {
    var apiAction = kind === 'migrate' ? 'move' : kind;
    var areas = destinationAreasFor(kind);
    if (!items.length || !areas.length) return;

    var pageAbility = kind === 'copy' ? 'filemanager_copy' : 'filemanager_move';
    // Pre-select the editor's current page when landing on Page files so
    // same-page moves stay one click; user can still change page via the
    // crumb root / search.
    var pick = {
      area: areas[0],
      id: areas[0] === 'pub' ? (PAGEID || '') : USERID,
      path: '',
      pageName: areas[0] === 'pub' && PAGEID ? 'Current page' : ''
    };

    var modal = el('div', { class: 'fm-modal' });
    var verb = kind === 'copy' ? 'Copy' : (kind === 'migrate' ? 'Migrate' : 'Move');
    modal.appendChild(el('div', {
      class: 'fm-modal-title',
      text: verb + ' ' + items.length + ' item' + (items.length > 1 ? 's' : '') + ' to\u2026'
    }));

    var tabsRow = el('div', { class: 'fm-modal-tabs' });
    var crumbRow = el('div', { class: 'fm-modal-crumb' });
    var searchRow = el('div', { class: 'fm-modal-search', style: 'display:none' });
    var listEl = el('div', { class: 'fm-modal-list' });
    modal.appendChild(tabsRow);
    modal.appendChild(crumbRow);
    modal.appendChild(searchRow);
    modal.appendChild(listEl);

    var searchInput = el('input', {
      type: 'search',
      class: 'fm-modal-search-input',
      placeholder: 'Search pages\u2026',
      autocomplete: 'off'
    });
    searchRow.appendChild(searchInput);
    var searchTimer = null;
    searchInput.addEventListener('input', function () {
      if (searchTimer) clearTimeout(searchTimer);
      searchTimer = setTimeout(function () { refresh(); }, 200);
    });

    var actionsRow = el('div', { class: 'fm-modal-actions' });
    var cancelBtn = el('button', { class: 'fm-btn secondary', text: 'Cancel' });
    var confirmBtn = el('button', { class: 'fm-btn', text: verb + ' here' });
    confirmBtn.addEventListener('click', doConfirm);
    actionsRow.appendChild(cancelBtn);
    actionsRow.appendChild(confirmBtn);
    modal.appendChild(actionsRow);

    var close = showModal(modal);
    cancelBtn.addEventListener('click', close);

    function needsPagePick() {
      return pick.area === 'pub' && !pick.id;
    }

    function updateConfirmEnabled() {
      // Can't land in Page files until a destination page is chosen.
      confirmBtn.disabled = needsPagePick();
    }

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
          pick.area = a;
          pick.path = '';
          if (a === 'pub') {
            pick.id = PAGEID || '';
            pick.pageName = PAGEID ? 'Current page' : '';
          } else {
            pick.id = USERID;
            pick.pageName = '';
          }
          renderTabs();
          refresh();
        });
        tabsRow.appendChild(btn);
      });
    }

    function renderCrumb() {
      crumbRow.innerHTML = '';
      var rootBtn = el('button', { text: AREA_LABELS[pick.area] });
      rootBtn.addEventListener('click', function () {
        if (pick.area === 'pub') {
          // Back to page search.
          pick.id = '';
          pick.pageName = '';
          pick.path = '';
        } else {
          pick.path = '';
        }
        refresh();
      });
      crumbRow.appendChild(rootBtn);

      if (pick.area === 'pub' && pick.id) {
        crumbRow.appendChild(document.createTextNode(' / '));
        var pageBtn = el('button', { text: pick.pageName || ('Page ' + pick.id) });
        pageBtn.addEventListener('click', function () {
          pick.path = '';
          refresh();
        });
        crumbRow.appendChild(pageBtn);
      }

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

    function showPageSearch() {
      searchRow.style.display = '';
      listEl.innerHTML = '';
      listEl.appendChild(el('div', { class: 'fm-empty', text: 'Loading pages\u2026' }));
      var q = (searchInput.value || '').trim();
      api('search_pages', { q: q, ability: pageAbility, current: PAGEID || '' }).then(function (res) {
        listEl.innerHTML = '';
        if (!res.ok) {
          listEl.appendChild(el('div', { class: 'fm-empty', text: res.body.error || 'Could not load pages.' }));
          return;
        }
        var pages = res.body.pages || [];
        if (!pages.length) {
          listEl.appendChild(el('div', {
            class: 'fm-empty',
            text: q ? 'No matching pages.' : 'No pages you can write to.'
          }));
          return;
        }
        pages.forEach(function (p) {
          var row = el('div', { class: 'fm-modal-folder fm-modal-page' });
          row.appendChild(el('span', { class: 'fm-list-icon', text: '\uD83D\uDCC4' }));
          var label = el('span', { text: p.name });
          row.appendChild(label);
          if (String(p.id) === String(PAGEID)) {
            row.appendChild(el('span', { class: 'fm-modal-page-badge', text: 'current' }));
          }
          row.addEventListener('click', function () {
            pick.id = String(p.id);
            pick.pageName = p.name || ('Page ' + p.id);
            pick.path = '';
            searchInput.value = '';
            refresh();
          });
          listEl.appendChild(row);
        });
      }).catch(function (err) {
        listEl.innerHTML = '';
        listEl.appendChild(el('div', { class: 'fm-empty', text: err.message || 'Could not load pages.' }));
      });
    }

    function showFolderBrowser() {
      searchRow.style.display = 'none';
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

    function refresh() {
      renderCrumb();
      updateConfirmEnabled();
      if (needsPagePick()) {
        showPageSearch();
      } else {
        showFolderBrowser();
      }
    }

    function doConfirm() {
      if (needsPagePick()) return;
      confirmBtn.disabled = true;
      var toArea = pick.area, toId = pick.id, toPath = pick.path;
      var fromArea = state.area, fromId = state.id, fromPath = state.path;
      // Same-folder copy is Duplicate: the sources themselves would always
      // show up as "conflicts", which is noise. Skip the dialog and let the
      // server auto-name with the "name (copy).ext" pattern.
      var sameFolderCopy = (kind === 'copy' && toArea === fromArea && toId === fromId && toPath === fromPath);

      var prep = sameFolderCopy
        ? Promise.resolve('rename')
        : checkConflicts(items, toArea, toId, toPath).then(function (conflicts) {
            return conflicts.length ? askConflictChoice(conflicts) : 'rename';
          });

      prep.then(function (onConflict) {
        if (!onConflict) { confirmBtn.disabled = false; return; } // cancelled - leave the picker open
        return Promise.all(items.map(function (it) {
          return api(apiAction, { name: it.name, target: it.isFolder ? 'folder' : 'file', toArea: toArea, toId: toId, toPath: toPath, onConflict: onConflict })
            .then(function (res) { return { item: it, res: res }; });
        })).then(function (outcomes) {
            var failed = outcomes.filter(function (o) { return !o.res.ok; });
            // Undo only for a plain 'move' - 'copy' leaves the source in place
            // (nothing destructive to reverse), and 'migrate' can't be
            // reversed through this same action since Old files is a valid
            // move source but never a valid destination (see api.php).
            var moved = kind === 'move'
              ? outcomes.filter(function (o) { return o.res.ok; })
                  .map(function (o) { return { name: o.res.body.name || o.item.name, isFolder: o.item.isFolder }; })
              : [];
            close();
            clearMultiSelect();
            state.selected = null;
            if (failed.length) {
              reportError(new Error(failed.length + ' item(s) could not be ' + (kind === 'copy' ? 'copied' : 'moved') + '.'));
            }
            load();
            if (moved.length) {
              showUndo('Moved ' + moved.length + ' item' + (moved.length > 1 ? 's' : '') + ' to ' + AREA_LABELS[toArea], function () {
                return Promise.all(moved.map(function (m) {
                  return apiFor(toArea, toId, toPath, 'move', { name: m.name, target: m.isFolder ? 'folder' : 'file', toArea: fromArea, toId: fromId, toPath: fromPath });
                })).then(function (results) {
                  var moveBackFailed = results.filter(function (r) { return !r.ok; });
                  return {
                    ok: moveBackFailed.length === 0,
                    body: { error: moveBackFailed.length ? moveBackFailed.length + ' item(s) could not be moved back.' : '' }
                  };
                });
              });
            }
          });
      }).catch(function (err) {
        confirmBtn.disabled = false;
        reportError(err);
      });
    }

    renderTabs();
    refresh();
  }


  function askConflictChoice(names) {
    return new Promise(function (resolve) {
      var modal = el('div', { class: 'fm-modal fm-conflict-modal' });
      modal.appendChild(el('div', {
        class: 'fm-modal-title',
        text: names.length === 1 ? '"' + names[0] + '" already exists' : names.length + ' items already exist'
      }));
      if (names.length > 1) {
        modal.appendChild(el('div', { class: 'fm-modal-crumb', text: names.join(', ') }));
      }
      modal.appendChild(el('div', { class: 'fm-conflict-note', text: 'Replaced items can be restored from Trash.' }));

      var actionsRow = el('div', { class: 'fm-modal-actions fm-conflict-actions' });
      var replaceBtn = el('button', { class: 'fm-btn danger', text: 'Replace' });
      var keepBtn = el('button', { class: 'fm-btn secondary', text: 'Keep both' });
      var cancelBtn = el('button', { class: 'fm-btn secondary', text: 'Cancel' });
      actionsRow.appendChild(replaceBtn);
      actionsRow.appendChild(keepBtn);
      actionsRow.appendChild(cancelBtn);
      modal.appendChild(actionsRow);

      // Escape/backdrop-click resolve as cancelled. Button clicks must
      // resolve FIRST, then close - showModal's close() always invokes
      // onDismiss, and a Promise only accepts its first resolve(). Calling
      // close() before resolve(value) was settling every choice as null
      // (cancel), so Replace / Keep both never actually moved the file.
      var settled = false;
      var close = showModal(modal, function () {
        if (!settled) { settled = true; resolve(null); }
      });
      function choose(value) {
        if (settled) return;
        settled = true;
        resolve(value);
        close();
      }
      replaceBtn.addEventListener('click', function () { choose('replace'); });
      keepBtn.addEventListener('click', function () { choose('rename'); });
      cancelBtn.addEventListener('click', function () { choose(null); });
    });
  }

  /**
   * Precheck for the above - asks the server which of `items`
   * ({name, isFolder}) already exist at the destination, so the choice
   * modal is only shown when something would really collide. Destination
   * defaults to the current area/id/path (e.g. for a same-folder upload
   * or duplicate precheck) when toArea/toId/toPath are omitted. Fails
   * open (reports no conflicts) if the precheck request itself fails -
   * the real action's own numbering loop is still there as a backstop.
   */
  function checkConflicts(items, toArea, toId, toPath) {
    var payload = {
      items: JSON.stringify(items.map(function (it) { return { name: it.name, target: it.isFolder ? 'folder' : 'file' }; }))
    };
    if (toArea !== undefined) { payload.toArea = toArea; payload.toId = toId; payload.toPath = toPath; }
    return api('check_conflicts', payload).then(function (res) {
      return (res.ok && res.body.conflicts) || [];
    }).catch(function () { return []; });
  }

  /**
   * Lightbox-style preview for images, video, audio, and PDFs - the file
   * types isPreviewable() recognizes. Reuses previewUrl as-is (no dl=1),
   * which filegate.php serves with Content-Disposition: inline by
   * default, so the browser renders it in place instead of downloading.
   */
  function openPreview(opts) {
    var modal = el('div', { class: 'fm-modal fm-preview-modal' });
    modal.appendChild(el('div', { class: 'fm-modal-title', text: opts.name }));

    var body = el('div', { class: 'fm-preview-body' });
    if (IMAGE_EXT.indexOf(opts.ext) !== -1) {
      body.appendChild(el('img', { class: 'fm-preview-media', src: opts.previewUrl, alt: opts.name }));
    } else if (opts.ext === 'mp4' || opts.ext === 'webm') {
      body.appendChild(el('video', { class: 'fm-preview-media', src: opts.previewUrl, controls: 'controls' }));
    } else if (opts.ext === 'mp3') {
      body.appendChild(el('audio', { class: 'fm-preview-audio', src: opts.previewUrl, controls: 'controls' }));
    } else if (opts.ext === PDF_EXT) {
      body.appendChild(el('iframe', { class: 'fm-preview-pdf', src: opts.previewUrl, title: opts.name }));
    } else {
      body.appendChild(el('div', { class: 'fm-empty', text: 'No preview available for this file type.' }));
    }
    modal.appendChild(body);

    var actionsRow = el('div', { class: 'fm-modal-actions' });
    var downloadBtn = iconBtn('\u2B07', 'Download');
    downloadBtn.addEventListener('click', function () { onDownload(opts); });
    actionsRow.appendChild(downloadBtn);
    var closeBtn = el('button', { class: 'fm-btn secondary', text: 'Close' });
    modal.appendChild(actionsRow);

    var close = showModal(modal);
    closeBtn.addEventListener('click', close);
    actionsRow.appendChild(closeBtn);
  }

  /**
   * "Trash" toolbar button - browses this area+id's soft-deleted items
   * (see the 'delete'/'restore'/'trash_list' actions in api.php) so
   * anything past the undo toast's 8-second window is still recoverable
   * for the full 30-day retention window. Never shown for Old files,
   * since nothing is ever deleted from there.
   */
  function openTrash() {
    var area = state.area, id = state.id;
    var modal = el('div', { class: 'fm-modal fm-trash-modal' });
    modal.appendChild(el('div', { class: 'fm-modal-title', text: 'Trash \u2014 ' + AREA_LABELS[area] }));
    modal.appendChild(el('div', {
      class: 'fm-modal-crumb',
      text: 'Deleted items are kept for 30 days, then removed automatically.'
    }));
    var listEl = el('div', { class: 'fm-modal-list fm-trash-list' });
    modal.appendChild(listEl);

    var actionsRow = el('div', { class: 'fm-modal-actions' });
    var emptyBtn = el('button', { class: 'fm-btn danger', text: 'Empty trash' });
    emptyBtn.addEventListener('click', function () { emptyTrash(area, id, refresh); });
    var closeBtn = el('button', { class: 'fm-btn secondary', text: 'Close' });
    actionsRow.appendChild(emptyBtn);
    actionsRow.appendChild(closeBtn);
    modal.appendChild(actionsRow);

    var close = showModal(modal);
    closeBtn.addEventListener('click', close);

    function refresh() {
      listEl.innerHTML = '';
      listEl.appendChild(el('div', { class: 'fm-empty', text: 'Loading\u2026' }));
      apiFor(area, id, '', 'trash_list', {}).then(function (res) {
        listEl.innerHTML = '';
        if (!res.ok) {
          listEl.appendChild(el('div', { class: 'fm-empty', text: res.body.error || 'Could not load trash.' }));
          emptyBtn.disabled = true;
          return;
        }
        var items = res.body.items || [];
        emptyBtn.disabled = !items.length;
        // Keep the toolbar recycle indicator in sync while browsing trash.
        if (state.area === area && state.id === id) {
          trashCount = items.length;
          renderSearchSortBar();
        }
        if (!items.length) {
          listEl.appendChild(el('div', { class: 'fm-empty', text: 'Trash is empty.' }));
          return;
        }
        items.forEach(function (it) {
          listEl.appendChild(buildTrashRow(area, id, it, function () {
            if (state.area === area && state.id === id) load(); // the folder it'll reappear in may be the one currently open
            refresh();
          }));
        });
      }).catch(function (err) {
        listEl.innerHTML = '';
        listEl.appendChild(el('div', { class: 'fm-empty', text: err.message || 'Could not load trash.' }));
      });
    }

    refresh();
  }

  function buildTrashRow(area, id, it, onChanged) {
    var row = el('div', { class: 'fm-trash-row' });
    row.appendChild(el('span', { class: 'fm-trash-icon', text: it.target === 'folder' ? '\uD83D\uDCC1' : '\uD83D\uDCC4' }));

    var info = el('div', { class: 'fm-trash-info' });
    info.appendChild(el('div', { class: 'fm-trash-name', text: it.name }));
    var whereText = 'Was in ' + AREA_LABELS[area] + (it.path ? ' / ' + it.path.split('/').join(' / ') : ' (root)');
    var whenText = new Date(it.deletedAt * 1000).toLocaleString();
    info.appendChild(el('div', { class: 'fm-trash-meta', text: whereText + ' \u00b7 Deleted ' + whenText }));
    row.appendChild(info);

    var restoreBtn = el('button', { class: 'fm-btn secondary', text: 'Restore' });
    restoreBtn.addEventListener('click', function () {
      restoreBtn.disabled = true;
      apiFor(area, id, '', 'restore', { trashId: it.trashId }).then(function (res) {
        if (!res.ok) { reportError(new Error(res.body.error || 'Restore failed')); restoreBtn.disabled = false; return; }
        onChanged();
      }).catch(function (err) { reportError(err); restoreBtn.disabled = false; });
    });
    row.appendChild(restoreBtn);

    var deleteBtn = el('button', { class: 'fm-btn danger', text: 'Delete forever' });
    deleteBtn.addEventListener('click', function () {
      if (!confirm('Permanently delete "' + it.name + '"? This cannot be undone.')) return;
      deleteBtn.disabled = true;
      apiFor(area, id, '', 'trash_delete', { trashId: it.trashId }).then(function (res) {
        if (!res.ok) { reportError(new Error(res.body.error || 'Delete failed')); deleteBtn.disabled = false; return; }
        onChanged();
      }).catch(function (err) { reportError(err); deleteBtn.disabled = false; });
    });
    row.appendChild(deleteBtn);

    return row;
  }

  function emptyTrash(area, id, onChanged) {
    apiFor(area, id, '', 'trash_list', {}).then(function (res) {
      if (!res.ok) { reportError(new Error(res.body.error || 'Could not load trash.')); return; }
      var items = res.body.items || [];
      if (!items.length) return;
      if (!confirm('Permanently delete all ' + items.length + ' item' + (items.length > 1 ? 's' : '') + ' in trash? This cannot be undone.')) return;
      Promise.all(items.map(function (it) {
        return apiFor(area, id, '', 'trash_delete', { trashId: it.trashId });
      })).then(function (results) {
        var failed = results.filter(function (r) { return !r.ok; });
        if (failed.length) reportError(new Error(failed.length + ' item(s) could not be deleted.'));
        onChanged();
      }).catch(reportError);
    }).catch(reportError);
  }

  function copyToClipboard(url, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function () {
        var iconSpan = btn.querySelector('.fm-icon-btn-icon');
        var labelSpan = btn.querySelector('.fm-icon-btn-label');
        var originalIcon = iconSpan ? iconSpan.textContent : null;
        var originalLabel = labelSpan ? labelSpan.textContent : null;
        if (iconSpan) iconSpan.textContent = '\u2713';
        if (labelSpan) labelSpan.textContent = 'Copied!';
        btn.title = 'Copied!';
        setTimeout(function () {
          if (iconSpan) iconSpan.textContent = originalIcon;
          if (labelSpan) labelSpan.textContent = originalLabel;
          btn.title = 'Copy link';
        }, 1500);
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
    var wrap = el('div', { class: 'fm-item-actions' });
    var menuItems = [];

    if (!opts.isFolder && opts.previewUrl && isPreviewable(opts.ext)) {
      menuItems.push({ icon: '\uD83D\uDC41', label: 'Preview', handler: function () { openPreview(opts); } });
    }
    if (!opts.isFolder && opts.previewUrl) {
      menuItems.push({ icon: '\u2B07', label: 'Download', handler: function () { onDownload(opts); } });
    }
    if (opts.isFolder) {
      menuItems.push({ icon: '\u2B07', label: 'Download zip', handler: function () { downloadZip([opts]); } });
    }
    if (opts.readOnly) {
      if (destinationAreasFor('migrate').length) {
        menuItems.push({ icon: '\u21ea', label: 'Migrate to\u2026', handler: function () { openDestinationPicker('migrate', [opts]); } });
      }
    } else {
      if (opts.isFolder && opts.onSelect) {
        menuItems.push({ icon: '\uD83D\uDD17', label: 'Get a link', handler: function () { opts.onSelect(container); } });
      }
      if (destinationAreasFor('move').length) {
        menuItems.push({ icon: '\uD83D\uDCE4', label: 'Move to\u2026', handler: function () { openDestinationPicker('move', [opts]); } });
      }
      if (destinationAreasFor('copy').length) {
        menuItems.push({ icon: '\u29C9', label: 'Copy to\u2026', handler: function () { openDestinationPicker('copy', [opts]); } });
      }
      if (pubAreaOK(PERM.edit)) {
        menuItems.push({ icon: '\u270e', label: 'Rename', handler: function () { onRename(opts); } });
      }
      if (pubAreaOK(PERM.copy)) {
        menuItems.push({ icon: '\u29C9', label: 'Duplicate', handler: function () { onDuplicate(opts); } });
      }
      if (pubAreaOK(PERM.delete)) {
        menuItems.push({ icon: '\u2715', label: 'Delete', handler: function () { onDelete(opts); }, danger: true });
      }
    }

    if (!menuItems.length) return wrap; // nothing this item can do - no kebab needed

    var menuBtn = el('button', { class: 'fm-item-menu-btn', text: '\u22EE', title: 'More actions', 'aria-label': 'More actions' });
    menuBtn.setAttribute('aria-haspopup', 'true');
    menuBtn.setAttribute('aria-expanded', 'false');
    var menu = el('div', { class: 'fm-item-menu' });
    menuItems.forEach(function (mi) {
      var itemBtn = el('button', { class: mi.danger ? 'danger' : '' }, [
        el('span', { class: 'fm-item-menu-icon', text: mi.icon }),
        el('span', { text: mi.label })
      ]);
      itemBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        closeItemMenu();
        mi.handler();
      });
      menu.appendChild(itemBtn);
    });
    wireMenuKeyboardNav(menu);

    function openMenu(place) {
      closeItemMenu();
      menu.classList.add('open');
      container.classList.add('menu-open');
      menuBtn.setAttribute('aria-expanded', 'true');
      openItemMenu = menu;
      openItemMenu.onCloseExtra = function () {
        container.classList.remove('menu-open');
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.focus();
      };
      place();
      var firstItem = focusableIn(menu)[0];
      if (firstItem) firstItem.focus();
    }

    menuBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (menu.classList.contains('open')) { closeItemMenu(); return; }
      openMenu(function () { positionItemMenu(menu, menuBtn); });
    });

    // Right-click anywhere on the item opens the same menu at the cursor,
    // instead of the browser's native context menu.
    container.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      e.stopPropagation(); // don't also trigger the background New folder/Upload menu
      openMenu(function () { positionItemMenuAtPoint(menu, e.clientX, e.clientY); });
    });

    wrap.appendChild(menuBtn);
    wrap.appendChild(menu);
    return wrap;
  }

  // Checkbox for bulk move/copy/delete/migrate. Shown even when readOnly -
  // Old files items can still be bulk-migrated.
  //
  // Shift+click on a checkbox ranges from the current selectionAnchorKey
  // (same as Shift+click on the row itself). Plain click toggles just this
  // item. We handle the logic on 'click' (where shiftKey is reliable) and
  // prevent the subsequent 'change' from double-toggling.
  function buildMultiCheckbox(opts) {
    var box = el('input', { type: 'checkbox', class: 'fm-multi-check' });
    box.checked = isMultiSelected(opts);
    box.addEventListener('click', function (e) {
      e.stopPropagation();
      if (!bulkActionsAvailable()) return;
      var key = multiKey(opts.name, opts.isFolder);
      if (e.shiftKey) {
        // Range-select from anchor (or this item if no anchor yet).
        e.preventDefault(); // stop the browser flipping the checkbox itself
        applyRangeSelection(selectionAnchorKey || key, key);
        // Keep the original anchor; only set it if there was none.
        if (!selectionAnchorKey) selectionAnchorKey = key;
        focusedKey = key;
        renderBody();
        renderFooter();
        focusItemByKey(key);
        return;
      }
      // Plain / Ctrl click: let the checkbox toggle natively, then sync state
      // on the following 'change' event. We still establish the anchor when
      // the item becomes selected (see toggleMultiSelect).
    });
    box.addEventListener('change', function (e) {
      // Shift path already handled everything and called preventDefault, so
      // the checkbox state was not flipped by the browser. For non-Shift we
      // sync multiSelected to match the new checked state.
      if (e.shiftKey) return;
      var key = multiKey(opts.name, opts.isFolder);
      if (box.checked) {
        multiSelected[key] = { name: opts.name, isFolder: opts.isFolder };
        if (!selectionAnchorKey) selectionAnchorKey = key;
      } else {
        delete multiSelected[key];
      }
      state.selected = null;
      focusedKey = key;
      renderBody();
      renderFooter();
    });
    return box;
  }

  // Makes `item` draggable for Chromium drag-out-to-download (always),
  // and also for internal move when not read-only and move is permitted.
  // Wires folders as drop targets only when internal move is allowed.
  //
  // Drag-out-to-download uses the DownloadURL DataTransfer type so the
  // browser issues a background GET (cookies included) when the drop
  // lands on the OS desktop / a folder. Single files go through the
  // existing previewUrl + dl=1 path; folders / multi-select go through
  // download_zip as a GET (it is not CSRF-gated and already accepts
  // $_REQUEST). Chromium-only (Chrome/Edge/Brave/Opera); Firefox and
  // Safari ignore DownloadURL and fall back to the Download buttons.
  function wireDragAndDrop(item, opts) {
    item.setAttribute('draggable', 'true');
    item.addEventListener('dragstart', function (e) {
      dragging = { name: opts.name, isFolder: !!opts.isFolder };
      item.classList.add('fm-dragging');

      var canMove = !opts.readOnly && moveAllowed(state.area);
      e.dataTransfer.effectAllowed = canMove ? 'copyMove' : 'copy';
      // Needed for some browsers (notably Firefox) to allow the drag at all.
      try { e.dataTransfer.setData('text/plain', opts.name); } catch (e2) { /* ignore */ }

      // Drag-out-to-download (Chromium only). Prefer the whole multi-
      // selection when the dragged item is part of one.
      try {
        var key = multiKey(opts.name, opts.isFolder);
        var items;
        if (multiSelected[key] && multiCount() > 0) {
          items = Object.keys(multiSelected).map(function (k) { return multiSelected[k]; });
        } else {
          items = [{ name: opts.name, isFolder: !!opts.isFolder, previewUrl: opts.previewUrl }];
        }

        var downloadUrl, filename, mime;
        if (items.length === 1 && !items[0].isFolder && items[0].previewUrl) {
          // Single file → existing authenticated preview URL with dl=1
          var base = items[0].previewUrl;
          downloadUrl = state.area === 'old'
            ? base
            : base + (base.indexOf('?') === -1 ? '?' : '&') + 'dl=1';
          downloadUrl = new URL(downloadUrl, location.href).href;
          filename = items[0].name;
          mime = 'application/octet-stream';
          e.dataTransfer.setData('DownloadURL', mime + ':' + filename + ':' + downloadUrl);
        } else if (items.length <= 200) {
          // Folder or multi-select → download_zip as GET.
          // Cap at 200 to keep the query string reasonable (server already
          // has 200 MB / 2000-file limits; this is just URL length).
          var params = new URLSearchParams({
            action: 'download_zip',
            area: state.area,
            id: state.id,
            path: state.path,
            pageid: PAGEID,
            items: JSON.stringify(items.map(function (it) {
              return { name: it.name, target: it.isFolder ? 'folder' : 'file' };
            }))
          });
          downloadUrl = new URL(API + '?' + params.toString(), location.href).href;
          filename = 'files-' + new Date().toISOString().slice(0, 19).replace(/[-:T]/g, '') + '.zip';
          mime = 'application/zip';
          e.dataTransfer.setData('DownloadURL', mime + ':' + filename + ':' + downloadUrl);
        }
        // else: too many items for a comfortable GET URL — user can use the Download zip button
      } catch (e3) { /* non-Chromium or URL construction failure */ }
    });
    item.addEventListener('dragend', function () {
      item.classList.remove('fm-dragging');
      dragging = null;
    });
    if (!opts.readOnly && moveAllowed(state.area) && opts.isFolder) {
      makeDropTarget(item, (state.path ? state.path + '/' : '') + opts.name);
    }
  }

  /**
   * Delegated keydown handler for the file list (attached once on the
   * filemanager root - see render()), rather than wired per item, since
   * items are torn down and rebuilt on every render. Covers arrow-key/
   * Home/End navigation (with Shift for range-select, Ctrl/Cmd to move
   * focus without changing selection), Space to toggle the focused
   * item's multi-selection, Enter to activate it (open folder / preview
   * file, matching double-click), Delete/Backspace to delete it (or the
   * whole multi-selection if one is active), and Ctrl/Cmd+A to select
   * everything visible.
   *
   * When focus is not yet on an item (e.g. still on a toolbar button),
   * the first Arrow/Home/End press jumps into the list - first item for
   * Down/Right/Home, last item for Up/Left/End - so the user does not
   * have to Tab through the toolbar to start keyboard navigation.
   */
  function handleItemKeydown(e) {
    if (!visibleItems.length) return;

    // Never steal keys from form fields, an open item menu, a modal, or
    // the area tablist (those handle Left/Right themselves).
    var tag = (e.target && e.target.tagName) ? e.target.tagName.toUpperCase() : '';
    if (tag === 'SELECT' || tag === 'INPUT' || tag === 'TEXTAREA') return;
    if (e.target.closest && e.target.closest('.fm-tab, .fm-tabs')) return;
    if (openItemMenu) return;
    if (root.querySelector('.fm-modal-overlay')) return;

    var navKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
    var isNavKey = navKeys.indexOf(e.key) !== -1;
    var isSelectAllKey = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a';

    var itemEl = e.target && e.target.closest ? e.target.closest('.fm-item') : null;

    // Bootstrap into the list when nothing is focused yet.
    if (!itemEl && isNavKey) {
      e.preventDefault();
      var bootstrapKey;
      if (e.key === 'End' || e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
        var last = visibleItems[visibleItems.length - 1];
        bootstrapKey = multiKey(last.name, last.isFolder);
      } else {
        // Down / Right / Home → first item, or the last focused item if
        // it is still in the visible set (rovingKey already encodes that).
        bootstrapKey = rovingKey();
      }
      if (!bootstrapKey) return;
      var bootstrapItem = visibleItems[findVisibleIndex(bootstrapKey)];
      if (!bootstrapItem) return;

      if (e.shiftKey && bulkActionsAvailable()) {
        selectionAnchorKey = selectionAnchorKey || bootstrapKey;
        applyRangeSelection(selectionAnchorKey, bootstrapKey);
      } else if (!(e.ctrlKey || e.metaKey)) {
        clearMultiSelect();
        selectionAnchorKey = bootstrapKey;
        state.selected = bootstrapItem.isFolder
          ? null
          : { name: bootstrapItem.name, isFolder: false, ext: bootstrapItem.ext, previewUrl: bootstrapItem.previewUrl };
      }
      focusedKey = bootstrapKey;
      renderBody();
      renderFooter();
      focusItemByKey(bootstrapKey);
      return;
    }

    if (!itemEl || !itemEl.dataset || !itemEl.dataset.key) {
      // Ctrl/Cmd+A with focus outside the list still selects all when
      // bulk actions are available (and we already bailed on form fields).
      if (isSelectAllKey && bulkActionsAvailable()) {
        e.preventDefault();
        var allOutside = {};
        visibleItems.forEach(function (it) {
          allOutside[multiKey(it.name, it.isFolder)] = { name: it.name, isFolder: it.isFolder };
        });
        multiSelected = allOutside;
        state.selected = null;
        focusedKey = rovingKey();
        renderBody();
        renderFooter();
        focusItemByKey(focusedKey);
      }
      return;
    }

    // Enter/Space/Delete only act on the item when the item itself has
    // focus (the roving-tabindex target) - if focus is on a nested
    // control instead (the multi-select checkbox, the kebab menu
    // button), that control's own native Enter/Space behavior should
    // apply, not this. Arrow keys/Home/End/Ctrl+A don't conflict with
    // any nested control's native behavior, so those still work
    // regardless of which element inside the item has focus.
    if (!isNavKey && !isSelectAllKey && e.target !== itemEl) return;

    if (isSelectAllKey) {
      if (!bulkActionsAvailable()) return;
      e.preventDefault();
      var all = {};
      visibleItems.forEach(function (it) { all[multiKey(it.name, it.isFolder)] = { name: it.name, isFolder: it.isFolder }; });
      multiSelected = all;
      state.selected = null;
      renderBody();
      renderFooter();
      focusItemByKey(itemEl.dataset.key);
      return;
    }

    var currentIndex = findVisibleIndex(itemEl.dataset.key);
    if (currentIndex === -1) currentIndex = 0;
    var f = visibleItems[currentIndex];

    if (isNavKey) {
      e.preventDefault();
      var columns = gridColumnCount();
      var newIndex = computeNavIndex(currentIndex, visibleItems.length, columns, e.key);
      var target = visibleItems[newIndex];
      var targetKey = multiKey(target.name, target.isFolder);

      if (e.shiftKey && bulkActionsAvailable()) {
        if (!selectionAnchorKey) selectionAnchorKey = itemEl.dataset.key;
        applyRangeSelection(selectionAnchorKey, targetKey);
      } else if (e.ctrlKey || e.metaKey) {
        // Move focus only - leave the current selection exactly as is,
        // matching the standard "move without selecting" listbox convention.
      } else {
        clearMultiSelect();
        selectionAnchorKey = targetKey;
        state.selected = target.isFolder ? null : { name: target.name, isFolder: false, ext: target.ext, previewUrl: target.previewUrl };
      }
      focusedKey = targetKey;
      renderBody();
      renderFooter();
      focusItemByKey(targetKey);
      return;
    }

    if (e.key === ' ') {
      if (!bulkActionsAvailable()) return;
      e.preventDefault();
      toggleMultiSelect(f);
      focusedKey = itemEl.dataset.key;
      focusItemByKey(focusedKey);
      return;
    }

    if (e.key === 'Enter') {
      e.preventDefault();
      if (f.isFolder) {
        state.path = (state.path ? state.path + '/' : '') + f.name;
        state.selected = null;
        state.query = '';
        clearMultiSelect();
        renderToolbar();
        load();
      } else if (f.previewUrl && isPreviewable(f.ext)) {
        openPreview(f);
      } else {
        selectItem({ name: f.name, isFolder: false, ext: f.ext, previewUrl: f.previewUrl }, itemEl);
      }
      return;
    }

    if (e.key === 'Delete' || e.key === 'Backspace') {
      e.preventDefault();
      if (multiCount() > 0) {
        onBulkDelete(Object.keys(multiSelected).map(function (k) { return multiSelected[k]; }));
      } else {
        onDelete(f);
      }
      return;
    }
  }

  /**
   * Wires the selection/navigation behavior shared by grid tiles and list
   * rows: listbox option semantics (role, aria-selected, roving
   * tabindex), plain click (select/open, same as before), Ctrl/Cmd+click
   * (toggle just this item in the multi-selection), and Shift+click
   * (range-select from the last plain-selection anchor). Arrow keys,
   * Enter, Delete, Space, and Ctrl+A are handled once via delegation on
   * the filemanager root (see handleItemKeydown) rather than wired per item.
   */
  function wireItemInteractions(item, opts) {
    var key = multiKey(opts.name, opts.isFolder);
    item.dataset.key = key;
    item.setAttribute('role', 'option');
    var isSelected = isMultiSelected(opts)
      || (!!state.selected && state.selected.name === opts.name && !!state.selected.isFolder === !!opts.isFolder);
    item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    item.tabIndex = (rovingKey() === key) ? 0 : -1;

    item.addEventListener('click', function (e) {
      if (bulkActionsAvailable() && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        toggleMultiSelect(opts);
        focusedKey = key;
        focusItemByKey(key);
        return;
      }
      if (bulkActionsAvailable() && e.shiftKey) {
        e.preventDefault();
        applyRangeSelection(selectionAnchorKey || key, key);
        focusedKey = key;
        renderBody();
        renderFooter();
        focusItemByKey(key);
        return;
      }
      selectionAnchorKey = key;
      focusedKey = key;
      if (opts.isFolder) opts.onOpen();
      else opts.onOpen(item);
      item.focus(); // harmless no-op if opts.onOpen() already navigated away and replaced this node
    });
    item.addEventListener('dblclick', function () {
      if (opts.isFolder) opts.onOpen();
      else if (opts.previewUrl && isPreviewable(opts.ext)) openPreview(opts);
    });
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
    wireItemInteractions(item, opts);
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

    wireItemInteractions(row, opts);
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
    var area = state.area, id = state.id, path = state.path;
    api('rename', { old: opts.name, new: newName, target: opts.isFolder ? 'folder' : 'file' }).then(function (res) {
      if (!res.ok) { reportError(new Error(res.body.error || 'Rename failed')); return; }
      var finalName = res.body.name || newName; // may differ from newName if the extension was corrected
      // It's still the same item, but under a new name - other fields the
      // footer relies on (previewUrl in particular) are keyed to the old
      // name and would 404, so clear the selection rather than leave it
      // pointing at stale data.
      if (state.selected && state.selected.name === opts.name) state.selected = null;
      load();
      showUndo('Renamed "' + opts.name + '" to "' + finalName + '"', function () {
        return apiFor(area, id, path, 'rename', { old: finalName, new: opts.name, target: opts.isFolder ? 'folder' : 'file' });
      });
    }).catch(reportError);
  }

  /**
   * Forces a save-as instead of the browser just navigating to/previewing
   * the file. For Old files, previewUrl is already the direct legacy URL
   * (no filegate involved) - the `download` attribute alone is enough
   * there since it's same-origin. Everywhere else it's an admin-preview
   * filegate.php URL, which honors `dl=1` to send
   * Content-Disposition: attachment (see filegate.php) - belt and braces
   * with the `download` attribute for older browsers.
   */
  function onDownload(opts) {
    if (opts.isFolder || !opts.previewUrl) return;
    var url = state.area === 'old'
      ? opts.previewUrl
      : opts.previewUrl + (opts.previewUrl.indexOf('?') === -1 ? '?' : '&') + 'dl=1';
    var a = el('a', { href: url, download: opts.name });
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  /**
   * Bulk download for a multi-select - unlike single-file download
   * (which just links straight to filegate.php), a zip has to be built
   * fresh server-side, so this can't be a plain <a> click. Goes through
   * fetch()+blob() instead of api()/apiFor(), which assume a JSON body -
   * the response here is the zip's raw bytes, or JSON only on error.
   */
  function downloadZip(items, btn) {
    if (!items.length) return;
    var iconSpan = btn ? btn.querySelector('.fm-icon-btn-icon') : null;
    var labelSpan = btn ? btn.querySelector('.fm-icon-btn-label') : null;
    var originalIcon = iconSpan ? iconSpan.textContent : null;
    var originalLabel = labelSpan ? labelSpan.textContent : null;
    var originalTitle = btn ? btn.title : null;
    if (btn) {
      btn.disabled = true;
      btn.title = 'Zipping\u2026';
      if (iconSpan) iconSpan.textContent = '\u23F3'; // hourglass
      if (labelSpan) labelSpan.textContent = 'Zipping\u2026';
    }

    var body = new URLSearchParams({
      action: 'download_zip', area: state.area, id: state.id, path: state.path,
      pageid: PAGEID, csrf: CSRF,
      items: JSON.stringify(items.map(function (it) { return { name: it.name, target: it.isFolder ? 'folder' : 'file' }; }))
    });

    fetch(API, { method: 'POST', body: body }).then(function (r) {
      var contentType = r.headers.get('Content-Type') || '';
      if (contentType.indexOf('application/json') !== -1) {
        return r.json().then(function (body) { throw new Error((body && body.error) || 'Could not create zip'); });
      }
      if (!r.ok) throw new Error('Could not create zip');
      var cd = r.headers.get('Content-Disposition') || '';
      var match = /filename="?([^";]+)"?/.exec(cd);
      var filename = match ? match[1] : 'files.zip';
      return r.blob().then(function (blob) {
        var url = URL.createObjectURL(blob);
        var a = el('a', { href: url, download: filename });
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
      });
    }).catch(reportError).then(function () {
      if (btn) {
        btn.disabled = false;
        btn.title = originalTitle;
        if (iconSpan) iconSpan.textContent = originalIcon;
        if (labelSpan) labelSpan.textContent = originalLabel;
      }
    });
  }

  function onDuplicate(opts) {
    if (!pubAreaOK(PERM.copy)) return;
    api('duplicate', { name: opts.name, target: opts.isFolder ? 'folder' : 'file' }).then(function (res) {
      if (!res.ok) { reportError(new Error(res.body.error || 'Duplicate failed')); return; }
      load();
    }).catch(reportError);
  }

  function onDelete(opts) {
    if (!pubAreaOK(PERM.delete)) return;
    if (!confirm('Delete "' + opts.name + '"?' + (opts.isFolder ? ' This deletes everything inside it.' : ''))) return;
    var area = state.area, id = state.id, path = state.path;
    api('delete', { name: opts.name, target: opts.isFolder ? 'folder' : 'file' }).then(function (res) {
      if (!res.ok) { reportError(new Error(res.body.error || 'Delete failed')); return; }
      // The item's gone - if it was the one showing in the footer (Copy
      // link/Download/Move to...), that panel would otherwise keep
      // pointing at a file that no longer exists.
      if (state.selected && state.selected.name === opts.name) state.selected = null;
      load();
      if (res.body.trashId) {
        showUndo('Deleted "' + opts.name + '"', function () {
          return apiFor(area, id, path, 'restore', { trashId: res.body.trashId });
        });
      }
    }).catch(reportError);
  }

  function doUpload(fileList) {
    if (state.area === 'old' || !pubAreaOK(PERM.upload)) return;
    if (uploadState || uploadPending) return; // one batch at a time - toolbar button/dropzone are disabled while either is true

    var files = Array.prototype.slice.call(fileList);
    if (!files.length) return;

    uploadPending = true;
    renderToolbar();

    // Precheck against the current folder before asking anything - only
    // shows the Replace/Keep-both choice if a name in this batch would
    // actually collide with something already here.
    var items = files.map(function (f) { return { name: f.name, isFolder: false }; });
    checkConflicts(items).then(function (conflicts) {
      var choicePromise = conflicts.length ? askConflictChoice(conflicts) : Promise.resolve('rename');
      return choicePromise.then(function (onConflict) {
        uploadPending = false;
        if (!onConflict) { renderToolbar(); return; } // cancelled - upload nothing
        startUpload(files, onConflict);
      });
    }).catch(function (err) {
      uploadPending = false;
      renderToolbar();
      reportError(err);
    });
  }

  function startUpload(files, onConflict) {
    var fd = new FormData();
    fd.append('action', 'upload');
    fd.append('area', state.area);
    fd.append('id', state.id);
    fd.append('path', state.path);
    fd.append('pageid', PAGEID);
    fd.append('csrf', CSRF);
    fd.append('onConflict', onConflict);
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

  var UNDO_TIMEOUT_MS = 8000;

  /**
   * Arms the undo toast for one reversible action. `undoFn` is called only
   * if the person clicks Undo, and must return a promise resolving to the
   * usual `{ok, body}` shape (see api()/apiFor()). Replaces any
   * currently-showing toast - only one undo is offered at a time, matching
   * the single-toast UX (see undoState's docblock above).
   */
  function showUndo(message, undoFn) {
    if (undoState && undoState.timeoutId) clearTimeout(undoState.timeoutId);
    undoState = { message: message, action: undoFn };
    undoState.timeoutId = setTimeout(dismissUndo, UNDO_TIMEOUT_MS);
    renderUndoToast();
  }

  function dismissUndo() {
    if (undoState && undoState.timeoutId) clearTimeout(undoState.timeoutId);
    undoState = null;
    renderUndoToast();
  }

  function performUndo() {
    if (!undoState) return;
    var action = undoState.action;
    if (undoState.timeoutId) clearTimeout(undoState.timeoutId);
    var btn = document.querySelector('#fm-undo-toast .fm-undo-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Undoing\u2026'; }
    action().then(function (res) {
      undoState = null;
      renderUndoToast();
      if (res && res.ok === false) reportError(new Error((res.body && res.body.error) || 'Undo failed'));
      load();
    }).catch(function (err) {
      undoState = null;
      renderUndoToast();
      reportError(err);
    });
  }

  function renderUndoToast() {
    var wrap = document.getElementById('fm-undo-toast');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!undoState) { wrap.classList.remove('active'); return; }
    wrap.classList.add('active');
    wrap.appendChild(el('span', { class: 'fm-undo-msg', text: undoState.message }));
    var undoBtn = el('button', { class: 'fm-undo-btn', text: 'Undo' });
    undoBtn.addEventListener('click', performUndo);
    wrap.appendChild(undoBtn);
    var dismissBtn = el('button', { class: 'fm-undo-dismiss', text: '\u2715', title: 'Dismiss', 'aria-label': 'Dismiss' });
    dismissBtn.addEventListener('click', dismissUndo);
    wrap.appendChild(dismissBtn);
  }

  render();
})();