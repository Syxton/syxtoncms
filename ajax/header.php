<?php
/***************************************************************************
* header.php - Ajax header
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.2
***************************************************************************/

define('LIBHEADER', true);
if (session_status() === PHP_SESSION_NONE && !headers_sent()) { session_start(); }

if (!isset($CFG)) {
    $sub = '';
    while (!file_exists($sub . 'config.php')) {
        $sub .= '../';
    }
    include_once($sub . 'config.php');
}

$libs = ['ERRORSLIB', 'DBLIB', 'FILELIB', 'PAGELIB', 'SETTINGSLIB', 'COMLIB', 'ROLESLIB', 'RSSLIB', 'USERLIB', 'TIMELIB', 'STYLESLIB', 'HELPLIB'];
foreach ($libs as $lib) {
    if (!defined($lib)) {
        include_once($CFG->dirroot . '/lib/' . strtolower($lib) . '.php');
    }
}

if (!isset($USER)) { $USER = (object)[]; }
if (!isset($MYVARS)) { $MYVARS = (object)[]; }
if (!isset($ROLES)) { $ROLES = load_roles(); }

collect_vars();

if (!is_logged_in()) { load_user_cookie(); }

?>