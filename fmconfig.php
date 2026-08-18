<?php
/***************************************************************************
* fmconfig.php - shared configuration/helpers for the filemanager.
* -------------------------------------------------------------------------
* Lives at the app root, alongside config.php. Included by filegate.php
* (public/gateway) and tinymce/plugins/filemanager/api.php + index.php
* (authenticated admin UI).
*
* Boots exactly like every other lib file (dblib.php, roleslib.php, etc.):
* walk up to lib/header.php if it isn't already loaded. That gives us
* $CFG, $USER, is_logged_in(), user_is_able(), is_siteadmin() for free.
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('FMCONFIG', true);

// Root storage folder for the filemanager. Must be OUTSIDE the web root -
// $CFG->userfilespath today is *inside* it (that's the thing we're
// migrating away from), so this defaults to a sibling folder one level up.
// Point it wherever you actually want it; just keep it out of docroot.
if (!isset($CFG->fmroot)) {
    $CFG->fmroot = dirname($CFG->userfilespath) . DIRECTORY_SEPARATOR . 'fmstorage';
}

// Secret used to sign file tokens (see fm_build_token below). MUST be a
// long random value set once per install - move this into config.php
// itself (e.g. $CFG->fm_secret = getenv('FM_SECRET');) rather than leaving
// it here. Placeholder only, so nothing fatals out in dev before you set it.
if (!isset($CFG->fm_secret)) {
    $CFG->fm_secret = 'CHANGE-ME-set-a-random-64-char-secret-in-config.php';
}

// Extensions the filemanager will store/serve, mapped to their MIME type.
$GLOBALS['FM_ALLOWED_EXT'] = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt'  => 'text/plain',
    'csv'  => 'text/csv',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mp3'  => 'audio/mpeg',
    'zip'  => 'application/zip',
];

// Read-only legacy area: files still sitting in the old web-accessible
// $CFG->userfilespath/{userid} structure, browsed here purely so they can
// be manually reviewed and moved into the new private/public areas. Never
// goes through filegate.php - these files are already directly linkable
// at their existing (pre-migration) URL, so that's what's handed out.
const FM_AREA_PUBLIC  = 'pub';
const FM_AREA_PRIVATE = 'priv';
const FM_AREA_OLD = 'old';

/**
 * Sanitize a user-supplied relative path (subfolder chain + optional
 * filename). Rejects traversal, absolute paths, null bytes, and empty
 * segments. Returns null if the input is not safe.
 */
function fm_sanitize_relpath(string $relpath): ?string {
    $relpath = str_replace('\\', '/', $relpath);
    $relpath = trim($relpath, '/');
    if ($relpath === '') {
        return '';
    }
    if (strpos($relpath, "\0") !== false) {
        return null;
    }
    $parts = explode('/', $relpath);
    $clean = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9 _\-\.\(\)]+$/', $part)) {
            return null;
        }
        $clean[] = $part;
    }
    return implode('/', $clean);
}

/**
 * Sanitize a single new name (used for rename / mkdir / upload targets) -
 * same rule as fm_sanitize_relpath but for exactly one path segment.
 */
function fm_sanitize_name(string $name): ?string {
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        return null;
    }
    if (!preg_match('/^[A-Za-z0-9 _\-\.\(\)]+$/', $name)) {
        return null;
    }
    return $name;
}

/**
 * Absolute base folder on disk for a given area + id (pageid for public,
 * userid for private). Creates it on first use, same idea as userlib.php's
 * is_logged_in() calling recursive_mkdir() for the old per-user folder.
 */
function fm_area_root(string $area, string $id): ?string {
    global $CFG;
    $id = preg_replace('/[^A-Za-z0-9_\-]/', '', $id);
    if ($id === '') {
        return null;
    }
    $sub = ($area === FM_AREA_PUBLIC) ? 'public' : 'private';
    $base = rtrim($CFG->fmroot, '/\\') . DIRECTORY_SEPARATOR . $sub . DIRECTORY_SEPARATOR . $id;
    recursive_mkdir($base); // from filelib.php - same helper the app already uses
    return realpath($base) ?: null;
}

/**
 * Resolve area+id+relpath to a real, contained absolute path. Returns null
 * if it doesn't exist or escapes the area root.
 */
