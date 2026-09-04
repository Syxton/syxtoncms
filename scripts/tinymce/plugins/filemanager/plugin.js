/***************************************************************************
 * plugins/filemanager/plugin.js
 * -------------------------------------------------------------------------
 * Replaces the old responsivefilemanager plugin. Two ways to open it:
 *
 * 1. Toolbar/menu button ("filemanager") - opens the browser standalone,
 *    inserts an <img>, a link, or raw content depending on file type.
 *
 * 2. As `file_picker_callback` - TinyMCE's native Image/Link/Media dialogs
 *    call this when the user clicks "Browse", already scoped to the right
 *    file type (image/media/file), and expect just the URL back.
 *
 * The picker renders as its own overlay appended directly to the host
 * page's <body> - not inside TinyMCE's dialog system at all. That matters
 * most for case 2: the native Image/Link/Media dialog is already a
 * TinyMCE modal, so stacking a second TinyMCE dialog on top of it (the
 * old behavior) was a modal-inside-a-modal. A real window.open() popup
 * would dodge that too, but is unreliable here specifically because it's
 * launched from a click already one layer deep inside another dialog -
 * several browsers no longer treat that as a "direct" user gesture and
 * silently block it. Our own overlay is just DOM, appended to the same
 * top-level document as the editor, so it's not subject to popup
 * blocking at all, and it paints above TinyMCE's own dialog (higher
 * z-index) without being a child of it.
 *
 * Required editor init options:
 *   filemanager_pageid : string  - current page id (public/page area)
 *   filemanager_userid : string  - current user id (private area)
 *   filemanager_url     : string - path to plugins/filemanager/index.php
 *                                  (defaults to the plugin's own base URL)
 *
 * Example:
 *   tinymce.init({
 *     selector: '#content',
 *     plugins: 'filemanager image link media',
 *     toolbar: 'filemanager image link media',
 *     filemanager_pageid: '42',
 *     filemanager_userid: '7',
 *     file_picker_types: 'image media file',
 *   });
 ***************************************************************************/
