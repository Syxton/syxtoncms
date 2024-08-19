<?php
/***************************************************************************
* page.php - Page relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.5.8
***************************************************************************/

if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}

echo fill_template("tmp/page.template", "start_of_page_template");

callfunction();

echo fill_template("tmp/page.template", "end_of_page_template");

function browse() {
    $section = clean_myvar_opt("section", "string", "search");

    switch($section) {
        case "users":
            $pagesearch = "notselected";
            $usersearch = "selected";
            break;
        default:
            $pagesearch = "selected";
            $usersearch = "notselected";
            break;
    }

    $searchtab = "";
    if (is_logged_in()) {
        $searchtab = fill_template("tmp/page.template", "browse_usersearch_template", false, ["usersearchselected" => $usersearch]);
    }

    $params = [
        "pagesearchselected" => $pagesearch,
        "usersearchtab" => $searchtab,
    ];

    echo fill_template("tmp/page.template", "browse_template", false, $params);
}

function browse_search() {
global $CFG;
    $params = [
        "wwwroot" => $CFG->wwwroot,
        "search_results_box" => make_search_box(false, "pagesearch"),
    ];

    ajaxapi([
        "id" => "pagesearch",
        "url" => "/ajax/page_ajax.php",
        "data" => [
            "action" => "pagesearch",
            "searchwords" => "js||encodeURIComponent($('#searchbox').val())||js",
        ],
        "event" => "submit",
        "loading" => "loading_overlay_pagesearch",
        "display" => "searchcontainer_pagesearch",
    ]);

    echo fill_template("tmp/page.template", "browse_search_template", false, $params);
}

function browse_users() {
global $CFG;
    $params = [
        "wwwroot" => $CFG->wwwroot,
        "search_results_box" => make_search_box(false, "usersearch"),
    ];

    ajaxapi([
		"id" => "usersearch",
		"url" => "/ajax/page_ajax.php",
		"data" => [
			"action" => "usersearch",
			"searchwords" => "js||encodeURIComponent($('#searchbox').val())||js",
		],
		"event" => "submit",
		"loading" => "loading_overlay_usersearch",
		"display" => "searchcontainer_usersearch",
	]);

    echo fill_template("tmp/page.template", "browse_user_template", false, $params);
}

