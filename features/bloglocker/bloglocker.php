<?php
/***************************************************************************
* bloglocker.php - Blog Locker Page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 0.2.6
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); } 
    
    callfunction();
    
    echo '</body></html>';
}

function bloglocker_settings() {
global $MYVARS, $CFG, $USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
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
	if (!isset($HTMLLIB)) { include_once('../html/htmllib.php');}
	$htmlid = $MYVARS->GET['htmlid'];
	$pageid = $MYVARS->GET['pageid'];
    if (!user_is_able($USER->userid, "viewbloglocker", $pageid)) {
        debugging(error_string("no_permission", ["viewbloglocker"]), 2);
		return;
	}

	$row = get_db_row("SELECT * FROM html WHERE htmlid='$htmlid'");
	$settings = fetch_settings("html", $htmlid, $pageid);
	$comments = $settings->html->$htmlid->allowcomments->setting && user_is_able($USER->userid, "viewcomments", $pageid) ? get_html_comments($row['htmlid'], $pageid, true,10) : '';
	
	echo '
	<div style="width:550px">
		' . $row['html'] . $comments . '
	</div>';
}
?>