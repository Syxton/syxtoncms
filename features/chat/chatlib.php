<?php
/***************************************************************************
* chatlib.php - Chat function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.3.7
***************************************************************************/

if (!LIBHEADER) {
	$sub = './';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == './' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php'); 
}
define('CHATLIB', true);

function display_chat($pageid, $area, $featureid) {
global $CFG, $USER, $ROLES, $PAGE;
	
	//get settings or create default settings if they don't exist
	if (!$settings = fetch_settings("chat", $featureid, $pageid)) {
		save_batch_settings(default_settings("chat", $pageid, $featureid));
		$settings = fetch_settings("chat", $featureid, $pageid);
	}
	
	$title = $settings->chat->$featureid->feature_title->setting;
	$title = '<span class="box_title_text">' . $title . '</span>';
	if (!get_db_row("SELECT * FROM chat WHERE pageid=$pageid")) {
		execute_db_sql("INSERT INTO chat (pageid, name) VALUES($pageid, 'Chat Room')");
	}
	
	$content = get_js_tags(["scripts/frame_resize.js"]);

	if ((is_logged_in() && user_is_able($USER->userid, "chat", $pageid)) || (!is_logged_in() && role_is_able($ROLES->visitor,"chat", $pageid))) {
        $styles=get_styles($pageid, $PAGE->themeid);
		if ($area == "middle") { 
			$content .= '<div style="width:100%;"><iframe id="myframe" onload="resizeCaller();" src="' . $CFG->wwwroot . '/features/chat/plugin/index.php?pageid=' . $pageid . '" frameborder="0" style="background-color:' . $styles['contentbgcolor'] . ';overflow:hidden;height:500px;width:100%;"></iframe></div>';
		} else { 
			$content .= '<span class="centered_span">Cannot be used as a side panel.</span>'; 
        }
	} else {
		$content .= '<span class="centered_span">You do not have permission to join this chat.</span>';
	}
	$buttons = is_logged_in() ? get_button_layout("chat", $featureid, $pageid) : ""; 
	return "<style>.chatbox{ padding:0 !important;}</style>" . get_css_box($title, $content, $buttons,NULL,"chat", $featureid, false, false, false, false, false, false,"chatbox");
}

function chat_delete($pageid, $featureid) {
	$params = [
		"pageid" => $pageid,
		"featureid" => $featureid,
		"feature" => "chat",
	];

	$SQL = use_template("dbsql/features.sql", $params, "delete_feature");
	execute_db_sql($SQL);
	$SQL = use_template("dbsql/features.sql", $params, "delete_feature_settings");
	execute_db_sql($SQL);
	$SQL = use_template("dbsql/chat.sql", $params, "delete_chat", "chat");
	execute_db_sql($SQL);

	resort_page_features($pageid);
}

function chat_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
	$returnme = "";
    return $returnme;
}

function chat_default_settings($type, $pageid, $featureid) {
	$settings = [
		[
			"setting_name" => "feature_title",
			"defaultsetting" => "Chat",
			"display" => "Feature Title",
			"inputtype" => "text",
		],
	];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
	return $settings;
}

function get_course_channels($pageid) {
global $CFG;
	$channels_array = [];
	if ($channels = get_db_result("SELECT * FROM chat WHERE pageid='$pageid'")) {
  		while ($channel = fetch_row($channels)) {
			$channels_array[$channel['channel_id']] = $channel['name'];
  		}		 
	}
	return $channels_array;
}

function get_chat_users($pageid) {
global $CFG;
    $i=0; $channel_list=""; 
    
    if ($channels = get_db_result("SELECT * FROM chat WHERE pageid='$pageid'")) {
  		while ($channel = fetch_row($channels)) {
  			$channel_list .= $channel_list == "" ? $channel["channel_id"] : "," . $channel["channel_id"];	
  		}        
    }
   
	if ($users_list = users_that_have_ability_in_page("chat", $pageid)) {
  		while ($user = fetch_row($users_list)) {
  			$users[$i]['userRole'] = user_is_able($user["userid"], "moderate", $pageid) ? 2:0;
  			$users[$i]['userName'] = substr($user["fname"],0,1) . "." . $user["lname"];
  			$users[$i]['password'] = "";
  			$users[$i]['channels'] = explode(",", $channel_list);
  			$i++;
  		}		 
	}

    return $users;
}
?>