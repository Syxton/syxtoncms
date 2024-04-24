<?php
/***************************************************************************
 * index.php
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/
if (!isset($CFG)) {
    include_once('config.php');
}

if (isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip, ',' . $_SERVER['REMOTE_ADDR'] . ',')) {
    header('Cache-Control: max-age=86400'); // HTTP 1.1.
    header('Expires: 0'); // Proxies.
    include($CFG->dirroot . $CFG->alternatepage);
} else {
    include_once($CFG->dirroot . '/lib/header.php');

    //Get User info
    load_user_cookie();
    update_user_cookie();

    $directory = $CFG->directory == '' ? 'root' : $CFG->directory;
    setcookie('directory', $directory, get_timestamp() + $CFG->cookietimeout, '/');
    $_SESSION['directory'] = $directory;

    unset($_COOKIE["pageid"]);
    unset($_SESSION['pageid']);
    $pageid = get_pageid();
    setcookie('pageid', $pageid, get_timestamp() + $CFG->cookietimeout, '/');
    $_SESSION['pageid'] = $pageid;
    $currentpage = get_db_row("SELECT * FROM pages WHERE pageid='$pageid'");

    $PAGE->title   = $CFG->sitename . " - " . $currentpage["name"]; // Title of page
    $PAGE->name   = $currentpage["name"]; // Title of page
    $PAGE->description = $currentpage["description"]; // Descriptoin of page
    $PAGE->themeid = get_page_themeid($pageid);
    
    header('Cache-Control: max-age=86400'); // HTTP 1.1.
    header('Expires: 0'); // Proxies.

    //Use this page only to keep session and cookies refreshed (during forms)
    if (!empty($_GET['keepalive'])) {
        header("Refresh:30");
        die();
    }

    //Start Page
    include('header.html');

    //Cache roles
    $ROLES = load_roles();

    //Check for upgrades or uninstalled components
    upgrade_check();

    if (is_logged_in()) {
        $params = ["timeout" => 14599]; // Javascript that checks for valid login every x seconds.
        echo template_use("tmp/index.template", $params, "valid_login_check");

        $ABILITIES = get_user_abilities($USER->userid, $PAGE->id);
        if (empty($ABILITIES->viewpages->allow)) {
            if ($currentpage["opendoorpolicy"] == "0") {
                $PAGE->id = $CFG->SITEID;
            }
        }
    } else {
        $ABILITIES = get_role_abilities($ROLES->visitor, $PAGE->id);
        if (!($currentpage["siteviewable"] && !empty($ABILITIES->viewpages->allow))) {
            if ($currentpage["opendoorpolicy"] == "0") {
                $PAGE->id = $CFG->SITEID;
            }
        }
    }

    // Main Layout
    $params = [ "mainmast" => page_masthead(true),
                "sidemast" => page_masthead(false),
                "sidecontents" => get_page_contents($PAGE->id, 'side'),
                "middlecontents" => get_page_contents($PAGE->id, 'middle'),
    ];

    echo template_use("tmp/index.template", $params, "mainlayout_template");

    // End Page
    include('footer.html');

    // Log
    log_entry("page", null, "Page View");
}
?>
