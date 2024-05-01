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

function fetch_settings($type, &$featureid, $pageid = false) {
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
		$defaultsettings = default_settings($type, $pageid, $featureid); //get all default settings for the feature
		foreach ($defaultsettings as $defaults) {
			$name = $defaults["setting_name"];
			if (!isset($settings->$type->$featureid->$name->setting)) {
				make_or_update_setting(false, $defaults, $defaults["defaultsetting"], false, $settings);
			}
		}
		return $settings;
	}
}

function get_setting_names($settings_list) {
  $setting_names = [];
  foreach ($settings_list as $setting) {
    $setting_names[] .= $setting["setting_name"];
  }
  return $setting_names;
}

function make_settings_page($setting_names, $settings, $default_settings) {
global $CFG, $USER, $PAGE;
	//Check if user has permission to be here
	if (!user_has_ability_in_page($USER->userid, "editfeaturesettings", $PAGE->id)) {
		echo get_error_message("generic_permissions");
		return;
	}

	$settingslist = "";
	foreach ($setting_names as $name) {
		$defaults = get_setting($name, $default_settings); // Get single setting defaults array.
		$type = $defaults["type"];
		$featureid = $defaults["featureid"];
		if (!isset($settings->$type->$featureid->$name)) { //Setting has never been saved for this type instance.
			make_or_update_setting(false, $defaults, $defaults["defaultsetting"], $settings);
		}

		$settingslist .= make_setting_input($name, $defaults, $settings->$type->$featureid->$name->settingid, $settings->$type->$featureid->$name->setting);
	}

  return template_use("tmp/settings.template", array("settingslist" => $settingslist), "make_settings_page_template");
}

function make_setting_input($name, $defaults, $settingid = false, $setting = "", $savebutton = true) {
global $CFG;
	$valign = $defaults["inputtype"] == "textarea" ? "top" : "middle";
	$params = [	
		"valign" => $valign,
		"istext" => false,
		"isyesno" => false,
		"isnoyes" => false, 
		"isselect" => false,
		"istextarea" => false,
		"settingid" => $settingid,
		"title" => $defaults["display"],
		"name" => $name,
		"numeric" => $defaults["numeric"] ?? false,
		"setting" => stripslashes($setting),
		"savebutton" => $savebutton,
		"ifnumeric" => false,
		"ifextravalidation" => false,
		"extra" => $defaults["extra"] ?? false,
		"extravalidation" => $defaults["validation"] ?? false, 
		"extra_alert" => $defaults["warning"] ?? false,
	];

	switch ($defaults["inputtype"]) {
		case "text":
			$params["istext"] = true;
			$params["ifnumeric"] = $defaults["numeric"] ?? false;
			$params["ifextravalidation"] = $defaults["validation"] ?? false;
		    break;
		case "yes/no":
			$params["isyesno"] = true;
			$params["yes"] = (string) $setting == "1" ? "selected" : "";
			$params["no"] = (string) $setting != "1" ? "selected" : "";
			break;
		case "no/yes":
			$params["isnoyes"] = true;
			$params["yes"] = (string) $setting == "1" ? "selected" : "";
			$params["no"] = (string) $setting != "1" ? "selected" : "";
			break;
	  	case "select": //extra will look like 'SELECT id as selectvalue,text as selectname from table'  the value and name must be labeled as selectvalue and selectname
			$params["isselect"] = true;
			$selected = $setting != 0 ? "" : "selected";
			$params["options"] = template_use("tmp/page.template", ["selected" => $selected, "value" => "0", "display" => "No"], "select_options_template");

			if (!empty($defaults["extra"]))	{
				if ($data = get_db_result($defaults["extra"])) {
					while ($row = fetch_row($data)) {
						$selected = $setting == $row["selectvalue"] ? "selected" : "";
						$p = [
							"selected" => $selected,
							"value" => $row["selectvalue"],
							"display" => stripslashes($row["selectname"]),
						];
						$params["options"] .= template_use("tmp/page.template", $p, "select_options_template");
					}
				}
			}
			break;
	  	case "select_array": //extra will be an array of arrays. The value and name must be labeled as selectvalue and selectname
			$params["isselect"] = true;
			$params["options"] = "";
			if (!empty($defaults["extra"]))	{
				foreach ($defaults["extra"] as $e) {
					$selected = $setting == $e["selectvalue"] ? "selected" : "";
					$p = [
						"selected" => $selected,
						"value" => $e["selectvalue"],
						"display" => stripslashes($e["selectname"]),
					];
					$params["options"] .= template_use("tmp/page.template", $p, "select_options_template");
				}
			}
			break;
		case "textarea":
			$params["istextarea"] = true;
			$params["ifnumeric"] = $defaults["numeric"] ?? false;
			$params["ifextravalidation"] = $defaults["validation"] ?? false;
	      	break;
	}
	return template_use("tmp/settings.template", $params, "make_setting_input_template");
}

