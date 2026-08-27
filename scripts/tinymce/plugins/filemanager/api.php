<?php

/***************************************************************************
* plugins/filemanager/api.php - admin-side operations for the file manager
* dialog. Always requires a logged-in session with edit permission on the
* current area. All state-changing actions also require the CSRF token
* that index.php embeds from the session.
*
* Actions (POST 'action'): list, mkdir, upload, rename, delete, move, copy, geturl, restore, trash_list, trash_delete, duplicate, check_conflicts, download_zip
* Common params: area=pub|priv, id=<pageid|userid>, path=<relative folder>,
* pageid=<the page being edited, for ability scoping - see fm_is_able()>
*
* Permissions: My files (area=priv) is open to any logged-in owner for
* every action, ownership only. Page files (area=pub) also requires
* filemanager_view to browse, plus filemanager_delete/upload/move/copy/
* createfolder/edit per action (source OR destination - see 'move'/'copy').
* filemanager_migrate always gates migrating out of Old files. See
* fmconfig.php's fm_is_able().
*
* Output buffering: your app runs with $CFG->debug = 3 ("log and print"),
* which means any stray notice/warning from included libs would otherwise
* get echoed into the middle of this response and corrupt the JSON (this
* was the cause of "upload succeeds but the screen doesn't refresh" - the
* file really did save, but the browser's res.json() silently threw on the
* malformed body). ob_start() here captures any such output so it can't
* leak into the response; genuine PHP errors still go to your error log
* per $CFG->debug, they just won't corrupt the JSON the browser sees.
***************************************************************************/
ob_start();

if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    require_once($sub . 'lib/header.php');
}
if (!defined('FMCONFIG')) {
    $sub = '';
    while (!file_exists($sub . 'fmconfig.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    require_once($sub . 'fmconfig.php');
}

function fm_json($data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    ob_end_clean(); // discard anything a library printed before we got here
    echo json_encode($data);
    exit;
}

if (!is_logged_in()) {
    fm_json(['error' => 'Not authenticated'], 403);
}

$action = $_REQUEST['action'] ?? '';
$area   = $_REQUEST['area'] ?? '';
$id     = (string) ($_REQUEST['id'] ?? '');
$path   = (string) ($_REQUEST['path'] ?? '');
// Scopes every filemanager_* check below (see fm_is_able()).
$pageid = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_REQUEST['pageid'] ?? ''));

if (!in_array($area, [FM_AREA_PUBLIC, FM_AREA_PRIVATE, FM_AREA_OLD], true) || $id === '') {
    fm_json(['error' => 'Bad request'], 400);
}

// Permission choke point.
if (!fm_can_access_area($area, $id)) {
    fm_json(['error' => 'Forbidden'], 403);
}

// Page files/Old files also require filemanager_view (see index.php) -
// My files has no such gate (see fm_is_able() in fmconfig.php).
if (($area === FM_AREA_PUBLIC || $area === FM_AREA_OLD) && !fm_is_able('filemanager_view', $pageid)) {
    fm_json(['error' => 'Forbidden'], 403);
}

// "Old files" is read-only browsing for manual migration - only list and
// move (as a source) are allowed. Enforced here, not just hidden in the
// UI, since the endpoint itself is the actual security boundary.
if ($area === FM_AREA_OLD && !in_array($action, ['list', 'move', 'download_zip'], true)) {
    fm_json(['error' => 'Old files is read-only - move items into My files or Page files first'], 403);
}

// CSRF check for anything that changes state.
$stateChanging = in_array($action, ['mkdir', 'upload', 'rename', 'delete', 'move', 'copy', 'restore', 'trash_delete', 'duplicate'], true);
if ($stateChanging) {
    $csrf = $_REQUEST['csrf'] ?? '';
    if (!isset($_SESSION['fm_csrf']) || !hash_equals($_SESSION['fm_csrf'], (string) $csrf)) {
        fm_json(['error' => 'Bad CSRF token'], 403);
    }
}

$relPath = fm_sanitize_relpath($path);
if ($relPath === null) {
    fm_json(['error' => 'Invalid path'], 400);
}

if ($area === FM_AREA_OLD && $relPath === '' && fm_old_root($id) === null) {
    // No legacy files for this user yet - that's normal, not an error.
    if ($action === 'list') {
        fm_json(['path' => '', 'folders' => [], 'files' => []]);
    }
    fm_json(['error' => 'Folder not found'], 404);
}

$dir = fm_resolve_area_path($area, $id, $relPath);
if ($dir === null || !is_dir($dir)) {
    fm_json(['error' => 'Folder not found'], 404);
}

// filegate.php lives at the app root, so build its URL off $CFG->wwwroot -
// stays correct whether the app is at the domain root or a subdirectory.
$gateUrl = $CFG->wwwroot . '/filegate.php';

