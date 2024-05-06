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

echo use_template("tmp/page.template", [], "end_of_page_template");

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

  $searchtab = "";
  if (is_logged_in()) {
    $searchtab = use_template("tmp/page.template", ["usersearchselected" => $usersearch], "browse_usersearch_template");
  }
  
  $params = [ "pagesearchselected" => $pagesearch,
							"usersearchtab" => $searchtab,
  ];

	echo use_template("tmp/page.template", $params, "browse_template");
}

function browse_search() {
global $CFG;
	$params = [ "wwwroot" => $CFG->wwwroot,
              "search_results_box" => make_search_box(false, "pagesearch"),
  ];
	echo use_template("tmp/page.template", $params, "browse_search_template");
}

function browse_users() {
global $CFG;
  $params = [ "wwwroot" => $CFG->wwwroot,
              "search_results_box" => make_search_box(false, "usersearch"),
  ];
	echo use_template("tmp/page.template", $params, "browse_user_template");
}

function create_edit_page() {
global $CFG, $MYVARS, $ROLES, $USER;

	if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }
  $content = '';
  $admin = is_siteadmin($USER->userid) ? true : false;
  if (isset($MYVARS->GET["pageid"])) {
      if (!user_is_able($USER->userid, "editpage", $MYVARS->GET["pageid"])) {
          $content .= error_string("generic_permissions");
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
      if (!user_is_able($USER->userid, "createpage", $CFG->SITEID)) {
          $content .= error_string("generic_permissions");
          return;
      }

      $menu_no = $menu_yes = $hide_no = $hide_yes = $global_yes = $global_no = $open_yes = $open_no = $name = $description = $keywords = "";
      $role_selected = 4; $menu_page = 0; $hidefromvisitors = 0;
  }

  if (isset($MYVARS->GET["pageid"])) {
  	$content .= create_validation_script("create_page_form" , use_template("tmp/page.template", ["pageid" => $MYVARS->GET["pageid"]], "edit_page_validation"));
  } else {
  	$content .= create_validation_script("create_page_form" , use_template("tmp/page.template", [], "create_page_validation"));
  }

  $SQL = 'SELECT * FROM roles WHERE roleid > "' . $ROLES->creator . '" AND roleid < "' . $ROLES->none . '" ORDER BY roleid DESC';
  $roleselector = [
    "properties" => [
      "name" => "role_select",
      "id" => "role_select",
    ],
    "values" => get_db_result($SQL),
    "valuename" => "roleid",
    "displayname" => "display_name",
    "selected" => $role_selected,
  ];
	$params = [ "name" => $name,
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
							"buttonname" => (isset($MYVARS->GET["pageid"]) ? "Submit Changes" : "Create Page"),
  ];
	$content .= use_template("tmp/page.template", $params, "create_edit_page_template");

  echo format_popup($content, 'Create/Edit Page');
}

function create_edit_links() {
global $CFG, $MYVARS, $USER;
  $content = '';
  $pageid = $MYVARS->GET["pageid"];
  //Stop right there you!
  if (!user_is_able($USER->userid, "editpage", $pageid)) {
      $content .= error_string("generic_permissions");
      return;
  }

	$params = ["pageid" => $pageid];
	$content .= use_template("tmp/page.template", $params, "create_edit_links_template");
  echo format_popup($content,'Edit Links');
}
?>
