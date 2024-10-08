<?php
/***************************************************************************
* themes.php - Themes and Styles
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.5
***************************************************************************/
include('header.php');

$head = fill_template("tmp/page.template", "page_js_css", false, ["dirroot" => $CFG->directory]);
$head .= fill_template("tmp/themes.template", "theme_manager_header_template", false, ["dirroot" => $CFG->directory]);

echo fill_template("tmp/page.template", "start_of_page_template", false, ["head" => $head]);

callfunction();

echo fill_template("tmp/page.template", "end_of_page_template");

function change_theme() {
global $CFG, $PAGE;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = clean_myvar_opt("feature", "string", false);
	$featureid = clean_myvar_opt("featureid", "int", false);

	$PAGE = (object)[];
	$PAGE->id = $pageid;
	$PAGE->themeid = get_page_themeid($PAGE->id);

	$variables = (object)[];
	$variables->pageid = $pageid;
	$variables->feature = $feature;
	$variables->featureid = $featureid;
	$params = ["variables" => $variables];

	// Allow the Theme Selector
	if ($feature == "page") {
		$params["pane"] = theme_selector($pageid, $PAGE->themeid, $feature);
	} else {
		include_once($CFG->dirroot . '/features/' . $feature . '/' . $feature . 'lib.php');
		$function = "display_$feature";
		$p = ["left" => custom_styles_selector($pageid, $feature, $featureid), "right" => $function($pageid, "side", $featureid)];
		$params["pane"] = fill_template("tmp/themes.template", "make_template_selector_panes_template", false, $p);
	}

	ajaxapi([
        "id" => "update_theme_preview",
        "url" => "/ajax/themes_ajax.php",
		"reqstring" => "colors",
        "data" => [
            "action" => "preview",
            "pageid" => $pageid,
            "feature" => $feature,
            "featureid" => $featureid,
        ],
        "display" => "color_preview",
        "event" => "none",
    ]);

	ajaxapi([
        "id" => "show_themes_selector",
        "url" => "/ajax/themes_ajax.php",
        "data" => [
            "action" => "show_themes",
            "pageid" => $pageid,
            "feature" => $feature,
        ],
        "display" => "themes_page",
		"event" => "none",
    ]);

    ajaxapi([
        "id" => "show_custom_styles",
        "url" => "/ajax/themes_ajax.php",
        "data" => [
            "action" => "show_styles",
            "pageid" => $pageid,
            "feature" => $feature,
        ],
        "display" => "themes_page",
		"event" => "none",
    ]);

	echo fill_template("tmp/themes.template", "change_theme_template", false, $params);
}
?>