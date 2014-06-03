<?php
/***************************************************************************
* styleslib.php - Styles and Theme function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/28/2012
* Revision: 0.1.7
***************************************************************************/

if(!isset($LIBHEADER)){ include('header.php'); }
$STYLESLIB = true;
$STYLES = "";

function get_styles($pageid, $themeid=false, $feature='', $featureid='')
{
global $CFG,$MYVARS;

// RULES
// Default styles are given pageid=0
// Global styles are given forced=1
// Feature type specific styles are given featureid=0
	if($themeid === '0'){ //CUSTOM THEME
		$pageid = $pageid == $CFG->SITEID ? 0 : $pageid;
		
		//Hasn't saved custom colors yet return defaults;
		if(!get_db_field("id","styles","pageid=$pageid")) {
			$feature = "page";
			if($default_list = get_feature_styles($pageid,$feature,NULL,true)){
                foreach($default_list as $style){
    				$temparray[$style[1]] = isset($MYVARS->GET[$style[1]]) ? dbescape($MYVARS->GET[$style[1]]) : false;
    			}
			}
			return $temparray;	
		}
		
		$SQL = "
		SELECT *, 1 as ranky FROM styles WHERE pageid=$pageid AND themeid='$themeid'
		  UNION
		SELECT *, 2 as ranky FROM styles WHERE
			pageid=$pageid AND themeid='$themeid' AND feature='$feature' AND featureid='0'
		  UNION
		SELECT *, 3 as ranky FROM styles WHERE
			pageid=$pageid AND themeid='$themeid' AND feature='$feature' AND featureid='$featureid'
		  UNION
		SELECT *, 4 as ranky FROM styles WHERE
			pageid=0 AND forced=1 AND feature='$feature' AND featureid='0'
		ORDER BY ranky
		";
	}elseif($themeid > 0){ //PAGE THEME IS SET TO A SAVED THEME
		$SQL = "
		SELECT *, 1 as ranky FROM styles WHERE themeid='$themeid'
		  UNION
		SELECT *, 2 as ranky FROM styles WHERE
			themeid='$themeid' AND feature='$feature' AND featureid='0'
		  UNION
		SELECT *, 3 as ranky FROM styles WHERE
			themeid='$themeid' AND feature='$feature' AND featureid='$featureid'
		  UNION
		SELECT *, 4 as ranky FROM styles WHERE
			pageid=0 AND forced=1 AND feature='$feature' AND featureid='0'
		ORDER BY ranky
		";
	}else{ //NO THEME...LOOK FOR PARENT THEMES
		$themeid = getpagetheme($CFG->SITEID);
		if($themeid !== false){ 
            return get_styles($CFG->SITEID,$themeid);
        }else{
    		//$pageid = $CFG->SITEID;
    		$root = $themeid ? " UNION SELECT *, 2 as ranky FROM styles WHERE themeid=$themeid" : "";
    		
    		$SQL = "
    		SELECT *, 1 as ranky FROM styles WHERE
    			pageid = 0 AND feature IS NULL
     		$root 		
    		  UNION
    		SELECT *, 3 as ranky FROM styles WHERE
    			pageid = '$pageid' AND feature IS NULL
    		  UNION
    		SELECT *, 4 as ranky FROM styles WHERE
    			pageid = '$pageid' AND feature='$feature' AND featureid='0'
    		  UNION
    		SELECT *, 5 as ranky FROM styles WHERE
    			pageid = '$pageid' AND feature='$feature' AND featureid='$featureid'
    		  UNION
    		SELECT *, 6 as ranky FROM styles WHERE
    			pageid=0 AND forced=1 AND feature='$feature' AND featureid='0'
    		ORDER BY ranky
    		";
		}
	}

	if($result = get_db_result($SQL)){
        $styles = false;
		while($row = fetch_row($result)){
			$styles[$row["attribute"]] = $row["value"];
		}
		return $styles;
	}
    return false;
}

function theme_selector($pageid, $themeid, $feature="page",$checked1="checked", $checked2="")
{
global $CFG, $MYVARS, $USER;
	$selected = $themeid ? $themeid : false;
	
	$left = $themeid === '0' ? '<input type="radio" name="group1" value="Theme Selector" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'show_themes\',\'&pageid='.$pageid.'&feature='.$feature.'\',function() { simple_display(\'themes_page\');}); blur();" '.$checked1.'/>Theme Selector <input type="radio" name="group1" value="Page Styles" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'show_styles\',\'&pageid='.$pageid.'&feature='.$feature.'\',function() { simple_display(\'themes_page\');}); blur();" '.$checked2.'/>Page Styles ' : '<input type="radio" name="group1" value="Theme Selector" '.$checked1.' />Theme Selector';
	
	$left .='
    	<br /><br /><div id="left_pane">
    	Select Theme:
    	<div id="theme_select">
    	'.
    	make_select("themes",get_db_result("SELECT * FROM themes"),"themeid","name",$selected,'onchange="ajaxapi(\'/ajax/themes_ajax.php\',\'theme_change\',\'&pageid='.$pageid.'&themeid=\'+escape(document.getElementById(\'themes\').value),function() { simple_display(\'color_preview\');});"',true,null,"width:225px;","Custom")
    	.'&nbsp;<input type="button" value="Save" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'change_theme_save\',\'&pageid='.$pageid.'&themeid=\'+escape(document.getElementById(\'themes\').value),function() { simple_display(\'themes_page\');});" />
    	</div></div>
    	';
	
	$right = '
    	Preview:<br />
    	<div id="color_preview">
    	'.
    	get_css_box(get_db_field("name", "pages", "pageid=$pageid"), get_db_field("display_name", "roles", "roleid=" . get_user_role($USER->userid, $pageid)),false,NULL,'pagename',NULL,$themeid,null,$pageid) . '<div style="padding:3px;"></div>'
    	.
    	get_css_box("Title", "Content", null, null, null, null, $themeid, null, $pageid)
    	.'
    	</div>
    	';	
	
	return make_panes($left, $right);
}

