<?php
/***************************************************************************
 * index.php
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

if (!isset($CFG)) {
    include_once('config.php');
}

if (isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip, ',' . $_SERVER['REMOTE_ADDR'] . ',')) {
    include($CFG->dirroot . $CFG->alternatepage);
} else {
    include_once($CFG->dirroot . '/lib/header.php');

    //Get User info
    load_user_cookie();
    update_user_cookie();

    $directory = $CFG->directory == '' ? 'root' : $CFG->directory;
    setcookie('directory', $directory, get_timestamp() + $CFG->cookietimeout, '/');
    $_SESSION['directory'] = $directory;

    setcookie('pageid', $PAGE->id, get_timestamp() + $CFG->cookietimeout, '/');
    $_SESSION['pageid'] = $PAGE->id;
    $currentpage = get_db_row("SELECT * FROM pages WHERE pageid='$PAGE->id'");

    $PAGE->title   = $CFG->sitename . " - " . $currentpage["name"]; // Title of page
    $PAGE->name   = $currentpage["name"]; // Title of page
    $PAGE->description = $currentpage["description"]; // Descriptoin of page
    $PAGE->themeid = get_page_themeid($PAGE->id);

    //Use this page only to keep session and cookies refreshed (during forms)
    if (!empty($_GET['keepalive'])) {
        header("Refresh:30");
        echo rand();
        die();
    }

    //Check for upgrades or uninstalled components
    upgrade_check();

    //Cache roles
    $ROLES = load_roles();
echo "MAKE TAG";
    echo get_js_set("main");
    echo get_js_set("main");
}
?>
