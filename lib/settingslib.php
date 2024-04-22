<?php
/***************************************************************************
* settingslib.php - Settings Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/20/2021
* Revision: 0.2.6
***************************************************************************/

if (!isset($LIBHEADER)) { include('header.php'); }
$SETTINGSLIB = true;

function fetch_settings($type, &$featureid, $pageid=false) {
global $CFG;

	if (empty($featureid)) { // Non Feature settings ex. Site or page
		$SQL = "SELECT * FROM settings WHERE type='$type' AND pageid='$pageid'";
		if ($results = get_db_result($SQL)) {
      $settings = new \stdClass;
      $settings->$type = new \stdClass;
			while ($row = fetch_row($results)) {
        $setting_name = $row["setting_name"];
        if (empty($settings->$type->$setting_name)) { $settings->$type->$setting_name = new \stdClass; }
				if (isset($row["settingid"])) { $settings->$type->$setting_name->settingid = $row["settingid"]; }
				if (isset($row["setting"])) { $settings->$type->$setting_name->setting = stripslashes($row["setting"]); }
				if (isset($row["extra"])) { $settings->$type->$setting_name->extra = stripslashes($row["extra"]); }
				if (isset($row["sort"])) { $settings->$type->$setting_name->sort = $row["sort"]; }
				if (isset($row["defaultsetting"])) { $settings->$type->$setting_name->defaultsetting = stripslashes($row["defaultsetting"]); }
			}
			return $settings;
		}
		return false;
	} else { // Feature settings
    if ($featureid == "*") { // Find the featureid: Only valid on features that cannot have duplicates on a page
      $featureid = get_db_field("featureid", "pages_features", "feature='$type' AND pageid='$pageid'");
      if (empty($featureid)) {
        return false;
      }
    }

		$defaultsettings = default_settings($type, $pageid, $featureid); //get all default settings for the feature
		$SQL = "SELECT * FROM settings WHERE type='$type' AND featureid='$featureid'";
		if ($results = get_db_result($SQL)) {
    	$settings = new \stdClass;
      $settings->$type = new \stdClass;
      $settings->$type->$featureid = new \stdClass;
			while ($row = fetch_row($results)) {
        $setting_name = $row["setting_name"];
        if (empty($settings->$type->$featureid->$setting_name)) { $settings->$type->$featureid->$setting_name = new \stdClass; }
				if (isset($row["settingid"])) { $settings->$type->$featureid->$setting_name->settingid = $row["settingid"];}
				if (isset($row["setting"])) { $settings->$type->$featureid->$setting_name->setting = stripslashes($row["setting"]);}
				if (isset($row["extra"])) { $settings->$type->$featureid->$setting_name->extra = stripslashes($row["extra"]);}
				if (isset($row["sort"])) { $settings->$type->$featureid->$setting_name->sort = $row["sort"];}
				if (isset($row["defaultsetting"])) { $settings->$type->$featureid->$setting_name->defaultsetting = stripslashes($row["defaultsetting"]);}
			}
		}

		//Make sure all settings are set
		foreach ($defaultsettings as $setting) {
	    $setting_name = $setting[4];
			if (!isset($settings->$type->$featureid->$setting_name->setting)) {
				make_or_update_setting(false, $setting[1], $setting[2], $setting[3], $setting[4], $setting[5], $setting[6], $setting[7], $settings);
			}
		}
		return $settings;
	}
}

function get_setting_names($settings_list) {
  $setting_names = [];
  foreach ($settings_list as $setting) {
    $setting_names[] .= $setting[4];
  }
  return $setting_names;
}

function make_settings_page($setting_names, $settings, $default_settings, $feature, $featureid, $pageid) {
global $CFG, $USER;
	//Check if user has permission to be here
	if (!user_has_ability_in_page($USER->userid, "editfeaturesettings", $pageid)) {
		echo get_error_message("generic_permissions");
		return;
	}

  foreach ($setting_names as $name) {
    $setting = get_setting($name, $default_settings); //Get default setting details.
		if (!isset($settings->$feature->$featureid->$name)) { //Setting has never been saved for this feature instance.
			make_or_update_setting(false,$setting[1],$setting[2],$setting[3],$setting[4],$setting[5],$setting[6],$setting[7],$settings);
    }

    if (isset($setting[10]) && isset($setting[11]) && isset($setting[12])) {
      $settingslist .= make_setting_input($name, $setting[8], $setting[9], $setting[6], $settings->$feature->$featureid->$name->settingid, $settings->$feature->$featureid->$name->setting, $setting[10], $setting[11], $setting[12]);
    } else {
      $settingslist .= make_setting_input($name, $setting[8], $setting[9], $setting[6], $settings->$feature->$featureid->$name->settingid, $settings->$feature->$featureid->$name->setting);
    }
  }

  return template_use("tmp/settings.template", array("settingslist" => $settingslist), "make_settings_page_template");
}

