<?php
/***************************************************************************
* participantslib.php - Participants feature library
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
            $content = make_modal_links(["title"=> stripslashes($title),"text"=> stripslashes($title),"path" => action_path("participants") . "view_participants&amp;pageid=$pageid&amp;featureid=$featureid", "width" => "400", "icon" => icon("user"), "styles" => "vertical-align: top;"]);
			$buttons = get_button_layout("participants", $featureid, $pageid);
			$title = '<span class="box_title_text">' . $title . '</span>';
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

	try {
		start_db_transaction();
		$sql = [];
		$sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature"];
		$sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature_settings"];

		// Delete feature
		execute_db_sqls(fetch_template_set($sql), $params);

		resort_page_features($pageid);
		commit_db_transaction();
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
		return false;
	}
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