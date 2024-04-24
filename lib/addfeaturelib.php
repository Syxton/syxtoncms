<?php
/***************************************************************************
* addfeaturelib.php - Add Feature function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/28/2021
* Revision: 0.9.4
***************************************************************************/

$ADDFEATURELIB = true;
include('header.php');
//ADDFEATURELIB Config

function display_addfeature($pageid, $area) {
	global $CFG, $USER, $ABILITIES;

	if (is_logged_in()) {
		if (user_has_ability_in_page($USER->userid, 'addfeature', $pageid)) {
			$options = "";
			$SQL = template_use("dbsql/features.sql", ["pageid" => $pageid, "issite" => ($pageid == $CFG->SITEID)], "addable_features");
			if ($result = get_db_result($SQL)) {
				while ($row = fetch_row($result)) {
					$options .= template_use("tmp/page.template", ["value" => $row['feature'], "display" => $row['feature_title']], "select_options_template");
				}
			}
			$content = template_use("tmp/page.template", ["pageid" => $pageid, "options" => $options], "display_addfeature_template");
			$title = "Add Features";
			return get_css_box($title, $content, NULL, NULL, "addfeature");
		}
	}
}
?>