function fm_resolve_path(string $area, string $id, string $relpath): ?string {
    $root = fm_area_root($area, $id);
    if ($root === null) {
        return null;
    }
    $target = $relpath === '' ? $root : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relpath);
    $real = realpath($target);
    if ($real === false) {
        return null;
    }
    if ($real !== $root && strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $real;
}

/**
 * Old-area equivalent of fm_area_root/fm_resolve_path: read-only, rooted at
 * $CFG->userfilespath/{userid} (the pre-migration structure) rather than
 * $CFG->fmroot. Returns null if that folder doesn't exist yet - unlike the
 * new areas, this is never auto-created.
 */
function fm_old_root(string $userid): ?string {
    global $CFG;
    $userid = preg_replace('/[^A-Za-z0-9_\-]/', '', $userid);
    if ($userid === '') {
        return null;
    }
    $base = $CFG->userfilespath . "/$userid";
    return is_dir($base) ? (realpath($base) ?: null) : null;
}

function fm_resolve_old_path(string $userid, string $relpath): ?string {
    $root = fm_old_root($userid);
    if ($root === null) {
        return null;
    }
    $target = $relpath === '' ? $root : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relpath);
    $real = realpath($target);
    if ($real === false) {
        return null;
    }
    if ($real !== $root && strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $real;
}

/**
 * True if this user has no Old files at all (no root folder, or an empty
 * one) - used to hide the Old files tab instead of showing an area that
 * will always be empty. Only checks the top level, not nested subfolders.
 */
function fm_old_is_empty(string $userid): bool {
    $root = fm_old_root($userid);
    if ($root === null) {
        return true;
    }
    $entries = @scandir($root);
    if ($entries === false) {
        return true;
    }
    return count(array_diff($entries, ['.', '..'])) === 0;
}

/**
 * Direct (non-filegate) URL for a file/folder still in the old structure -
 * it's already sitting in a web-accessible folder today, so there's
 * nothing to gate; this is just the existing public URL.
 */
function fm_old_direct_url(string $userid, string $relpath): string {
    global $CFG;
    $segments = $relpath === '' ? [] : explode('/', $relpath);
    $encoded = array_map('rawurlencode', $segments);
    return rtrim($CFG->userfilesurl, '/') . '/' . rawurlencode($userid)
        . ($encoded ? '/' . implode('/', $encoded) : '');
}

/**
 * Generalized dispatch used by api.php so callers don't need to branch on
 * area type themselves. FM_AREA_OLD reuses the private-area permission
 * (same ownership model, just a different physical location).
 */
function fm_can_access_area(string $area, string $id): bool {
    if ($area === FM_AREA_PUBLIC) {
        return fm_can_access_page($id);
    }
    return fm_can_access_private($id); // priv and old both gate on ownership
}

function fm_resolve_area_path(string $area, string $id, string $relpath): ?string {
    if ($area === FM_AREA_OLD) {
        return fm_resolve_old_path($id, $relpath);
    }
    return fm_resolve_path($area, $id, $relpath);
}

/**
 * Build the signed token for an ADMIN PREVIEW url - used only for
 * thumbnails/previews inside the authenticated filemanager dialog itself.
 * Gated by the same access check that already let the user browse this
 * area (fm_can_access_private / fm_can_access_page), never handed out as a
 * link to embed anywhere.
 */
function fm_build_admin_token(string $area, string $id, string $relpath, int $mtime): string {
    global $CFG;
    return substr(hash_hmac('sha256', 'admin|' . $area . '|' . $id . '|' . $relpath . '|' . $mtime, $CFG->fm_secret), 0, 32);
}

function fm_verify_admin_token(string $area, string $id, string $relpath, int $mtime, string $token): bool {
    return hash_equals(fm_build_admin_token($area, $id, $relpath, $mtime), $token);
}

function fm_admin_preview_url(string $gateUrl, string $area, string $id, string $relpath, int $mtime): string {
    $token = fm_build_admin_token($area, $id, $relpath, $mtime);
    // Explicit '&' separator - http_build_query() otherwise defaults to
    // the arg_separator.output ini setting, which some PHP installs set
    // to '&amp;' for HTML-escaping convenience. That silently breaks
    // parse_str() on the reading side (everything after the first param
    // ends up under keys like 'amp;a' instead of 'a') - always use a real
    // '&' here and htmlspecialchars() the URL separately wherever it's
    // actually echoed into HTML.
    $qs = http_build_query(['a' => $area, 'id' => $id, 'p' => $relpath, 'm' => $mtime, 't' => $token], '', '&');
    return $gateUrl . '?' . $qs;
}