/**
 * Create and run either an insert or update statement for a setting.
 *
 * @param int $settingid The settingid of the setting to be updated. If not specified, a new setting will be created.
 * @param array $defaults An array of default values to use for the setting. Required fields are type, pageid, featureid, setting_name.
 * @param mixed $value The value of the setting. Defaults to false.
 * @param mixed $extravalue The value of the extravalue field. Defaults to false.
 * @param object $settings A reference to the settings object that will be updated to show the changes made. Defaults to false.
 *
 * @return bool Whether the setting was updated successfully.
 */
function make_or_update_setting($settingid = false, $defaults = [], $value = false, $extravalue = false, &$settings = false) {
	$vars = ["list" => "", "values" => "", "fields" => ["value" => "setting", "extravalue" => "extra"]];

	if (!empty($defaults)) {
		$fields = ["type", "pageid", "featureid", "setting_name", "defaultsetting"];
		$vars["fields"] += $fields;

		// Check if settingid was not provided but can be found.
		if (!$settingid) {
			$idsql = "";
			foreach ($fields as $f) {
				if ($f !== "defaultsetting") {
					if (isset($defaults[$f]) && $defaults[$f] !== false) {
						$idsql .= $idsql == "" ? "" : " AND "; // Add AND if not first field.
						$idsql .= "$f = '" . $defaults[$f] . "'";
					}
				}
			}

			// Make sure you have enough info to find only a single setting.
			if ($idsql !== "" && get_db_count("SELECT * FROM settings WHERE $idsql") == 1) {
				$settingid = get_db_field("settingid", "settings", $idsql);
			}
		}
	}

	if ($settingid) { // Update statement.
		$vars["settingid"] = $settingid;
		foreach ($vars["fields"] as $k => $field) {
			if ($k == "value" || $k == "extravalue") { // Setting values.
				if ($$k !== false) { // Check $value or $extravalue is set.
					$vars["list"] .= $vars["list"] == "" ? "" : ", "; // Add comma if not first field.
					$vars["list"] .= "$field = '" . $$k . "'";	
				}
			} elseif (isset($defaults[$field]) && $defaults[$field] !== false) { // Standard fields from default array.
				$vars["list"] .= $vars["list"] == "" ? "" : ", "; // Add comma if not first field.
				$vars["list"] .= "$field = '" . $defaults[$field] . "'"; // Add field set statement.
			}
		}
		$SQL = "UPDATE settings SET " . $vars["list"] . " WHERE settingid = '" . $vars["settingid"] . "'";
	} else { // Insert statement.
		foreach ($vars["fields"] as $k => $field) {
			if ($k == "value" || $k == "extravalue") { // Setting values.
				if ($$k !== false) { // Check $value or $extravalue is set.
					$vars["list"] .= $vars["list"] == "" ? "" : ", "; // Add comma if not first field.
					$vars["list"] .= "$field"; // Add field to list of fields.
					$vars["values"] .= $vars["values"] == "" ? "" : ", "; // Add comma if not first field.
					$vars["values"] .= "'" . $$k . "'"; // Add value to list of values.
				}
			} elseif (isset($defaults[$field]) && $defaults[$field] !== false) { // Standard fields from default array.
				$vars["list"] .= $vars["list"] == "" ? "" : ", "; // Add comma if not first field.
				$vars["list"] .= "$field"; // Add field to list of fields.
				$vars["values"] .= $vars["values"] == "" ? "" : ", "; // Add comma if not first field.
				$vars["values"] .= "'" . $defaults[$field] . "'"; // Add value to list of values.
			}
		}
		$SQL = "INSERT INTO settings(" . $vars["list"] . ") VALUES(" . $vars["values"] . ")";
	}

	if ($settingid = execute_db_sql($SQL)) { // Whether insert or update statement succeeded we will get the settingid.
		$settings = update_settings_variable(["settingid" => $settingid, "settings" => $settings, "defaults" => $defaults, "value" => $value, "extravalue" => $extravalue]);
		return true;
	}

	return false;
}

