<?php
/***************************************************************************
* settingslib.php - Settings Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 2/10/2012
* Revision: 0.2.3
***************************************************************************/

if(!isset($LIBHEADER)){ include('header.php'); }
$SETTINGSLIB = true;

function fetch_settings($type, &$featureid, $pageid=false){
global $CFG;

	if(empty($featureid)){ //Non Feature settings ex. Site or page
		$SQL = "SELECT * FROM settings WHERE type='$type' AND pageid='$pageid'";
		if($results = get_db_result($SQL)){
            $settings = new stdClass();
            $settings->$type = new stdClass();
			while($row = fetch_row($results)){
                if(empty($settings->$type->$row["setting_name"])){ $settings->$type->$row["setting_name"] = new stdClass(); }
				if(isset($row["settingid"])){ $settings->$type->$row["setting_name"]->settingid = $row["settingid"]; }
				if(isset($row["setting"])){ $settings->$type->$row["setting_name"]->setting = stripslashes($row["setting"]); }
				if(isset($row["extra"])){ $settings->$type->$row["setting_name"]->extra = stripslashes($row["extra"]); }
				if(isset($row["sort"])){ $settings->$type->$row["setting_name"]->sort = $row["sort"]; }
				if(isset($row["defaultsetting"])){ $settings->$type->$row["setting_name"]->defaultsetting = stripslashes($row["defaultsetting"]); }
			}
			return $settings;
		}
		return false;
	} else { //Feature settings
        if ($featureid == "*") { // Find the featureid: Only valid on features that cannot have duplicates on a page
            $featureid = get_db_field("featureid", "pages_features", "feature='$type' AND pageid='$pageid'");
            if(empty($featureid)){
                return false;
            }
        }
		$defaultsettings = default_settings($type,$pageid,$featureid); //get all default settings for the feature
		
		$SQL = "SELECT * FROM settings WHERE type='$type' AND featureid='$featureid'";
		if($results = get_db_result($SQL)){
            $settings = new stdClass();
            $settings->$type = new stdClass();
            $settings->$type->$featureid = new stdClass();
			while($row = fetch_row($results)){
                if(empty($settings->$type->$featureid->$row["setting_name"])){ $settings->$type->$featureid->$row["setting_name"] = new stdClass(); }
				if(isset($row["settingid"])){ $settings->$type->$featureid->$row["setting_name"]->settingid = $row["settingid"];}
				if(isset($row["setting"])){ $settings->$type->$featureid->$row["setting_name"]->setting = stripslashes($row["setting"]);}
				if(isset($row["extra"])){ $settings->$type->$featureid->$row["setting_name"]->extra = stripslashes($row["extra"]);}
				if(isset($row["sort"])){ $settings->$type->$featureid->$row["setting_name"]->sort = $row["sort"];}
				if(isset($row["defaultsetting"])){ $settings->$type->$featureid->$row["setting_name"]->defaultsetting = stripslashes($row["defaultsetting"]);}
			}
		}

		//Make sure all settings are set
		foreach($defaultsettings as $setting){
			if(!isset($settings->$type->$featureid->$setting[4]->setting)){
				make_or_update_setting(false,$setting[1],$setting[2],$setting[3],$setting[4],$setting[5],$setting[6],$setting[7],$settings);
			}
		}
		return $settings;	
	}
}

function get_setting_names($settings_list){
    $setting_names = array();
    foreach($settings_list as $setting){
        $setting_names[] .= $setting[4];
    }
    return $setting_names;
}

function make_settings_page($setting_names, $settings, $default_settings, $feature, $featureid, $pageid){
global $CFG, $USER;
	//Check if user has permission to be here
	if(!user_has_ability_in_page($USER->userid,"editfeaturesettings",$pageid)) { echo get_error_message("generic_permissions"); return;}
      
	$returnme = '<div id="settings_div"><table style="width:100%; border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;"><tr><td><strong>Feature Attributes</strong><br /><br />';
    
    foreach($setting_names as $name){
        $setting = get_setting($name,$default_settings); //Get default setting details.
		if(!isset($settings->$feature->$featureid->$name)){ //Setting has never been saved for this feature instance.
			make_or_update_setting(false,$setting[1],$setting[2],$setting[3],$setting[4],$setting[5],$setting[6],$setting[7],$settings);
	    }    
        
        if(isset($setting[10]) && isset($setting[11]) && isset($setting[12])){
            $returnme .= make_setting_input($name, $setting[8], $setting[9], $setting[6], $settings->$feature->$featureid->$name->settingid, $settings->$feature->$featureid->$name->setting,$setting[10],$setting[11],$setting[12]);
        } else {
            $returnme .= make_setting_input($name, $setting[8], $setting[9], $setting[6], $settings->$feature->$featureid->$name->settingid, $settings->$feature->$featureid->$name->setting);    
        }
    }   
		
	$returnme .= '</td></tr></table></div>';
    return $returnme;
}