/**
 * Access levels for SHARE links - the URL actually inserted into content
 * or handed to "Copy Link". Ordered least -> most restrictive:
 *   link    - anyone with the URL, no login ever required.
 *   page    - viewable by whoever can view the associated page (that page
 *             may itself require login - see fm_can_view_page).
 *   private - only the owning user's own session ("my eyes only") - only
 *             meaningful for files in the private (My files) area.
 */
const FM_LEVEL_LINK    = 'link';
const FM_LEVEL_PAGE    = 'page';
const FM_LEVEL_PRIVATE = 'private';

/**
 * Build a signed SHARE token. $extra carries the pageid for level=page
 * (the page whose view-permission gates this link) - bound into the
 * signature so it can't be swapped for a different page after the fact.
 *
 * Folder links: pass $mtime = 0. filegate.php treats that as a sentinel
 * meaning "this is a folder link, don't pin it to one snapshot in time" -
 * unlike files, a folder's contents are *expected* to change (that's the
 * point of a gallery/index link), so there's no specific mtime to bind.
 * The token still fully authenticates level/area/id/path, just not "as of
 * this exact moment."
 */
function fm_build_share_token(string $level, string $area, string $id, string $relpath, int $mtime, string $extra = ''): string {
    global $CFG;
    return substr(hash_hmac('sha256', 'share|' . $level . '|' . $area . '|' . $id . '|' . $relpath . '|' . $mtime . '|' . $extra, $CFG->fm_secret), 0, 32);
}

function fm_verify_share_token(string $level, string $area, string $id, string $relpath, int $mtime, string $extra, string $token): bool {
    return hash_equals(fm_build_share_token($level, $area, $id, $relpath, $mtime, $extra), $token);
}

function fm_share_url(string $gateUrl, string $level, string $area, string $id, string $relpath, int $mtime, string $extra = '', bool $download = false): string {
    $token = fm_build_share_token($level, $area, $id, $relpath, $mtime, $extra);
    $qs = http_build_query([
        'lvl' => $level, 'a' => $area, 'id' => $id, 'p' => $relpath, 'm' => $mtime,
        'ex' => $extra, 't' => $token, 'dl' => $download ? 1 : 0,
    ], '', '&'); // explicit '&' - see fm_admin_preview_url's comment above
    return $gateUrl . '?' . $qs;
}

/* -------------------------------------------------------------------------
 * PERMISSION HOOKS - wired to the app's real auth (roleslib.php / userlib.php).
 * These are the only places access decisions are made; every other file
 * just calls them.
 * ---------------------------------------------------------------------- */

/** Can the current user manage the given userid's private area? */
function fm_can_access_private(string $userid): bool {
    global $USER;
    if (!is_logged_in()) {
        return false;
    }
    if (is_siteadmin($USER->userid)) {
        return true; // site admins can reach any user's private area
    }
    return (string) $USER->userid === (string) $userid;
}

/**
 * Basic sanity check for the Page files area - actual access is decided
 * by filemanager_view, and each mutation by its own filemanager_* ability
 * (see fm_is_able()). Deliberately does NOT require "editpage" - a
 * view-only user (filemanager_view but no other ability) must still be
 * able to reach Page files at all.
 */
function fm_can_access_page(string $pageid): bool {
    return is_logged_in() && $pageid !== '';
}

/**
 * Ability gate for Page files - filemanager_view controls the Page files /
 * Old files tabs (see index.php); filemanager_delete/upload/move/copy/
 * createfolder/edit each gate one state-changing action there (see
 * api.php). filemanager_migrate is unconditional - it always gates
 * migrating OUT of Old files, regardless of destination.
 *
 * My files (area=priv) has no gate beyond fm_can_access_private()
 * ownership - never call this unconditionally for a My files operation.
 *
 * Denies with no pageid, since there's nothing to scope to.
 */