(function () {
  'use strict';

  tinymce.PluginManager.add('filemanager', function (editor) {

    function dialogUrl(type) {
      var base = editor.getParam('filemanager_url', urlOf());
      var pageid = editor.getParam('filemanager_pageid', '');
      var userid = editor.getParam('filemanager_userid', '');
      // Set only by the HTML feature's editor - gates the Gallery/Index
      // folder-link option (only that feature's photogallery filter uses it).
      var allowGallery = !!editor.getParam('filemanager_allow_gallery', false);
      // embed=1 marks this as opened by our own picker - see index.php/app.js.
      var qs = 'pageid=' + encodeURIComponent(pageid) +
        '&userid=' + encodeURIComponent(userid) +
        '&type=' + encodeURIComponent(type || '') +
        '&embed=1' +
        (allowGallery ? '&gallery=1' : '');
      return base + (base.indexOf('?') === -1 ? '?' : '&') + qs;
    }

    function urlOf() {
      // Resolve the plugin's own folder so index.php can be found without
      // hardcoding filemanager_url in every init() call.
      var resource = editor.editorManager.baseURL + '/plugins/filemanager/index.php';
      return resource;
    }

    /**
     * Opens the picker as an overlay directly on the host page (i.e. the
     * top-level document the editor itself lives in) - a backdrop plus a
     * centered box holding an <iframe src="index.php">. Not a child of
     * any TinyMCE dialog, so it's never "a modal inside a modal", and not
     * a window.open() popup either, so there's no popup blocker to fight
     * with - appending elements to the DOM always works.
     */
    function openPicker(type, onPick) {
      var doc = document;
      var prevOverflow = doc.body.style.overflow;

      var backdrop = doc.createElement('div');
      backdrop.setAttribute('data-fm-picker-overlay', '1');
      // Size the dialog to the viewport. On phones the old fixed 1000×680
      // with 20px padding left a tiny usable area; go nearly full-screen
      // under 700px width.
      var narrow = (window.innerWidth || doc.documentElement.clientWidth || 0) < 700;
      applyStyle(backdrop, {
        position: 'fixed', top: '0', right: '0', bottom: '0', left: '0',
        background: 'rgba(0, 0, 0, .5)', zIndex: '2147483000',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        padding: narrow ? '0' : '20px',
      });

      var box = doc.createElement('div');
      applyStyle(box, {
        position: 'relative',
        width: narrow ? '100%' : '1000px',
        maxWidth: '100%',
        height: narrow ? '100%' : '680px',
        maxHeight: '100%',
        background: '#fff',
        borderRadius: narrow ? '0' : '6px',
        overflow: 'hidden',
        boxShadow: '0 10px 40px rgba(0,0,0,.35)',
      });

      var closeBtn = doc.createElement('button');
      closeBtn.type = 'button';
      closeBtn.setAttribute('aria-label', 'Close');
      closeBtn.textContent = '\u2715';
      applyStyle(closeBtn, {
        position: 'absolute', top: '6px', right: '8px', zIndex: '1',
        border: 'none', background: 'transparent', fontSize: '16px',
        lineHeight: '1', cursor: 'pointer', color: '#50575e', padding: '6px',
      });
      closeBtn.addEventListener('click', function () { cleanup(); });

      var iframe = doc.createElement('iframe');
      iframe.src = dialogUrl(type);
      iframe.title = 'File Manager';
      applyStyle(iframe, { display: 'block', width: '100%', height: '100%', border: '0' });

      box.appendChild(iframe);
      box.appendChild(closeBtn);
      backdrop.appendChild(box);
      doc.body.appendChild(backdrop);
      doc.body.style.overflow = 'hidden';

      function onMessage(e) {
        if (e.source !== iframe.contentWindow) return;
        var message = e.data;
        if (!message || !message.mceAction) return;
        if (message.mceAction === 'insert' && message.file) {
          onPick(message.file);
          cleanup();
        } else if (message.mceAction === 'cancel') {
          cleanup();
        }
      }
      function onKeydown(e) {
        if (e.key === 'Escape') cleanup();
      }
      // Clicking the backdrop itself (not the box) cancels, like a normal modal.
      function onBackdropClick(e) {
        if (e.target === backdrop) cleanup();
      }
      function cleanup() {
        window.removeEventListener('message', onMessage);
        doc.removeEventListener('keydown', onKeydown, true);
        backdrop.removeEventListener('mousedown', onBackdropClick);
        if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
        doc.body.style.overflow = prevOverflow;
      }

      window.addEventListener('message', onMessage);
      doc.addEventListener('keydown', onKeydown, true);
      backdrop.addEventListener('mousedown', onBackdropClick);

      return { close: cleanup };
    }

    function applyStyle(node, styles) {
      Object.keys(styles).forEach(function (k) { node.style[k] = styles[k]; });
    }

    function insertAsContent(file) {
      if (file.isFolder) {
        // htmllib.php's photogallery filter turns a folder link into a
        // gallery when it finds title="gallery" - add it automatically.
        var galleryAttr = file.mode === 'gallery' ? ' title="gallery"' : '';
        editor.insertContent('<a href="' + file.url + '"' + galleryAttr + '>' + escapeAttr(file.name) + '</a>');
        return;
      }
      var ext = file.ext;
      var imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
      var mediaExt = ['mp4', 'webm', 'mp3'];
      if (imageExt.indexOf(ext) !== -1) {
        editor.insertContent('<img src="' + file.url + '" alt="' + escapeAttr(file.name) + '">');
      } else if (mediaExt.indexOf(ext) !== -1) {
        editor.insertContent('<a href="' + file.url + '">' + escapeAttr(file.name) + '</a>');
      } else {
        editor.insertContent('<a href="' + file.url + '" download>' + escapeAttr(file.name) + '</a>');
      }
    }

    function escapeAttr(s) {
      return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    editor.ui.registry.addButton('filemanager', {
      icon: 'browse',
      tooltip: 'File Manager',
      onAction: function () {
        openPicker('', insertAsContent);
      },
    });

    editor.ui.registry.addMenuItem('filemanager', {
      icon: 'browse',
      text: 'File Manager',
      onAction: function () {
        openPicker('', insertAsContent);
      },
    });

    // Wire into TinyMCE's native image/media/link "Source" browse button.
    editor.options.set('file_picker_callback', function (callback, value, meta) {
      openPicker(meta.filetype, function (file) {
        var cbMeta = { alt: file.name };
        if (file.isFolder && file.mode === 'gallery') {
          // insertAsContent() never runs for this path - the Link
          // dialog's Title field auto-fills from url.meta.title, so this
          // is the only way to get title="gallery" onto the link.
          cbMeta.title = 'gallery';
        }
        callback(file.url, cbMeta);
      });
    });

    return { getMetadata: function () { return { name: 'File Manager' }; } };
  });
})();
