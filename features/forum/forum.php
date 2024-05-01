<?php
/***************************************************************************
* forum.php - Forum modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/25/2014
* Revision: 0.2.8
***************************************************************************/

if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); }
    if (!isset($FORUMLIB)) { include_once($CFG->dirroot . '/features/forum/forumlib.php'); }

    callfunction();

    echo get_editor_javascript();

    echo '</body></html>';
}

function forum_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "forum";

	//Default Settings
	$default_settings = default_settings($feature, $pageid, $featureid);
	$setting_names = get_setting_names($default_settings);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($setting_names, $settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (make_or_update_settings_array($default_settings)) { forum_settings(); }
	}
}

function createforumcategory() {
global $MYVARS, $CFG, $USER;
	if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$forumid = dbescape($MYVARS->GET['forumid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$title = "";

	if (isset($MYVARS->GET["catid"])) {
        if (!user_has_ability_in_page($USER->userid,"editforumcategory", $pageid)) { echo get_page_error_message("no_permission",array("editforumcategory")); return; }
		$category = get_db_row("SELECT * FROM forum_categories WHERE catid=" . dbescape($MYVARS->GET["catid"]));
		$title = $category["title"];
	} else {
        if (!user_has_ability_in_page($USER->userid,"createforumcategory", $pageid)) { echo get_page_error_message("no_permission",array("createforumcategory")); return; }
		if (!user_has_ability_in_page($USER->userid,"createforumcategory", $pageid)) { echo get_error_message("generic_permissions"); return;}
	}

	if (isset($MYVARS->GET["catid"])) { echo create_validation_script("new_category_form" , 'ajaxapi(\'/features/forum/forum_ajax.php\',\'edit_category\',\'&catid=' . dbescape($MYVARS->GET["catid"]) . '&catname=\'+escape(document.getElementById(\'catname\').value),function() { simple_display(\'category_div\');}); close_modal();');
	} else { echo create_validation_script("new_category_form" , 'ajaxapi(\'/features/forum/forum_ajax.php\',\'create_category\',\'&forumid=' . $forumid . '&pageid=' . $pageid . '&catname=\'+escape(document.getElementById(\'catname\').value),function() { simple_display(\'category_div\');}); close_modal();');}

	echo '
	<div class="formDiv" id="category_div">
		<input id="hiddenusername" type="hidden" /><input id="hiddenpassword" type="hidden" />
		<form id="new_category_form">
			<fieldset class="formContainer">
				<div class="rowContainer">
					<label for="catname">Category Name</label><input type="text" id="catname" name="catname" value="' . $title . '" data-rule-required="true" /><div class="tooltipContainer info">' . get_help("new_category") . '</div><br />
				</div>
		  		<input class="submit" name="submit" type="submit" value="Submit" />
			</fieldset>
		</form>
	</div>';
}

function show_forum_editor() {
global $CFG, $MYVARS;
	$postid = $MYVARS->GET["postid"]; $quote = $MYVARS->GET["quote"]; $edit = $MYVARS->GET["edit"];
	$discussion = get_db_row("SELECT * FROM forum_discussions WHERE discussionid IN (SELECT discussionid FROM forum_posts WHERE postid=$postid)");
	$forumid = $discussion['forumid'];
	$pagenum = $MYVARS->GET["pagenum"];
	if ($edit == 1) {
		$value = get_db_field("message", "forum_posts", "postid='$postid'");
	} else {
		$value = "";
	}
	echo '<img id="edit_area_' . $forumid . '" name="edit_area_' . $forumid . '" src="' . $CFG->wwwroot . '/images/edit_area.gif" />';
	if ($quote == 1) echo '<br /><span style="font-size:.8em; color:#CCCCCC;">Quoting Post: #' . $postid . '</span>';
	if ($edit == 1) echo '<br /><span style="font-size:.8em; color:#CCCCCC;">Editing Post: #' . $postid . '</span>';
	echo get_editor_box(stripslashes($value),null,null,850,"Forum");

	echo '<span style="position:relative; float:right;"><input type="button" name="forum_submit" id="forum_submit" value="Submit" onclick="if (' . get_editor_value_javascript() . ' != \'\') { ajaxapi(\'/features/forum/forum_ajax.php\',\'post\',\'&message=\'+escape(' . get_editor_value_javascript() . ')+\'&postid=' . $postid . '&quote=' . $quote . '&edit=' . $edit . '\',function() { close_modal(); }); this.blur();}" /></span>';
}

function shoutbox_editor() {
global $CFG, $MYVARS, $USER;
    $forumid = $MYVARS->GET["forumid"];
	$username = isset($USER->userid) && $USER->userid > 0 ? '<input type="hidden" id="ownerid" name="ownerid" value="' . $USER->userid . '" /><input type="hidden" id="alias" name="alias" />' : '<span class="shoutbox_editortext" style="float:left;margin-top:3px;"><img src="' . $CFG->wwwroot . '/images/shoutbox_alias.gif" style="margin-bottom:-12px;" /></span><input type="text" id="alias" size="21" name="alias" style="float:left;margin-top:5px; margin-left:-195px;" /><input type="hidden" id="ownerid" name="ownerid" />';
    echo '<input name="contentWordCount" type="hidden" value="5" />';
    echo get_editor_box(null,null,"400",null,"Shoutbox");
	  echo $username;
    echo '<input type="button" value="Submit" style="float:right;margin-top:3px" onclick="if (' . get_editor_value_javascript() . '.length > 0) { ajaxapi(\'/features/forum/forum_ajax.php\',\'shoutbox_post\',\'&amp;forumid=' . $forumid . '&amp;alias=\'+document.getElementById(\'alias\').value+\'&amp;ownerid=\'+document.getElementById(\'ownerid\').value+\'&amp;message=\'+escape(' . get_editor_value_javascript() . '),function() { close_modal(); }); this.blur();}" />';
}

function create_discussion_form() {
global $CFG, $MYVARS;
	$title = $message = "";
	$pageid = $MYVARS->GET["pageid"]; $forumid = $MYVARS->GET["forumid"]; $catid = $MYVARS->GET["catid"];

	if (isset($MYVARS->GET["discussionid"])) { //EDIT MODE
		$discussionid = $MYVARS->GET["discussionid"];
		$discussion = get_db_row("SELECT * FROM forum_discussions WHERE discussionid=$discussionid");
		$postid = first_post($discussionid);
		$message = get_db_field("message","forum_posts","postid=$postid ORDER BY postid");
		$title = $discussion["title"];
	}

	echo '<img id="edit_area_' . $forumid . '" name="edit_area_' . $forumid . '" src="' . $CFG->wwwroot . '/images/edit_area.gif" /><br />';
	echo '<br /><span style="padding:2px;"><strong>Discussion Title</strong>: <input type="text" size="80" id="discussion_title" value="' . $title . '" /></span><br /><br />';
	echo get_editor_box(stripslashes($message),null,"300",null,"Forum");
	if (isset($MYVARS->GET["discussionid"])) { echo '<span style="position:relative; float:right;"><input type="button" name="forum_submit" id="forum_submit" value="Submit" onclick="if (' . get_editor_value_javascript() . ' != \'\' && document.getElementById(\'discussion_title\').value != \'\') { ajaxapi(\'/features/forum/forum_ajax.php\',\'create_discussion\',\'&message=\'+escape(' . get_editor_value_javascript() . ')+\'&title=\'+escape(document.getElementById(\'discussion_title\').value)+\'&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&postid=' . $postid . '&discussionid=' . $discussionid . '\',function() { close_modal(); }); }" /></span>';
	} else { echo '<span style="position:relative; float:right;"><input type="button" name="forum_submit" id="forum_submit" value="Submit" onclick="javascript: if (' . get_editor_value_javascript() . ' != \'\' && document.getElementById(\'discussion_title\').value != \'\') { ajaxapi(\'/features/forum/forum_ajax.php\',\'create_discussion\',\'&message=\'+escape(' . get_editor_value_javascript() . ')+\'&title=\'+escape(document.getElementById(\'discussion_title\').value)+\'&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '\',function() { close_modal(); });  }" /></span>';}
}

?>