function fm_is_able(string $ability, string $pageid): bool {
    global $USER;
    if (!is_logged_in()) {
        return false;
    }
    if (is_siteadmin($USER->userid)) {
        return true;
    }
    if ($pageid === '') {
        return false;
    }
    return (bool) user_is_able($USER->userid, $ability, (int) $pageid);
}

/**
 * VIEW permission for a "page" level share link - this is deliberately
 * separate from fm_can_access_page (which governs the filemanager dialog
 * itself, not links embedded in page content). Runs with whatever session
 * loaded the page containing the embedded file, which is usually a site
 * visitor, not the editor. Wire this to your real page-visibility rules
 * (published/draft, member-only pages, etc.) - this default just requires
 * the page id to be non-empty, i.e. treats every page as publicly
 * viewable. If some of your pages require login to view, add that check here.
 */
function fm_can_view_page(string $pageid): bool {
    global $CFG, $USER;

    if (is_siteadmin($USER->userid)) {
        return true;
    }
    return (bool) user_is_able($USER->userid, "viewpages", (int) $pageid);
}

/**
 * Single source of truth for "is this session allowed to use a share link
 * at this level" - used by filegate.php when actually serving a request,
 * and by fm_gated_url_to_path() below when resolving one back to a local
 * path instead. Keeping this in one place means the two can't drift apart.
 */
function fm_check_share_permission(string $level, string $area, string $id, string $extra): bool {
    if (!in_array($level, [FM_LEVEL_LINK, FM_LEVEL_PAGE, FM_LEVEL_PRIVATE], true)) {
        return false;
    }
    if ($level === FM_LEVEL_PRIVATE) {
        return $area === FM_AREA_PRIVATE && fm_can_access_private($id);
    }
    if ($level === FM_LEVEL_PAGE) {
        return $extra !== '' && fm_can_view_page($extra);
    }
    return true; // FM_LEVEL_LINK - never requires login
}

/**
 * Defensive normalization for URLs that may have picked up HTML-entity
 * encoding somewhere along the way (e.g. copied out of an href attribute's
 * rendered source, or generated before fm_share_url/fm_admin_preview_url
 * started forcing '&' explicitly) - '&amp;' is not a valid separator for
 * parse_str(), so without this, everything after the first query param
 * silently vanishes. None of the actual param values (area codes, ids,
 * hex tokens, sanitized paths) can legitimately contain the substring
 * '&amp;', so this is safe to apply unconditionally.
 */
function fm_normalize_gated_url(string $url): string {
    return str_replace('&amp;', '&', $url);
}

function fm_get_gated_files_from_path($folderurl, $extensions) {
    global $CFG;
    $gateUrl = $CFG->wwwroot . '/filegate.php';

    $localpath = fm_gated_url_to_path($folderurl);

    if ($localpath === null || !is_dir($localpath)) {
        return [];
    }

    $query = parse_url(fm_normalize_gated_url($folderurl), PHP_URL_QUERY);
    if (!$query) {
        return null;
    }
    parse_str($query, $q);

    $area = (string) ($q['a'] ?? '');
    $id   = (string) ($q['id'] ?? '');
    $lvl  = (string) ($q['lvl'] ?? '');
    $rel  = (string) ($q['p'] ?? '');
    $ex   = (string) ($q['ex'] ?? '');

    $files = getdirectoryfiles($localpath, $extensions);

    $urlarray = [];
    foreach ($files as $file) {
        $newrel = fm_sanitize_relpath($rel . DIRECTORY_SEPARATOR . $file);
        if ($newrel === null) {
            continue;
        }

        $urlarray[$file] = [
            "fileurl" => fm_share_url(
                $gateUrl,
                $lvl,
                $area,
                $id,
                $newrel,
                filemtime($localpath . DIRECTORY_SEPARATOR . $file),
                $ex
            ),
            "filename" => $file,
        ];
    }

    return $urlarray;
}
/**
 * Resolve a gated filegate.php URL back to a local filesystem path, IF the
 * current session has permission to view it and the token/mtime are valid
 * and unmodified. Returns null if the URL is malformed, tampered, stale
 * (the file changed since the link was generated), or the current session
 * isn't allowed to see it - never throws, so callers can treat null as a
 * plain "no access" result.
 *
 * The returned path may be a file OR a directory - check with is_dir() /
 * is_file() as needed; a folder-link resolves to the directory itself,
 * same as what filegate.php would list.
 *
 * Only ever resolves pub/priv area links. "Old files" entries are already
 * plain direct URLs (see fm_old_direct_url) and never go through
 * filegate.php or this function at all.
 */
