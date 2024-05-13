<?php
/***************************************************************************
* onlineusers_ajax.php - Online Users ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.2
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

if (!defined('ONLINEUSERSLIB')) { include_once($CFG->dirroot . '/features/onlineusers/onlineuserslib.php'); }

callfunction();

function run_lib_function() {
global $CFG, $MYVARS;
	
	$i = 1;
	$args = [];
	while (isset($MYVARS->GET["var$i"])) {
		$args[$i] = $MYVARS->GET["var$i"];
		$i++;
	}

	echo call_user_func_array($MYVARS->GET["runthis"], $args);
}
?>