function make_setting_input($name, $title, $type, $extra="", $settingid="", $setting = "", $numeric = false, $extravalidation = "", $extra_alert = "", $savebutton = true) {
global $CFG;
  $valign = $type == "textarea" ? "top" : "middle";
	$params = array("valign" => $valign, "istext" => false, "isyesno" => false, "isnoyes" => false, "isselect" => false, "istextarea" => false,
									"settingid" => $settingid, "title" => $title, "name" => $name, "numeric" => $numeric, "setting" => stripslashes($setting),
									"savebutton" => $savebutton, "ifnumeric" => false, "ifextravalidation" => false, "extra" => $extra, "extravalidation" => $extravalidation, "extra_alert" => $extra_alert);
	switch ($type) {
		case "text":
			$params["istext"] = true;
			$params["ifnumeric"] = $numeric;
			$params["ifextravalidation"] = !empty($extravalidation);
		    break;
		case "yes/no":
			$params["isyesno"] = true;
			$params["yes"] = $setting == 1 ? "selected" : "";
			$params["no"] = $setting != 1 ? "selected" : "";
				break;
		case "no/yes":
			$params["isnoyes"] = true;
			$params["yes"] = $setting == 1 ? "selected" : "";
			$params["no"] = $setting != 1 ? "selected" : "";
				break;
	  case "select": //extra will look like 'SELECT id as selectvalue,text as selectname from table'  the value and name must be labeled as selectvalue and selectname
			$params["isselect"] = true;
			$selected = $setting != 0 ? "" : "selected";
			$params["options"] = template_use("tmp/page.template", array("selected" => $selected, "value" => "0", "display" => "No"), "select_options_template");

	    if ($data = get_db_result($extra)) {
	      while ($row = fetch_row($data)) {
	        $selected = $setting == $row["selectvalue"] ? "selected" : "";
					$params["options"] .= template_use("tmp/page.template", array("selected" => $selected, "value" => $row["selectvalue"], "display" => stripslashes($row["selectname"])), "select_options_template");
	      }
	    }
			break;
	  case "select_array": //extra will be an array of arrays. The value and name must be labeled as selectvalue and selectname
			$params["isselect"] = true;
			$params["options"] = "";
	    foreach ($extra as $e) {
	      $selected = $setting == $e["selectvalue"] ? "selected" : "";
				$params["options"] .= template_use("tmp/page.template", array("selected" => $selected, "value" => $e["selectvalue"], "display" => stripslashes($e["selectname"])), "select_options_template");
	    }
	    break;
		case "textarea":
			$params["istextarea"] = true;
			$params["ifnumeric"] = $numeric;
			$params["ifextravalidation"] = !empty($extravalidation);
	      break;
	}
	return template_use("tmp/settings.template", $params, "make_setting_input_template");
}

