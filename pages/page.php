<?php
/***************************************************************************
* page.php - Page relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 04/28/2021
* Revision: 1.5.8
***************************************************************************/

include ('header.php');

callfunction();

echo template_use("tmp/page.template", array(), "end_of_page_template");

function browse() {
global $CFG;
  $section = isset($MYVARS->GET["section"]) ? $MYVARS->GET["section"] : "search";

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

	$params = array("pagesearchselected" => $pagesearch,
									"usersearchtab" => (!is_logged_in() ? "" : template_use("tmp/page.template", array("usersearchselected" => $usersearch), "browse_usersearch_template")));
	echo template_use("tmp/page.template", $params, "browse_template");
}

function browse_search() {
global $CFG;
	$params = array("wwwroot" => $CFG->wwwroot, "search_results_box" => make_search_box(false,"pagesearch"));
	echo template_use("tmp/page.template", $params, "browse_search_template");
}

function browse_users() {
global $CFG;
	$params = array("wwwroot" => $CFG->wwwroot, "search_results_box" => make_search_box(false,"usersearch"));
	echo template_use("tmp/page.template", $params, "browse_user_template");
}

function create_edit_page() {
global $CFG, $MYVARS, $ROLES, $USER;

	if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }
  $content = '';
  $admin = is_siteadmin($USER->userid) ? true : false;
  if (isset($MYVARS->GET["pageid"])) {
      if (!user_has_ability_in_page($USER->userid, "editpage", $MYVARS->GET["pageid"])) {
          $content .= get_error_message("generic_permissions");
          return;
      }
      $page = get_db_row("SELECT * FROM pages WHERE pageid=" . $MYVARS->GET["pageid"]);
      $name = stripslashes($page["name"]);
      $description = stripslashes($page["description"]);
      $keywords = stripslashes($page["keywords"]);
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
          $hidefromvisitors = get_db_field("hidefromvisitors", "menus", "pageid=" . $MYVARS->GET["pageid"]);
          $hide_yes = $hidefromvisitors != "0" ? "selected" : "";
          $hide_no = $hide_yes == "" ? "selected" : "";
      }
  } else {
      if (!user_has_ability_in_page($USER->userid, "createpage", $CFG->SITEID)) {
          $content .= get_error_message("generic_permissions");
          return;
      }

      $menu_no = $menu_yes = $hide_no = $hide_yes = $global_yes = $global_no = $open_yes = $open_no = $name = $description = $keywords = "";
      $role_selected = 4; $menu_page = 0; $hidefromvisitors = 0;
  }

  if (isset($MYVARS->GET["pageid"])) {
  	$content .= create_validation_script("create_page_form" , template_use("tmp/page.template", array("pageid" => $MYVARS->GET["pageid"]), "edit_page_validation"));
  } else {
  	$content .= create_validation_script("create_page_form" , template_use("tmp/page.template", array(), "create_page_validation"));
  }

  $SQL = 'SELECT * FROM roles WHERE roleid > "' . $ROLES->creator . '" AND roleid < "'.$ROLES->none.'" ORDER BY roleid DESC';
  $roles = get_db_result($SQL);

	$params = array("name" => $name, "input_name_help" => get_help("input_page_name"),
									"keywords" => $keywords, "input_page_tags" => get_help("input_page_tags"),
									"description" => stripslashes($description), "input_page_summary" => get_help("input_page_summary"),
									"roleselector" => make_select("role_select", $roles, "roleid", "display_name", $role_selected), "input_page_default_role" => get_help("input_page_default_role"),
									"openno" => $open_no, "openyes" => $open_yes, "input_page_opendoor" => get_help("input_page_opendoor"),
									"globalno" => $global_no, "globalyes" => $global_yes, "input_page_siteviewable" => get_help("input_page_siteviewable"),
									"admin" => $admin, "menuno" => $menu_no, "menuyes" => $menu_yes, "input_page_menulink" => get_help("input_page_menulink"),
									"hideno" => $hide_no, "hideyes" => $hide_yes, "input_page_menulink" => get_help("input_page_menulink"),
									"menupage" => $menu_page, "hidefromvisitors" => $hidefromvisitors,
									"buttonname" => (isset($MYVARS->GET["pageid"]) ? "Submit Changes" : "Create Page"));
	$content .= template_use("tmp/page.template", $params, "create_edit_page_template");

  echo format_popup($content,'Create/Edit Page');
}

function create_edit_links() {
global $CFG, $MYVARS, $USER;
  $content = '';
  $pageid = $MYVARS->GET["pageid"];
  //Stop right there you!
  if (!user_has_ability_in_page($USER->userid, "editpage", $pageid)) {
      $content .= get_error_message("generic_permissions");
      return;
  }

	$params = array("pageid" => $pageid);
	$content .= template_use("tmp/page.template", $params, "create_edit_links_template");
  echo format_popup($content,'Edit Links');
}
?>
