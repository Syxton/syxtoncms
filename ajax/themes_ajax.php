<?php
/***************************************************************************
* themes_ajax.php - Themes and Styles ajax
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 0.1.6
***************************************************************************/

include ('header.php');
update_user_cookie();

callfunction();

function theme_change(){
global $CFG, $MYVARS, $USER, $PAGE;
	$themeid = dbescape($MYVARS->GET["themeid"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);

	if($themeid == ""){ $themeid = '0'; }

	echo get_css_box(get_db_field("name", "pages", "pageid=$pageid"), get_db_field("display_name", "roles", "roleid=" . get_user_role($USER->userid,$pageid)), false,NULL,'pagename',NULL,$themeid,false,$pageid) . '<div style="padding:3px;"></div>'.get_css_box("Title", "Content", null, null, null, null, $themeid,false,$pageid);
}

function show_themes(){
global $CFG, $MYVARS, $USER, $PAGE;
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$themeid = getpagetheme($pageid);
	$themeid = $themeid !== false ? $themeid : $PAGE->thememid;

	//Allow the Theme Selector
	//Page has theme selected show themes
	if(isset($PAGE->themeid) && $PAGE->themeid > 0){ //Theme selected
		echo theme_selector($pageid,$themeid);
	}else{ //Custom Theme
		echo theme_selector($pageid, $themeid);
	}
}

function save_custom_theme(){
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET["featureid"]);
	$feature = dbescape($MYVARS->GET["feature"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);

	$pageid = $pageid == $CFG->SITEID ? 0 : $pageid;

	if ($feature == "page") {
		$default_list = get_feature_styles($pageid, $feature, NULL, true);
		$i=0;
		foreach ($default_list as $style) {
			$styles[$i] = array(false, false, "$pageid", false, $style[1], dbescape($MYVARS->GET[$style[1]]), '0', '0');
			$i++;
		}
	} else {
		$default_list = get_feature_styles($pageid, $feature, $featureid, true);
		foreach ($default_list as $style) {
			$styles[$i] = array(false, "$feature", "$pageid", $featureid, $style[1], dbescape($MYVARS->GET[$style[1]]), '0', '0');
			$i++;
		}
	}

	if (make_or_update_styles_array($styles)) { echo "Saved";
	} else { echo "Failed"; }
}

function preview(){
global $CFG, $MYVARS, $USER, $STYLES;
	$featureid = dbescape($MYVARS->GET["featureid"]);
	$feature = dbescape($MYVARS->GET["feature"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);

	if($feature == "page"){
		$default_list = get_feature_styles($pageid,$feature,NULL,true);
		foreach($default_list as $style){
			$temparray[$style[1]] = dbescape($MYVARS->GET[$style[1]]);
		}
		$STYLES->pagename = $temparray;
		$STYLES->page = $temparray;

		$returnme =
		get_css_box(get_db_field("name", "pages", "pageid=$pageid"), get_db_field("display_name", "roles", "roleid=" . get_user_role($USER->userid,$pageid)), false,NULL,'pagename',NULL,NULL,true) . '<div style="padding:3px;"></div>'
		.
		get_css_box("Title", "Content",NULL,NULL,"page",NULL,NULL,true);
	}else{
		$STYLES->preview = true;

		$default_list = get_feature_styles($pageid,$feature,$featureid,true);
		foreach($default_list as $style){
			$temparray[$style[1]] = dbescape($MYVARS->GET[$style[1]]);
		}
		$STYLES->$feature = $temparray;

		include_once($CFG->dirroot . '/features/'.$feature.'/'.$feature.'lib.php');
		$function = "display_$feature";
		$returnme = $function($pageid,"side",$featureid);
		unset($STYLES->preview);
	}

	echo $returnme;
}

function show_styles(){
global $CFG, $MYVARS, $USER;
	$feature = dbescape($MYVARS->GET["feature"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;

	if($feature == "page"){
		$left = get_feature_styles($revised_pageid,$feature);
		$right = '
		Preview:<br />
		<div id="color_preview">
		'.get_css_box(get_db_field("name", "pages", "pageid=$pageid"), get_db_field("display_name", "roles", "roleid=" . get_user_role($USER->userid,$pageid)),false,NULL,'pagename',NULL,'0',NULL,$pageid) . '<div style="padding:3px;"></div>'
		.get_css_box("Title", "Content",NULL,NULL,NULL,NULL,'0',NULL,$pageid).
        '</div>';

		echo make_panes($left, $right);
	}else{
    	include_once($CFG->dirroot . '/features/'.$feature.'/'.$feature.'lib.php');
    	$function = "display_$feature";
    	echo make_panes(get_feature_styles($revised_pageid,$feature,$featureid), $function($pageid,"side",$featureid));
	}
}

function change_theme_save(){
global $CFG, $MYVARS, $USER;
	$themeid = dbescape($MYVARS->GET["themeid"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$themeid = $themeid == "" ? '0' : $themeid;

	//Save selected Theme
	make_or_update_setting(false,'page',$pageid,0,"themeid",$themeid,false,false);

	//Page has theme selected show themes
	echo theme_selector($pageid,$themeid);
}
?>
