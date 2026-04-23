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

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0");

if (isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip, ',' . get_ip_address() . ',')) {
    include($CFG->dirroot . $CFG->alternatepage);
} else {
    include_once($CFG->dirroot . '/lib/header.php');

    // Get User info
    load_user_cookie();
    update_user_cookie();

    $directory = $CFG->directory == '' ? 'root' : $CFG->directory;
    setcookie('directory', $directory, get_timestamp() + $CFG->cookietimeout, '/');
    $_SESSION['directory'] = $directory;

    unset($_COOKIE["pageid"]);
    unset($_SESSION['pageid']);

    $PAGE->title   = $CFG->sitename . " - Upgrade Check"; // Title of page
    $PAGE->name   = "Upgrade Check"; // Title of page
    $PAGE->description = "Page checks for site or feature updates"; // Description of page
    $PAGE->themeid = get_page_themeid($CFG->SITEID);

    if (!is_logged_in()) {
        // Start Page
        include('header.html');

        $url = "/upgrade.php";
        $content = fill_template("tmp/pagelib.template", "reroute_after_login", false, ["url" => $url]);
        echo simple_page($content, false, false);

        include('footer.html');
        die(); // End Page
    }

    if (!is_siteadmin($USER->userid)) {
        header('Location: ' . $CFG->wwwroot);
    }

    // Start Page
    include('header.html');
    
    // Check for upgrades or uninstalled components
    $content = upgrade_check();

    echo simple_page($content, false, false);

    // End Page
    include('footer.html');

    // Log
    log_entry("page", null, "Page View");
}
?>
