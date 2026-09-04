<?php

/***************************************************************************
* filegate.php - filemanager file gateway (no database).
* -------------------------------------------------------------------------
* Lives at the app root (same convention as your other front-line scripts).
* Everything the filemanager stores lives outside the web root under
* $CFG->fmroot; this script is the only way to read it. (Files still in the
* legacy $CFG->userfilespath/{userid} "Old files" area are NOT served here
* at all - those are linked directly at their existing URL, unchanged.)
*
* Two kinds of request, distinguished by the presence of `lvl`:
*
*  - ADMIN PREVIEW (no `lvl` param): used only for thumbnails inside the
*    authenticated filemanager dialog, always a single file. Gated by the
*    same edit permission that already let the session browse this area.
*
*  - SHARE (`lvl=link|page|private`): the URL actually inserted into page
*    content or handed to "Copy Link" / "Insert" - a single file, OR a
*    whole folder (see below). Gated per level - see fmconfig.php's
*    fm_can_view_page / fm_can_access_private.
*
* Folder links: same share levels as files, but with `m=0` (the sentinel
* meaning "evergreen, not pinned to one snapshot in time" - see
* fm_build_share_token's docblock). When the resolved path is a directory,
* this renders a simple index of its contents instead of streaming bytes;
* the "gallery" vs "index" distinction the filemanager UI offers is purely
* a CSS class on the *inserted* <a> tag (for hooking into whatever gallery
* JS you already have) - this endpoint's own output is the same either way.
*
* No uploads table: every URL is self-authenticating via an HMAC token
* bound to area/id/path/mtime(/level), so a tampered or guessed URL simply
* fails the signature check, and replacing a file automatically invalidates
* old links since mtime is part of what's signed.
*
* Usage:
*   filegate.php?a=priv&id=7&p=x.jpg&m=169...&t=...               (admin preview)
*   filegate.php?lvl=link&a=priv&id=7&p=x.jpg&m=169...&ex=&t=...  (file share link)
*   filegate.php?lvl=link&a=priv&id=7&p=photos&m=0&ex=&t=...      (folder link)
***************************************************************************/

if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
if (!defined('FMCONFIG')) {
    $sub = '';
    while (!file_exists($sub . 'fmconfig.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'fmconfig.php');
}

function fmgate_deny($code, $filename = null) {
    //http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');

    $variant = fm_gate_message($code, is_string($filename) ? $filename : null);

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$variant['title']}</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f5f7;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1f2328;
    padding: 24px;
  }
  .card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    padding: 48px 40px;
    max-width: 420px;
    width: 100%;
    text-align: center;
  }
  .icon {
    font-size: 48px;
    line-height: 1;
    margin-bottom: 20px;
  }
  h1 {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 12px;
  }
  p {
    font-size: 15px;
    line-height: 1.5;
    color: #57606a;
    margin: 0;
  }
  .code {
    margin-top: 24px;
    font-size: 12px;
    color: #8b949e;
    letter-spacing: 0.03em;
  }
</style>
</head>
<body>
  <div class="card">
    <div class="icon">{$variant['icon']}</div>
    <h1>{$variant['title']}</h1>
    <p>{$variant['message']}</p>
    <div class="code">Error {$code}</div>
  </div>
</body>
</html>
HTML;
    exit;
}

$area = isset($_GET['a']) ? (string) $_GET['a'] : '';
$id   = isset($_GET['id']) ? (string) $_GET['id'] : '';
$rel  = isset($_GET['p']) ? (string) $_GET['p'] : '';
$mt   = isset($_GET['m']) ? (int) $_GET['m'] : -1; // -1 = missing, distinct from the m=0 folder sentinel
$tok  = isset($_GET['t']) ? (string) $_GET['t'] : '';
$lvl  = isset($_GET['lvl']) ? (string) $_GET['lvl'] : '';
$ex   = isset($_GET['ex']) ? (string) $_GET['ex'] : '';

if (!in_array($area, [FM_AREA_PUBLIC, FM_AREA_PRIVATE], true) || $id === '' || $rel === '' || $tok === '' || $mt < 0) {
    fmgate_deny(404);
}

$rel = fm_sanitize_relpath($rel);
if ($rel === null || $rel === '') {
    fmgate_deny(404);
}
$gateName = basename(str_replace(['\\', '/'], '/', $rel));

$isFolderLink = $mt === 0;
if ($isFolderLink && $lvl === '') {
    // Admin-preview kind is only ever issued for single files - never
    // minted for a folder, so treat one as invalid rather than guessing.
    fmgate_deny(404, $gateName);
}

