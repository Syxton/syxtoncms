<?php
/***************************************************************************
* styleslib.php - Styles and Theme function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/19/2021
* Revision: 0.1.9
***************************************************************************/

if (!isset($LIBHEADER)) { include('header.php'); }
$STYLESLIB = true;
$STYLES = new \stdClass;

function get_styles($pageid, $themeid = false, $feature = '', $featureid = '') {
global $CFG, $MYVARS;
	// THEME RULES
	// Default styles are given pageid = 0
	// Global styles are given forced = 1
	// Feature type specific styles are given featureid = 0
	$revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;
	$params = [	"pageid" => $revised_pageid,
				"themeid" => $themeid,
				"feature" => $feature,
				"featureid" => $featureid,
	];

	if ($themeid === "0") { // CUSTOM THEME
		// Hasn't saved custom colors yet return defaults;
		if (!get_db_field("id", "styles", "pageid = '$revised_pageid'")) {
			$feature = "page";
			if ($default_list = get_custom_styles($revised_pageid, $feature)) {
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
		$params["themeid"] = get_page_themeid($CFG->SITEID);
		$SQL = template_use("dbsql/styles.sql", $params, "parent_theme_styles");
	}

	if ($result = get_db_result($SQL)) {
    $styles = [];
		while ($row = fetch_row($result)) {
			$styles[$row["attribute"]] = $row["value"];
		}
		return empty($styles) ? false : $styles;
	}
    return false;
}

function theme_selector($pageid, $themeid, $feature="page", $checked1="checked", $checked2="") {
global $CFG, $MYVARS, $USER;
	$params =[
		"pageid" => $pageid,
		"feature" => $feature,
		"checked1" => $checked1,
		"checked2" => $checked2,
		"iscustom" => ($themeid === '0' ),
	];

	$themeselector = [
		"properties" => [
			"name" => "themes",
			"id" => "themes",
			"onchange" => template_use("tmp/themes.template", $params, "theme_selector_menu_action_template"),
			"style" => "width:225px;",
		],
		"values" => get_db_result(template_use("dbsql/styles.sql", ["notsite" => ($pageid != $CFG->SITEID)], "theme_selector_sql")),
		"valuename" => "themeid",
		"displayname" => "name",
		"selected" => $themeid,
	];
	$params["menu"] = make_select($themeselector);
	$tabs = template_use("tmp/themes.template", $params, "theme_selector_tabs_template");
	$left = $tabs . template_use("tmp/themes.template", $params, "theme_selector_left_template");

	$pagename = get_db_field("name", "pages", "pageid = '$pageid'");
	$rolename = get_db_field("display_name", "roles", "roleid = " . get_user_role($USER->userid, $pageid));

	$params["pagelist"] = get_css_box($pagename, $rolename, false, NULL, 'pagename', NULL, $themeid, null, $pageid);
	$params["block"] = get_css_box("Title", "Content", null, null, null, null, $themeid, null, $pageid);
	$right = template_use("tmp/themes.template", $params, "theme_selector_right_template");

	return template_use("tmp/themes.template", ["left" => $left, "right" => $right], "make_template_selector_panes_template");
}

function custom_styles_selector($pageid, $feature, $featureid=false) {
	global $CFG;
		$params = [
			"pageid" => $pageid,
			"feature" => $feature,
			"featureid" => $featureid,
			"checked1" => "",
			"checked2" => "checked",
			"iscustom" => ($feature == "page"),
		];
		$tabs = template_use("tmp/themes.template", $params, "theme_selector_tabs_template");

		// Styles function
		$styles = $feature . '_default_styles';
		$styles = $styles();
	  $revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;

		if ($feature != "page") {
			include_once($CFG->dirroot . '/features/' . $feature. "/" . $feature . 'lib.php');
		}

		$style_inputs = "";
		foreach ($styles as $style) { // go through each style type and see if there is a db setting that can replace it.
			if ($feature == "page") {
				$SQL = "themeid=0 AND attribute='" . $style[1]."' AND pageid='$revised_pageid' AND feature <= '' AND featureid <= 0 ORDER BY pageid DESC";
			} else {
				$SQL = "themeid=0 AND attribute='" . $style[1]."' AND pageid='$revised_pageid' AND feature='$feature' AND featureid='$featureid' ORDER BY pageid DESC";
			}
			$value = get_db_field("value","styles", $SQL);
			if (!$value) { // No db value found, use the hard coded default value.
				$value = $style[2];
			}
			$style_inputs .= template_use("tmp/themes.template", ["style" => $style, "value" => $value, "wwwroot" => $CFG->wwwroot], "style_inputs_template");
		}

		$params["style_inputs"] = $style_inputs;
		return $tabs . template_use("tmp/themes.template", $params, "custom_styles_selector_template");
}

function get_custom_styles($pageid, $feature, $featureid=false) {
global $CFG;
	//Styles function
	$styles = $feature . '_default_styles';
	$styles = $styles();

  $revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;

	if ($feature != "page") {
		include_once($CFG->dirroot . '/features/' . $feature. "/" . $feature . 'lib.php');
	}

	$i = 0;
	foreach ($styles as $style) { // go through each style type and see if there is a db setting that can replace it.
		if ($feature == "page") {
			$SQL = "themeid=0 AND attribute='" . $style[1]."' AND pageid='$revised_pageid' AND feature <= '' AND featureid <= 0 ORDER BY pageid DESC";
		} else {
			$SQL = "themeid=0 AND attribute='" . $style[1]."' AND pageid='$revised_pageid' AND feature='$feature' AND featureid='$featureid' ORDER BY pageid DESC";
		}
		$value = get_db_field("value","styles", $SQL);
		if ($value) { // No db value found, use the hard coded default value.
			$styles[$i][2] = $value;
		}
		$i++;
	}

	return $styles;
}

function get_page_themeid($pageid) {
	$featureid = false;
	$settings = fetch_settings("page", $featureid, $pageid);

	if ($settings === false) {
	   return "";
  } else {
    if (isset($settings->page->themeid->setting)) {
      return $settings->page->themeid->setting;
    } else {
      return "";
    }
  }
}

/**
 * Make or update a style setting.
 *
 * If id is not provided in $params, it will try to find the id based on
 * the values provided. If id is found, it will update the setting. If
 * id is not found, it will insert a new setting.
 *
 * @param array $params An array of settings objects with the following keys
 *   - id: The id of the setting to update. If not provided it will be found.
 *   - feature: The feature of the setting.
 *   - pageid: The pageid of the setting.
 *   - featureid: The featureid of the setting.
 *   - attribute: The attribute of the setting.
 *   - themeid: The themeid of the setting.
 *   - value: The value of the setting.
 *
 * @return integer|boolean The id of the setting that was made/updated or false
 *   if the statement failed.
 */
function make_or_update_styles($params = []) {
	$vars = ["list" => "", "values" => "", "fields" => ["value"]];

	$fields = ["feature", "pageid", "featureid", "attribute", "themeid"];
	$vars["fields"] += $fields;

	// Check if id was not provided but can be found.
	if (!isset($params["id"])) {
		$idsql = "";
		foreach ($fields as $f) {
			if (isset($params[$f]) && $params[$f] !== false) {
				$idsql .= $idsql == "" ? "" : " AND "; // Add AND if not first field.
				$idsql .= "$f = '" . $params[$f] . "'";
			}
		}

		// Make sure you have enough info to find only a single setting.
		if ($idsql !== "" && get_db_count("SELECT * FROM styles WHERE $idsql") == 1) {
			$params["id"] = get_db_field("id", "styles", $idsql);
		}
	}

	if (isset($params["id"])) { // Update statement.
		$vars["id"] = $params["id"];
		foreach ($vars["fields"] as $field) {
			if (isset($params[$field]) && $params[$field] !== false) { // Check $value is set.
				$vars["list"] .= $vars["list"] == "" ? "" : ", "; // Add comma if not first field.
				$vars["list"] .= "$field = '" . $params[$field] . "'";	
			}
		}
		$SQL = "UPDATE styles SET " . $vars["list"] . " WHERE id = '" . $vars["id"] . "'";
	} else { // Insert statement.
		foreach ($vars["fields"] as $field) {
			if (isset($params[$field]) && $params[$field] !== false) { // Check $value or $extravalue is set.
				$vars["list"] .= $vars["list"] == "" ? "" : ", "; // Add comma if not first field.
				$vars["list"] .= "$field"; // Add field to list of fields.
				$vars["values"] .= $vars["values"] == "" ? "" : ", "; // Add comma if not first field.
				$vars["values"] .= "'" . $params[$field] . "'"; // Add value to list of values.
			}
		}
		$SQL = "INSERT INTO styles(" . $vars["list"] . ") VALUES(" . $vars["values"] . ")";
	}

	// Whether insert or update statement succeeded we will get the settingid.
	return execute_db_sql($SQL);
}

/**
 * Update settings array with new settings or update existing settings
 *
 * @param array $settings An array of settings objects
 *
 * @return boolean Returns true if all settings were updated or inserted successfully
 */
function make_or_update_styles_array($params) {
	/* Loop through each setting and make it */
	foreach ($params as $p) {
		/* Make or update the setting */
		if (!make_or_update_styles($p)) {
			/* If one setting fails, return false */
			return false;
		}
	}
	/* Return true if all settings were updated or inserted */
	return true;
}
?>
