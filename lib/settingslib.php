<?php
/***************************************************************************
* settingslib.php - Settings Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.2.6
***************************************************************************/

if (!LIBHEADER) { include('header.php'); }
define('SETTINGSLIB', true);

function fetch_settings($type, &$featureid, $pageid = false) {
global $CFG;

	if (empty($featureid)) { // Non Feature settings ex. Site or page
        $pageid = $pageid ?: "0"; // Set to 0 if page not set.
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

        $settings = new \stdClass;
		$SQL = "SELECT * FROM settings WHERE type='$type' AND featureid='$featureid'";
		if ($results = get_db_result($SQL)) {
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
        if (is_array($defaultsettings)) {
            foreach ($defaultsettings as $info) {
                $name = $info["setting_name"];
                if (!isset($settings->$type->$featureid->$name->setting)) {
                    save_setting(false, $info, $info["defaultsetting"], false, $settings);
                }
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

function make_settings_page($settings, $settinginfo, $title = "Feature Settings") {
global $CFG, $USER, $PAGE;
	//Check if user has permission to be here
	if (!user_is_able($USER->userid, "editfeaturesettings", $PAGE->id)) {
		echo error_string("generic_permissions");
		return;
	}

    $settingslist = "";
	foreach ($settinginfo as $info) {
		$type = $info["type"];
		$featureid = $info["featureid"];
        $name = $info["setting_name"];
		if (!isset($settings->$type->$featureid->$name)) { // Setting has never been saved for this type instance.
			save_setting(false, $info, $info["defaultsetting"], false, $settings);
		}

		$settingslist .= make_setting_input($info, $settings->$type->$featureid->$name->settingid, $settings->$type->$featureid->$name->setting);
	}

  return use_template("tmp/settings.template", ["title" => $title, "settingslist" => $settingslist], "make_settings_page_template");
}

function make_setting_input($info, $settingid = false, $value = "", $savebutton = true) {
global $CFG;
	$valign = $info["inputtype"] == "textarea" ? "top" : "middle";
	$params = [	
		"valign" => $valign,
		"istext" => false,
		"isyesno" => false,
		"isnoyes" => false, 
		"isselect" => false,
		"istextarea" => false,
		"settingid" => $settingid,
		"title" => $info["display"],
		"name" => $info["setting_name"],
		"numeric" => $info["numeric"] ?? false,
		"setting" => stripslashes($value),
		"savebutton" => $savebutton,
		"ifnumeric" => false,
		"ifvalidation" => false,
		"validation" => $info["validation"] ?? false, 
		"warning" => $info["warning"] ?? false,
	];

	switch ($info["inputtype"]) {
		case "text":
			$params["istext"] = true;
			$params["ifnumeric"] = $info["numeric"] ?? false;
			$params["ifvalidation"] = $info["validation"] ?? false;
			  break;
		case "yes/no":
			$params["isyesno"] = true;
			$params["yes"] = (string) $value == "1" ? "selected" : "";
			$params["no"] = (string) $value != "1" ? "selected" : "";
			break;
		case "no/yes":
			$params["isnoyes"] = true;
			$params["yes"] = (string) $value == "1" ? "selected" : "";
			$params["no"] = (string) $value != "1" ? "selected" : "";
			break;
			case "select": //extra will look like 'SELECT id as selectvalue,text as selectname from table'  the value and name must be labeled as selectvalue and selectname
			$params["isselect"] = true;
			$selected = $value != 0 ? "" : "selected";
			$params["options"] = use_template("tmp/page.template", ["selected" => $selected, "value" => "0", "display" => "No"], "select_options_template");

			if (isset($info["extraforminfo"]))	{
				if ($data = get_db_result($info["extraforminfo"])) {
					while ($row = fetch_row($data)) {
						$selected = $value == $row["selectvalue"] ? "selected" : "";
						$p = [
							"selected" => $selected,
							"value" => $row["selectvalue"],
							"display" => stripslashes($row["selectname"]),
						];
						$params["options"] .= use_template("tmp/page.template", $p, "select_options_template");
					}
				}
			}
			break;
			case "select_array": // extraforminfo will be an array of arrays. The value and name must be labeled as selectvalue and selectname
			$params["isselect"] = true;
			$params["options"] = "";
			if (isset($info["extraforminfo"]))	{
				foreach ($info["extraforminfo"] as $e) {
					$selected = $value == $e["selectvalue"] ? "selected" : "";
					$p = [
						"selected" => $selected,
						"value" => $e["selectvalue"],
						"display" => stripslashes($e["selectname"]),
					];
					$params["options"] .= use_template("tmp/page.template", $p, "select_options_template");
				}
			}
			break;
		case "textarea":
			$params["istextarea"] = true;
            $params["extraforminfo"] = $info["extraforminfo"] ?? false;
			$params["ifnumeric"] = $info["numeric"] ?? false;
			$params["ifvalidation"] = $info["validation"] ?? false;
		  		break;
	}
	return use_template("tmp/settings.template", $params, "make_setting_input_template");
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
function save_setting($settingid = false, $settinginfo = [], $value = false, $extravalue = false, &$settings = false) {
	$fields = [];
    $sqlfields = "";
    $sqlvalues = "";

    // If settingid wasn't provided, we may be able to find it with the provided values.
	if (!empty($settinginfo)) {
        // Add settinginfo fields to list of possible fields to check/update.
		$fields += ["type", "pageid", "featureid", "setting_name", "defaultsetting"];

		// Check if settingid was not provided but can be found.
        // Also check that the forced insert is not requested.
		if (!$settingid && !isset($settinginfo["insert"])) {
			$idsql = "";
			foreach ($fields as $field) {
				if ($field !== "defaultsetting") {
					if (isset($settinginfo[$field]) && $settinginfo[$field] !== false) {
						$idsql .= $idsql == "" ? "" : " AND "; // Add AND if not first field.
						$idsql .= "$field = '" . $settinginfo[$field] . "'";
					}
				}
			}

			// Make sure you have enough info to find only a single setting.
			if ($idsql !== "" && get_db_count("SELECT * FROM settings WHERE $idsql") == 1) {
				$settingid = get_db_field("settingid", "settings", $idsql);
			}
		}
	}

    // Add value and extravalue fields to the list of possible fields to insert/update.
    // <-TODO-> The key/value pairs exist for these fieldsbecause of naming differences in the database.
    $fields += ["value" => "setting", "extravalue" => "extra"];

    // Was setting already found in the db?
	if ($settingid) {
        // Setting already exists.  Let's update the row in the settings table.
		foreach ($fields as $index => $field) {
			if ($index == "value" || $index == "extravalue") { // Setting values.
				if ($$index !== false) { // Check $value or $extravalue is set.
					$sqlfields .= empty($sqlfields) ? "" : ", "; // Add comma if not first field.
					$sqlfields .= "$field = '" . $$index . "'";	
				}
			} elseif (isset($settinginfo[$field]) && $settinginfo[$field] !== false) { // Standard fields from default array.
				$sqlfields .= empty($sqlfields) ? "" : ", "; // Add comma if not first field.
				$sqlfields .= "$field = '" . $settinginfo[$field] . "'"; // Add field set statement.
			}
		}
		$SQL = "UPDATE settings SET $sqlfields WHERE settingid = '$settingid'";
	} else {
        // Setting has never been created.  Let's insert a row in the settings table.
		foreach ($fields as $index => $field) {
            // Insert requires default values.
			if ($index == "value" || $index == "extravalue") { // Setting values.
				if ($$index === false && isset($settinginfo["default$field"])) { // Check $value or $extravalue is set and not empty.
                    $$index = $settinginfo["default$field"] ?? ""; // Use default value if set and blank if empty.
                    $settinginfo[$field] = $$index; // Set value in settinginfo.
				} elseif ($$index !== false) {
                    $settinginfo[$field] = $$index; // Set value in settinginfo.
                }
            }

            if (isset($settinginfo[$field]) && $settinginfo[$field] !== false) { // Standard fields from default array.
				$sqlfields .= empty($sqlfields) ? "" : ", "; // Add comma if not first field.
				$sqlfields .= "$field"; // Add field to list of fields.
				$sqlvalues .= empty($sqlvalues) ? "" : ", "; // Add comma if not first field.
				$sqlvalues .= "'" . $settinginfo[$field] . "'"; // Add value to list of values.
			}
		}
		$SQL = "INSERT INTO settings($sqlfields) VALUES($sqlvalues)";
	}

	if ($settingid = execute_db_sql($SQL)) { // Whether insert or update statement succeeded we will get the settingid.
		$settings = refresh_settings(["settingid" => $settingid, "settings" => $settings, "settinginfo" => $settinginfo, "value" => $value, "extravalue" => $extravalue]);
		return true;
	}

	return false;
}

function refresh_settings($params) {
	if (!empty($params["settings"])) { // Update settings variable to show changes
		$type = $params["settinginfo"]["type"];
		$featureid = $params["settinginfo"]["featureid"];
		$name = $params["settinginfo"]["setting_name"];

		if (empty($params["settings"]->$type)) { $params["settings"]->$type = new \stdClass; }
		if (empty($params["settings"]->$type->$featureid)) { $params["settings"]->$type->$featureid = new \stdClass; }
		if (empty($params["settings"]->$type->$featureid->$name)) { $params["settings"]->$type->$featureid->$name = new \stdClass; }

        $params["settings"]->$type->$featureid->$name->settingid = $params["settingid"];

        if ($params["value"] !== false) {
            $params["settings"]->$type->$featureid->$name->setting = is_string($params["value"]) ? stripslashes($params["value"]) : $params["value"];
        }

        if ($params["extravalue"] !== false) {
            $params["settings"]->$type->$featureid->$name->extra = is_string($params["extravalue"]) ? stripslashes($params["extravalue"]) : $params["extravalue"];
        }

        if (isset($params["settinginfo"]["defaultsetting"])) {
            $params["settings"]->$type->$featureid->$name->defaultsetting = stripslashes($params["settinginfo"]["defaultsetting"]);
        }
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
function save_batch_settings($settings) {
	/* Loop through each setting and make it */
	foreach ($settings as $info) {
        $value = $info["value"] ?? false;
        $extravalue= $info["extravalue"] ?? false;

		/* Make or update the setting */
		if (!save_setting(
			/* If settingid is set, we are updating */
			($info["settingid"] ?? false),
			/* The setting information */
			$info,
			/* The setting value */
			$value,
            /* Extra setting value */
            $extravalue
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
function get_setting_value($type, $setting_name, $extra = false) {
	$extrasql = $extra ? "AND extra='$extra'" : "";
	return get_db_field("setting", "settings", "type='$type' AND setting_name='$setting_name'" . $extrasql);
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

function attach_setting_identifiers($settings, $type = "", $pageid = "", $featureid = "") {
    // Loop through settings and if set, add type, pageid and featureid attributes to each setting.
    foreach ($settings as $key => $setting) {
        $settings[$key]["type"] = $type;
        $settings[$key]["pageid"] = $pageid;
        $settings[$key]["featureid"] = $featureid;
    }
    return $settings;
}
?>