function create_edit_page() {
global $CFG, $MYVARS, $ROLES, $USER;
    if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }
    $content = '';
    $admin = is_siteadmin($USER->userid) ? true : false;
    $pageid = clean_myvar_opt("pageid", "int", false);
    if ($pageid) {
        if (!user_is_able($USER->userid, "editpage", $pageid)) {
            $content .= error_string("generic_permissions");
            return;
        }
        $page = get_db_row("SELECT * FROM pages WHERE pageid = ||pageid||", ["pageid" => $pageid]);
        $name = $page["name"];
        $description = $page["description"];
        $keywords = $page["keywords"];
        $role_selected = $page["default_role"];
        $global_yes = $page["siteviewable"] != "0" ? "selected" : "";
        $global_no = $global_yes == "" ? "selected" : "";
        $open_yes = $page["opendoorpolicy"] != "0" ? "selected" : "";
        $open_no = $open_yes == "" ? "selected" : "";
        $menu_yes = $page["menu_page"] != "0" ? "selected" : "";
        $menu_no = $menu_yes == "" ? "selected" : "";
        $menu_page = $page["menu_page"];
        $hide_no = $hide_yes = "";
        if ($page["menu_page"] != "0") {
            $hidefromvisitors = get_db_field("hidefromvisitors", "menus", "pageid=" . $pageid);
            $hide_yes = $hidefromvisitors != "0" ? "selected" : "";
            $hide_no = $hide_yes == "" ? "selected" : "";
        }
    } else {
        if (!user_is_able($USER->userid, "createpage", $CFG->SITEID)) {
            $content .= error_string("generic_permissions");
            return;
        }

        $menu_no = $menu_yes = $hide_no = $hide_yes = $global_yes = $global_no = $open_yes = $open_no = $name = $description = $keywords = "";
        $role_selected = DEFAULT_PAGEROLE;
        $menu_page = 0;
        $hidefromvisitors = 0;
    }

    if ($pageid) {
        ajaxapi([
            "id" => "valid_edit_page_form",
            "url" => "/ajax/page_ajax.php",
            "data" => [
                "action" => "edit_page",
                "name" => "js||encodeURIComponent($('#name').val())||js",
                "description" => "js||encodeURIComponent($('#summary').val())||js",
                "keywords" => "js||encodeURIComponent($('#keywords').val())||js",
                "defaultrole" => "js||encodeURIComponent($('#role_select').val())||js",
                "opendoor" => "js||encodeURIComponent($('#opendoor').val())||js",
                "siteviewable" => "js||encodeURIComponent($('#siteviewable').val())||js",
                "menu_page" => "js||encodeURIComponent($('#menu_page').val())||js",
                "hidefromvisitors" => "js||encodeURIComponent($('#hidefromvisitors').val())||js",
                "pageid" => $pageid,
            ],
            "ondone" => "close_modal();",
            "event" => "none",
        ]);

        $content .= create_validation_script("create_page_form" , "valid_edit_page_form();");
    } else {
        ajaxapi([
            "id" => "valid_create_page_form",
            "url" => "/ajax/page_ajax.php",
            "data" => [
                "action" => "create_page",
                "name" => "js||encodeURIComponent($('#name').val())||js",
                "description" => "js||encodeURIComponent($('#summary').val())||js",
                "keywords" => "js||encodeURIComponent($('#keywords').val())||js",
                "defaultrole" => "js||encodeURIComponent($('#role_select').val())||js",
                "opendoor" => "js||encodeURIComponent($('#opendoor').val())||js",
                "siteviewable" => "js||encodeURIComponent($('#siteviewable').val())||js",
                "menu_page" => "js||encodeURIComponent($('#menu_page').val())||js",
                "hidefromvisitors" => "js||encodeURIComponent($('#hidefromvisitors').val())||js",
            ],
            "ondone" => "create_page_display(data);",
            "event" => "none",
        ]);
        $content .= create_validation_script("create_page_form", "valid_create_page_form();");
    }

    $SQL = 'SELECT * FROM roles WHERE roleid > ||creator|| AND roleid < ||none|| ORDER BY roleid DESC';
    $roleselector = [
            "properties" => [
            "name" => "role_select",
            "id" => "role_select",
        ],
        "values" => get_db_result($SQL, ["creator" => $ROLES->creator, "none" => $ROLES->none]),
        "valuename" => "roleid",
        "displayname" => "display_name",
        "selected" => $role_selected,
    ];
    $params = [
        "name" => $name,
        "input_name_help" => get_help("input_page_name"),
        "keywords" => $keywords,
        "input_page_tags" => get_help("input_page_tags"),
        "description" => stripslashes($description),
        "input_page_summary" => get_help("input_page_summary"),
        "roleselector" => make_select($roleselector),
        "input_page_default_role" => get_help("input_page_default_role"),
        "openno" => $open_no,
        "openyes" => $open_yes,
        "input_page_opendoor" => get_help("input_page_opendoor"),
        "globalno" => $global_no,
        "globalyes" => $global_yes,
        "input_page_siteviewable" => get_help("input_page_siteviewable"),
        "admin" => $admin,
        "menuno" => $menu_no,
        "menuyes" => $menu_yes,
        "input_page_menulink" => get_help("input_page_menulink"),
        "hideno" => $hide_no,
        "hideyes" => $hide_yes,
        "input_page_menulink" => get_help("input_page_menulink"),
        "menupage" => $menu_page,
        "hidefromvisitors" => $hidefromvisitors ?? false,
        "buttonname" => $pageid ? "Submit Changes" : "Create Page",
    ];
    $content .= fill_template("tmp/page.template", "create_edit_page_template", false, $params);

    echo format_popup($content, 'Create/Edit Page');
}

function create_edit_links() {
global $USER;
    $content = '';
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    if (!user_is_able($USER->userid, "editpage", $pageid)) {
        $content .= error_string("generic_permissions");
        return;
    }

    ajaxapi([
        "id" => "addremovelinks",
        "url" => "/ajax/page_ajax.php",
        "data" => [
            "action" => "get_new_link_form",
            "pageid" => $pageid,
        ],
        "display" => "links_mode_span",
    ]);

    ajaxapi([
        "id" => "sortlinks",
        "url" => "/ajax/page_ajax.php",
        "data" => [
            "action" => "get_link_manager",
            "pageid" => $pageid,
        ],
        "display" => "links_mode_span",
    ]);

    $params = ["pageid" => $pageid];
    $content .= fill_template("tmp/page.template", "create_edit_links_template", false, $params);
    echo format_popup($content, 'Edit Links');
}
?>
