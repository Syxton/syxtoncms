<?php
/***************************************************************************
* addfeaturelib.php - Add Feature function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.9.4
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define('ADDFEATURELIB', true);

function display_addfeature($pageid, $area) {
	global $CFG, $USER, $ABILITIES;

	if (is_logged_in()) {
		if (user_is_able($USER->userid, 'addfeature', $pageid)) {
			$options = "";
			$SQL = fetch_template("dbsql/features.sql", "addable_features", false, ["issite" => ($pageid == $CFG->SITEID)]);
			if ($result = get_db_result($SQL, ["pageid" => $pageid])) {
				while ($row = fetch_row($result)) {
					$options .= fill_template("tmp/page.template", "select_options_template", false, ["value" => $row['feature'], "display" => $row['feature_title']]);
				}
			}

			ajaxapi([
				"id" => "addfeature_button",
				"if" => "$('#addfeaturelist').val() != ''",
				"url" => "/ajax/site_ajax.php",
				"paramlist" => "linkid, direction",
				"data" => [
					"action" => "addfeature",
					"pageid" => $pageid,
					"feature" => "js||$('#addfeaturelist').val()||js",
				],
				"ondone" => "go_to_page($pageid);",
			]);

			$content = fill_template("tmp/page.template", "display_addfeature_template", false, ["pageid" => $pageid, "options" => $options]);
			$title = "Add Features";
			$title = '<span class="box_title_text">' . $title . '</span>';
			return get_css_box($title, $content, "", NULL, "addfeature");
		}
	}
}
?>