function make_panes($left, $right)
{
	return '
	<div id="panes">
		<table style="font-size:1em;width:100%;">
			<tr>
				<td style="width:48%;vertical-align:top;">
				'.$left.'
				</td>
				<td style="width:2%;"></td>
				<td style="width:50%;vertical-align:top;*padding-right:10px !important;">
				<div style="border:1px;position:absolute;width:50%;z-index:1000;"></div>
				'.$right.'
				</td>			
			</tr>
		</table>
	</div>
	';
}

function get_feature_styles($pageid,$feature,$featureid=false,$getarray=false)
{
global $CFG;
	$returnme = $feature == "page" ? '<input type="radio" name="group1" value="Theme Selector" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'show_themes\',\'&pageid='.$pageid.'&feature='.$feature.'\',function() { simple_display(\'themes_page\');}); blur();" />Theme Selector <input type="radio" name="group1" value="Page Styles" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'show_styles\',\'&pageid='.$pageid.'&feature='.$feature.'\',function() { simple_display(\'themes_page\');}); blur();" checked/>Page Styles ' : '<input type="radio" name="group1" value="Theme Selector" '.$checked1.' />Theme Selector';
	$returnme .= '<br /><br /><form id="colors" name="colors">';
	
	//Styles function
	$styles = $feature.'_default_styles';
    $revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;
	if($feature == "page"){
		$styles = $styles();
		
		if($getarray){ return $styles; }
		
		foreach($styles as $style){
			if(!$value = get_db_field("value","styles","themeid=0 AND attribute='".$style[1]."' AND pageid='$revised_pageid' AND feature IS NULL AND featureid IS NULL ORDER BY pageid DESC")){ $value = $style[2]; }
			$returnme .= '<div><table style="font-size:1em;"><tr><td style="width:170px;vertical-align:middle;">' . $style[0] . '</td><td><input type="text" name="'.$style[1].'" value="'.$value.'" style="background-color:'.$value.';width:70px;" ><a onclick="blur();" href="javascript:TCP.popup(document.forms[\'colors\'].elements[\''.$style[1].'\'])"><img alt="Click Here to Pick up the color" src="' . $CFG->wwwroot . '/images/themes.gif" /></a></td></tr></table></div>';
		}
	}else{
		include_once($CFG->dirroot . '/features/'.$feature."/".$feature.'lib.php');
 		$styles = $styles();
		
		if($getarray){ return $styles; }
		
		foreach($styles as $style){
			if(!$value = get_db_field("value","styles","themeid=0 AND attribute='".$style[1]."' AND pageid='$revised_pageid' AND feature='$feature' AND featureid='$featureid' ORDER BY pageid DESC")){ $value = $style[2]; }
			$returnme .= '<div><table style="font-size:1em;><tr><td style="width:170px;vertical-align:middle;">' . $style[0] . '</td><td><input type="text" name="'.$style[1].'" value="'.$value.'" style="background-color:'.$value.';width:70px;" ><a onclick="blur();" href="javascript:TCP.popup(document.forms[\'colors\'].elements[\''.$style[1].'\'])"><img alt="Click Here to Pick up the color" src="' . $CFG->wwwroot . '/images/themes.gif" /></a></td></tr></table></div>';
		}
	}
	
	$returnme .= '<br /><input type="button" value="Save" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'save_custom_theme\',create_request_string(\'colors\')+\'&pageid='.$pageid.'&feature='.$feature.'&featureid='.$featureid.'\',function(){ location.reload(true);});" />&nbsp;<input type="button" value="Preview" onclick="ajaxapi(\'/ajax/themes_ajax.php\',\'preview\',\'&\'+create_request_string(\'colors\')+\'&pageid='.$pageid.'&feature='.$feature.'&featureid='.$featureid.'\',function(){ simple_display(\'color_preview\'); });" /></form>';
	return $returnme;
}

function getpagetheme($pageid)
{
	$settings = fetch_settings("page",NULL,$pageid);

	if($settings === false) return false; 
	else return $settings->page->themeid->setting;
}

function make_or_update_styles($id=false,$feature=false,$pageid=false,$featureid=false,$attribute=false,$value=false,$themeid=false,$forced=false)
{
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
	
	if($id){ //Setting Exists
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
	}else{ //Setting does not exist
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

function make_or_update_styles_array($array)
{
	foreach($array as $style){
		if(!make_or_update_styles($style[0],$style[1],$style[2],$style[3],$style[4],$style[5],$style[6],$style[7])){ return false; }
	}
	return true;
}
?>