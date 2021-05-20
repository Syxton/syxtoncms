<?php
/***************************************************************************
* html.php - html page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/3/2014
* Revision: 1.7.5
***************************************************************************/

if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); }
    if (!isset($HTMLLIB)) { include_once($CFG->dirroot . '/features/html/htmllib.php'); }

    callfunction();

    echo get_editor_javascript();

    echo '</body></html>';
}

function html_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "html";

	//Default Settings
	$default_settings = default_settings($feature,$pageid,$featureid);
	$setting_names = get_setting_names($default_settings);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature,$featureid,$pageid)) {
        echo make_settings_page($setting_names,$settings,$default_settings,$feature,$featureid,$pageid);
	} else { //No Settings found...setup default settings
		if (make_or_update_settings_array($settings_array)) { html_settings(); }
	}
}

function edithtml() {
global $CFG, $MYVARS, $USER;
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$featureid = dbescape($MYVARS->GET['featureid']);
	$now = get_timestamp();
	if (!user_has_ability_in_page($USER->userid,"edithtml",$pageid)) { echo get_page_error_message("no_permission",array("edithtml")); return; }
	$SQL = "SELECT * FROM html WHERE htmlid='$featureid'";
    if ($row = get_db_row($SQL)) {
        if (($now - $row["edit_time"]) > 30) {
    		$content = stripslashes($row['html']);
    		$userid = is_logged_in() ? $USER->userid : "0";
    		echo '
    		<div id="edit_html_div">
    			<table style="width:100%">
    				<tr>
    					<td colspan="2" style="text-align:center">';
                        echo get_editor_box($content);
    				    echo '<input type="button" value="Save" onclick="ajaxapi(\'/features/html/html_ajax.php\',\'edit_html\',\'&amp;htmlid='.$featureid.'&amp;html=\'+ escape('.get_editor_value_javascript().'),function() { do_nothing();}); close_modal();" />
    					</td>
    				</tr>
    			</table>
    		</div>
    		<script type="text/javascript">var stillediting = setInterval(function() { ajaxapi(\'/features/html/html_ajax.php\',\'still_editing\',\'&htmlid='.$featureid.'&userid='.$userid.'\',function() { if (xmlHttp.readyState == 4) { do_nothing(); }},true);},5000);</script>';
    		execute_db_sql("UPDATE html SET edit_user='$userid',edit_time='$now' WHERE htmlid=$featureid");
    	} else {
    		echo '
    		<div style="width:100%;text-align:center;">
    			<img src="'.$CFG->wwwroot.'/images/underconstruction.gif" />
    		</div>
    		<div style="width:100%;text-align:center;">
    		This area is currently being edited by: '.get_user_name($row["edit_user"]).'
    		</div>
    		';
    	}
    }
    donothing();
}

function deletecomment() {
global $CFG, $MYVARS, $USER;
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$userid = dbescape($MYVARS->GET["userid"]);
	if (!(user_has_ability_in_page($USER->userid,"deletecomments",$pageid) || ($USER->userid == $userid && user_has_ability_in_page($USER->userid,"makecomments",$pageid)))) { echo get_page_error_message("generic_permissions"); return;}
	$commentid = dbescape($MYVARS->GET["commentid"]);
	echo '
	<table style="width:80%;margin-left: auto; margin-right: auto;">
	<tr>
		<td style="text-align:center;">
			Are you sure you want to delete this comment?
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<input type="button" value="Yes" onclick="ajaxapi(\'/features/html/html_ajax.php\',\'deletecomment\',\'&amp;commentid='.$commentid.'&amp;pageid='.$pageid.'\',function() { close_modal();});" />
		</td>
	</tr>
	</table>
	';
}

function makecomment() {
global $CFG, $MYVARS, $USER;
	$pageid = dbescape($MYVARS->GET["pageid"]);
    if (!user_has_ability_in_page($USER->userid,"makecomments",$pageid)) { echo get_page_error_message("no_permission",array("makecomments")); return; }

	$htmlid = dbescape($MYVARS->GET["htmlid"]);
	echo '
	<table style="width:80%;margin-left: auto; margin-right: auto;">
	<tr>
		<td style="text-align:left; font-size:.85em;">
			<b><u>Make Comment</u></b>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<textarea id="comment" cols="40" rows="10"></textarea>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<input type="button" value="Submit Comment" onclick="ajaxapi(\'/features/html/html_ajax.php\',\'makecomment\',\'&amp;htmlid='.$htmlid.'&amp;pageid='.$pageid.'&amp;comment=\'+escape(document.getElementById(\'comment\').value),function() { close_modal();});" />
		</td>
	</tr>
	</table>
	';
}

function makereply() {
global $CFG, $MYVARS, $USER;
	$pageid = dbescape($MYVARS->GET["pageid"]);
    if (!user_has_ability_in_page($USER->userid,"makereplies",$pageid)) { echo get_page_error_message("no_permission",array("makereplies")); return; }

	$commentid = dbescape($MYVARS->GET["commentid"]);
	$comment = htmlentities(get_db_field("comment","html_comments","commentid='$commentid'"));
	echo '
	<table style="width:80%;margin-left: auto; margin-right: auto;">
	<tr>
		<td style="text-align:left; font-size:.85em;">
			<b><u>Comment</u></b>
		</td>
	</tr>
	<tr>
		<td style="text-align:left; font-size:.75em; color:gray">
			'.$comment.'
		<br /><br /></td>
	</tr>
	<tr>
		<td style="text-align:left; font-size:.85em;">
			<b><u>Make Reply</u></b>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<textarea id="reply" cols="40" rows="8"></textarea>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<input type="button" value="Reply to Comment" onclick="ajaxapi(\'/features/html/html_ajax.php\',\'makereply\',\'&amp;commentid='.$commentid.'&amp;pageid='.$pageid.'&amp;reply=\'+escape(document.getElementById(\'reply\').value),function() { close_modal();});" />
		</td>
	</tr>
	</table>
	';
}

function editreply() {
global $CFG, $MYVARS, $USER;
	$pageid = dbescape($MYVARS->GET["pageid"]);
    if (!user_has_ability_in_page($USER->userid,"makereplies",$pageid)) { echo get_page_error_message("no_permission",array("makereplies")); return; }

	$commentid = dbescape($MYVARS->GET["commentid"]);
	$replyid = dbescape($MYVARS->GET["replyid"]);
	$reply = htmlentities(get_db_field("reply","html_replies","replyid='$replyid'"));
	$comment = htmlentities(get_db_field("comment","html_comments","commentid='$commentid'"));
	echo '
	<table style="width:80%;margin-left: auto; margin-right: auto;">
	<tr>
		<td style="text-align:left; font-size:.85em;">
			<b><u>Comment</u></b>
		</td>
	</tr>
	<tr>
		<td style="text-align:left; font-size:.75em; color:gray">
			'.$comment.'
		<br /><br /></td>
	</tr>
	<tr>
		<td style="text-align:left; font-size:.85em;">
			<b><u>Edit Reply</u></b>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<textarea id="reply" cols="40" rows="8">'.$reply.'</textarea>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<input type="button" value="Edit Reply" onclick="ajaxapi(\'/features/html/html_ajax.php\',\'editreply\',\'&amp;replyid='.$replyid.'&amp;pageid='.$pageid.'&amp;reply=\'+escape(document.getElementById(\'reply\').value),function() { close_modal();});" />
		</td>
	</tr>
	</table>';
}

function editcomment() {
global $CFG, $MYVARS, $USER;
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$userid = dbescape($MYVARS->GET["userid"]);
	$commentid = dbescape($MYVARS->GET["commentid"]);
	$commentuser = htmlentities(get_db_field("userid","html_comments","commentid='$commentid'"));
	if (!(user_has_ability_in_page($USER->userid,"editanycomment",$pageid) || ($USER->userid == $userid && user_has_ability_in_page($USER->userid,"makecomments",$pageid)))) { echo get_page_error_message("generic_permissions"); return;}

	$comment = htmlentities(get_db_field("comment","html_comments","commentid='$commentid'"));
	echo '
	<table style="width:80%;margin-left: auto; margin-right: auto;">
	<tr>
		<td style="text-align:left; font-size:.85em;">
			<b><u>Comment</u></b>
		</td>
	</tr>
	<tr>
		<td style="text-align:left; font-size:.75em; color:gray">
			'.$comment.'
		<br /><br /></td>
	</tr>
	<tr>
		<td style="text-align:left; font-size:.85em;">
			<b><u>Edit Comment</u></b>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<textarea id="comment" cols="40" rows="8">'.$comment.'</textarea>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<input type="button" value="Edit Comment" onclick="ajaxapi(\'/features/html/html_ajax.php\',\'editcomment\',\'&amp;commentid='.$commentid.'&amp;pageid='.$pageid.'&amp;comment=\'+escape(document.getElementById(\'comment\').value),function() { close_modal();});" />
		</td>
	</tr>
	</table>';
}

function deletereply() {
global $CFG, $MYVARS, $USER;
	$pageid = dbescape($MYVARS->GET["pageid"]);
    if (!user_has_ability_in_page($USER->userid,"deletereply",$pageid)) { echo get_page_error_message("no_permission",array("deletereply")); return; }

	$replyid = dbescape($MYVARS->GET["replyid"]);
	echo '
	<table style="width:80%;margin-left: auto; margin-right: auto;">
	<tr>
		<td style="text-align:center;">
			Are you sure you want to delete this reply message?
		</td>
	</tr>
	<tr>
		<td style="text-align:center;">
			<input type="button" value="Yes" onclick="ajaxapi(\'/features/html/html_ajax.php\',\'deletereply\',\'&amp;replyid='.$replyid.'&amp;pageid='.$pageid.'\',function() { close_modal();});" />
		</td>
	</tr>
	</table>
	';
}

function viewhtml() {
global $CFG, $MYVARS, $USER, $ROLES;
    $key = $MYVARS->GET['key'];
    $htmlid = $MYVARS->GET['htmlid'];
    $pageid = $MYVARS->GET['pageid'];

    if (!is_logged_in() && isset($key)) { key_login($key); }

    $settings = fetch_settings("html",$htmlid,$pageid);

	if (is_logged_in()) {
		if (user_has_ability_in_page($USER->userid,"viewhtml",$pageid)) {
			$abilities = get_user_abilities($USER->userid,$pageid,"html");
			echo '<a href="'.$CFG->wwwroot.'/index.php?pageid='.$pageid.'">Home</a>
			<table style="margin-left:auto;margin-right:auto;width:100%">
				<tr>
					<td>
						'.
						get_html($pageid,$htmlid,$settings,$abilities,false,true)
						.'
					</td>
				</tr>
			</table>';
		} else { echo '<center>You do not have proper permissions to view this item.</center>';}
	} else {
		if (get_db_field("siteviewable","pages","pageid=$pageid") && role_has_ability_in_page($ROLES->visitor, 'viewhtml', $pageid)) {
			$abilities = get_user_abilities($USER->userid,$pageid,"html");
			echo '<a href="'.$CFG->wwwroot.'/index.php?pageid='.$pageid.'">Home</a>
			<table style="margin-left:auto;margin-right:auto;width:100%">
				<tr>
					<td>
						'.
						get_html($pageid,$htmlid,$settings,$abilities,false,true)
						.'
					</td>
				</tr>
			</table>';
		} else {
            echo '<div id="standalone_div"><input type="hidden" id="reroute" value="/features/html/html.php:viewhtml:&amp;pageid='.$pageid.'&amp;htmlid='.$htmlid . ':standalone_div" />';
            echo '<div style="width:100%; text-align:center;">You must login to see this content.<br /><center>'.get_login_form(true,false) . '</center></div></div>';
		}
	}
}
?>
