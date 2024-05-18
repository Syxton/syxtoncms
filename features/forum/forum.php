<?php
/***************************************************************************
* forum.php - Forum modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.2.8
***************************************************************************/

if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

    if (!defined('FORUMLIB')) { include_once($CFG->dirroot . '/features/forum/forumlib.php'); }

    callfunction();

    echo '</body></html>';
}

function forum_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = clean_myvar_opt("featureid", "int", false); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = "forum";

	//Default Settings
	$default_settings = default_settings($feature, $pageid, $featureid);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { forum_settings(); }
	}
}

function createforumcategory() {
global $MYVARS, $CFG, $USER;
	if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

	$forumid = dbescape($MYVARS->GET['forumid']); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$title = "";

	if (isset($MYVARS->GET["catid"])) {
        if (!user_is_able($USER->userid, "editforumcategory", $pageid)) { trigger_error(error_string("no_permission", ["editforumcategory"]), E_USER_WARNING); return; }
		$category = get_db_row("SELECT * FROM forum_categories WHERE catid=" . dbescape($MYVARS->GET["catid"]));
		$title = $category["title"];
	} else {
        if (!user_is_able($USER->userid, "createforumcategory", $pageid)) { trigger_error(error_string("no_permission", ["createforumcategory"]), E_USER_WARNING); return; }
		if (!user_is_able($USER->userid, "createforumcategory", $pageid)) { trigger_error(error_string("generic_permissions"), E_USER_WARNING); return;}
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
			</fieldset>
            <input class="submit" name="submit" type="submit" value="Submit" style="margin: 0px auto;display: block;" />
		</form>
	</div>';
}

function show_forum_editor() {
global $CFG, $MYVARS;
	$postid = $MYVARS->GET["postid"] ?? false;
    $discussionid = $MYVARS->GET["discussionid"] ?? false;
    $quote = $MYVARS->GET["quote"] ?? false;
    $edit = $MYVARS->GET["edit"] ?? false;
    $pagenum = $MYVARS->GET["pagenum"] ?? 0;
    $forumid = $MYVARS->GET["forumid"] ?? false;

    $value = "";
    if (!$forumid && $postid) {
        $forumid = get_db_field("forumid", "forum_discussions", "discussionid IN (SELECT discussionid FROM forum_posts WHERE postid = '$postid')");
        $value = $edit == 1 ? get_db_field("message", "forum_posts", "postid='$postid'") : "";
    }

	echo '<img id="edit_area_' . $forumid . '" name="edit_area_' . $forumid . '" src="' . $CFG->wwwroot . '/images/edit_area.gif" />';
	if ($quote == 1) {
        echo '<br /><span style="font-size:.8em; color:#CCCCCC;">Quoting Post: #' . $postid . '</span>';
    }

	if ($edit == 1) {
        echo '<br /><span style="font-size:.8em; color:#CCCCCC;">Editing Post: #' . $postid . '</span>';
    }

    echo get_editor_box(["initialvalue" => $value, "type" => "Forum", "height" => "400", "width" => "700"]) . '
		 <input type="button" style="margin: 10px auto;display: block;"
                name="forum_submit"
                id="forum_submit"
                value="Submit"
                onclick="if (' . get_editor_value_javascript() . ' != \'\') {
                            ajaxapi(\'/features/forum/forum_ajax.php\',
                                    \'post\',
                                    \'&message=\' + escape(' . get_editor_value_javascript() . ') + \'&amp;forumid=' . $forumid . '&amp;discussionid=' . $discussionid . '&amp;postid=' . $postid . '&amp;quote=' . $quote . '&amp;edit=' . $edit . '\',
                                    function() {
                                        close_modal();
                                    }
                            );
                            this.blur();
                        }" />';
}

function shoutbox_editor() {
global $CFG, $MYVARS, $USER;
	$forumid = clean_myvar_req("forumid", "int");

	if (isset($USER->userid) && $USER->userid > 0) {
		$username = '<input type="hidden" id="userid" name="userid" value="' . $USER->userid . '" />';
	}
	$username = isset($USER->userid) && $USER->userid > 0 ?  : '<span class="shoutbox_editortext" style="float:left;margin-top:3px;"><img src="' . $CFG->wwwroot . '/images/shoutbox_alias.gif" style="margin-bottom:-12px;" /></span><input type="text" id="alias" size="21" name="alias" style="float:left;margin-top:5px; margin-left:-195px;" /><input type="hidden" id="userid" name="userid" />';
    echo '<input name="contentWordCount" type="hidden" value="5" />';
    echo get_editor_box(["type" => "Shoutbox", "height" => "400"]);
	echo $username;
    echo '<input type="button" value="Submit" style="float:right;margin-top:3px" onclick="if (' . get_editor_value_javascript() . '.length > 0) { ajaxapi(\'/features/forum/forum_ajax.php\',\'shoutbox_post\',\'&amp;forumid=' . $forumid . '&amp;alias=\'+document.getElementById(\'alias\').value+\'&amp;userid=\'+document.getElementById(\'userid\').value+\'&amp;message=\'+escape(' . get_editor_value_javascript() . '),function() { close_modal(); }); this.blur();}" />';
}

function create_discussion_form() {
global $CFG;
	$title = $message = "";
	$catid = clean_myvar_req("catid", "int");
	$forumid = clean_myvar_req("forumid", "int");
	$pageid = clean_myvar_req("pageid", "int");
	$discussionid = clean_myvar_opt("discussionid", "int", false);

	if ($discussionid) { // EDIT MODE
		$discussion = get_db_row("SELECT * FROM forum_discussions WHERE discussionid=$discussionid");
		$postid = first_post($discussionid);
		$message = get_db_field("message", "forum_posts", "postid=$postid ORDER BY postid");
		$title = $discussion["title"];
	}

	$existingpost = $discussionid ? '&postid=' . $postid . '&discussionid=' . $discussionid : '';
	$editor = get_editor_box(["initialvalue" => $message, "type" => "Forum", "height" => "300"]);
	$editorvalue = get_editor_value_javascript();
	$params = [
		"forumid" => $forumid,
		"catid" => $catid,
		"pageid" => $pageid,
		"wwwroot" => $CFG->wwwroot,
		"existingpost" => $existingpost,
		"editor" => $editor,
		"editorvalue" => $editorvalue,
		"title" => $title,
	];
	echo use_template("tmp/forum.template", $params, "create_discussion_form", "forum");
}

?>