function make_setting_input($name, $title, $type, $extra="", $settingid="", $setting = "", $numeric = false, $extravalidation = "", $extra_alert = "", $savebutton = true){
global $CFG; 
	$returnme = '<table style="width:100%;margin: 2px 0px;">
					<tr>
						<td class="field_title" style="vertical-align:top;width:230px;">
						'.$title.':
						</td>
						<td class="field_input" style="width:230px">';
	
	switch ($type){
	case "text":
		$numeric_open = $numeric ? 'if(!IsNumeric($(\'#'.$name.'\').val())){alert(\'Must be numeric!\');}else{ ' : '';
		$numeric_close = $numeric ? '}' : '';
		$extravalidation_open = $extravalidation != '' ? 'if($(\'#'.$name.'\').val() '.$extravalidation.'){alert(\''.$extra_alert.'\');}else{ ' : '';
		$extravalidation_close = $extravalidation != '' ? '}' : '';
		$returnme .= '<input type="text" id="'.$name.'" name="'.$name.'" style="width:100%" value="'.$setting.'"/></td><td>';
		$returnme .= !$savebutton ? '' : '<input type="button" value="Save" onclick="'.$numeric_open.''.$extravalidation_open.' ajaxapi(\'/ajax/site_ajax.php\',\'save_settings\',\'&amp;settingid='.$settingid.'&amp;setting=\'+escape($(\'#'.$name.'\').val()),function() { simple_display(\''.$name.'_results\'); setTimeout(function() {clear_display(\''.$name.'_results\');}, 3000);});'.$extravalidation_close.''.$numeric_close.'" /> ';

	    break;
	case "yes/no":
	    $yes = $setting == 1 ? "selected" : "";
		$no = $yes == "" ? "selected" : "";
        $returnme .= '<select name="'.$name.'" id="'.$name.'" style="width:100%;">
						<option value="1" '.$yes.'>Yes</option>
						<option value="0" '.$no.'>No</option>
					</select></td><td>';
		$returnme .= !$savebutton ? '' : '<input type="button" value="Save" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'save_settings\',\'&amp;settingid='.$settingid.'&amp;setting=\'+escape($(\'#'.$name.'\').val()),function() { simple_display(\''.$name.'_results\'); setTimeout(function() {clear_display(\''.$name.'_results\');}, 3000);});" />';
	    break;
	case "no/yes":
		$no = $setting == 1 ? "" : "selected";
		$yes = $no == "" ? "selected" : "";
        $returnme .= '<select name="'.$name.'" id="'.$name.'" style="width:100%;">
						<option value="1" '.$no.'>No</option>
						<option value="0" '.$yes.'>Yes</option>
					</select></td><td>';
		$returnme .= !$savebutton ? '' : '<input type="button" value="Save" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'save_settings\',\'&amp;settingid='.$settingid.'&amp;setting=\'+escape($(\'#'.$name.'\').val()),function() { simple_display(\''.$name.'_results\'); setTimeout(function() {clear_display(\''.$name.'_results\');}, 3000);});" />';
	    break;
    case "select": //extra will look like 'SELECT id as selectvalue,text as selectname from table'  the value and name must be labeled as selectvalue and selectname
        $no = $setting != 0 ? "" : "selected";
        $returnme .= '<select name="'.$name.'" id="'.$name.'" style="width:100%;"><option value="0" '.$no.'>No</option>';
        if($data = get_db_result($extra)){
            while($row = fetch_row($data)){
                $yes = $setting == $row["selectvalue"] ? "selected" : "";
                $returnme .= '<option value="'.$row["selectvalue"].'" '.$yes.'>'.stripslashes($row["selectname"]).'</option>';
            }
        }
        $returnme .= '</select></td><td>';
        $returnme .= !$savebutton ? '' : '<input type="button" value="Save" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'save_settings\',\'&amp;settingid='.$settingid.'&amp;setting=\'+escape($(\'#'.$name.'\').val()),function() { simple_display(\''.$name.'_results\'); setTimeout(function() {clear_display(\''.$name.'_results\');}, 3000);});" />';       
	    break;
        
    case "select_array": //extra will be an array of arrays. The value and name must be labeled as selectvalue and selectname
        $returnme .= '<select name="'.$name.'" id="'.$name.'" style="width:100%;">';
        foreach($extra as $e){
            $yes = $setting == $e["selectvalue"] ? "selected" : "";
            $returnme .= '<option value="'.$e["selectvalue"].'" '.$yes.'>'.stripslashes($e["selectname"]).'</option>';    
        }
        $returnme .= '</select></td><td>';
        $returnme .= !$savebutton ? '' : '<input type="button" value="Save" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'save_settings\',\'&amp;settingid='.$settingid.'&amp;setting=\'+escape($(\'#'.$name.'\').val()),function() { simple_display(\''.$name.'_results\'); setTimeout(function() {clear_display(\''.$name.'_results\');}, 3000);});" />';       
	    break;        
     case "textarea": 
		$numeric_open = $numeric ? 'if(!IsNumeric($(\'#'.$name.'\').val())){alert(\'Must be numeric!\');}else{ ' : '';
		$numeric_close = $numeric ? '}' : '';
		$extravalidation_open = $extravalidation != '' ? 'if($(\'#'.$name.'\').val() '.$extravalidation.'){alert(\''.$extra_alert.'\');}else{ ' : '';
		$extravalidation_close = $extravalidation != '' ? '}' : '';
		$returnme .= '<textarea id="'.$name.'" wrap="off" cols="23" rows="'.$extra.'" >'.stripslashes($setting).'</textarea></td><td>';
		$returnme .= !$savebutton ? '' : '<input type="button" value="Save" onclick="'.$numeric_open.''.$extravalidation_open.' ajaxapi(\'/ajax/site_ajax.php\',\'save_settings\',\'&amp;settingid='.$settingid.'&amp;setting=\'+escape($(\'#'.$name.'\').val()),function() { simple_display(\''.$name.'_results\'); setTimeout(function() {clear_display(\''.$name.'_results\');}, 3000);});'.$extravalidation_close.''.$numeric_close.'" /> ';
        break;
	}

	$returnme .= '<span id="'.$name.'_results" class="notification"></span></td></tr></table>';
	return $returnme;
}

