<?php
/***************************************************************************
* styleslib.php - Styles and Theme function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/19/2021
* Revision: 0.1.9
***************************************************************************/

if(!isset($LIBHEADER)){ include('header.php'); }
$STYLESLIB = true;
$STYLES = new \stdClass;

function get_styles($pageid, $themeid=false, $feature='', $featureid='') {
global $CFG,$MYVARS;
	// THEME RULES
	// Default styles are given pageid = 0
	// Global styles are given forced = 1
	// Feature type specific styles are given featureid = 0
	$revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;
	$params = array("pageid" => $revised_pageid, "themeid" => $themeid, "feature" => $feature, "featureid" => $featureid);

	if ($themeid === "0") { // CUSTOM THEME
		// Hasn't saved custom colors yet return defaults;
		if (!get_db_field("id", "styles", "pageid = '$revised_pageid'")) {
			$feature = "page";
			if ($default_list = get_feature_styles($revised_pageid, $feature, NULL, true)) {
        foreach ($default_list as $style) {
  				$temparray[$style[1]] = isset($MYVARS->GET[$style[1]]) ? dbescape($MYVARS->GET[$style[1]]) : false;
  			}
			}
			return $temparray;
		}
		$SQL = template_use("dbsql/styles.sql", $params, "custom_theme_styles");
	} elseif ($themeid > 0) { // PAGE THEME IS SET TO A SAVED THEME
		$SQL = template_use("dbsql/styles.sql", $params, "set_theme_styles");
	} else { // NO THEME...LOOK FOR PARENT THEMES
		$params["themeid"] = getpagetheme($CFG->SITEID);
		$SQL = template_use("dbsql/styles.sql", $params, "parent_theme_styles");
	}

	if ($result = get_db_result($SQL)) {
    $styles = false;
		while ($row = fetch_row($result)) {
			$styles[$row["attribute"]] = $row["value"];
		}
		return $styles;
	}
    return false;
}

function theme_selector($pageid, $themeid, $feature="page", $checked1="checked", $checked2="") {
global $CFG, $MYVARS, $USER;
	$selected = $themeid;

	$SQL = template_use("dbsql/styles.sql", array("notsite" => ($pageid != $CFG->SITEID)), "theme_selector_sql");
	$params = array("pageid" => $pageid, "feature" => $feature, "checked1" => $checked1, "checked2" => $checked2, "iscustom" => ($themeid === '0' ));
	$params["menu"] = make_select("themes", get_db_result($SQL), "themeid", "name", $selected, 'onchange="' . template_use("templates/themes.template", $params, "theme_selector_menu_action_template") . '"', false, null, "width:225px;");
	$left = template_use("templates/themes.template", $params, "theme_selector_left_template");

	$pagename = get_db_field("name", "pages", "pageid = '$pageid'");
	$rolename = get_db_field("display_name", "roles", "roleid = " . get_user_role($USER->userid, $pageid));

	$params["pagelist"] = get_css_box($pagename, $rolename, false, NULL, 'pagename', NULL, $themeid, null, $pageid);
	$params["block"] = get_css_box("Title", "Content", null, null, null, null, $themeid, null, $pageid);
	$right = template_use("templates/themes.template", $params, "theme_selector_right_template");

	return template_use("templates/themes.template", array("left" => $left, "right" => $right), "make_template_selector_panes_template");
}

function get_feature_styles($pageid, $feature, $featureid=false, $getarray=false) {
global $CFG;
	$returnme = $feature == "page" ? '<input type="radio" name="group1" value="Theme Selector" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'show_themes\',\'&pageid='.$pageid.'&feature='.$feature.'\',function() { simple_display(\'themes_page\');}); blur();" />Theme Selector <input type="radio" name="group1" value="Page Styles" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'show_styles\',\'&pageid='.$pageid.'&feature='.$feature.'\',function() { simple_display(\'themes_page\');}); blur();" checked/>Page Styles ' : '<input type="radio" name="group1" value="Theme Selector" '.$checked1.' />Theme Selector';
	$returnme .= '<br /><br /><form id="colors" name="colors">';

	//Styles function
	$styles = $feature.'_default_styles';
  $revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;
	if($feature == "page"){
		$styles = $styles(); // hard coded default styles.

		$i = 0;
		foreach ($styles as $style) { // go through each style type and see if there is a db setting that can replace it.
			$value = get_db_field("value","styles","themeid=0 AND attribute='".$style[1]."' AND pageid='$revised_pageid' AND feature <= '' AND featureid <= 0 ORDER BY pageid DESC");
			if (!$value) { // No db value found, use the hard coded default value.
				$value = $style[2];
			} else { // db value found, replace it in the array so that the array return gets the correct value.
				$styles[$i][2] = $value;
			}
			if (!$getarray) {
				$returnme .= '<div><table style="font-size:1em;"><tr><td style="width:170px;vertical-align:middle;">' . $style[0] . '</td><td><input class="themeinput" type="text" name="'.$style[1].'" value="'.$value.'" style="background-color:'.$value.';width:70px;" ><a onclick="blur();" href="javascript:TCP.popup(document.forms[\'colors\'].elements[\''.$style[1].'\'])"><img alt="Click Here to Pick up the color" src="' . $CFG->wwwroot . '/images/themes.gif" /></a></td></tr></table></div>';
			}
			$i++;
		}

		if ($getarray) { return $styles; }
	}else{
		include_once($CFG->dirroot . '/features/'.$feature."/".$feature.'lib.php');
 		$styles = $styles();

		if($getarray){ return $styles; }

		foreach($styles as $style){
			if(!$value = get_db_field("value","styles","themeid=0 AND attribute='".$style[1]."' AND pageid='$revised_pageid' AND feature='$feature' AND featureid='$featureid' ORDER BY pageid DESC")){ $value = $style[2]; }
			$returnme .= '<div><table style="font-size:1em;><tr><td style="width:170px;vertical-align:middle;">' . $style[0] . '</td><td><input class="themeinput" type="text" name="'.$style[1].'" value="'.$value.'" style="background-color:'.$value.';width:70px;"><a onclick="blur();" href="javascript:TCP.popup(document.forms[\'colors\'].elements[\''.$style[1].'\'])"><img alt="Click Here to Pick up the color" src="' . $CFG->wwwroot . '/images/themes.gif" /></a></td></tr></table></div>';
		}
	}

	$returnme .= '	<br />
									<input type="button" value="Save" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'save_custom_theme\',create_request_string(\'colors\')+\'&pageid='.$pageid.'&feature='.$feature.'&featureid='.$featureid.'\',function(){ location.reload(true);});" />
								</form>';
	return $returnme;
}