function update_settings_variable($params) {
	if (!empty($params["settings"])) { // Update settings variable to show changes
		$type = $params["defaults"]["type"];
		$featureid = $params["defaults"]["featureid"];
		$name = $params["defaults"]["setting_name"];

		if (empty($params["settings"]->$type)) { $params["settings"]->$type = new \stdClass; }
		if (empty($params["settings"]->$type->$featureid)) { $params["settings"]->$type->$featureid = new \stdClass; }
		if (empty($params["settings"]->$type->$featureid->$name)) { $params["settings"]->$type->$featureid->$name = new \stdClass; }

		$params["settings"]->$type->$featureid->$name->settingid = $params["settingid"];
		$params["settings"]->$type->$featureid->$name->setting = stripslashes($params["value"]);

		if ($params["extravalue"]) { $params["settings"]->$type->$featureid->$name->extra = is_string($params["extravalue"]) ? stripslashes($params["extravalue"]) : $extravalue; }
		if (isset($params["defaults"]["defaultsetting"])) { $params["settings"]->$type->$featureid->$name->defaultsetting = stripslashes($params["defaults"]["defaultsetting"]); }
	}
	return $params["settings"];
}

/**
 * Update settings array with new settings or update existing settings
 *
 * @param array $settings An array of settings objects
 *
 * @return boolean Returns true if all settings were updated or inserted successfully
 */
function make_or_update_settings_array($settings) {
	/* Loop through each setting and make it */
	foreach ($settings as $setting) {
		/* Make or update the setting */
		if (!make_or_update_setting(
			/* If settingid is set, we are updating */
			($setting["settingid"] ?? false), 
			/* The setting object */
			$setting, 
			/* Default setting value */
			$setting["defaultsetting"]
		)) {
			/* If one setting fails, return false */
			return false;
		}
	}
	/* Return true if all settings were updated or inserted */
	return true;
}


/**
 * Returns a setting object from an array of settings objects.
 *
 * @param string $setting The name of the setting to search for
 * @param array $settings An array of settings objects
 *
 * @return mixed The setting object or false if not found
 */
function get_setting($setting, $settings) {
	// Search through each settings object in the array to find the setting we are looking for.
	foreach ($settings as $s) {
		// If the setting we are looking for is in the current settings object, return it.
		if (array_search($setting, $s, true)) {
			return $s;
		}
	}
	// If the setting was not found, return false.
	return false;
}

function default_settings($feature, $pageid, $featureid) {
	global $CFG;
    if ($featureid == "*") { // Find the featureid: Only valid on features that cannot have duplicates on a page
      	$featureid = get_db_field("featureid", "pages_features", "feature = '$feature' AND pageid = '$pageid'");
		if (!empty($featureid)) {
			return false;
		}
    }
	return all_features_function(false, $feature, "", "_default_settings", false, $feature, $pageid, $featureid);
}
?>
