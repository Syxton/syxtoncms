<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */

// List containing the registered chat users:
if(!isset($CFG)){ include('../../../config.php'); }

include_once($CFG->dirroot . '/lib/header.php');
include_once($CFG->dirroot . '/features/chat/chatlib.php');

if(empty($PAGE)){ $PAGE = new stdClass(); }
$PAGE->id = isset($_GET['pageid']) ? $_GET['pageid'] : $CFG->SITEID;
$users = get_chat_users($PAGE->id);
?>