<?php
/***************************************************************************
* pagelistlib.php - Page list function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.3.5
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define('PAGELISTLIB', true);

global $MYVARS;

collect_vars();
if (!defined('SEARCH_PERPAGE')) { define('SEARCH_PERPAGE', 8); }

function display_pagelist($pageid) {
global $CFG, $USER, $ROLES, $PAGE, $STYLES;
    $preview = isset($STYLES->preview) ? true : false;
    if (!$pageid) { $pageid = $CFG->SITEID; }

    $title = get_db_field("name", "pages", "pageid = ||pageid||", ["pageid" => $pageid]);
    $rolename = get_db_field("display_name", "roles", "roleid=" . user_role($USER->userid, $pageid));
    $buttons = $userid = $button_layout = NULL;
    $browse_vars = "";

    if (is_logged_in()) {
        $userid = $USER->userid;
        $button_layout = get_button_layout("pagename", 1, $pageid);
        $browse_vars = "&section=search&userid=$USER->userid";
        $buttons = get_button_layout("pagelist", 1, $pageid);
    }
    $params = [
        "pagelist" => !is_siteadmin($USER->userid) ? get_pagelist($USER->userid) : "",
        "browse" => make_modal_links([
                        "title" => "Browse for Pages",
                        "path" => "$CFG->wwwroot/pages/page.php?action=browse$browse_vars",
                        "iframe" => true,
                        "width" => "640",
                        "height" => "626",
                    ]),
        "pagelinks" => get_page_links($pageid, $userid),
    ];
    $pagelist = fill_template("tmp/page.template", "pagelist_template", false, $params);

    $params = [
        "roleonpage" => get_css_box($title, $rolename, $button_layout, NULL, 'pagename'),
        "pagelistblock" => get_css_box('<span class="box_title_text">My Page List</span>', $pagelist, $buttons, null, "pagelist", null, false, $preview),
    ];

    return fill_template("tmp/page.template", "role_on_pagelist_template", false, $params);
}

function get_pagelist($userid) {
global $CFG, $ROLES, $USER;
    $roleid = user_role($userid, $CFG->SITEID);
    $returnme = "";
    $SQL = fetch_template("dbsql/pages.sql", "my_pagelist");
    if ($result = get_db_result($SQL, ["userid" => $userid, "siteid" => $CFG->SITEID, "roleid" => $roleid])) {
        $returnme = format_pagelist($result);
    }
    return $returnme;
}

function format_pagelist($pageresults) {
global $PAGE;
    $returnme = $options = "";
    if (!empty($pageresults)) {
        while ($row = fetch_row($pageresults)) {
            $selected = $PAGE->id == $row['pageid'] ? "selected" : ""; // Preselect page if you are there
            $options .= fill_template("tmp/page.template", "select_options_template", false, ["value" => $row['pageid'], "display" => $row['name'], "selected" => $selected]);
        }
        $returnme = fill_template("tmp/page.template", "format_pagelist_select", false, ["options" => $options]);
    }
    return $returnme;
}

function get_page_links($pageid, $userid = false) {
global $CFG, $ROLES, $USER;
    $links = "";
	$params = ["siteid" => $CFG->SITEID, "pageid" => $pageid, "userid" => $userid];

	if ($userid) {
		if (is_siteadmin($userid)) {
			$SQL = fetch_template("dbsql/pages.sql", "admin_pagelinks");
		} else {
			$SQL = fetch_template("dbsql/pages.sql", "user_pagelinks");
		}
	} else {
		$SQL = fetch_template("dbsql/pages.sql", "default_pagelinks");
	}

	if ($result = get_db_result($SQL, $params)) {
            ajaxapi([
                "id" => "refresh_page_links",
                "url" => "/ajax/page_ajax.php",
                "data" => [
                    "action" => "refresh_page_links",
                    "pageid" => $pageid,
                ],
                "display" => "page_links_div",
                "event" => "none",
            ]);

            ajaxapi([
                "id" => "unlink_page",
                "paramlist" => "linkpageid",
                "if" => "confirm('Are you sure you want to unlink this page?')",
                "url" => "/ajax/page_ajax.php",
                "data" => [
                    "action" => "unlink_page",
                    "linkpageid" => "js||linkpageid||js",
                    "pageid" => $pageid,
                ],
                "event" => "none",
                "ondone" => "refresh_page_links();",
            ]);
		$params = ["wwwroot" => $CFG->wwwroot, "pageid" => $pageid];
		while ($page = fetch_row($result)) {
			$params["page"] = $page;
			$params["canedit"] = user_is_able($userid, "editpage", $pageid);
			$links .= fill_template("tmp/page.template", "pagelinks_links_template", false, $params);
		}
		$params["links"] = $links;
		return fill_template("tmp/page.template", "pagelinks_template", false, $params);
	}
	return "";
}

function pagelist_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER, $PAGE;
    $returnme = "";
    if (user_is_able($USER->userid, "createpage", $CFG->SITEID)) {
        $params = [
            "title" => "Create",
            "path" => $CFG->wwwroot . "/pages/page.php?action=create_edit_page",
            "refresh" => "true",
            "validate" => "true",
            "width" => "640",
            "height" => "475",
            "icon" => icon("plus"),
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($params);
    }

    if (user_is_able($USER->userid, "editpage", $pageid)) {
        $params = [
            "title" => "Create/Edit Page Links",
            "path" => $CFG->wwwroot . "/pages/page.php?action=create_edit_links&pageid=$pageid",
            "refresh" => "true",
            "width" => "600",
            "height" => "500",
            "icon" => icon("link"),
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($params);
    }
    return $returnme;
}

function pagename_buttons($pageid) {
global $CFG, $USER, $PAGE;
    $returnme = "";
    if (user_is_able($USER->userid, "editpage", $pageid)) {
        $params = [
            "title" => "Edit Page Settings",
            "path" => $CFG->wwwroot . "/pages/page.php?action=create_edit_page&pageid=$pageid",
            "refresh" => "true",
            "validate" => "true",
            "width" => "640",
            "height" => "475",
            "icon"=> icon("sliders"),
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($params);
    }

    if (user_is_able($USER->userid, "editpage", $pageid)) {
        $params = [
            "title" => "Edit Page Theme",
            "path" => $CFG->wwwroot . "/pages/themes.php?action=change_theme&pageid=$pageid&feature=page",
            "iframe" => true,
            "refresh" => "true",
            "width" => "640",
            "height" => "600",
            "icon" => icon("palette"),
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($params);
    }
    return $returnme;
}
?>