function getpagetheme($pageid) {
  $featureid = false;

	$settings = fetch_settings("page", $featureid, $pageid);

	if ($settings === false) {
	   return "";
  } else {
    if(isset($settings->page->themeid->setting)) {
        return $settings->page->themeid->setting;
    } else {
        return "";
    }
  }
}

function make_or_update_styles($id=false,$feature=false,$pageid=false,$featureid=false,$attribute=false,$value=false,$themeid=false,$forced=false) {
	//Make select to find out if setting exists
	$SQL = "";
	$SQL2 = $id !== false ? "id='$id'" : false;
	$SQL3 = $feature !== false ? "feature='$feature'" : false;
	$SQL4 = $pageid !== false ? "pageid='$pageid'" : false;
	$SQL5 = $featureid !== false ? "featureid='$featureid'" : false;
	$SQL6 = $attribute !== false ? "attribute='$attribute'" : false;
	$SQL7 = $value !== false ? "value='$value'" : false;
	$SQL8 = $themeid !== false ? "themeid='$themeid'" : false;
	$SQL9 = $forced !== false ? "forced='$forced'" : false;

	if(!$id){
    	$SQL .= $SQL2 ? $SQL2 : "";
    	if($SQL3){ $SQL .= $SQL2 ? " AND $SQL3" : $SQL3; }
    	if($SQL4){ $SQL .= $SQL2 || $SQL3 ? " AND $SQL4" : $SQL4; }
    	if($SQL5){ $SQL .= $SQL2 || $SQL3 || $SQL4 ? " AND $SQL5" : $SQL5; }
    	if($SQL6){ $SQL .= $SQL2 || $SQL3 || $SQL4 || $SQL5 ? " AND $SQL6" : $SQL6; }
    	if($SQL8){ $SQL .= $SQL2 || $SQL3 || $SQL4 || $SQL5 || $SQL6 ? " AND $SQL8" : $SQL8; }
	}
	////////////////////////////////////////////////////////////////////////////////////////////////////////

	$id = $id ? $id : get_db_field("id", "styles", $SQL);

	if($id) { //Setting Exists
		//Make update SQL
		$SQL = "UPDATE styles s SET ";
		if($SQL3){ $SQL .= "s.".$SQL3; }
		if($SQL4){ $SQL .= $SQL3 ? ", s.$SQL4" : "s.".$SQL4; }
		if($SQL5){ $SQL .= $SQL3 || $SQL4 ? ", s.$SQL5" : "s.".$SQL5; }
		if($SQL6){ $SQL .= $SQL3 || $SQL4 || $SQL5 ? ", s.$SQL6" : "s.".$SQL6; }
		if($SQL7){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", s.$SQL7" : "s.".$SQL7; }
		if($SQL8){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", s.$SQL8" : "s.".$SQL8; }
		if($SQL9){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", s.$SQL9" : "s.".$SQL9; }

		$SQL .= " WHERE s.id='$id'";
	} else { //Setting does not exist
		//Make insert SQL
		$SQL = "INSERT INTO styles (";
		if($SQL3){ $SQL .= "feature"; }
		if($SQL4){ $SQL .= $SQL3 ? ",pageid" : "pageid"; }
		if($SQL5){ $SQL .= $SQL3 || $SQL4 ? ", featureid" : "featureid"; }
		if($SQL6){ $SQL .= $SQL3 || $SQL4 || $SQL5 ? ", attribute" : "attribute"; }
		if($SQL7){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", value" : "value"; }
		if($SQL8){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", themeid" : "themeid"; }
		if($SQL9){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", forced" : "forced"; }
		$SQL .= ")";

		$SQL2 = " VALUES (";
		if($SQL3){ $SQL2 .= "'$feature'"; }
		if($SQL4){ $SQL2 .= $SQL3 ? ",'$pageid'" : "'$pageid'"; }
		if($SQL5){ $SQL2 .= $SQL3 || $SQL4 ? ", '$featureid'" : "'$featureid'"; }
		if($SQL6){ $SQL2 .= $SQL3 || $SQL4 || $SQL5 ? ", '$attribute'" : "'$attribute'"; }
		if($SQL7){ $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", '$value'" : "'$value'"; }
		if($SQL8){ $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", '$themeid'" : "'$themeid'"; }
		if($SQL9){ $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", '$forced'" : "'$forced'"; }
		$SQL2 .= ")";
		$SQL .= $SQL2;
	}

	if(execute_db_sql($SQL)){ return true; }
	return false;
}

function make_or_update_styles_array($array) {
	foreach($array as $style) {
		if(!make_or_update_styles($style[0],$style[1],$style[2],$style[3],$style[4],$style[5],$style[6],$style[7])){ return false; }
	}
	return true;
}
?>
