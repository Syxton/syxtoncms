<?php
/***************************************************************************
* chat.php - Chat modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.6
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

	if (!isset($CHATLIB)) { include_once($CFG->dirroot . '/features/chat/chatlib.php'); }

    echo fill_template("tmp/page.template", "start_of_page_template");

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");
}


function chat_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = "chat";

	//Default Settings
	$default_settings = default_settings($feature, $pageid, $featureid);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { chat_settings(); }
	}
}
?>