<?php
/***************************************************************************
* plugins/filemanager/index.php - the file manager dialog itself.
*
* Works two ways:
*  - Opened by plugin.js's picker: an iframe pointing here, inside an
*    overlay plugin.js appends directly to the host page's <body> (not a
*    TinyMCE dialog, not a window.open() popup - see plugin.js's file
*    header for why). pageid + userid + embed=1 are in the query string;
*    Insert/Cancel post messages back to the editor's page. app.js only
*    enables this when BOTH embed=1 is present AND it can actually see a
*    parent window to post to - neither alone is enough, since a copied
*    query string or an unrelated iframe embedding elsewhere in the app
*    could otherwise be mistaken for a picker session.
*  - Opened directly in a browser tab/window (linked to from anywhere else
*    in the app, no embed=1) - Insert/Cancel are hidden automatically,
*    everything else (browse/upload/rename/delete/move/copy link) still
*    works.
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
// Only plugin.js's own picker sets this - see the comment above.
$embed = !empty($_GET['embed']);
// Only set by plugin.js when the editor was opened by the HTML feature
// (see tmp/pagelib.template / filemanager_allow_gallery) - gates whether
// the Gallery/Index folder-link option is shown at all, since the
// "gallery" attribute it adds is only understood by that feature's
// photogallery filter (see filter_photogallery() in htmllib.php).
$allowGallery = !empty($_GET['gallery']);

$canView = fm_is_able('filemanager_view', $pageid);

// My files: always available to any logged-in user who owns it - no
// ability gate, only ownership (fm_can_access_private). Page files / Old
// files: additionally require filemanager_view, or the tab isn't shown at
// all - see fm_is_able()'s docblock in fmconfig.php.
$canPublic  = $pageid !== '' && $canView && fm_can_access_page($pageid);
$canPrivate = $userid !== '' && fm_can_access_private($userid);
// Same ownership permission as private - just a different (legacy)
// physical location. Shown whenever My files is (and filemanager_view is
// held), even if that user happens to have no old files yet (browsing
// then just shows "empty").
$canOld = $canPrivate && $canView;

// Per-action abilities, all scoped to Page files only (see fm_is_able()).
// api.php is the actual enforcement point; these just drive which buttons
// app.js shows. Computed regardless of $canPublic - harmless either way,
// since area=pub is unreachable in the UI without it.
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
<script src="app.js"></script>
</body>
</html>
