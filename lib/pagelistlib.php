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
	$rolename = get_db_field("display_name", "roles", "roleid=" . get_user_role($USER->userid, $pageid));
	$buttons = $userid = $button_layout = NULL;
	$browse_vars = "";

  if (is_logged_in()) {
		$userid = $USER->userid;
		$button_layout = get_button_layout("pagename", 1, $pageid);
		$browse_vars = "&amp;section=search&amp;userid=$USER->userid";
		$buttons = get_button_layout("pagelist", 1, $pageid);
  }
	$params = array();
	$params["pagelist"] = !is_siteadmin($USER->userid) ? get_pagelist($USER->userid) : "";
	$params["browse"] = make_modal_links(array("title" => "Browse for Pages",
																							"path" => "$CFG->wwwroot/pages/page.php?action=browse$browse_vars",
																						"iframe" => "true",
																						 "width" => "640",
																						"height" => "626"));
	$params["pagelinks"] = get_page_links($pageid, $userid);
	$pagelist = template_use("tmp/page.template", $params, "pagelist_template");

	return template_use("tmp/page.template",
											array("roleonpage" => get_css_box($name, $rolename, $button_layout, NULL, 'pagename'),
														"pagelistblock" => get_css_box('My Page List', $pagelist, $buttons, null, "pagelist", null, false, $preview)),
											"role_on_pagelist_template");
}

function get_pagelist($userid) {
global $CFG, $ROLES, $USER;
  $roleid = get_user_role($userid, $CFG->SITEID);
	$returnme = "";
	$SQL = template_use("dbsql/pages.sql", array("userid" => $userid, "siteid" => $CFG->SITEID, "roleid" => $roleid), "my_pagelist");
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
			$options .= template_use("tmp/page.template", array("value" => $row['pageid'], "display" => $row['name'], "selected" => $selected), "select_options_template");
	  }
		$returnme = template_use("tmp/page.template", array("options" => $options), "format_pagelist_select");
  }
  return $returnme;
}

function get_page_links($pageid, $userid = false) {
global $CFG, $ROLES, $USER;
    $links = "";
		$params = array("siteid" => $CFG->SITEID, "pageid" => $pageid, "userid" => $userid);

    if ($userid) {
      if (is_siteadmin($userid)) {
        $SQL = template_use("dbsql/pages.sql", $params, "admin_pagelinks");
      } else {
				$SQL = template_use("dbsql/pages.sql", $params, "user_pagelinks");
      }
    } else {
			$SQL = template_use("dbsql/pages.sql", $params, "default_pagelinks");
    }

    if ($result = get_db_result($SQL)) {
			$params = array("wwwroot" => $CFG->wwwroot, "pageid" => $pageid);
      while ($page = fetch_row($result)) {
				$params["page"] = $page;
        $params["canedit"] = user_has_ability_in_page($userid, "editpage", $pageid);
				$links .= template_use("tmp/page.template", $params, "pagelinks_links_template");
			}
			$params["links"] = $links;
			return template_use("tmp/page.template", $params, "pagelinks_template");
    }
    return "";
}

function pagelist_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER, $PAGE;
  $returnme = "";
  if (user_has_ability_in_page($USER->userid, "createpage", $CFG->SITEID)) {
    $returnme .= make_modal_links(array("title" => "Create", "path" => $CFG->wwwroot . "/pages/page.php?action=create_edit_page", "refresh" => "true",
																				"validate" => "true", "width" => "640", "height" => "475", "image" => $CFG->wwwroot . "/images/add.png",
																				"class" => "slide_menu_button"));
  }
  if (user_has_ability_in_page($USER->userid, "editpage", $pageid)) {
    $returnme .= make_modal_links(array("title" => "Create/Edit Page Links", "path" => $CFG->wwwroot . "/pages/page.php?action=create_edit_links&amp;pageid=$pageid",
																				"refresh" => "true", "width" => "600", "height" => "500", "image" => $CFG->wwwroot . "/images/link.gif",
																				"class" => "slide_menu_button"));
  }
  return $returnme;
}

function pagename_buttons($pageid) {
global $CFG, $USER, $PAGE;
  $returnme = "";
  if (user_has_ability_in_page($USER->userid, "editpage", $pageid)) {
    $returnme .= make_modal_links(array("title" => "Edit Page Settings", "path" => $CFG->wwwroot . "/pages/page.php?action=create_edit_page&amp;pageid=$pageid",
																				"refresh" => "true", "validate" => "true", "width" => "640", "height" => "475", "image"=> $CFG->wwwroot . "/images/settings.png",
																				"class" => "slide_menu_button"));
  }
	if (user_has_ability_in_page($USER->userid, "editpage", $pageid)) {
    $returnme .= make_modal_links(array("title" => "Edit Page Theme", "path" => $CFG->wwwroot . "/pages/themes.php?action=change_theme&amp;pageid=$pageid&amp;feature=page",
																				"iframe" => "true", "refresh" => "true", "width" => "640", "height" => "600", "image" => $CFG->wwwroot . "/images/themes.gif",
																				"class" => "slide_menu_button"));
  }
	return $returnme;
}
?>
