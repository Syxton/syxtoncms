<?php
/***************************************************************************
* participants.php - View page participants
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.5
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

    if (!defined('HTMLLIB')) { include_once($CFG->dirroot . '/features/html/htmllib.php'); }

	echo fill_template("tmp/page.template", "start_of_page_template");

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");
}

function view_participants() {
global $MYVARS, $CFG, $USER;
	$pageid = clean_myvar_req("pageid", "int");
    $featureid = clean_myvar_req("featureid", "int");

    $feature = "participants";
    if (!$settings = fetch_settings($feature, $featureid, $pageid)) {
		save_batch_settings(default_settings($feature, $pageid, $featureid));
		$settings = fetch_settings($feature, $featureid, $pageid);
	}

	$limit = $settings->$feature->$featureid->viewable_limit->setting;
    $show_total = $settings->$feature->$featureid->show_total->setting;

    if (!user_is_able($USER->userid, "viewparticipants", $pageid)) { trigger_error(error_string("no_permission", ["viewparticipants"]), E_USER_WARNING); return; }

    $SQL = "SELECT fname, lname, display_name FROM roles_assignment ra JOIN users u ON u.userid=ra.userid JOIN roles r ON r.roleid = ra.roleid WHERE ra.pageid='$pageid' AND ra.confirm=0 ORDER BY r.display_name,u.lname";
	if ($results = get_db_result($SQL . " LIMIT $limit")) {
        if ($show_total) {
			$total = get_db_count($SQL);
			echo "<div style='text-align:center;'><strong>Total:</strong> $total</div>";
		}
		echo '
			<table style="border-collapse:collapse;width:100%">
				<tr>
					<td style="width:50%;padding:5px;white-space:nowrap">
						' . icon("user") . ' <strong>Name</strong>
					</td>
					<td style="width:5%;">
					</td>
					<td style="width:45%;text-align:center;white-space:nowrap;padding:5px;">
						' . icon("key") . ' <strong>Page Role</strong>
					</td>
				</tr>';

		$toggle=true;
		while ($row = fetch_row($results)) {
			$color = $toggle ? "#FAFAFA" : "#F2F2F2";
			$toggle = $toggle ? false : true;

			echo '<tr style="background-color:' . $color . '"><td style="width:50%;padding:5px;white-space:nowrap">' . $row["fname"] . ' ' . $row["lname"] . '</td><td style="float:left;width:5%;"></td><td style="width:45%;text-align:center;white-space:nowrap;padding:5px;">' . $row["display_name"] . '</td></tr>';
		}
		echo '</table>';
	}
}

function participants_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = "participants";

	//Default Settings
	$default_settings = default_settings($feature, $pageid, $featureid);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { participants_settings(); }
	}
}
?>