function fm_gated_url_to_path(string $url): ?string {
    $query = parse_url(fm_normalize_gated_url($url), PHP_URL_QUERY);
    if (!$query) {
        return null;
    }
    parse_str($query, $q);

    $area = (string) ($q['a'] ?? '');
    $id   = (string) ($q['id'] ?? '');
    $rel  = (string) ($q['p'] ?? '');
    $mt   = isset($q['m']) ? (int) $q['m'] : -1;
    $tok  = (string) ($q['t'] ?? '');
    $lvl  = (string) ($q['lvl'] ?? '');
    $ex   = (string) ($q['ex'] ?? '');

    if (!in_array($area, [FM_AREA_PUBLIC, FM_AREA_PRIVATE], true) || $id === '' || $rel === '' || $tok === '' || $mt < 0) {
        return null;
    }
    $rel = fm_sanitize_relpath($rel);
    if ($rel === null || $rel === '') {
        return null;
    }

    // Permission check - identical logic to filegate.php.
    if ($lvl === '') {
        if ($mt === 0 || !fm_can_access_area($area, $id)) {
            return null; // admin-preview kind is never issued for folders
        }
    } elseif (!fm_check_share_permission($lvl, $area, $id, $ex)) {
        return null;
    }

    $full = fm_resolve_path($area, $id, $rel);
    if ($full === null) {
        return null;
    }

    if ($mt === 0) {
        // Folder link - evergreen, not pinned to a specific mtime.
        if (!is_dir($full) || !fm_verify_share_token($lvl, $area, $id, $rel, 0, $ex, $tok)) {
            return null;
        }
        return $full;
    }

    if (!is_file($full)) {
        return null;
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $GLOBALS['FM_ALLOWED_EXT'])) {
        return null; // same extension boundary filegate.php itself enforces
    }
    $actualMtime = filemtime($full);
    if ($actualMtime !== $mt) {
        return null; // stale - file has changed since the link was generated
    }
    $tokenOk = $lvl === ''
        ? fm_verify_admin_token($area, $id, $rel, $mt, $tok)
        : fm_verify_share_token($lvl, $area, $id, $rel, $mt, $ex, $tok);
    if (!$tokenOk) {
        return null;
    }

    return $full;
}

/**
 * Diagnostic sibling of fm_gated_url_to_path() - runs the exact same
 * checks but returns *why* it failed instead of a bare null. Only use
 * this in a throwaway debug script (it echoes things like the resolved
 * filesystem path and the expected token) - never wire it into anything
 * that responds to a request from someone you haven't already verified
 * has permission, since the whole point of fm_gated_url_to_path()
 * returning silent null is to not leak *why* access was denied.
 *
 * Usage:
 *   var_dump(fm_gated_url_diagnose($url));
 */