switch ($action) {

    case 'list': {
        $folders = [];
        $files = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                $folders[] = ['name' => $entry, 'mtime' => filemtime($full)];
            } else {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (!array_key_exists($ext, $GLOBALS['FM_ALLOWED_EXT'])) {
                    continue; // hide anything we wouldn't serve anyway
                }
                $mtime = filemtime($full);
                $entryRel = ($relPath === '' ? '' : $relPath . '/') . $entry;
                $files[] = [
                    'name'  => $entry,
                    'size'  => filesize($full),
                    'mtime' => $mtime,
                    'ext'   => $ext,
                    // Old area: already directly linkable at its existing
                    // (pre-migration) URL, no filegate involved. Otherwise:
                    // admin-only preview URL, valid only within this
                    // editing session - never insert this into content.
                    'previewUrl' => $area === FM_AREA_OLD
                        ? fm_old_direct_url($id, $entryRel)
                        : fm_admin_preview_url($gateUrl, $area, $id, $entryRel, $mtime),
                ];
            }
        }
        // Folders: alphabetic. Files: newest modified first.
        usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        fm_json(['path' => $relPath, 'folders' => $folders, 'files' => $files]);
        break;
    }

    case 'mkdir': {
        if ($area === FM_AREA_PUBLIC && !fm_is_able('filemanager_createfolder', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }
        $name = fm_sanitize_name((string) ($_REQUEST['name'] ?? ''));
        if ($name === null) {
            fm_json(['error' => 'Invalid folder name'], 400);
        }
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target)) {
            fm_json(['error' => 'Already exists'], 409);
        }
        if (!mkdir($target, 0750)) {
            fm_json(['error' => 'Could not create folder'], 500);
        }
        fm_json(['ok' => true]);
        break;
    }

    case 'upload': {
        if ($area === FM_AREA_PUBLIC && !fm_is_able('filemanager_upload', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }
        if (empty($_FILES['file']) || !is_array($_FILES['file']['name'])) {
            fm_json(['error' => 'No files received'], 400);
        }
        $maxBytes = 20 * 1024 * 1024; // 20MB, tune as needed
        $onConflict = ((string) ($_REQUEST['onConflict'] ?? 'rename')) === 'replace' ? 'replace' : 'rename';
        $results = [];
        $count = count($_FILES['file']['name']);
        for ($i = 0; $i < $count; $i++) {
            $origName = $_FILES['file']['name'][$i];
            $tmpPath  = $_FILES['file']['tmp_name'][$i];
            $error    = $_FILES['file']['error'][$i];
            $size     = $_FILES['file']['size'][$i];

            if ($error !== UPLOAD_ERR_OK) {
                $results[] = ['name' => $origName, 'ok' => false, 'error' => 'Upload error'];
                continue;
            }
            if ($size > $maxBytes) {
                $results[] = ['name' => $origName, 'ok' => false, 'error' => 'Too large'];
                continue;
            }
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!array_key_exists($ext, $GLOBALS['FM_ALLOWED_EXT'])) {
                $results[] = ['name' => $origName, 'ok' => false, 'error' => 'File type not allowed'];
                continue;
            }
            $baseName = fm_sanitize_name(pathinfo($origName, PATHINFO_FILENAME));
            if ($baseName === null) {
                $baseName = 'file';
            }
            $finalName = $baseName . '.' . $ext;
            if ($onConflict === 'replace' && file_exists($dir . DIRECTORY_SEPARATOR . $finalName)) {
                $existing = $dir . DIRECTORY_SEPARATOR . $finalName;
                fm_clear_for_replace($area, $id, $relPath, $existing, $finalName, is_dir($existing) ? 'folder' : 'file');
            } else {
                // Avoid overwriting: file, file(1), file(2), ...
                $n = 1;
                while (file_exists($dir . DIRECTORY_SEPARATOR . $finalName)) {
                    $finalName = $baseName . '(' . $n . ').' . $ext;
                    $n++;
                }
            }
            $dest = $dir . DIRECTORY_SEPARATOR . $finalName;
            if (!move_uploaded_file($tmpPath, $dest)) {
                $results[] = ['name' => $origName, 'ok' => false, 'error' => 'Could not save'];
                continue;
            }
            chmod($dest, 0640);
            $results[] = ['name' => $finalName, 'ok' => true];
        }
        fm_json(['results' => $results]);
        break;
    }

    case 'rename': {
        if ($area === FM_AREA_PUBLIC && !fm_is_able('filemanager_edit', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }
        $old    = fm_sanitize_name((string) ($_REQUEST['old'] ?? ''));
        $new    = fm_sanitize_name((string) ($_REQUEST['new'] ?? ''));
        $target = (string) ($_REQUEST['target'] ?? ''); // 'file' | 'folder'
        if ($old === null || $new === null || !in_array($target, ['file', 'folder'], true)) {
            fm_json(['error' => 'Invalid request'], 400);
        }
        if ($target === 'file') {
            $oldExt = strtolower(pathinfo($old, PATHINFO_EXTENSION));
            $newExt = strtolower(pathinfo($new, PATHINFO_EXTENSION));
            if ($newExt !== $oldExt) {
                // Don't allow renaming to change/spoof the extension.
                $new = pathinfo($new, PATHINFO_FILENAME) . '.' . $oldExt;
            }
        }
        $oldPath = $dir . DIRECTORY_SEPARATOR . $old;
        $newPath = $dir . DIRECTORY_SEPARATOR . $new;
        if (!file_exists($oldPath)) {
            fm_json(['error' => 'Not found'], 404);
        }
        if (file_exists($newPath)) {
            fm_json(['error' => 'A file/folder with that name already exists'], 409);
        }
        if (!rename($oldPath, $newPath)) {
            fm_json(['error' => 'Rename failed'], 500);
        }
        fm_json(['ok' => true, 'name' => $new]);
        break;
    }

    case 'delete': {
        if ($area === FM_AREA_PUBLIC && !fm_is_able('filemanager_delete', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }
        $name   = fm_sanitize_name((string) ($_REQUEST['name'] ?? ''));
        $target = (string) ($_REQUEST['target'] ?? '');
        if ($name === null || !in_array($target, ['file', 'folder'], true)) {
            fm_json(['error' => 'Invalid request'], 400);
        }
        $victim = $dir . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($victim)) {
            fm_json(['error' => 'Not found'], 404);
        }
        if ($target === 'folder' && !is_dir($victim)) {
            fm_json(['error' => 'Not a folder'], 400);
        }
        if ($target === 'file' && !is_file($victim)) {
            fm_json(['error' => 'Not a file'], 400);
        }

        // Soft-delete: move into this area+id's trash instead of unlinking,
        // so the filemanager's undo toast can reverse it via 'restore'
        // below. $trashId is the handle the client hangs onto for that.
        $trashId = fm_trash_move($area, $id, $relPath, $victim, $name, $target);
        if ($trashId === null) {
            fm_json(['error' => 'Could not delete'], 500);
        }
        fm_json(['ok' => true, 'trashId' => $trashId]);
        break;
    }

    case 'restore': {
        // Undo for 'delete' above - same permission, since it's delete's
        // exact inverse. Only reachable within the retention window
        // fm_purge_old_trash() enforces (default 30 days); past that (or
        // once already restored/undone) this just 404s.
        if ($area === FM_AREA_PUBLIC && !fm_is_able('filemanager_delete', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }
        $trashId = (string) ($_REQUEST['trashId'] ?? '');
        if (!preg_match('/^[0-9a-f]{16}$/', $trashId)) {
            fm_json(['error' => 'Invalid request'], 400);
        }
        $trashRoot = fm_trash_root($area, $id);
        $entryDir  = $trashRoot !== null ? $trashRoot . DIRECTORY_SEPARATOR . $trashId : null;
        $metaFile  = $entryDir !== null ? $entryDir . DIRECTORY_SEPARATOR . '.meta.json' : null;
        if ($entryDir === null || !is_dir($entryDir) || !is_file($metaFile)) {
            fm_json(['error' => 'Nothing to restore - it may already have been restored, or the undo window has passed'], 404);
        }
        $meta = json_decode((string) file_get_contents($metaFile), true);
        if (!is_array($meta) || !isset($meta['name'], $meta['target'], $meta['path'])) {
            fm_json(['error' => 'Trash entry is corrupt'], 500);
        }
        $srcPath = $entryDir . DIRECTORY_SEPARATOR . $meta['name'];
        if (!file_exists($srcPath)) {
            fm_json(['error' => 'Trash entry is corrupt'], 500);
        }

        // Restore into its original folder if that folder still exists;
        // fall back to the area root if it was itself renamed/moved/
        // deleted in the meantime.
        $destDir = fm_resolve_path($area, $id, (string) $meta['path']);
        $restoredPath = (string) $meta['path'];
        if ($destDir === null) {
            $destDir = fm_area_root($area, $id);
            $restoredPath = '';
        }

        // Avoid clobbering anything created at the destination since deletion.
        $finalName = (string) $meta['name'];
        $n = 1;
        $ext = $meta['target'] === 'file' ? '.' . pathinfo($finalName, PATHINFO_EXTENSION) : '';
        $base = $meta['target'] === 'file' ? pathinfo($finalName, PATHINFO_FILENAME) : $finalName;
        while (file_exists($destDir . DIRECTORY_SEPARATOR . $finalName)) {
            $finalName = $base . '(' . $n . ')' . $ext;
            $n++;
        }

        if (!fm_move_any($srcPath, $destDir . DIRECTORY_SEPARATOR . $finalName)) {
            fm_json(['error' => 'Restore failed'], 500);
        }
        fm_rrmdir($entryDir); // drop the now-empty trash entry (and its .meta.json)
        fm_json(['ok' => true, 'name' => $finalName, 'path' => $restoredPath]);
        break;
    }

    case 'trash_list': {
        // Backs the "Trash" toolbar button - lets someone browse and
        // restore anything soft-deleted within the retention window, not
        // just the last delete (that's what the client's undo toast
        // already covers via the trashId 'delete' hands back).
        if ($area === FM_AREA_PUBLIC && !fm_is_able('filemanager_delete', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }
        $trashRoot = fm_trash_root($area, $id);
        if ($trashRoot === null) {
            fm_json(['items' => []]);
        }
        fm_purge_old_trash($trashRoot); // keep the list honest before showing it
        $items = [];
        foreach (scandir($trashRoot) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryDir = $trashRoot . DIRECTORY_SEPARATOR . $entry;
            $metaFile = $entryDir . DIRECTORY_SEPARATOR . '.meta.json';
            if (!is_dir($entryDir) || !is_file($metaFile)) {
                continue; // ignore anything that isn't a well-formed trash entry
            }
            $meta = json_decode((string) file_get_contents($metaFile), true);
            if (!is_array($meta) || !isset($meta['name'], $meta['target'], $meta['path'], $meta['deletedAt'])) {
                continue;
            }
            $items[] = [
                'trashId'   => $entry,
                'name'      => $meta['name'],
                'target'    => $meta['target'],
                'path'      => $meta['path'],
                'deletedAt' => $meta['deletedAt'],
            ];
        }
        usort($items, fn($a, $b) => $b['deletedAt'] <=> $a['deletedAt']); // most recently deleted first
        fm_json(['items' => $items]);
        break;
    }

    case 'trash_delete': {
        // Permanent delete FROM trash ("Delete forever" in the Trash
        // browser / "Empty trash") - unlike 'delete' above, this one has
        // no undo.
        if ($area === FM_AREA_PUBLIC && !fm_is_able('filemanager_delete', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }
        $trashId = (string) ($_REQUEST['trashId'] ?? '');
        if (!preg_match('/^[0-9a-f]{16}$/', $trashId)) {
            fm_json(['error' => 'Invalid request'], 400);
        }
        $trashRoot = fm_trash_root($area, $id);
        $entryDir  = $trashRoot !== null ? $trashRoot . DIRECTORY_SEPARATOR . $trashId : null;
        if ($entryDir === null || !is_dir($entryDir)) {
            fm_json(['error' => 'Not found'], 404);
        }
        fm_rrmdir($entryDir);
        fm_json(['ok' => true]);
        break;
    }

    case 'move': {
        // Move a file/folder from the current area/id/path into a
        // DIFFERENT area/id - e.g. My files into the page's Page files, or back.
        $name   = fm_sanitize_name((string) ($_REQUEST['name'] ?? ''));
        $target = (string) ($_REQUEST['target'] ?? ''); // 'file' | 'folder'
        $toArea = (string) ($_REQUEST['toArea'] ?? '');
        $toId   = (string) ($_REQUEST['toId'] ?? '');
        $toPath = (string) ($_REQUEST['toPath'] ?? '');

        if ($name === null || !in_array($target, ['file', 'folder'], true)
            || !in_array($toArea, [FM_AREA_PUBLIC, FM_AREA_PRIVATE], true) || $toId === '') {
            fm_json(['error' => 'Invalid request'], 400);
        }

        // Destination needs its own permission check - Old files can only
        // be a source (toArea's allow-list above excludes it).
        if (!fm_can_access_area($toArea, $toId)) {
            fm_json(['error' => 'Forbidden (destination)'], 403);
        }
        if ($toArea === FM_AREA_PUBLIC && !fm_is_able('filemanager_view', $pageid)) {
            fm_json(['error' => 'Forbidden (destination)'], 403);
        }
        // Migrating out of Old files always needs filemanager_migrate.
        // Otherwise, filemanager_move is needed only if Page files is
        // touched on either end (My files -> My files is always allowed).
        if ($area === FM_AREA_OLD) {
            if (!fm_is_able('filemanager_migrate', $pageid)) {
                fm_json(['error' => 'Forbidden'], 403);
            }
        } elseif (($area === FM_AREA_PUBLIC || $toArea === FM_AREA_PUBLIC) && !fm_is_able('filemanager_move', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }

        $toRelPath = fm_sanitize_relpath($toPath);
        if ($toRelPath === null) {
            fm_json(['error' => 'Invalid destination path'], 400);
        }
        $toDir = fm_resolve_path($toArea, $toId, $toRelPath); // never 'old' here
        if ($toDir === null || !is_dir($toDir)) {
            fm_json(['error' => 'Destination folder not found'], 404);
        }

        $srcPath = $dir . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($srcPath)) {
            fm_json(['error' => 'Not found'], 404);
        }
        if ($area === $toArea && $id === $toId && $relPath === $toRelPath) {
            fm_json(['error' => 'Source and destination are the same'], 400);
        }

        // Resolve a destination name: onConflict='replace' clears whatever
        // is already there (via trash, so it's still undoable) and moves
        // in under the exact requested name; otherwise fall back to the
        // usual file(1), file(2)... numbering.
        $onConflict = ((string) ($_REQUEST['onConflict'] ?? 'rename')) === 'replace' ? 'replace' : 'rename';
        $finalName = $name;
        $ext = $target === 'file' ? '.' . pathinfo($name, PATHINFO_EXTENSION) : '';
        $base = $target === 'file' ? pathinfo($name, PATHINFO_FILENAME) : $name;
        $existingPath = $toDir . DIRECTORY_SEPARATOR . $finalName;
        if ($onConflict === 'replace' && file_exists($existingPath)) {
            fm_clear_for_replace($toArea, $toId, $toRelPath, $existingPath, $finalName, is_dir($existingPath) ? 'folder' : 'file');
        } else {
            $n = 1;
            while (file_exists($toDir . DIRECTORY_SEPARATOR . $finalName)) {
                $finalName = $base . '(' . $n . ')' . $ext;
                $n++;
            }
        }
        $destPath = $toDir . DIRECTORY_SEPARATOR . $finalName;

        // "Old files" migration crosses from $CFG->userfilespath into
        // $CFG->fmroot, which may not be the same filesystem/mount, so a
        // plain rename() can fail there even though nothing is wrong -
        // fall back to a recursive copy + delete-source in that case.
        if (!fm_move_any($srcPath, $destPath)) {
            fm_json(['error' => 'Move failed'], 500);
        }
        fm_json(['ok' => true, 'name' => $finalName]);
        break;
    }

    case 'copy': {
        // Same shape as 'move' above, except the source is left in place.
        // Old files is never a valid source (excluded from the read-only
        // allow-list near the top of this file).
        $name   = fm_sanitize_name((string) ($_REQUEST['name'] ?? ''));
        $target = (string) ($_REQUEST['target'] ?? ''); // 'file' | 'folder'
        $toArea = (string) ($_REQUEST['toArea'] ?? '');
        $toId   = (string) ($_REQUEST['toId'] ?? '');
        $toPath = (string) ($_REQUEST['toPath'] ?? '');

        if ($name === null || !in_array($target, ['file', 'folder'], true)
            || !in_array($toArea, [FM_AREA_PUBLIC, FM_AREA_PRIVATE], true) || $toId === '') {
            fm_json(['error' => 'Invalid request'], 400);
        }

        if (!fm_can_access_area($toArea, $toId)) {
            fm_json(['error' => 'Forbidden (destination)'], 403);
        }
        if ($toArea === FM_AREA_PUBLIC && !fm_is_able('filemanager_view', $pageid)) {
            fm_json(['error' => 'Forbidden (destination)'], 403);
        }
        // filemanager_copy is needed only if Page files is touched on
        // either end (My files -> My files is always allowed).
        if (($area === FM_AREA_PUBLIC || $toArea === FM_AREA_PUBLIC) && !fm_is_able('filemanager_copy', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }

        $toRelPath = fm_sanitize_relpath($toPath);
        if ($toRelPath === null) {
            fm_json(['error' => 'Invalid destination path'], 400);
        }
        $toDir = fm_resolve_path($toArea, $toId, $toRelPath);
        if ($toDir === null || !is_dir($toDir)) {
            fm_json(['error' => 'Destination folder not found'], 404);
        }

        $srcPath = $dir . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($srcPath)) {
            fm_json(['error' => 'Not found'], 404);
        }
        if ($area === $toArea && $id === $toId && $relPath === $toRelPath) {
            fm_json(['error' => 'Source and destination are the same'], 400);
        }

        // Resolve a destination name: onConflict='replace' clears whatever
        // is already there (via trash, so it's still undoable) and copies
        // in under the exact requested name; otherwise fall back to the
        // usual file(1), file(2)... numbering.
        $onConflict = ((string) ($_REQUEST['onConflict'] ?? 'rename')) === 'replace' ? 'replace' : 'rename';
        $finalName = $name;
        $ext = $target === 'file' ? '.' . pathinfo($name, PATHINFO_EXTENSION) : '';
        $base = $target === 'file' ? pathinfo($name, PATHINFO_FILENAME) : $name;
        $existingPath = $toDir . DIRECTORY_SEPARATOR . $finalName;
        if ($onConflict === 'replace' && file_exists($existingPath)) {
            fm_clear_for_replace($toArea, $toId, $toRelPath, $existingPath, $finalName, is_dir($existingPath) ? 'folder' : 'file');
        } else {
            $n = 1;
            while (file_exists($toDir . DIRECTORY_SEPARATOR . $finalName)) {
                $finalName = $base . '(' . $n . ')' . $ext;
                $n++;
            }
        }
        $destPath = $toDir . DIRECTORY_SEPARATOR . $finalName;

        $ok = is_dir($srcPath) ? fm_copy_dir($srcPath, $destPath) : @copy($srcPath, $destPath);
        if (!$ok) {
            fm_json(['error' => 'Copy failed'], 500);
        }
        if (!is_dir($srcPath)) {
            @chmod($destPath, 0640);
        }
        fm_json(['ok' => true, 'name' => $finalName]);
        break;
    }

    case 'duplicate': {
        // "Duplicate" quick action - copies an item into its own folder
        // under an auto-generated name, e.g. 'photo.png' -> 'photo (copy).png'.
        // Deliberately its own action rather than 'copy' with a matching
        // source/destination: it gets a friendlier name pattern this way,
        // and 'copy' keeps its "source and destination are the same"
        // guard as a real no-op guard for that general-purpose action.
        if ($area === FM_AREA_PUBLIC && !fm_is_able('filemanager_copy', $pageid)) {
            fm_json(['error' => 'Forbidden'], 403);
        }
        $name   = fm_sanitize_name((string) ($_REQUEST['name'] ?? ''));
        $target = (string) ($_REQUEST['target'] ?? '');
        if ($name === null || !in_array($target, ['file', 'folder'], true)) {
            fm_json(['error' => 'Invalid request'], 400);
        }
        $srcPath = $dir . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($srcPath)) {
            fm_json(['error' => 'Not found'], 404);
        }

        $ext  = $target === 'file' ? '.' . pathinfo($name, PATHINFO_EXTENSION) : '';
        $base = $target === 'file' ? pathinfo($name, PATHINFO_FILENAME) : $name;
        $finalName = $base . ' (copy)' . $ext;
        $n = 2;
        while (file_exists($dir . DIRECTORY_SEPARATOR . $finalName)) {
            $finalName = $base . ' (copy ' . $n . ')' . $ext;
            $n++;
        }
        $destPath = $dir . DIRECTORY_SEPARATOR . $finalName;

        $ok = is_dir($srcPath) ? fm_copy_dir($srcPath, $destPath) : @copy($srcPath, $destPath);
        if (!$ok) {
            fm_json(['error' => 'Duplicate failed'], 500);
        }
        if (!is_dir($srcPath)) {
            @chmod($destPath, 0640);
        }
        fm_json(['ok' => true, 'name' => $finalName]);
        break;
    }

    case 'check_conflicts': {
        // Lightweight batch precheck the client uses to decide whether a
        // Replace/Keep-both choice actually needs asking, for upload/
        // move/copy - so the choice is only surfaced when something would
        // really collide. Read-only, no CSRF needed (like 'list').
        // 'items' is a JSON-encoded array of {name, target}; toArea/toId/
        // toPath default to the current area/id/path (e.g. for an upload
        // or duplicate precheck within the folder already being viewed).
        $itemsParam = json_decode((string) ($_REQUEST['items'] ?? '[]'), true);
        if (!is_array($itemsParam) || !$itemsParam) {
            fm_json(['conflicts' => []]);
        }
        $toArea = (string) ($_REQUEST['toArea'] ?? $area);
        $toId   = (string) ($_REQUEST['toId'] ?? $id);
        $toPath = (string) ($_REQUEST['toPath'] ?? $relPath);

        if ($toArea !== $area || $toId !== $id) {
            // Cross-area/id destination (a move/copy target) needs its own
            // access check, same as 'move'/'copy' require for theirs.
            if (!fm_can_access_area($toArea, $toId)) {
                fm_json(['error' => 'Forbidden (destination)'], 403);
            }
        }
        $toRelPath = fm_sanitize_relpath($toPath);
        $toDir = $toRelPath !== null ? fm_resolve_path($toArea, $toId, $toRelPath) : null;
        if ($toDir === null) {
            fm_json(['error' => 'Destination folder not found'], 404);
        }

        $conflicts = [];
        foreach ($itemsParam as $it) {
            $n = is_array($it) ? fm_sanitize_name((string) ($it['name'] ?? '')) : null;
            if ($n !== null && file_exists($toDir . DIRECTORY_SEPARATOR . $n)) {
                $conflicts[] = $n;
            }
        }
        fm_json(['conflicts' => $conflicts]);
        break;
    }

    case 'download_zip': {
        // Bulk download for a multi-select - bundles the given items
        // (files and/or whole folders, from the current $dir only) into
        // one zip and streams it back directly rather than through
        // fm_json(). No extra permission beyond the area access already
        // established above: this is a read, same as 'list'/single-file
        // download, and Old files' read-only gate above explicitly allows
        // it alongside 'list'/'move'.
        if (!class_exists('ZipArchive')) {
            fm_json(['error' => 'Zip downloads are not available on this server'], 500);
        }
        $itemsParam = json_decode((string) ($_REQUEST['items'] ?? '[]'), true);
        if (!is_array($itemsParam) || !$itemsParam) {
            fm_json(['error' => 'Nothing selected'], 400);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'fmzip_');
        if ($tmpZip === false) {
            fm_json(['error' => 'Could not create zip'], 500);
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpZip);
            fm_json(['error' => 'Could not create zip'], 500);
        }

        $added = 0;
        foreach ($itemsParam as $it) {
            $name   = is_array($it) ? fm_sanitize_name((string) ($it['name'] ?? '')) : null;
            $target = is_array($it) ? (string) ($it['target'] ?? '') : '';
            if ($name === null || !in_array($target, ['file', 'folder'], true)) {
                continue; // silently skip anything malformed rather than failing the whole batch
            }
            $srcPath = $dir . DIRECTORY_SEPARATOR . $name;
            if (!file_exists($srcPath)) {
                continue; // may have been deleted/moved since the selection was made
            }
            if ($target === 'folder' && is_dir($srcPath)) {
                fm_zip_add_dir($zip, $srcPath, $name);
                $added++;
            } elseif ($target === 'file' && is_file($srcPath)) {
                $zip->addFile($srcPath, $name);
                $added++;
            }
        }
        $zip->close();

        if ($added === 0) {
            @unlink($tmpZip);
            fm_json(['error' => 'None of the selected items could be found'], 404);
        }

        // Bypassing fm_json() for a binary response - same ob_end_clean()
        // discipline it uses, so a stray library warning can't corrupt
        // the zip the way it used to corrupt JSON (see file header).
        ob_end_clean();
        http_response_code(200);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="files-' . date('Y-m-d-His') . '.zip"');
        header('Content-Length: ' . filesize($tmpZip));
        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    }

    case 'geturl': {
        // Generate a signed SHARE link (what actually gets inserted into
        // content or copied) for a specific file OR folder at a chosen
        // access level. Distinct from the 'previewUrl' the list action
        // returns, which only ever works inside this authenticated dialog.
        $name   = fm_sanitize_name((string) ($_REQUEST['name'] ?? ''));
        $target = (string) ($_REQUEST['target'] ?? 'file'); // 'file' | 'folder'
        $level  = (string) ($_REQUEST['level'] ?? '');
        $pageid = (string) ($_REQUEST['pageid'] ?? ''); // required for level=page
        $mode   = (string) ($_REQUEST['mode'] ?? 'index'); // folder only: 'gallery' | 'index'

        if ($name === null || !in_array($target, ['file', 'folder'], true)
            || !in_array($level, [FM_LEVEL_LINK, FM_LEVEL_PAGE, FM_LEVEL_PRIVATE], true)) {
            fm_json(['error' => 'Invalid request'], 400);
        }
        if ($target === 'folder' && !in_array($mode, ['gallery', 'index'], true)) {
            fm_json(['error' => 'Invalid request'], 400);
        }
        if ($level === FM_LEVEL_PRIVATE && $area !== FM_AREA_PRIVATE) {
            fm_json(['error' => '"My eyes only" is only available for files in My files'], 400);
        }
        if ($level === FM_LEVEL_PAGE && $pageid === '') {
            fm_json(['error' => 'No page context available for a page-level link'], 400);
        }

        $entryRel = ($relPath === '' ? '' : $relPath . '/') . $name;
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $extra = $level === FM_LEVEL_PAGE ? $pageid : '';

        if ($target === 'folder') {
            if (!is_dir($full)) {
                fm_json(['error' => 'Not found'], 404);
            }
            // Folder links are evergreen (mtime=0 sentinel) - see
            // fm_build_share_token's docblock in fmconfig.php.
            $url = fm_share_url($gateUrl, $level, $area, $id, $entryRel, 0, $extra, false);
            fm_json(['ok' => true, 'url' => $url, 'level' => $level, 'mode' => $mode]);
        }

        if (!is_file($full)) {
            fm_json(['error' => 'Not found'], 404);
        }
        $mtime = filemtime($full);
        $url = fm_share_url($gateUrl, $level, $area, $id, $entryRel, $mtime, $extra, false);
        fm_json(['ok' => true, 'url' => $url, 'level' => $level]);
        break;
    }

    default:
        fm_json(['error' => 'Unknown action'], 400);
}

/**
 * Recursively delete a folder and everything in it.
 */
function fm_rrmdir(string $dir): void {
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($full)) {
            fm_rrmdir($full);
        } else {
            unlink($full);
        }
    }
    rmdir($dir);
}

