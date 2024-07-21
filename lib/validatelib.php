<?php
/***************************************************************************
* validatelib.php - Validatation script library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.4
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define("VALIDATELIB", true);

function create_validation_script($formname, $code, $ajax=false) {
global $CFG;
	$params = [
		"formname" => $formname,
		"code" => $code,
	];
	$return = fill_template("tmp/pagelib.template", "validation_tooltip_actions", false, $params);

    if ($ajax) {
		 return $return;
	} else {
		 return js_code_wrap($return, "defer", true);
	}
}
?>
