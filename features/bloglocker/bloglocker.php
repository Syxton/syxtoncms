<?php
/***************************************************************************
* bloglocker.php - Blog Locker Page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.2.6
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

    echo fill_template("tmp/page.template", "start_of_page_template");

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");
}

function bloglocker_settings() {
global $MYVARS, $CFG, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = "bloglocker";

	//Default Settings
	$default_settings = default_settings($feature, $pageid, $featureid);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { bloglocker_settings(); }
	}
}

function view_locker() {
global $MYVARS, $CFG, $USER;
	if (!defined('HTMLLIB')) { include_once('../html/htmllib.php');}
	$htmlid = clean_myvar_req("htmlid", "int");
	$pageid = clean_myvar_req("pageid", "int");
    if (!user_is_able($USER->userid, "viewbloglocker", $pageid)) {
        trigger_error(getlang("generic_permissions", false, ["viewbloglocker"]), E_USER_WARNING);
		return;
	}

	$row = get_html($htmlid);
	$settings = fetch_settings("html", $htmlid, $pageid);
	$comments = $settings->html->$htmlid->allowcomments->setting && user_is_able($USER->userid, "viewcomments", $pageid) ? get_html_comments($row['htmlid'], $pageid, true,10) : '';

	echo '
	<div style="width:100%;">
		' . $row['html'] . '
		<div id="comment_area_' . $htmlid . '">
		' . $comments . '
		</div>
	</div>';
}
?>