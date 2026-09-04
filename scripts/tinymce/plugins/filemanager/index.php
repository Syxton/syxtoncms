<?php
/***************************************************************************
* plugins/filemanager/index.php - the file manager dialog itself.
*
* Opened by plugin.js's picker (an overlay iframe, pageid+userid+embed=1 in
* the query string, posts Insert/Cancel back to the editor - see plugin.js)
* or directly in a browser tab (no embed=1, Insert/Cancel hidden, browsing
* still works).
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
// Defaults to the logged-in user's own id so "My files" works even
// without a userid in the query string (e.g. a direct link).
$userid = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['userid'] ?? (string) $USER->userid);
// Narrows the picker for TinyMCE's native image/media/link dialogs; '' = anything.
$type = preg_replace('/[^a-z]/', '', $_GET['type'] ?? '');
$embed = !empty($_GET['embed']); // set only by plugin.js's own picker
// Set only when opened by the HTML feature (see tmp/pagelib.template) -
// gates the Gallery/Index folder-link option (see filter_photogallery() in htmllib.php).
$allowGallery = !empty($_GET['gallery']);

$canView = fm_is_able('filemanager_view', $pageid);

// My files: any logged-in owner, no ability gate. Page files/Old files:
// also require filemanager_view or the tab isn't shown (see fmconfig.php).
$canPublic  = $pageid !== '' && $canView && fm_can_access_page($pageid);
$canPrivate = $userid !== '' && fm_can_access_private($userid);
$canOld = $canPrivate && $canView && !fm_old_is_empty($userid); // same ownership check, legacy location

// Per-action abilities, Page files only (see fm_is_able()) - api.php
// enforces these; here they just drive which buttons app.js shows.
$canDeleteAbility       = fm_is_able('filemanager_delete', $pageid);
$canUploadAbility       = fm_is_able('filemanager_upload', $pageid);
$canMoveAbility         = fm_is_able('filemanager_move', $pageid);
$canCopyAbility         = fm_is_able('filemanager_copy', $pageid);
$canCreateFolderAbility = fm_is_able('filemanager_createfolder', $pageid);
$canMigrateAbility      = fm_is_able('filemanager_migrate', $pageid);
$canEditAbility         = fm_is_able('filemanager_edit', $pageid);

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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
     data-can-delete="<?php echo $canDeleteAbility ? '1' : '0'; ?>"
     data-can-upload="<?php echo $canUploadAbility ? '1' : '0'; ?>"
     data-can-move="<?php echo $canMoveAbility ? '1' : '0'; ?>"
     data-can-copy="<?php echo $canCopyAbility ? '1' : '0'; ?>"
     data-can-createfolder="<?php echo $canCreateFolderAbility ? '1' : '0'; ?>"
     data-can-migrate="<?php echo $canMigrateAbility ? '1' : '0'; ?>"
     data-can-edit="<?php echo $canEditAbility ? '1' : '0'; ?>"
     data-type="<?php echo htmlspecialchars($type, ENT_QUOTES); ?>"
     data-embed="<?php echo $embed ? '1' : '0'; ?>"
     data-allow-gallery="<?php echo $allowGallery ? '1' : '0'; ?>"
     data-csrf="<?php echo htmlspecialchars($_SESSION['fm_csrf'], ENT_QUOTES); ?>"
     data-api="api.php">
</div>
<script src="app.min.js"></script>
</body>
</html>
