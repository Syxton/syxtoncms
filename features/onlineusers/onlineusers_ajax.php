<?php
/***************************************************************************
* onlineusers_ajax.php - Online Users ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 0.0.2
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); } 

if (!isset($ONLINEUSERSLIB)) { include_once($CFG->dirroot . '/features/onlineusers/onlineuserslib.php'); }

callfunction();

function run_lib_function() {
global $CFG, $MYVARS;
	
	$i=1;
	$args = array();
	while (isset($MYVARS->GET["var$i"])) {
		$args[$i] = $MYVARS->GET["var$i"];
		$i++;
	}

	echo call_user_func_array($MYVARS->GET["runthis"], $args);
}
?>