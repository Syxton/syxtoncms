<?php
/***************************************************************************
* participantslib.php - Participants feature library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.4
***************************************************************************/

if (!LIBHEADER) {
	$sub = './';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == './' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php'); 
}
define('PARTICIPANTSLIB', true);

function display_participants($pageid, $area, $featureid) {
global $CFG, $USER, $ABILITIES;
    $feature = "participants";
    if (!$settings = fetch_settings($feature, $featureid, $pageid)) {
		save_batch_settings(default_settings($feature, $pageid, $featureid));
		$settings = fetch_settings($feature, $featureid, $pageid);
	}
	
	$title = $settings->$feature->$featureid->feature_title->setting;
  		
	if (is_logged_in()) {
		if (user_is_able($USER->userid, 'viewparticipants', $pageid)) {
            $content = make_modal_links(["title"=> stripslashes($title),"text"=> stripslashes($title),"path" => action_path("participants") . "view_participants&amp;pageid=$pageid&amp;featureid=$featureid", "width" => "400", "image" => $CFG->wwwroot . "/images/user.png", "styles" => "vertical-align: top;"]); 
			$buttons = get_button_layout("participants", $featureid, $pageid); 
			return get_css_box($title, $content, $buttons, NULL, "participants", $featureid);
		}
	}
}

function participants_delete($pageid, $featureid) {
	$params = [
		"pageid" => $pageid,
		"featureid" => $featureid,
		"feature" => "participants",
	];

	$SQL = use_template("dbsql/features.sql", $params, "delete_feature");
	execute_db_sql($SQL);
	$SQL = use_template("dbsql/features.sql", $params, "delete_feature_settings");
	execute_db_sql($SQL);

	resort_page_features($pageid);
}

function participants_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
	$returnme = "";
    return $returnme;
}

function participants_default_settings($type, $pageid, $featureid) {
	$settings = [
		[
			"setting_name" => "feature_title",
			"defaultsetting" => "Participants",
			"display" => "Feature Title",
			"inputtype" => "text",
		],
		[
			"setting_name" => "viewable_limit",
			"defaultsetting" => "25",
			"display" => "Viewable Limit",
			"inputtype" => "text",
			"numeric" => true,
			"validation" => "<=0",
			"warning" => "Must be greater than 0.",
		],
		[
			"setting_name" => "show_total",
			"defaultsetting" => "1",
			"display" => "Show Total",
			"inputtype" => "yes/no",
		],
	];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
	return $settings;
}
?>