<?php
/***************************************************************************
* themes.php - Themes and Styles
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.5
***************************************************************************/
include('header.php');

$params = ["dirroot" => $CFG->directory];
echo use_template("tmp/page.template", $params, "page_js_css");
echo use_template("tmp/themes.template", $params, "theme_manager_header_template");

callfunction();

echo use_template("tmp/page.template", [], "end_of_page_template");

function change_theme() {
global $CFG, $MYVARS, $USER, $PAGE;
	$pageid = dbescape($MYVARS->GET["pageid"]);
	$feature = isset($MYVARS->GET['feature']) ? dbescape($MYVARS->GET['feature']) : false;
	$featureid = isset($MYVARS->GET['featureid']) ? dbescape($MYVARS->GET['featureid']) : false;
	$PAGE = new \stdClass;
	$PAGE->id = $pageid;
	$PAGE->themeid = get_page_themeid($PAGE->id);

	$variables = new \stdClass();
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
		$params["pane"] = use_template("tmp/themes.template", ["left" => custom_styles_selector($pageid, $feature, $featureid), "right" => $function($pageid, "side", $featureid)], "make_template_selector_panes_template");
	}

	
	echo use_template("tmp/themes.template", $params, "change_theme_template");
}
?>