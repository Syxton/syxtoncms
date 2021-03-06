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

function theme_change() {
global $CFG, $MYVARS, $USER, $PAGE;
	$themeid = dbescape($MYVARS->GET["themeid"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);

	$pagename = get_db_field("name", "pages", "pageid = '$pageid'");
	$rolename = get_db_field("display_name", "roles", "roleid = " . get_user_role($USER->userid, $pageid));

	$params["pagelist"] = get_css_box($pagename, $rolename, false, NULL, 'pagename', NULL, $themeid, false, $pageid);
	$params["block"] = get_css_box("Title", "Content", null, null, null, null, $themeid, false, $pageid);
	echo template_use("tmp/themes.template", $params, "theme_selector_right_template");
}

function show_themes() {
global $CFG, $MYVARS, $USER, $PAGE;
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$themeid = get_page_themeid($pageid);
	$themeid = $themeid !== false ? $themeid : $PAGE->thememid;

	echo theme_selector($pageid, $themeid);
}

function save_custom_theme() {
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET["featureid"]);
	$feature = dbescape($MYVARS->GET["feature"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);

	$pageid = $pageid == $CFG->SITEID ? 0 : $pageid;

	if ($feature == "page") {
		$default_list = get_custom_styles($pageid, $feature);
		$i=0;
		foreach ($default_list as $style) {
			$styles[$i] = array(false, false, "$pageid", false, $style[1], dbescape($MYVARS->GET[$style[1]]), '0', '0');
			$i++;
		}
	} else {
		$default_list = get_custom_styles($pageid, $feature, $featureid);
		foreach ($default_list as $style) {
			$styles[$i] = array(false, "$feature", "$pageid", $featureid, $style[1], dbescape($MYVARS->GET[$style[1]]), '0', '0');
			$i++;
		}
	}

	if (make_or_update_styles_array($styles)) { echo "Saved";
	} else { echo "Failed"; }
}

function preview() {
global $CFG, $MYVARS, $USER, $STYLES;
	$featureid = dbescape($MYVARS->GET["featureid"]);
	$feature = dbescape($MYVARS->GET["feature"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);

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
		$rolename = get_db_field("display_name", "roles", "roleid = " . get_user_role($USER->userid, $pageid));
		$params["pagelist"] = get_css_box($pagename, $rolename, false, NULL, 'pagename', NULL, NULL, true);
		$params["block"] = get_css_box("Title", "Content", NULL, NULL, "page", NULL, NULL, true);
		echo template_use("tmp/themes.template", $params, "theme_selector_right_template");
	} else {
		$STYLES->preview = true;
		$default_list = get_custom_styles($pageid, $feature, $featureid);
		foreach ($default_list as $style) {
			$temparray[$style[1]] = dbescape($MYVARS->GET[$style[1]]);
		}
		$STYLES->$feature = $temparray;

		include_once($CFG->dirroot . '/features/'.$feature.'/'.$feature.'lib.php');
		$function = "display_$feature";
		echo $function($pageid, "side", $featureid);
		unset($STYLES->preview);
	}
}

function show_styles() {
global $CFG, $MYVARS, $USER;
	$feature = dbescape($MYVARS->GET["feature"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);

	if ($feature == "page") {
		$left = custom_styles_selector($pageid, $feature);

		$pagename = get_db_field("name", "pages", "pageid = '$pageid'");
		$rolename = get_db_field("display_name", "roles", "roleid = " . get_user_role($USER->userid, $pageid));

		$params = array();
		$params["pagelist"] = get_css_box($pagename, $rolename, false, NULL, 'pagename', NULL, '0', NULL, $pageid);
		$params["block"] = get_css_box("Title", "Content", NULL, NULL, NULL, NULL, '0', NULL, $pageid);
		$right = template_use("tmp/themes.template", $params, "theme_selector_right_template");

		echo template_use("tmp/themes.template", array("left" => $left, "right" => $right), "make_template_selector_panes_template");
	} else {
    	include_once($CFG->dirroot . '/features/'.$feature.'/'.$feature.'lib.php');
    	$function = "display_$feature";
			$left = custom_styles_selector($pageid, $feature, $featureid);
			$right = $function($pageid, "side", $featureid);
			echo template_use("tmp/themes.template", array("left" => $left, "right" => $right), "make_template_selector_panes_template");
	}
}

function change_theme_save() {
global $CFG, $MYVARS, $USER;
	$themeid = dbescape($MYVARS->GET["themeid"]);
	$pageid = dbescape($MYVARS->GET["pageid"]);

	//Save selected Theme
	if ($themeid == "" && $pageid != $CFG->SITEID) {
		execute_db_sql("DELETE FROM settings WHERE pageid='$pageid' AND setting_name='themeid'");
	} else {
		make_or_update_setting(false, 'page', $pageid , 0, "themeid", $themeid, false, false);
	}

	//Page has theme selected show themes
	echo theme_selector($pageid, $themeid);
}
?>
