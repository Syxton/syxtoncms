<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) 2007 Sebastian Tschan
 * @license http://creativecommons.org/licenses/by-sa/
 * @link https://blueimp.net/ajax/
 */
if(!isset($CFG)) include('../../../config.php');
include_once($CFG->dirroot . '/lib/header.php');

// Path to the chat directory:
define('AJAX_CHAT_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');
if(!empty($_GET["pageid"])){
    define('PAGEID', $_GET["pageid"]);    
}elseif(!empty($_POST["pageid"])){
    define('PAGEID', $_POST["pageid"]);    
}elseif(!empty($_SESSION["pageid"])){
    define('PAGEID', $_SESSION["pageid"]);    
}     

if(is_logged_in()){
    define('USERNAME', substr($USER->fname,0,1) . "." . $USER->lname);
    define('MYPASSWORD', $USER->email);
    define('USERID', $USER->userid);
}else{
	$id = rand(1,10000);
	define('USERNAME', "Guest" . $id);
	define('MYPASSWORD', "");
	define('USERID', $id);
}

// Include custom libraries and initialization code:
require(AJAX_CHAT_PATH.'lib/custom.php');

// Include Class libraries:
require(AJAX_CHAT_PATH.'lib/classes.php');

// Initialize the chat:
$ajaxChat = new CustomAJAXChat();
?>