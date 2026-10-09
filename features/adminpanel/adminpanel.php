<?php
/***************************************************************************
* adminpanel.php - Site Admin Panel Area
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.6
***************************************************************************/

if (empty($_POST["aslib"])) {
	if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

    $head = fill_template("tmp/page.template", "page_js_css", false, ["dirroot" => $CFG->directory]);
    echo fill_template("tmp/page.template", "start_of_page_template", false, ["head" => $head]);

    callfunction();

    echo fill_template("tmp/main.template", "header", "adminpanel");

    echo fill_template("tmp/page.template", "end_of_page_template");
}

function site_administration() {
    site_admin_javascript();
    echo fill_template("tmp/main.template", "site_administration", "adminpanel");
}


/**
 * Core Form Editor (System admin).
 * Named form engine under lib/formengine; staff_app uses events staffform UI until fully extracted.
 */
function form_editor() {
    global $CFG, $USER;
    if (!is_siteadmin($USER->userid ?? 0)) {
        echo getlang("generic_permissions");
        return;
    }
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $form_key = clean_myvar_opt("form_key", "string", defined("FORM_KEY_STAFF_APP") ? FORM_KEY_STAFF_APP : "staff_app");
    if (!defined("FORMEDITORLIB")) {
        include_once($CFG->dirroot . "/lib/formengine/formeditorlib.php");
    }
    form_editor_page($form_key, $pageid, true);
}


function site_admin_javascript() {
    ajaxapi([
        "id" => "user_admin",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "user_admin",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "camper_list",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "camper_list",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "site_versions",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "site_versions",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "unit_tests",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "unit_tests",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "get_phpinfo",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "get_phpinfo",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "admin_email_tester",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "admin_email_tester",
        ],
        "display" => "display",
    ]);
    ajaxapi([
        "id" => "admin_email_sender",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "admin_email_sender",
        ],
        "display" => "display",
    ]);
}
?>