function fm_gated_url_diagnose(string $url): array {
    $result = ['ok' => false, 'reason' => '', 'details' => []];

    $normalized = fm_normalize_gated_url($url);
    $result['details']['had_html_entity_amp'] = $normalized !== $url;

    $query = parse_url($normalized, PHP_URL_QUERY);
    $result['details']['parsed_query'] = $query;
    if (!$query) {
        $result['reason'] = 'Could not find a query string in the URL at all - check it was passed in full, including everything after the ?.';
        return $result;
    }
    parse_str($query, $q);
    $result['details']['params'] = $q;

    $area = (string) ($q['a'] ?? '');
    $id   = (string) ($q['id'] ?? '');
    $rel  = (string) ($q['p'] ?? '');
    $mt   = isset($q['m']) ? (int) $q['m'] : -1;
    $tok  = (string) ($q['t'] ?? '');
    $lvl  = (string) ($q['lvl'] ?? '');
    $ex   = (string) ($q['ex'] ?? '');

    if (!in_array($area, [FM_AREA_PUBLIC, FM_AREA_PRIVATE], true)) {
        $result['reason'] = "'a' param is '$area', expected 'pub' or 'priv'.";
        return $result;
    }
    if ($id === '' || $rel === '' || $tok === '' || $mt < 0) {
        $result['reason'] = 'One of id / p / t is empty, or m is missing/negative.';
        return $result;
    }

    $relClean = fm_sanitize_relpath($rel);
    $result['details']['sanitized_path'] = $relClean;
    if ($relClean === null || $relClean === '') {
        $result['reason'] = "Path \"$rel\" failed fm_sanitize_relpath() - disallowed characters, traversal, or empty after cleanup.";
        return $result;
    }
    $rel = $relClean;

    if ($lvl === '') {
        $permOk = $mt !== 0 && fm_can_access_area($area, $id);
        $result['details']['permission_kind'] = 'admin-preview (no lvl param)';
    } else {
        $permOk = fm_check_share_permission($lvl, $area, $id, $ex);
        $result['details']['permission_kind'] = "share, level=$lvl";
        if ($lvl === FM_LEVEL_PAGE) {
            $result['details']['fm_can_view_page(ex)'] = fm_can_view_page($ex);
        }
        if ($lvl === FM_LEVEL_PRIVATE) {
            $result['details']['area_is_private'] = $area === FM_AREA_PRIVATE;
            $result['details']['fm_can_access_private(id)'] = fm_can_access_private($id);
        }
    }
    $result['details']['permission_ok'] = $permOk;
    if (!$permOk) {
        $result['reason'] = 'Permission check failed - see details.permission_kind and the fm_can_* result next to it above.';
        return $result;
    }

    $full = fm_resolve_path($area, $id, $rel);
    $result['details']['area_root'] = fm_area_root($area, $id);
    $result['details']['resolved_path'] = $full;
    if ($full === null) {
        $result['reason'] = "fm_resolve_path() returned null - \"$rel\" doesn't exist under details.area_root on THIS server/environment (or it resolves outside the area root). If this link was generated on a different server or a different \$CFG->fmroot, that alone explains it.";
        return $result;
    }

    if ($mt === 0) {
        $result['details']['is_dir'] = is_dir($full);
        if (!is_dir($full)) {
            $result['reason'] = "m=0 (folder link) but the resolved path is not a directory.";
            return $result;
        }
        $expected = fm_build_share_token($lvl, $area, $id, $rel, 0, $ex);
        $result['details']['expected_token'] = $expected;
        $result['details']['given_token'] = $tok;
        $result['details']['token_ok'] = hash_equals($expected, $tok);
        if (!$result['details']['token_ok']) {
            $result['reason'] = 'Token mismatch. Either the URL was edited/tampered with, or $CFG->fm_secret is different now than when this link was generated (different server/environment, secret rotated, or config.php not loaded consistently before fmconfig.php).';
            return $result;
        }
        $result['ok'] = true;
        $result['path'] = $full;
        return $result;
    }

    $result['details']['is_file'] = is_file($full);
    if (!is_file($full)) {
        $result['reason'] = 'm != 0 (file link) but the resolved path is not a file.';
        return $result;
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $result['details']['extension'] = $ext;
    $result['details']['extension_allowed'] = array_key_exists($ext, $GLOBALS['FM_ALLOWED_EXT']);
    if (!$result['details']['extension_allowed']) {
        $result['reason'] = "Extension '$ext' is not in \$GLOBALS['FM_ALLOWED_EXT'].";
        return $result;
    }
    $actualMtime = filemtime($full);
    $result['details']['url_mtime'] = $mt;
    $result['details']['actual_mtime'] = $actualMtime;
    if ($actualMtime !== $mt) {
        $result['reason'] = "Stale link - the file's mtime on disk ($actualMtime) doesn't match the mtime baked into the URL ($mt). The file was replaced/re-uploaded since this link was generated; a fresh link is needed.";
        return $result;
    }
    $expected = $lvl === '' ? fm_build_admin_token($area, $id, $rel, $mt) : fm_build_share_token($lvl, $area, $id, $rel, $mt, $ex);
    $result['details']['expected_token'] = $expected;
    $result['details']['given_token'] = $tok;
    $result['details']['token_ok'] = hash_equals($expected, $tok);
    if (!$result['details']['token_ok']) {
        $result['reason'] = 'Token mismatch. Either the URL was edited/tampered with, or $CFG->fm_secret is different now than when this link was generated.';
        return $result;
    }

    $result['ok'] = true;
    $result['path'] = $full;
    return $result;
}