if ($lvl === '') {
    // Admin preview path: must be editing this area, verified before
    // touching the token so an invalid token on someone else's private
    // file never gets a distinguishable response either way.
    if (!fm_can_access_area($area, $id)) {
        fmgate_deny(403, $gateName);
    }
} else {
    if (!fm_check_share_permission($lvl, $area, $id, $ex)) {
        fmgate_deny(403, $gateName);
    }
    // FM_LEVEL_LINK: no session check at all - the whole point is it never
    // requires login. Still needs a valid, unmodified token below.
}

// Resolve to a real, contained path (works for files and folders alike).
$full = fm_resolve_path($area, $id, $rel);
if ($full === null) {
    fmgate_deny(404, $gateName);
}

if ($isFolderLink) {
    if (!is_dir($full)) {
        fmgate_deny(404, $gateName);
    }
    if (!fm_verify_share_token($lvl, $area, $id, $rel, 0, $ex, $tok)) {
        fmgate_deny(403, $gateName);
    }
    fmgate_render_folder_index($full, $lvl, $area, $id, $rel, $ex);
    exit;
}

if (!is_file($full)) {
    fmgate_deny(404, $gateName);
}

// Token binds mtime, so if the file was replaced since the link was
// generated, the old token no longer validates.
$actualMtime = filemtime($full);
if ($actualMtime !== $mt) {
    fmgate_deny(403, $gateName);
}
$tokenOk = $lvl === ''
    ? fm_verify_admin_token($area, $id, $rel, $mt, $tok)
    : fm_verify_share_token($lvl, $area, $id, $rel, $mt, $ex, $tok);
if (!$tokenOk) {
    fmgate_deny(403, $gateName);
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
if (!array_key_exists($ext, $GLOBALS['FM_ALLOWED_EXT'])) {
    fmgate_deny(404, $gateName);
}

$mtype = $GLOBALS['FM_ALLOWED_EXT'][$ext];
$download = !empty($_GET['dl']);

header('Content-Type: ' . $mtype);
header('Content-Length: ' . filesize($full));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($full) . '"');
header('X-Content-Type-Options: nosniff');

if ($lvl === FM_LEVEL_PRIVATE || ($lvl === '' && $area === FM_AREA_PRIVATE)) {
    // Personal content: never let intermediate caches or the browser
    // disk-cache keep a copy around.
    header('Cache-Control: private, no-store, max-age=0');
} else {
    // Public/page/link-level content: safe (and desirable) to cache since
    // the token already changes whenever the file is replaced.
    header('Cache-Control: public, max-age=31536000, immutable');
}

ob_clean();
flush();
readfile($full);
exit;

/**
 * Render a simple index of a folder's contents - each child file gets its
 * own normal (mtime-pinned) share link at the same level/extra as the
 * folder itself; subfolders get their own evergreen folder link so a
 * gallery/index can be browsed recursively. Deliberately not cached
 * (Cache-Control: no-cache) since, unlike a single file, this listing is
 * expected to change as files are added/removed.
 */
function fmgate_render_folder_index(string $dir, string $lvl, string $area, string $id, string $relBase, string $ex): void {
    $gateUrl = strtok($_SERVER['REQUEST_URI'], '?'); // this same script

    $folders = [];
    $files = [];
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $entry;
        $childRel = ($relBase === '' ? '' : $relBase . '/') . $entry;
        if (is_dir($full)) {
            $folders[] = ['name' => $entry, 'url' => fm_share_url($gateUrl, $lvl, $area, $id, $childRel, 0, $ex)];
        } else {
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!array_key_exists($ext, $GLOBALS['FM_ALLOWED_EXT'])) {
                continue;
            }
            $files[] = [
                'name' => $entry,
                'ext'  => $ext,
                'url'  => fm_share_url($gateUrl, $lvl, $area, $id, $childRel, filemtime($full), $ex),
            ];
        }
    }
    usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars(basename($dir)) . '</title>';
    echo '<style>body{font:14px/1.5 sans-serif;margin:20px}ul{list-style:none;padding:0;display:flex;flex-wrap:wrap;gap:12px}';
    echo 'li{width:120px;text-align:center}img{max-width:100%;max-height:100px;display:block;margin:0 auto 4px}';
    echo 'a{color:inherit;text-decoration:none;word-break:break-word}a:hover{text-decoration:underline}</style></head><body>';
    echo '<ul>';
    foreach ($folders as $f) {
        echo '<li><a href="' . htmlspecialchars($f['url']) . '">' . "\u{1F4C1}" . ' ' . htmlspecialchars($f['name']) . '</a></li>';
    }
    foreach ($files as $f) {
        echo '<li><a href="' . htmlspecialchars($f['url']) . '">';
        if (in_array($f['ext'], $imageExt, true)) {
            echo '<img src="' . htmlspecialchars($f['url']) . '" alt="' . htmlspecialchars($f['name']) . '">';
        }
        echo htmlspecialchars($f['name']) . '</a></li>';
    }
    if (!$folders && !$files) {
        echo '<li>(empty)</li>';
    }
    echo '</ul></body></html>';
}

