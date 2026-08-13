<?php
/***************************************************************************
* plugins/filemanager/index.php - the file manager dialog itself.
*
* Works two ways:
*  - Opened by plugin.js inside TinyMCE's dialog iframe, with pageid +
*    userid in the query string - Insert/Cancel post messages back to the
*    editor (app.js detects this via window.parent !== window).
*  - Opened directly in a browser tab/window (linked to from anywhere else
*    in the app) - Insert/Cancel are hidden automatically, everything else
*    (browse/upload/rename/delete/move/copy link) still works.
***************************************************************************/
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

if (!is_logged_in()) {
    http_response_code(403);
    exit('Forbidden');
}

global $USER;
$pageid = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['pageid'] ?? '');
// Default to the logged-in user's own id when opened without a userid
// (e.g. a direct link, or the standalone/non-TinyMCE case) so "My files"
// always works for whoever is logged in.
$userid = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['userid'] ?? (string) $USER->userid);
// 'type' narrows what the picker returns to TinyMCE's native dialogs
// (image / media / file) - '' means "anything allowed".
$type = preg_replace('/[^a-z]/', '', $_GET['type'] ?? '');

$canPublic  = $pageid !== '' && fm_can_access_page($pageid);
$canPrivate = $userid !== '' && fm_can_access_private($userid);
// Same ownership permission as private - just a different (legacy)
// physical location. Shown whenever My files is, even if that user
// happens to have no old files yet (browsing then just shows "empty").
$canOld = $canPrivate;

if (!$canPublic && !$canPrivate) {
    http_response_code(403);
    exit('Forbidden');
}

if (empty($_SESSION['fm_csrf'])) {
    $_SESSION['fm_csrf'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>File Manager</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div id="fm-root"
     data-pageid="<?php echo htmlspecialchars($pageid, ENT_QUOTES); ?>"
     data-userid="<?php echo htmlspecialchars($userid, ENT_QUOTES); ?>"
     data-can-public="<?php echo $canPublic ? '1' : '0'; ?>"
     data-can-private="<?php echo $canPrivate ? '1' : '0'; ?>"
     data-can-old="<?php echo $canOld ? '1' : '0'; ?>"
     data-type="<?php echo htmlspecialchars($type, ENT_QUOTES); ?>"
     data-csrf="<?php echo htmlspecialchars($_SESSION['fm_csrf'], ENT_QUOTES); ?>"
     data-api="api.php">
</div>
<script src="app.js"></script>
</body>
</html>
