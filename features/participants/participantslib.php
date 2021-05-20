<?php
/***************************************************************************
* participantslib.php - Participants feature library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2010
* Revision: 0.0.4
***************************************************************************/

if (!isset($LIBHEADER)) if (file_exists('./lib/header.php')) { include('./lib/header.php'); }elseif (file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif (file_exists('../../lib/header.php')) { include('../../lib/header.php'); }
$PARTICIPANTSLIB = true;
//PARTICIPANTSLIB Config

function display_participants($pageid,$area,$featureid) {
global $CFG, $USER, $ABILITIES;
    $feature = "participants";
    if (!$settings = fetch_settings($feature,$featureid,$pageid)) {
		make_or_update_settings_array(default_settings($feature,$pageid,$featureid));
		$settings = fetch_settings($feature,$featureid,$pageid);
	}
	
	$title = $settings->$feature->$featureid->feature_title->setting;
    	
	if (is_logged_in()) {
		if (user_has_ability_in_page($USER->userid, 'viewparticipants', $pageid)) {
            $content = make_modal_links(array("title"=> stripslashes($title),"text"=> stripslashes($title),"path"=>$CFG->wwwroot."/features/participants/participants.php?action=view_participants&amp;pageid=$pageid&amp;featureid=$featureid","width"=>"400","image"=>$CFG->wwwroot."/images/user.png","styles"=>"vertical-align: top;")); 
			$buttons = get_button_layout("participants",$featureid,$pageid); 
			return get_css_box($title,$content,$buttons,NULL,"participants",$featureid);
		}
	}
}

function participants_delete($pageid,$featureid,$sectionid) {
	execute_db_sql("DELETE FROM pages_features WHERE feature='participants' AND pageid='$pageid' AND featureid='$featureid'");
	resort_page_features($pageid);
}

function participants_buttons($pageid,$featuretype,$featureid) {
global $CFG,$USER;
	$returnme = "";
    return $returnme;
}

function participants_default_settings($feature,$pageid,$featureid) {
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","Participants",false,"Participants","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","viewable_limit","25",false,"25","Viewable Limit","text",true,"<=0","Must be greater than 0.");
//	$settings_array[] = array(false,"$feature","$pageid","$featureid","sorty_by","30",false,"30","Sort By","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","show_total","1",false,"1","Show Total","yes/no");
	return $settings_array;
}
?>