function make_or_update_setting($settingid=false,$type=false,$pageid=false,$featureid=false,$setting_name=false,$setting=false,$extra=false,$defaultsetting=false,&$settings = false){
	//Make select to find out if setting exists
	$SQL = "";
	$SQL2 = $settingid !== false ? "settingid='$settingid'" : false;
	$SQL3 = $type !== false ? "type='$type'" : false;
	$SQL4 = $pageid !== false ? "pageid='$pageid'" : false;
	$SQL5 = $featureid !== false ? "featureid='$featureid'" : false;
	$SQL6 = $setting_name !== false ? "setting_name='$setting_name'" : false;
	$SQL7 = $setting !== false ? "setting='$setting'" : false;
	$SQL8 = $extra !== false ? "extra='$extra'" : false;
	$SQL9 = $defaultsetting !== false ? "defaultsetting='$defaultsetting'" : false;
	
	if(!$settingid){
	$SQL .= $SQL2 ? $SQL2 : "";
	if($SQL3){ $SQL .= $SQL2 ? " AND $SQL3" : $SQL3; }
	if($SQL4){ $SQL .= $SQL2 || $SQL3 ? " AND $SQL4" : $SQL4; }
	if($SQL5){ $SQL .= $SQL2 || $SQL3 || $SQL4 ? " AND $SQL5" : $SQL5; }
	if($SQL6){ $SQL .= $SQL2 || $SQL3 || $SQL4 || $SQL5 ? " AND $SQL6" : $SQL6; }
	}
	////////////////////////////////////////////////////////////////////////////////////////////////////////
	$settingid = $settingid ? $settingid : get_db_field("settingid", "settings", $SQL);
	if($settingid){ //Setting Exists
		//Make update SQL
		$SQL = "UPDATE settings s SET ";
		if($SQL3){ $SQL .= "s.".$SQL3; }
		if($SQL4){ $SQL .= $SQL3 ? ", s.$SQL4" : "s.".$SQL4; }
		if($SQL5){ $SQL .= $SQL3 || $SQL4 ? ", s.$SQL5" : "s.".$SQL5; }
		if($SQL6){ $SQL .= $SQL3 || $SQL4 || $SQL5 ? ", s.$SQL6" : "s.".$SQL6; }
		if($SQL7){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", s.$SQL7" : "s.".$SQL7; }
		if($SQL8){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", s.$SQL8" : "s.".$SQL8; }
		if($SQL9){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", s.$SQL9" : "s.".$SQL9; }
		
		$SQL .= " WHERE s.settingid='$settingid'";	
	}else{ //Setting does not exist
		//Make insert SQL
		$SQL = "INSERT INTO settings (";
		if($SQL3){ $SQL .= "type"; }
		if($SQL4){ $SQL .= $SQL3 ? ",pageid" : "pageid"; }
		if($SQL5){ $SQL .= $SQL3 || $SQL4 ? ", featureid" : "featureid"; }
		if($SQL6){ $SQL .= $SQL3 || $SQL4 || $SQL5 ? ", setting_name" : "setting_name"; }
		if($SQL7){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", setting" : "setting"; }
		if($SQL8){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", extra" : "extra"; }
		if($SQL9){ $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", defaultsetting" : "defaultsetting"; }
		$SQL .= ")";
		
		$SQL2 = " VALUES (";
		if($SQL3){ $SQL2 .= "'$type'"; }
		if($SQL4){ $SQL2 .= $SQL3 ? ",'$pageid'" : "'$pageid'"; }
		if($SQL5){ $SQL2 .= $SQL3 || $SQL4 ? ", '$featureid'" : "'$featureid'"; }
		if($SQL6){ $SQL2 .= $SQL3 || $SQL4 || $SQL5 ? ", '$setting_name'" : "'$setting_name'"; }
		if($SQL7){ $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", '$setting'" : "'$setting'"; }
		if($SQL8){ $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", '$extra'" : "'$extra'"; }
		if($SQL9){ $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", '$defaultsetting'" : "'$defaultsetting'"; }
		$SQL2 .= ")";	
		$SQL .= $SQL2;
	}

	if($settingid = execute_db_sql($SQL)){
		if($settings){ //Update settings variable to show changes
            $settings->$type = new stdClass();
            $settings->$type->$featureid = new stdClass();
            $settings->$type->$featureid->$setting_name = new stdClass();
			$settings->$type->$featureid->$setting_name->settingid = $settingid;
			$settings->$type->$featureid->$setting_name->setting = stripslashes($setting);
			if($extra){ $settings->$type->$featureid->$setting_name->extra = is_string($extra) ? stripslashes($extra) : $extra; }
			if($defaultsetting){ $settings->$type->$featureid->$setting_name->defaultsetting = stripslashes($defaultsetting); }
		}	
		return true;
	}else{ return false; }
}

function make_or_update_settings_array($array){
	foreach($array as $setting){
		if(!make_or_update_setting($setting[0],$setting[1],$setting[2],$setting[3],$setting[4],$setting[5],$setting[6],$setting[7])){ return false; }
	}
	
	return true;
}

function get_setting($needle, $haystack){
	foreach($haystack as $stack){
		if(array_search($needle,$stack,true)){
			return $stack;
		}
	}
} 

function default_settings($feature,$pageid,$featureid){
	global $CFG;
    if ($featureid == "*") { // Find the featureid: Only valid on features that cannot have duplicates on a page
        $featureid = get_db_field("featureid", "pages_features", "feature='$type' AND pageid='$pageid'");
        if(!empty($featureid)){
            return false;
        }    
    }
	return all_features_function(false,$feature,"","_default_settings",false,$feature,$pageid,$featureid);
}
?>