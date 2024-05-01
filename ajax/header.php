<?php
/***************************************************************************
* header.php - Ajax header
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 0.1.2
***************************************************************************/
$LIBHEADER = true;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($CFG)) {
    $sub = '../';
    while (!file_exists($sub . 'config.php')) {
        $sub .= '../';
    }
    include_once($sub . 'config.php'); 
}
$libs = ['DBLIB', 'SETTINGSLIB', 'COMLIB', 'ROLESLIB', 'RSSLIB', 'PAGELIB', 'USERLIB', 'ERRORS', 'TIMELIB', 'FILELIB', 'STYLESLIB', 'HELP'];
foreach ($libs as $lib) {
    if (!isset($$lib)) {
        include_once($CFG->dirroot . '/lib/' . strtolower($lib) . '.php');
    }
}

if (!isset($ROLES)) { $ROLES = load_roles(); }
if (!is_logged_in()) { load_user_cookie(); }

?>