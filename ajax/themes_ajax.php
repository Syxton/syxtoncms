<?php
/***************************************************************************
* themes_ajax.php - Themes and Styles ajax
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.6
***************************************************************************/

include ('header.php');
update_user_cookie();

callfunction();

function theme_change() {
global $CFG, $MYVARS, $USER, $PAGE;
	$themeid = dbescape($MYVARS->GET["themeid"]);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	$pagename = get_db_field("name", "pages", "pageid = '$pageid'");
	$rolename = get_db_field("display_name", "roles", "roleid = " . user_role($USER->userid, $pageid));

	$params["pagelist"] = get_css_box($pagename, $rolename, false, NULL, 'pagename', NULL, $themeid, false, $pageid);
	$params["block"] = get_css_box("Title", "Content", null, null, null, null, $themeid, false, $pageid);
	echo fill_template("tmp/themes.template", "theme_selector_right_template", false, $params);
}

function show_themes() {
global $CFG, $MYVARS, $USER, $PAGE;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$themeid = get_page_themeid($pageid);
	$themeid = $themeid !== false ? $themeid : $PAGE->thememid;

	echo theme_selector($pageid, $themeid);
}

function save_custom_theme() {
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET["featureid"]);
	$feature = dbescape($MYVARS->GET["feature"]);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	$pageid = $pageid == $CFG->SITEID ? 0 : $pageid;
	$styles = [];
	if ($feature == "page") {
		$default_list = get_custom_styles($pageid, $feature);
		foreach ($default_list as $style) {
			$styles[] = [
				"pageid" => $pageid,
				"attribute" => $style[1],
				"value" => dbescape($MYVARS->GET[$style[1]]),
				"themeid" => '0',
				"forced" =>'0',
			];
		}
	} else {
		$default_list = get_custom_styles($pageid, $feature, $featureid);
		foreach ($default_list as $style) {
			$styles[] = [
				"feature" => $feature,
				"pageid" => $pageid,
				"featureid" => $featureid,
				"attribute" => $style[1],
				"value" => dbescape($MYVARS->GET[$style[1]]),
				"themeid" => '0',
				"forced" =>'0',
			];
		}
	}

	if (make_or_update_styles_array($styles)) {
		echo "Saved";
	} else {
		echo "Failed";
	}
}

function preview() {
global $CFG, $MYVARS, $USER, $STYLES;
	$featureid = dbescape($MYVARS->GET["featureid"]);
	$feature = dbescape($MYVARS->GET["feature"]);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	if ($feature == "page") {
		$default_list = get_custom_styles($pageid, $feature);
		foreach ($default_list as $style) {
			if (isset($MYVARS->GET[$style[1]])) {
				$temparray[$style[1]] = dbescape($MYVARS->GET[$style[1]]);
			} else {
				$temparray[$style[1]] = $style["2"];
			}
		}

		$STYLES->pagename = $temparray;
		$STYLES->page = $temparray;

		$pagename = get_db_field("name", "pages", "pageid = '$pageid'");
		$rolename = get_db_field("display_name", "roles", "roleid = " . user_role($USER->userid, $pageid));
		$params["pagelist"] = get_css_box($pagename, $rolename, false, NULL, 'pagename', NULL, NULL, true);
		$params["block"] = get_css_box("Title", "Content", NULL, NULL, "page", NULL, NULL, true);
		echo fill_template("tmp/themes.template", "theme_selector_right_template", false, $params);
	} else {
		$STYLES->preview = true;
		$default_list = get_custom_styles($pageid, $feature, $featureid);
		foreach ($default_list as $style) {
			$temparray[$style[1]] = dbescape($MYVARS->GET[$style[1]]);
		}
		$STYLES->$feature = $temparray;

		include_once($CFG->dirroot . '/features/' . $feature . '/' . $feature . 'lib.php');
		$function = "display_$feature";
		echo $function($pageid, "side", $featureid);
		unset($STYLES->preview);
	}
}

function show_styles() {
global $CFG, $MYVARS, $USER;
	$feature = clean_myvar_req("feature", "string");
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	if ($feature == "page") {
		$pagename = get_db_field("name", "pages", "pageid = '$pageid'");
		$rolename = get_db_field("display_name", "roles", "roleid = " . user_role($USER->userid, $pageid));

		$params = [];
		$params["pagelist"] = get_css_box($pagename, $rolename, false, NULL, 'pagename', NULL, '0', NULL, $pageid);
		$params["block"] = get_css_box("Title", "Content", NULL, NULL, NULL, NULL, '0', NULL, $pageid);
		$p = [
			"left" => custom_styles_selector($pageid, $feature),
			"right" => fill_template("tmp/themes.template", "theme_selector_right_template", false, $params),
		];
		echo fill_template("tmp/themes.template", "make_template_selector_panes_template", false, $p);
	} else {
  		include_once($CFG->dirroot . '/features/' . $feature . '/' . $feature . 'lib.php');
  		$function = "display_$feature";
		$p = [
			"left" => custom_styles_selector($pageid, $feature, $featureid),
			"right" => $function($pageid, "side", $featureid),
		];
		echo fill_template("tmp/themes.template", "make_template_selector_panes_template", false, $p);
	}
}

function change_theme_save() {
global $CFG, $MYVARS, $USER;
	$themeid = clean_myvar_opt("themeid", "int", false);
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	//Save selected Theme
	if (!$themeid && $pageid !== $CFG->SITEID) {
		execute_db_sql("DELETE FROM settings WHERE pageid='$pageid' AND setting_name='themeid'");
	} else {
		save_setting(false, ["type" => "page", "pageid" => $pageid, "setting_name" => "themeid"], $themeid);
	}

	//Page has theme selected show themes
	echo theme_selector($pageid, $themeid);
}
?>
