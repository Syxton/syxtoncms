<?php
/***************************************************************************
* styleslib.php - Styles and Theme function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.9
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define('STYLESLIB', true);

$STYLES = new \stdClass;

function get_styles($pageid, $themeid = false, $feature = '', $featureid = '') {
global $CFG, $MYVARS;
	$pageid = clean_var_opt($pageid, "int", get_pageid());
	$themeid = clean_var_opt($themeid, "int", false);
	$feature = clean_var_opt($feature, "string", "");
	$featureid = clean_var_opt($featureid, "int", "");

	// THEME RULES
	// Default styles are given pageid = 0
	// Global styles are given forced = 1
	// Feature type specific styles are given featureid = 0
	$revised_pageid = $pageid === $CFG->SITEID ? 0 : $pageid;
	$params = [
		"pageid" => $revised_pageid,
		"themeid" => $themeid,
		"feature" => $feature,
		"featureid" => $featureid,
	];

	if (!$themeid === 0) { // CUSTOM THEME
		// Hasn't saved custom colors yet return defaults;
		if (!get_db_field("id", "styles", "pageid = ||pageid||", ["pageid" => $revised_pageid])) {
			$feature = "page";
			if ($default_list = get_custom_styles($revised_pageid, $feature)) {
				foreach ($default_list as $style) {
					$temparray[$style[1]] = isset($MYVARS->GET[$style[1]]) ? dbescape($MYVARS->GET[$style[1]]) : false;
				}
			}
			return $temparray;
		}
		$SQL = fetch_template("dbsql/styles.sql", "custom_theme_styles");
	} elseif ($themeid > 0) { // PAGE THEME IS SET TO A SAVED THEME
		$SQL = fetch_template("dbsql/styles.sql", "saved_theme_styles");
	} else { // NO THEME...LOOK FOR PARENT THEMES
		$params["themeid"] = get_page_themeid($CFG->SITEID);
		$SQL = fetch_template("dbsql/styles.sql", "parent_theme_styles");
	}

	if ($result = get_db_result($SQL, $params)) {
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
			"onchange" => fill_template("tmp/themes.template", "theme_selector_menu_action_template", false, $params),
			"style" => "width:225px;",
		],
		"values" => get_db_result(fetch_template("dbsql/styles.sql", "theme_selector_sql", false, ["notsite" => ($pageid != $CFG->SITEID)])),
		"valuename" => "themeid",
		"displayname" => "name",
		"selected" => $themeid,
	];
	$params["menu"] = make_select($themeselector);
	$tabs = fill_template("tmp/themes.template", "theme_selector_tabs_template", false, $params);
	$left = $tabs . fill_template("tmp/themes.template", "theme_selector_left_template", false, $params);

	$title = get_db_field("name", "pages", "pageid = ||pageid||", ["pageid" => $pageid]);
	$title = '<span class="box_title_text">' . $title . '</span>';
	$rolename = get_db_field("display_name", "roles", "roleid = " . user_role($USER->userid, $pageid));

	$params["pagelist"] = get_css_box($title, $rolename, false, NULL, 'pagename', NULL, $themeid, null, $pageid);
	$params["block"] = get_css_box('<span class="box_title_text">Title</span>', "Content", null, null, null, null, $themeid, null, $pageid);
	$right = fill_template("tmp/themes.template", "theme_selector_right_template", false, $params);

	return fill_template("tmp/themes.template", "make_template_selector_panes_template", false, ["left" => $left, "right" => $right]);
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
		$tabs = fill_template("tmp/themes.template", "theme_selector_tabs_template", false, $params);

		// Styles function
		$styles = $feature . '_default_styles';
		$styles = $styles();
		$revised_pageid = $pageid == $CFG->SITEID ? 0 : $pageid;

		if ($feature != "page") {
			include_once($CFG->dirroot . '/features/' . $feature. "/" . $feature . 'lib.php');
		}

		$style_inputs = "";
		foreach ($styles as $style) { // go through each style type and see if there is a db setting that can replace it.
			$p = [
				"pageid" => $revised_pageid,
				"attribute" => $style[1],
			];
			$featuresql = "";
			if ($feature !== "page") {
				$p["featureid"] = $featureid;
				$p["feature"] = $feature;
				$featuresql = " AND feature = ||feature|| AND featureid = ||featureid|| ";
			}
			$value = get_db_field("value", "styles", "themeid = 0 AND attribute = ||attribute|| AND pageid = ||pageid|| $featuresql ORDER BY pageid DESC", $p);
			if (!$value) { // No db value found, use the hard coded default value.
				$value = $style[2];
			}
			$style_inputs .= fill_template("tmp/themes.template", "style_inputs_template", false, ["style" => $style, "value" => $value, "wwwroot" => $CFG->wwwroot]);
		}

		$params["style_inputs"] = $style_inputs;
		return $tabs . fill_template("tmp/themes.template", "custom_styles_selector_template", false, $params);
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
		$value = get_db_field("value", "styles", $SQL);
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
	$fields = ["feature", "pageid", "featureid", "attribute", "themeid"];
    $sqlfields = "";
    $sqlvalues = "";

	// Check if id was not provided but can be found.
	if (!isset($params["id"])) {
		$idsql = "";
		foreach ($fields as $field) {
			if (isset($params[$field]) && $params[$field] !== false) {
				$idsql .= $idsql == "" ? "" : " AND "; // Add AND if not first field.
				$idsql .= "$field = '" . $params[$field] . "'";
			}
		}

		// Make sure you have enough info to find only a single setting.
		if ($idsql !== "" && get_db_count("SELECT * FROM styles WHERE $idsql") == 1) {
			$params["id"] = get_db_field("id", "styles", $idsql);
		}
	}

    $fields += ["value"]; // Add value field to list.
	if (isset($params["id"])) { // Update statement.
		$vars["id"] = $params["id"];
		foreach ($fields as $field) {
			if (isset($params[$field]) && $params[$field] !== false) { // Check $value is set.
				$sqlfields .= empty($sqlfields) ? "" : ", "; // Add comma if not first field.
				$sqlfields .= "$field = '" . $params[$field] . "'";	
			}
		}
		$SQL = "UPDATE styles SET $sqlfields WHERE id = '" . $vars["id"] . "'";
	} else { // Insert statement.
		foreach ($fields as $field) {
			if (isset($params[$field]) && $params[$field] !== false) { // Check if field is set.
				$sqlfields .= empty($sqlfields) ? "" : ", "; // Add comma if not first field.
				$sqlfields .= "$field"; // Add field to list of fields.
				$sqlvalues .= empty($sqlvalues) ? "" : ", "; // Add comma if not first field.
				$sqlvalues .= "'" . $params[$field] . "'"; // Add value to list of values.
			}
		}
		$SQL = "INSERT INTO styles($sqlfields) VALUES($sqlvalues)";
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
