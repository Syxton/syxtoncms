<?php
/***************************************************************************
* header.php - Page header
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 0.1.1
***************************************************************************/
$LIBHEADER = true;
if (session_status() === PHP_SESSION_NONE) {session_start();}
if (!isset($CFG)) 	include_once('../config.php');
if (!isset($DBLIB)) { include_once($CFG->dirroot.'/lib/dblib.php'); }
if (!isset($SETTINGSLIB)) { include_once($CFG->dirroot.'/lib/settingslib.php'); }
if (!isset($COMLIB)) { include_once($CFG->dirroot.'/lib/comlib.php'); }
if (!isset($ROLESLIB)) { include_once($CFG->dirroot.'/lib/roleslib.php'); }
if (!isset($RSSLIB)) { include_once($CFG->dirroot.'/lib/rsslib.php'); }
if (!isset($PAGELIB)) { include_once($CFG->dirroot.'/lib/pagelib.php'); }
if (!isset($USERLIB)) { include_once($CFG->dirroot.'/lib/userlib.php'); }
if (!isset($ERRORS)) { include_once($CFG->dirroot.'/lib/errors.php'); }
if (!isset($TIMELIB)) { include_once($CFG->dirroot.'/lib/timelib.php'); }
if (!isset($FILELIB)) { include_once($CFG->dirroot.'/lib/filelib.php'); }
if (!isset($STYLESLIB)) { include_once($CFG->dirroot.'/lib/styleslib.php'); }
if (!isset($HELP)) { include_once($CFG->dirroot.'/lib/help.php'); }
if (!isset($ROLES)) $ROLES = load_roles();
if (!is_logged_in()) { load_user_cookie();}
?>