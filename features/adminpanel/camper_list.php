<?php
/***************************************************************************
* camper_list.php - Page relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.0.1
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

ajaxapi([
    "id" => "all_campers_list",
    "url" => "/features/adminpanel/camper_list_script.php",
    "data" => [
        "action" => "all_campers_list",
        "removeduplicates" => "js||$('#remdup').val()||js",
        "year" => "js||$('#year').val()||js",
    ],
    "display" => "downloadfile",
]);

ajaxapi([
    "id" => "all_over_19_list",
    "url" => "/features/adminpanel/camper_list_script.php",
    "data" => [
        "action" => "all_campers_list",
        "minage" => "19",
        "removeduplicates" => "js||$('#remdup').val()||js",
        "year" => "js||$('#year').val()||js",
    ],
    "display" => "downloadfile",
]);

ajaxapi([
    "id" => "all_under_19_list",
    "url" => "/features/adminpanel/camper_list_script.php",
    "data" => [
        "action" => "all_campers_list",
        "maxage" => "19.5",
        "removeduplicates" => "js||$('#remdup').val()||js",
        "year" => "js||$('#year').val()||js",
    ],
    "display" => "downloadfile",
]);

ajaxapi([
    "id" => "all_13_19_list",
    "url" => "/features/adminpanel/camper_list_script.php",
    "data" => [
        "action" => "all_campers_list",
        "minage" => "13",
        "maxage" => "19.5",
        "removeduplicates" => "js||$('#remdup').val()||js",
        "year" => "js||$('#year').val()||js",
    ],
    "display" => "downloadfile",
]);

echo fill_template("tmp/page.template", "start_of_page_template", false, [
    "head" => fill_template("tmp/page.template", "page_js_css", false, ["dirroot" => $CFG->directory]),
]);
echo fill_template("tmp/main.template", "camper_list", "adminpanel");
echo fill_template("tmp/page.template", "end_of_page_template");
?>