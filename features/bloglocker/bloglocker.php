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
global $MYVARS,$CFG,$USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "bloglocker";

	//Default Settings	
	$default_settings = default_settings($feature,$pageid,$featureid);
	$setting_names = get_setting_names($default_settings);
    
	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature,$featureid,$pageid)) {
        echo make_settings_page($setting_names,$settings,$default_settings,$feature,$featureid,$pageid);
	} else { //No Settings found...setup default settings
		if (make_or_update_settings_array($default_settings)) { bloglocker_settings(); }
	}
}

function view_locker() {
global $MYVARS,$CFG,$USER;
	if (!isset($HTMLLIB)) { include_once('../html/htmllib.php');}
	$htmlid = $MYVARS->GET['htmlid'];
	$pageid = $MYVARS->GET['pageid'];
    if (!user_has_ability_in_page($USER->userid,"viewbloglocker",$pageid)) { echo get_page_error_message("no_permission",array("viewbloglocker")); return; }

	$row = get_db_row("SELECT * FROM html WHERE htmlid='$htmlid'");
	$settings = fetch_settings("html",$htmlid,$pageid);
	$comments = $settings->html->$htmlid->allowcomments->setting && user_has_ability_in_page($USER->userid,"viewcomments",$pageid) ? get_html_comments($row['htmlid'],$pageid,true,10) : '';
	
	echo '
	<div style="width:550px">
		'.$row['html'] . $comments.'
	</div>';
}
?>