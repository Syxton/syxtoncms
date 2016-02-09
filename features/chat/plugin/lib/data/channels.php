<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) Sebastian Tschan
 * @license Modified MIT License
 * @link https://blueimp.net/ajax/
 */

// List containing the custom channels:
//$channels = array();

// Sample channel list:
//$channels[0] = 'Public';
//$channels[1] = 'Private';

if(!isset($CFG)){ include('../../../config.php'); }
include_once($CFG->dirroot . '/lib/header.php');
include_once($CFG->dirroot . '/features/chat/chatlib.php');
$channels = get_course_channels($_COOKIE["pageid"]);
?>