<?php
/***************************************************************************
* header.php - Includes all important lib files and collects variables
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/02/2025
* Revision: 0.2.5
***************************************************************************/
define('LIBHEADER', true);
if (session_status() === PHP_SESSION_NONE && !headers_sent()) { session_start(); }

if (!isset($CFG)) {
    $sub = '';
    while (!file_exists($sub . 'config.php')) {
        $sub .= '../';
    }
    require_once($sub . 'config.php');
}

$libs = ['ERRORSLIB', 'DBLIB', 'FILELIB', 'PAGELIB', 'SETTINGSLIB', 'COMLIB', 'ROLESLIB', 'RSSLIB', 'USERLIB', 'TIMELIB', 'STYLESLIB', 'HELPLIB', 'LANGLIB', 'PAYLIB'];
foreach ($libs as $lib) {
    if (!defined($lib)) {
        require_once($CFG->dirroot . '/lib/' . strtolower($lib) . '.php');
    }
}

if (!isset($USER)) { $USER = (object)[]; }
if (!isset($MYVARS)) { $MYVARS = (object)[]; }
if (!isset($ROLES)) { $ROLES = load_roles(); }

collect_vars();

if (!is_logged_in()) { load_user_cookie(); }

?>