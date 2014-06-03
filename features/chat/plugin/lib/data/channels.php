<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// List containing the custom channels:
if(!isset($CFG)){ include('../../../config.php'); }

include_once($CFG->dirroot . '/lib/header.php');
include_once($CFG->dirroot . '/features/chat/chatlib.php');
$pageid = !empty($_GET['pageid']) ? $_GET['pageid'] : (!empty($_SESSION["pageid"]) ? $_SESSION["pageid"] : (!empty($PAGE->id) ? $PAGE->id : $CFG->SITEID));
$_SESSION["pageid"] = $pageid;
$channels = get_course_channels($pageid);

?>