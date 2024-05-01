<?php
/***************************************************************************
* participantslib.php - Participants feature library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2010
* Revision: 0.0.4
***************************************************************************/

if (!isset($LIBHEADER)) {
	$sub = './';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == './' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php'); 
}
$PARTICIPANTSLIB = true;

function display_participants($pageid, $area, $featureid) {
global $CFG, $USER, $ABILITIES;
    $feature = "participants";
    if (!$settings = fetch_settings($feature, $featureid, $pageid)) {
		make_or_update_settings_array(default_settings($feature, $pageid, $featureid));
		$settings = fetch_settings($feature, $featureid, $pageid);
	}
	
	$title = $settings->$feature->$featureid->feature_title->setting;
    	
	if (is_logged_in()) {
		if (user_has_ability_in_page($USER->userid, 'viewparticipants', $pageid)) {
            $content = make_modal_links(array("title"=> stripslashes($title),"text"=> stripslashes($title),"path" => $CFG->wwwroot . "/features/participants/participants.php?action=view_participants&amp;pageid=$pageid&amp;featureid=$featureid","width" => "400","image" => $CFG->wwwroot . "/images/user.png","styles" => "vertical-align: top;")); 
			$buttons = get_button_layout("participants", $featureid, $pageid); 
			return get_css_box($title, $content, $buttons,NULL,"participants", $featureid);
		}
	}
}

function participants_delete($pageid, $featureid) {
	$params = [
		"pageid" => $pageid,
		"featureid" => $featureid,
		"feature" => "participants",
	];

	$SQL = template_use("dbsql/features.sql", $params, "delete_feature");
	execute_db_sql($SQL);
	$SQL = template_use("dbsql/features.sql", $params, "delete_feature_settings");
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
			"type" => "$type",
			"pageid" => "$pageid",
			"featureid" => "$featureid",
			"setting_name" => "feature_title",
			"setting" => "Participants",
			"extra" => false,
			"defaultsetting" => "Participants",
			"display" => "Feature Title",
			"inputtype" => "text",
		],
		[
			"type" => "$type",
			"pageid" => "$pageid",
			"featureid" => "$featureid",
			"setting_name" => "viewable_limit",
			"setting" => "25",
			"extra" => false,
			"defaultsetting" => "25",
			"display" => "Viewable Limit",
			"inputtype" => "text",
			"numeric" => true,
			"validation" => "<=0",
			"warning" => "Must be greater than 0.",
		],
		[
			"type" => "$type",
			"pageid" => "$pageid",
			"featureid" => "$featureid",
			"setting_name" => "show_total",
			"setting" => "1",
			"extra" => false,
			"defaultsetting" => "1",
			"display" => "Show Total",
			"inputtype" => "yes/no",
		],
	];

	return $settings;
}
?>