/**
 * Sweep one area+id's trash (see fm_trash_root()) for entries past the
 * retention window and hard-delete them. There's no cron here, so this
 * runs opportunistically from fm_trash_move() itself - cheap (one scandir
 * of a folder that's normally tiny) and self-limiting since it only ever
 * piggybacks on a request that's already mutating that same trash.
 */
function fm_purge_old_trash(string $trashRoot, int $maxAgeSeconds = 2592000): void { // 2592000s = 30 days
    $entries = @scandir($trashRoot);
    if ($entries === false) {
        return;
    }
    $cutoff = time() - $maxAgeSeconds;
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $trashRoot . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($full) && filemtime($full) < $cutoff) {
            fm_rrmdir($full);
        }
    }
}

/**
 * Soft-delete $srcPath (named $name, a $target 'file'|'folder', currently
 * at $relPath within $area/$id) into that area+id's trash. Shared by the
 * 'delete' action and by move/copy/upload's onConflict=replace path, so a
 * mistaken "Replace" is just as undoable via the Trash browser as an
 * explicit delete. Returns the new trashId on success, or null on
 * failure (caller decides how to handle that).
 */
function fm_trash_move(string $area, string $id, string $relPath, string $srcPath, string $name, string $target): ?string {
    $trashRoot = fm_trash_root($area, $id);
    if ($trashRoot === null) {
        return null;
    }
    fm_purge_old_trash($trashRoot);
    $trashId  = bin2hex(random_bytes(8));
    $entryDir = $trashRoot . DIRECTORY_SEPARATOR . $trashId;
    if (!mkdir($entryDir, 0750)) {
        return null;
    }
    if (!fm_move_any($srcPath, $entryDir . DIRECTORY_SEPARATOR . $name)) {
        fm_rrmdir($entryDir);
        return null;
    }
    file_put_contents($entryDir . DIRECTORY_SEPARATOR . '.meta.json', json_encode([
        'name'      => $name,
        'target'    => $target,
        'path'      => $relPath,
        'deletedAt' => time(),
    ]));
    return $trashId;
}

