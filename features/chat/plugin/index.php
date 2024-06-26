<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author Philip Nicolcev
 * @copyright (c) Sebastian Tschan
 * @license Modified MIT License
 * @link https://blueimp.net/ajax/
 */
if (!isset($CFG)) {
    $sub = '../';
    while (!file_exists($sub . 'config.php')) {
        $sub .= '../';
    }
    include($sub . 'config.php'); 
}
include_once($CFG->dirroot . '/lib/header.php');
define('PAGEID', get_pageid());

// Suppress errors:
//error_reporting(0);

// Path to the chat directory:
define('AJAX_CHAT_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . '/');

// Include custom libraries and initialization code:
require(AJAX_CHAT_PATH . 'lib/custom.php');

// Include Class libraries:
require(AJAX_CHAT_PATH . 'lib/classes.php');

// Initialize the chat:
$ajaxChat = new CustomAJAXChat();
