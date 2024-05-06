<?php
/***************************************************************************
* pagelistlib.php - Page list function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/28/2021
* Revision: 2.3.5
***************************************************************************/

if (!isset($LIBHEADER)) { include ('header.php'); }
$PAGELISTLIB = true;

if (empty($MYVARS)) {
  $MYVARS = new \stdClass;
}
$MYVARS->search_perpage = 8;

function display_pagelist($pageid) {
global $CFG, $USER, $ROLES, $PAGE, $STYLES;
    $preview = isset($STYLES->preview) ? true : false;
    if (!$pageid) { $pageid = $CFG->SITEID; }

    $name = stripslashes(get_db_field("name", "pages", "pageid = $pageid"));
    $rolename = get_db_field("display_name", "roles", "roleid=" . user_role($USER->userid, $pageid));
    $buttons = $userid = $button_layout = NULL;
    $browse_vars = "";

    if (is_logged_in()) {
        $userid = $USER->userid;
        $button_layout = get_button_layout("pagename", 1, $pageid);
        $browse_vars = "&amp;section=search&amp;userid=$USER->userid";
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
    $pagelist = use_template("tmp/page.template", $params, "pagelist_template");

    $params = [
        "roleonpage" => get_css_box($name, $rolename, $button_layout, NULL, 'pagename'),
        "pagelistblock" => get_css_box('My Page List', $pagelist, $buttons, null, "pagelist", null, false, $preview),
    ];
    return use_template("tmp/page.template", $params, "role_on_pagelist_template");
}

    function get_pagelist($userid) {
    global $CFG, $ROLES, $USER;
    $roleid = user_role($userid, $CFG->SITEID);
    $returnme = "";
    $SQL = use_template("dbsql/pages.sql", ["userid" => $userid, "siteid" => $CFG->SITEID, "roleid" => $roleid], "my_pagelist");
    if ($result = get_db_result($SQL)) {
        $returnme = format_pagelist($result);
    }
    return $returnme;
}

function format_pagelist($pageresults) {
global $CFG, $USER, $PAGE;
  $returnme = "";
  if (!empty($pageresults)) {
	  while ($row = fetch_row($pageresults)) {
      $selected = $PAGE->id == $row['pageid'] ? "selected" : ""; // Preselect page if you are there
			$options .= use_template("tmp/page.template", ["value" => $row['pageid'], "display" => $row['name'], "selected" => $selected], "select_options_template");
	  }
		$returnme = use_template("tmp/page.template", ["options" => $options], "format_pagelist_select");
  }
  return $returnme;
}

function get_page_links($pageid, $userid = false) {
global $CFG, $ROLES, $USER;
    $links = "";
	$params = ["siteid" => $CFG->SITEID, "pageid" => $pageid, "userid" => $userid];

    if ($userid) {
        if (is_siteadmin($userid)) {
            $SQL = use_template("dbsql/pages.sql", $params, "admin_pagelinks");
        } else {
			$SQL = use_template("dbsql/pages.sql", $params, "user_pagelinks");
        }
    } else {
		$SQL = use_template("dbsql/pages.sql", $params, "default_pagelinks");
    }

    if ($result = get_db_result($SQL)) {
		$params = ["wwwroot" => $CFG->wwwroot, "pageid" => $pageid];
        while ($page = fetch_row($result)) {
			$params["page"] = $page;
            $params["canedit"] = user_is_able($userid, "editpage", $pageid);
			$links .= use_template("tmp/page.template", $params, "pagelinks_links_template");
		}
        $params["links"] = $links;
        return use_template("tmp/page.template", $params, "pagelinks_template");
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
            "image" => $CFG->wwwroot . "/images/add.png",
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($params);
    }

    if (user_is_able($USER->userid, "editpage", $pageid)) {
        $params = [
            "title" => "Create/Edit Page Links",
            "path" => $CFG->wwwroot . "/pages/page.php?action=create_edit_links&amp;pageid=$pageid",
            "refresh" => "true",
            "width" => "600",
            "height" => "500",
            "image" => $CFG->wwwroot . "/images/link.gif",
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
            "path" => $CFG->wwwroot . "/pages/page.php?action=create_edit_page&amp;pageid=$pageid",
            "refresh" => "true",
            "validate" => "true",
            "width" => "640",
            "height" => "475",
            "image"=> $CFG->wwwroot . "/images/settings.png",
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($params);
    }

    if (user_is_able($USER->userid, "editpage", $pageid)) {
        $params = [
            "title" => "Edit Page Theme",
            "path" => $CFG->wwwroot . "/pages/themes.php?action=change_theme&amp;pageid=$pageid&amp;feature=page",
            "iframe" => true,
            "refresh" => "true",
            "width" => "640",
            "height" => "600",
            "image" => $CFG->wwwroot . "/images/themes.gif",
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($params);
    }
    return $returnme;
}
?>