function make_or_update_setting($settingid=false, $type=false, $pageid=false, $featureid=false, $setting_name=false, $setting=false, $extra=false, $defaultsetting=false, &$settings = false) {
	//Make select to find out if setting exists
	$SQL = "";
	$SQL2 = $settingid !== false ? "settingid = '$settingid'" : false;
	$SQL3 = $type !== false ? "type = '$type'" : false;
	$SQL4 = $pageid !== false ? "pageid = '$pageid'" : false;
	$SQL5 = $featureid !== false ? "featureid = '$featureid'" : false;
	$SQL6 = $setting_name !== false ? "setting_name = '$setting_name'" : false;
	$SQL7 = $setting !== false ? "setting = '$setting'" : false;
	$SQL8 = $extra !== false ? "extra = '$extra'" : false;
	$SQL9 = $defaultsetting !== false ? "defaultsetting = '$defaultsetting'" : false;

	if (!$settingid) {
		$SQL .= $SQL2 ? $SQL2 : "";
		if ($SQL3) { $SQL .= $SQL2 ? " AND $SQL3" : $SQL3; }
		if ($SQL4) { $SQL .= $SQL2 || $SQL3 ? " AND $SQL4" : $SQL4; }
		if ($SQL5) { $SQL .= $SQL2 || $SQL3 || $SQL4 ? " AND $SQL5" : $SQL5; }
		if ($SQL6) { $SQL .= $SQL2 || $SQL3 || $SQL4 || $SQL5 ? " AND $SQL6" : $SQL6; }
	}
	////////////////////////////////////////////////////////////////////////////////////////////////////////
	$settingid = $settingid ? $settingid : get_db_field("settingid", "settings", $SQL);
	if ($settingid) { //Setting Exists
		//Make update SQL
		$SQL = "UPDATE settings s SET ";
		if ($SQL3) { $SQL .= "s.".$SQL3; }
		if ($SQL4) { $SQL .= $SQL3 ? ", s.$SQL4" : "s.".$SQL4; }
		if ($SQL5) { $SQL .= $SQL3 || $SQL4 ? ", s.$SQL5" : "s.".$SQL5; }
		if ($SQL6) { $SQL .= $SQL3 || $SQL4 || $SQL5 ? ", s.$SQL6" : "s.".$SQL6; }
		if ($SQL7) { $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", s.$SQL7" : "s.".$SQL7; }
		if ($SQL8) { $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", s.$SQL8" : "s.".$SQL8; }
		if ($SQL9) { $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", s.$SQL9" : "s.".$SQL9; }

		$SQL .= " WHERE s.settingid='$settingid'";
	} else { //Setting does not exist
		//Make insert SQL
		$SQL = "INSERT INTO settings (";
		if ($SQL3) { $SQL .= "type"; }
		if ($SQL4) { $SQL .= $SQL3 ? ",pageid" : "pageid"; }
		if ($SQL5) { $SQL .= $SQL3 || $SQL4 ? ", featureid" : "featureid"; }
		if ($SQL6) { $SQL .= $SQL3 || $SQL4 || $SQL5 ? ", setting_name" : "setting_name"; }
		if ($SQL7) { $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", setting" : "setting"; }
		if ($SQL8) { $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", extra" : "extra"; }
		if ($SQL9) { $SQL .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", defaultsetting" : "defaultsetting"; }
		$SQL .= ")";

		$SQL2 = " VALUES (";
		if ($SQL3) { $SQL2 .= "'$type'"; }
		if ($SQL4) { $SQL2 .= $SQL3 ? ",'$pageid'" : "'$pageid'"; }
		if ($SQL5) { $SQL2 .= $SQL3 || $SQL4 ? ", '$featureid'" : "'$featureid'"; }
		if ($SQL6) { $SQL2 .= $SQL3 || $SQL4 || $SQL5 ? ", '$setting_name'" : "'$setting_name'"; }
		if ($SQL7) { $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 ? ", '$setting'" : "'$setting'"; }
		if ($SQL8) { $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 ? ", '$extra'" : "'$extra'"; }
		if ($SQL9) { $SQL2 .= $SQL3 || $SQL4 || $SQL5 || $SQL6 || $SQL7 || $SQL8 ? ", '$defaultsetting'" : "'$defaultsetting'"; }
		$SQL2 .= ")";
		$SQL .= $SQL2;
	}

	if ($settingid = execute_db_sql($SQL)) {
		if (!empty($settings)) { //Update settings variable to show changes
	    if (empty($settings->$type)) { $settings->$type = new \stdClass; }
	    if (empty($settings->$type->$featureid)) { $settings->$type->$featureid = new \stdClass; }
	    if (empty($settings->$type->$featureid->$setting_name)) { $settings->$type->$featureid->$setting_name = new \stdClass; }

			$settings->$type->$featureid->$setting_name->settingid = $settingid;
			$settings->$type->$featureid->$setting_name->setting = stripslashes($setting);

			if ($extra) { $settings->$type->$featureid->$setting_name->extra = is_string($extra) ? stripslashes($extra) : $extra; }
			if ($defaultsetting) { $settings->$type->$featureid->$setting_name->defaultsetting = stripslashes($defaultsetting); }
		}
		return true;
	}
	return false;
}

function make_or_update_settings_array($array) {
	foreach ($array as $setting) {
		if (!make_or_update_setting($setting[0],$setting[1],$setting[2],$setting[3],$setting[4],$setting[5],$setting[6],$setting[7])) {
			return false;
		}
	}
	return true;
}

function get_setting($needle, $haystack) {
	foreach ($haystack as $stack) {
		if (array_search($needle,$stack,true)) {
			return $stack;
		}
	}
}

function default_settings($feature,$pageid,$featureid) {
	global $CFG;
    if ($featureid == "*") { // Find the featureid: Only valid on features that cannot have duplicates on a page
      $featureid = get_db_field("featureid", "pages_features", "feature = '$type' AND pageid = '$pageid'");
      if (!empty($featureid)) {
        return false;
      }
    }
	return all_features_function(false, $feature, "", "_default_settings", false, $feature, $pageid, $featureid);
}
?>
