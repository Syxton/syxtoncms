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
      var qs = 'pageid=' + encodeURIComponent(pageid) +
        '&userid=' + encodeURIComponent(userid) +
        '&type=' + encodeURIComponent(type || '');
      return base + (base.indexOf('?') === -1 ? '?' : '&') + qs;
    }

    function urlOf() {
      // Resolve the plugin's own folder so index.php can be found without
      // hardcoding filemanager_url in every init() call.
      var resource = editor.editorManager.baseURL + '/plugins/filemanager/index.php';
      return resource;
    }

    function openPicker(type, onPick) {
      var win = editor.windowManager.openUrl({
        title: 'File Manager',
        url: dialogUrl(type),
        width: 900,
        height: 560,
        buttons: [],
        onMessage: function (api, message) {
          if (!message || !message.mceAction) return;
          if (message.mceAction === 'insert' && message.file) {
            onPick(message.file);
            api.close();
          } else if (message.mceAction === 'cancel') {
            api.close();
          }
        },
      });
      return win;
    }

    function insertAsContent(file) {
      if (file.isFolder) {
        var cls = file.mode === 'gallery' ? ' class="gallery"' : '';
        editor.insertContent('<a href="' + file.url + '"' + cls + '>' + escapeAttr(file.name) + '</a>');
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
        callback(file.url, { alt: file.name });
      });
    });

    return { getMetadata: function () { return { name: 'File Manager' }; } };
  });
})();