/**
 * Clears the way for onConflict='replace' in 'move'/'copy'/'upload' by
 * getting $existingPath (named $name, a $target 'file'|'folder', living
 * at $relPath within $area/$id) out of the way - preferably into trash so
 * a mistaken Replace can still be undone, falling back to a hard delete
 * only if the trash move itself fails for some reason.
 */
function fm_clear_for_replace(string $area, string $id, string $relPath, string $existingPath, string $name, string $target): void {
    if (fm_trash_move($area, $id, $relPath, $existingPath, $name, $target) !== null) {
        return;
    }
    is_dir($existingPath) ? fm_rrmdir($existingPath) : @unlink($existingPath);
}

/**
 * Move src to dest, tolerating a cross-filesystem move (e.g. migrating out
 * of the legacy $CFG->userfilespath tree into $CFG->fmroot, which may not
 * share a mount) by falling back to recursive copy + delete-source when a
 * plain rename() fails.
 */
function fm_move_any(string $src, string $dest): bool {
    if (@rename($src, $dest)) {
        return true;
    }
    if (is_dir($src)) {
        if (!fm_copy_dir($src, $dest)) {
            return false;
        }
        fm_rrmdir($src);
        return true;
    }
    if (!@copy($src, $dest)) {
        return false;
    }
    @unlink($src);
    return true;
}

function fm_copy_dir(string $src, string $dest): bool {
    if (!is_dir($dest) && !mkdir($dest, 0750, true)) {
        return false;
    }
    foreach (scandir($src) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $s = $src . DIRECTORY_SEPARATOR . $entry;
        $d = $dest . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($s)) {
            if (!fm_copy_dir($s, $d)) {
                return false;
            }
        } elseif (!copy($s, $d)) {
            return false;
        }
    }
    return true;
}

/**
 * Recursively adds $dirPath into $zip under $zipSubPath (typically the
 * folder's own name, since it's added as a top-level entry of a bulk
 * download), preserving its internal structure. Used by 'download_zip'
 * for any folders included in the selection.
 */
function fm_zip_add_dir(ZipArchive $zip, string $dirPath, string $zipSubPath): void {
    $zip->addEmptyDir($zipSubPath);
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        // Zip entries are always '/'-separated regardless of host OS.
        $localPath = $zipSubPath . '/' . str_replace('\\', '/', substr($item->getPathname(), strlen($dirPath) + 1));
        if ($item->isDir()) {
            $zip->addEmptyDir($localPath);
        } else {
            $zip->addFile($item->getPathname(), $localPath);
        }
    }
}
