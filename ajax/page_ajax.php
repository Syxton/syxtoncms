<?php
/***************************************************************************
* page_ajax.php - Page backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.4.3
***************************************************************************/

include ('header.php');
update_user_cookie();

$CFG->sitesearch = new \stdClass;
$CFG->sitesearch->perpage = 8;

callfunction();

function edit_page() {
global $CFG, $MYVARS;
	$pageid = clean_myvar_req("pageid", "int");
	$text = clean_myvar_req("name", "string");

	$description = clean_myvar_opt("description", "string", "");
	$keywords = clean_myvar_opt("keywords", "string", "");
	$defaultrole = clean_myvar_opt("defaultrole", "int", DEFAULT_PAGEROLE);
	$opendoor = clean_myvar_opt("opendoor", "int", 0);
	$siteviewable = clean_myvar_opt("siteviewable", "int", 0);
	$menu_page = clean_myvar_opt("menu_page", "int", 0);
	$hidefromvisitors = clean_myvar_opt("hidefromvisitors", "int", 0);

	try {
		start_db_transaction();
		if ($pageid) {
			$params = [
				"pageid" => $pageid,
				"short_name" => create_page_shortname($text),
				"name" => $text,
				"description" => $description,
				"keywords" => $keywords,
				"siteviewable" => $siteviewable,
				"default_role" => $defaultrole,
				"opendoorpolicy" => $opendoor,
				"menu_page" => $menu_page,
			];

			if ($menu_page) { // Menu Page
				// Create, Edit menu item.
				modify_menu_page($params);
			} else {
				// If not a menu, delete from menu table.
				execute_db_sql(fetch_template("dbsql/pages.sql", "delete_page_menus"), ["pageid" => $pageid]);
			}
			
			if (execute_db_sql(fetch_template("dbsql/pages.sql", "edit_page"), $params)) {
				echo "Page edited successfully";
			}
		}
		commit_db_transaction();
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
	}
}

function create_page() {
global $CFG, $USER, $ROLES, $PAGE;
	$name = clean_myvar_req("name", "string");
	$description = clean_myvar_opt("description", "string", "");
	$keywords = clean_myvar_opt("keywords", "string", "");
	$defaultrole = clean_myvar_opt("defaultrole", "int", DEFAULT_PAGEROLE);
	$opendoor = clean_myvar_opt("opendoor", "int", 0);
	$siteviewable = clean_myvar_opt("siteviewable", "int", 0);
	$menu_page = clean_myvar_opt("menu_page", "int", 0);

	update_user_cookie();

	try {
		start_db_transaction();
		$params = [
			"name" => $name,
			"short_name" => create_page_shortname($name),
			"description" => $description,
			"keywords" => $keywords,
			"siteviewable" => $siteviewable,
			"default_role" => $defaultrole,
			"opendoorpolicy" => $opendoor,
			"menu_page" => $menu_page,
		];
		$pageid = execute_db_sql(fetch_template("dbsql/pages.sql", "create_page"), $params);

		if ($menu_page) {
			// Create, Edit menu item.
			modify_menu_page($params);
		}

		$role = execute_db_sql(fetch_template("dbsql/roles.sql", "insert_role_assignment"),  ["pageid" => $pageid, "userid" => $USER->userid, "roleid" => $ROLES->creator]);
		commit_db_transaction();

		set_pageid($pageid);
		log_entry("page", $pageid, "Page Created");
		return json_encode(["true", $pageid, "Course Created"]);
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
	}
	echo json_encode(["false", $CFG->SITEID, error_string("page_not_created")]);
}

function pagesearch() {
global $CFG, $MYVARS, $USER;
  $searchwords = trim($MYVARS->GET["searchwords"]);
  // no search words given
  if ($searchwords == "") {
	$searchwords = '%';
  }

  // logged in
  $loggedin = is_logged_in() ? true : false;
  $userid = $loggedin ? $USER->userid : "";

  // is a site admin
  $admin = $loggedin && is_siteadmin($userid) ? true : false;

  //restrict possible page listings
  $siteviewableonly = $loggedin ? "" : " AND p.siteviewable=1";
  $opendoorpolicy = $admin ? "" : " AND (p.opendoorpolicy=1 OR p.siteviewable=1)";

  //Create the page limiter
  $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
  $firstonpage = $CFG->sitesearch->perpage * $pagenum;
  $limit = " LIMIT $firstonpage," . $CFG->sitesearch->perpage;
  $words = explode(" ", $searchwords);

  $i = 0; $searchstring = "";
  while (isset($words[$i])) {
	  $searchpart = "(p.name LIKE '%" . $words[$i] . "%' OR p.keywords LIKE '%" . $words[$i] . "%' OR p.description LIKE '%" . $words[$i] . "%')";
	  $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
	  $i++;
  }

  if ($loggedin) {
	  $roleid = user_role($userid, $CFG->SITEID);
	  $check_rights = "";
	  if (empty($admin)) { // Is my site role allowed to view pages.  REPLACE WITH user_has_ability_in_page?????
		  $check_rights = ", IF(p.pageid IN (SELECT p.pageid
											   FROM pages p
										 INNER JOIN roles_ability ry
												 ON ry.roleid = '$roleid'
												AND ry.ability = 'viewpages'
												AND allow = '1'
											  WHERE (p.pageid IN (SELECT ra.pageid
																	FROM roles_assignment ra
																   WHERE ra.userid = '$userid'
																	 AND ra.pageid = p.pageid
																	 AND ra.confirm = 0)
												 OR p.pageid IN (SELECT rau.pageid
																   FROM roles_ability_peruser rau
																  WHERE rau.userid = '$userid'
																	AND rau.ability = 'viewpages'
																	AND allow = '1'))
												AND p.pageid NOT IN (SELECT rau.pageid
																	   FROM roles_ability_peruser rau
																	  WHERE rau.userid = '$userid'
																		AND rau.ability = 'viewpages'
																		AND allow = '0')
												AND p.pageid != '$CFG->SITEID'
												AND p.menu_page != '1'), 1, 0) as added";
	  }

	  $SQL = "SELECT p.*
			  $check_rights
				FROM pages p
			   WHERE p.pageid != '$CFG->SITEID'
					 AND ($searchstring)
					 AND p.menu_page = 0
				ORDER BY p.name";
  } else {
	  $SQL = "SELECT p.*
				FROM pages p
			   WHERE p.pageid != '$CFG->SITEID'
  					 AND ($searchstring)
	$siteviewableonly
  				 ORDER BY p.name";
  }

  $total = get_db_count($SQL); //get the total for all pages returned.
  $SQL .= $limit; //Limit to one page of return.
  $pages = get_db_result($SQL);

  $count = $total > (($pagenum + 1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
  $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

  $params = [ "resultsfound" => ($count > 0),
			  "searchresults" => "",
			  "searchwords" => $searchwords,
			  "searchtype" => "pagesearch",
			  "isprev" => ($pagenum > 0),
			  "isnext" => ($firstonpage + $CFG->sitesearch->perpage < $total),
			  "wwwroot" => $CFG->wwwroot,
			  "prev_pagenum" => ($pagenum - 1),
			  "next_pagenum" => ($pagenum + 1),
			  "pagenum" => $pagenum,
			  "viewing" => ($firstonpage + 1),
			  "amountshown" => $amountshown,
			  "total" => $total];

	if ($count > 0) {
		while ($page = fetch_row($pages)) {
			$linked = true;
			$params["col3"] = "";
			if ($loggedin && !$admin) {
				if ($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_is_able($userid, "assign_roles", $page["pageid"])) {
					$vars = [
					"must_request" => false,
					"can_add_remove" => user_is_able($userid, "add_page", $CFG->SITEID),
					"isadd" => ($page["added"] == 0),
					"wwwroot" => $CFG->wwwroot,
					"pagenum" => $pagenum,
					"searchwords" => $searchwords,
					"pageid" => $page["pageid"],
					];
					$params["col3"] = use_template("tmp/page_ajax.template", $vars, "search_pages_buttons_template");
				} else {
					$linked = false;
					$alreadyrequested = get_db_row("SELECT * FROM roles_assignment WHERE userid='$userid' AND pageid='" . $page["pageid"] . "' AND confirm=1") ? true : false;
					$vars = [ "must_request" => true,
							"alreadyrequested" => $alreadyrequested,
							"wwwroot" => $CFG->wwwroot,
							"pagenum" => $pagenum,
							"searchwords" => $searchwords,
							"pageid" => $page["pageid"],
					];
					$params["col3"] = use_template("tmp/page_ajax.template", $vars, "search_pages_buttons_template");
				}
			}

			$params["linked"] = $linked;
			$vars = [
				"must_request" => false,
				"can_add_remove" => false,
				"alreadyrequested" => false,
				"isadd" => false,
				"wwwroot" => $CFG->wwwroot,
				"pagenum" => $pagenum,
				"searchwords" => $searchwords,
				"linked" => $linked,
				"pageid" => $page["pageid"],
				"admin" => is_siteadmin($userid),
				"name" => substr($page["name"], 0, 30),
			];
			$params["col1"] = use_template("tmp/page_ajax.template", $vars, "search_pages_link_template");
			$params["col2"] = htmlentities(strip_tags(substr($page["description"], 0, 50)));
			$params["col3"] = use_template("tmp/page_ajax.template", $vars, "search_pages_buttons_template");
			$params["searchresults"] = $params["searchresults"] . use_template("tmp/page_ajax.template", $params, "search_row_template");
		}
	}
	echo use_template("tmp/page_ajax.template", $params, "search_template");
}

function usersearch() {
global $CFG, $MYVARS, $USER;
  $userid = $USER->userid;
  $searchwords = trim($MYVARS->GET["searchwords"]);
  //no search words given
  if ($searchwords == "") {
	  $searchwords = '%';
  }

  echo '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';

  //is a site admin
  $admin = is_siteadmin($userid) ? true : false;

  //Create the page limiter
  $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
  $firstonpage = $CFG->sitesearch->perpage * $pagenum;
  $limit = " LIMIT $firstonpage," . $CFG->sitesearch->perpage;
  $words = explode(" ", $searchwords);

  $i = 0; $searchstring = "";
  while (isset($words[$i])) {
	  $searchpart = "(u.fname LIKE '%" . $words[$i] . "%' OR u.lname LIKE '%" . $words[$i] . "%' OR u.email LIKE '%" . $words[$i] . "%')";
	  $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
	  $i++;
  }

  $SQL = "SELECT u.*
			FROM users u
		   WHERE ($searchstring)
		   ORDER BY u.lname";

  $total = get_db_count($SQL); //get the total for all pages returned.
  $SQL .= $limit; //Limit to one page of return.
  $users = get_db_result($SQL);
  $count = $total > (($pagenum + 1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
  $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

  $params = [ "resultsfound" => ($count > 0),
			  "searchresults" => "",
			  "searchwords" => $searchwords,
			  "searchtype" => "usersearch",
			  "isprev" => ($pagenum > 0),
			  "isnext" => ($firstonpage + $CFG->sitesearch->perpage < $total),
			  "wwwroot" => $CFG->wwwroot,
			  "prev_pagenum" => ($pagenum - 1),
			  "next_pagenum" => ($pagenum + 1),
			  "pagenum" => $pagenum,
			  "viewing" => ($firstonpage + 1),
			  "amountshown" => $amountshown,
			  "total" => $total,
  ];

  if ($count > 0) {
	while ($user = fetch_row($users)) {
	  $params["isuser"] = ($userid != $user["userid"] && !is_siteadmin($user["userid"]));
	  $params["userid"] = $userid;
	  $params["user"] = $user;
	  $params["col1"] = $user["fname"] . " " . $user["lname"];
	  $params["col2"] = $user["email"];
	  $params["col3"] = use_template("tmp/page_ajax.template", $params, "search_users_buttons_template");
	  $params["searchresults"] = $params["searchresults"] . use_template("tmp/page_ajax.template", $params, "search_row_template");
	}
  }

  echo use_template("tmp/page_ajax.template", $params, "search_template");
}

function get_new_link_form() {
global $MYVARS, $CFG, $USER;
  echo use_template("tmp/page_ajax.template", ["pageid" => $MYVARS->GET['pageid']], "new_link_form_template");
}

function get_link_manager() {
global $MYVARS, $CFG, $USER;
	$pageid = clean_myvar_req("pageid", "int");
	$returnme = "";
	$i = 0;
	$params = ["pageid" => $pageid, "wwwroot" => $CFG->wwwroot, "haslinks" => false];

	$SQL = "SELECT *
			  FROM pages_links
			 WHERE hostpageid = '$pageid'
		  ORDER BY sort";
	if ($links = get_db_result($SQL)) {
	  $params["haslinks"] = true;
	  $SQL = "SELECT *
				FROM pages_links
			   WHERE hostpageid = '$pageid'";
	  $count = get_db_count($SQL);

	  while ($link = fetch_row($links)) {
		$rowparams = ["wwwroot" => $CFG->wwwroot,
					  "order" => $i,
					  "nextorder" => ($i + 1),
					  "pageid" => $pageid,
					  "linkdisplay" => stripslashes($link['linkdisplay']),
					  "linkid" => $link["linkid"],
					  "notfirstrow" =>  ($i > 0),
					  "notlastrow" => ($i < ($count - 1)),
		];
		$linkrows .= use_template("tmp/page_ajax.template", $rowparams, "sortable_links_template");
		$i++;
	  }

	  $params["links"] = $linkrows;
	}

	echo use_template("tmp/page_ajax.template", $params, "links_manager_template");
}

function linkpagesearch() {
global $CFG, $MYVARS, $USER;
  $searchwords = trim($MYVARS->GET["searchwords"]);
  $pageid = get_pageid();

  // no search words given
  if ($searchwords == "") {
	$searchwords = '%';
  }

  // logged in
  $loggedin = is_logged_in() ? true : false;
  $userid = $loggedin ? $USER->userid : "";

  // is a site admin
  $admin = $loggedin && is_siteadmin($userid) ? true : false;

  //restrict possible page listings
  $siteviewableonly = $loggedin ? "" : " AND p.siteviewable=1";
  $opendoorpolicy = $admin ? "" : " AND (p.opendoorpolicy=1 OR p.siteviewable=1)";

  //Create the page limiter
  $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
  $firstonpage = $CFG->sitesearch->perpage * $pagenum;
  $limit = " LIMIT $firstonpage," . $CFG->sitesearch->perpage;
  $words = explode(" ", $searchwords);

  $i = 0; $searchstring = "";
  while (isset($words[$i])) {
	  $searchpart = "(p.name LIKE '%" . $words[$i] . "%' OR p.keywords LIKE '%" . $words[$i] . "%' OR p.description LIKE '%" . $words[$i] . "%')";
	  $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
	  $i++;
  }

  if ($loggedin) {
	  $roleid = user_role($userid, $CFG->SITEID);
	  $SQL = "SELECT p.*, (SELECT pl.linkid
							 FROM pages_links pl
							WHERE pl.linkpageid = p.pageid
							  AND pl.hostpageid = '$pageid') as alreadylinked
				FROM pages p
			   WHERE p.pageid != '$CFG->SITEID'
				 AND ($searchstring)
				 AND p.pageid != '$pageid'
			ORDER BY p.name";
  }

  $total = get_db_count($SQL); //get the total for all pages returned.
  $SQL .= $limit; //Limit to one page of return.
  $pages = get_db_result($SQL);

  $count = $total > (($pagenum + 1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
  $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

  $params = [ "resultsfound" => ($count > 0),
			  "searchresults" => "",
			  "searchwords" => $searchwords,
			  "searchtype" => "linkpagesearch",
			  "isprev" => ($pagenum > 0),
			  "isnext" => ($firstonpage + $CFG->sitesearch->perpage < $total),
			  "wwwroot" => $CFG->wwwroot,
			  "prev_pagenum" => ($pagenum - 1),
			  "next_pagenum" => ($pagenum + 1),
			  "pagenum" => $pagenum,
			  "viewing" => ($firstonpage + 1),
			  "amountshown" => $amountshown,
			  "total" => $total,
			  "loggedin" => $loggedin,
			  "pageid" => $pageid,
  ];

  if ($count > 0) {
	while ($page = fetch_row($pages)) {
	  $params["alreadylinked"] = ($loggedin && empty($page["alreadylinked"]));
	  $params["confirmopen"] = ($page["siteviewable"] == 0);
	  $params["linkpageid"] = $page["pageid"];

	  $params["col1"] = substr(stripslashes($page["name"]), 0, 30);
	  $params["col2"] = substr(stripslashes(strip_tags($page["description"])), 0, 100);
	  $params["col3"] = ($loggedin) ? use_template("tmp/page_ajax.template", $params, "search_linkpagesearch_buttons_template") : "";
	  $params["searchresults"] = $params["searchresults"] . use_template("tmp/page_ajax.template", $params, "search_row_template");
	}
  }

  echo use_template("tmp/page_ajax.template", $params, "search_template");
}

function make_page_link() {
global $MYVARS, $CFG, $USER;
	$pageid = clean_myvar_req("pageid", "int");
	$linkid = $MYVARS->GET['linkpageid'];
	$SQL = "SELECT *
			  FROM pages_links
			 WHERE hostpageid = '$pageid'";
	$sort = get_db_count($SQL);
	$sort++;
	$page_name = get_db_field("name", "pages", "pageid='$linkid'");

	$SQL = "INSERT INTO pages_links (hostpageid, linkpageid, sort, linkdisplay)
				 VALUES($pageid, $linkid, $sort, '$page_name')";
	execute_db_sql($SQL);
	emptyreturn();
}


function unlink_page() {
global $MYVARS;
  $linkpageid = $MYVARS->GET['linkpageid'];
  $pageid = clean_myvar_req("pageid", "int");
  $SQL = "DELETE FROM pages_links
				WHERE hostpageid = '$pageid'
				  AND linkpageid = '$linkpageid'";
  execute_db_sql($SQL);
  resort_links($pageid);
  emptyreturn();
}

function move_link() {
global $MYVARS;
  $linkid = dbescape($MYVARS->GET['linkid']);
  $linkdisplay = dbescape($MYVARS->GET['linkdisplay']);
  $pageid = clean_myvar_req("pageid", "int");
  $direction = $MYVARS->GET['direction'];
  $change = $direction == "up" ? -1 : 1;

  $SQL = "SELECT *
			FROM pages_links
		   WHERE linkid = '$linkid'";
  $link1 = get_db_row($SQL);

  $position1 = $link1["sort"];
  $position2 = $position1 + $change;

  $SQL = "SELECT *
			FROM pages_links
		   WHERE hostpageid = '$pageid'
			 AND sort = $position2";
  $link2 = get_db_row($SQL);

  $SQL = "UPDATE pages_links
			 SET sort = $position2, linkdisplay = '$linkdisplay'
		   WHERE linkid = '$linkid'";
  execute_db_sql($SQL);

  $SQL = "UPDATE pages_links
			 SET sort = $position1
		   WHERE linkid = " . $link2["linkid"];
  execute_db_sql($SQL);

  resort_links($pageid);
  emptyreturn();
}

function rename_link() {
global $MYVARS;
  $linkid = $MYVARS->GET['linkid'];
  $linkdisplay = dbescape($MYVARS->GET['linkdisplay']);

  $SQL = "UPDATE pages_links
			 SET linkdisplay = '$linkdisplay'
		   WHERE linkid = '$linkid'";
  echo $SQL;
  execute_db_sql($SQL);
  emptyreturn();
}

function resort_links($pageid) {
global $MYVARS;
  $i = 1;
  $SQL = "SELECT *
			FROM pages_links
		   WHERE hostpageid = '$pageid'
		ORDER BY sort";
  if ($links = get_db_result($SQL)) {
	while ($link = fetch_row($links)) {
	  $SQL = "UPDATE pages_links
				 SET sort = $i
			   WHERE linkid = " . $link["linkid"];
	  execute_db_sql($SQL);
	  $i++;
	}
  }
}

function get_inviteable_pages() {
global $CFG, $MYVARS;
	$inviter = $MYVARS->GET["inviter"];
	$invitee = $MYVARS->GET["invitee"];

	$params = [
		"properties" => [
			"name" => "page_invite_list",
			"id" => "page_invite_list",
			"style" => "width:150px;",
			"onchange" => use_template("tmp/page_ajax.template", ["invitee" => $invitee], "get_inviteable_button_template"),
		],
		"values" => pages_user_is_able($inviter, "invite", false, false),
		"valuename" => "pageid",
		"displayname" => "name",
		"firstoption" => "",
		"exclude" => pages_user_is_able($invitee, "viewpages", false, false),
	];

	echo make_select($params);
}

function invite_user() {
global $CFG, $MYVARS;
  $userid = $MYVARS->GET["userid"];
  $pageid = clean_myvar_opt("pageid", "int", get_pageid());
  $defaultrole = get_db_field("default_role", "pages", "pageid='$pageid'");

  $SQL = "SELECT confirm
			FROM roles_assignment
		   WHERE userid = '$userid'
			 AND roleid = '$defaultrole'
			 AND pageid = '$pageid'
			 AND confirm = '2'";
  $invite_received = get_db_row($SQL);

  $SQL = "INSERT INTO roles_assignment (userid, roleid, pageid, confirm)
			   VALUES($userid, $defaultrole, $pageid, 2)";
  if ($invite_received || execute_db_sql($SQL)) {
	echo "Invite Sent";
  } else {
	echo "Invite Error";
  }
}

function refresh_page_links() {
global $CFG, $USER, $MYVARS;
  $userid = $USER->userid;
  $pageid = clean_myvar_opt("pageid", "int", get_pageid());

  if (!defined('PAGELISTLIB')) { include_once ($CFG->dirroot . '/lib/pagelistlib.php'); }

  echo get_page_links($pageid, $userid);
}

function delete_page_ajax() {
	$pageid = clean_myvar_req("pageid", "int");
	if (delete_page($pageid)) {
		echo "deleted";
		exit();
	}

	echo "error";
}

function change_subscription() {
global $CFG, $MYVARS, $USER;
  $userid = $USER->userid;
  $pageid = clean_myvar_opt("pageid", "int", get_pageid());
  $pagenum = $MYVARS->GET["pagenum"];

  $params = ["wwwroot" => $CFG->wwwroot, "can_add" => false, "pageid" => $pageid, "pagenum" => $pagenum, "userid" => $userid];
  $subscription_added = subscribe_to_page($pageid, $userid, true);
  if (!$subscription_added) {
	$SQL = "SELECT added, opendoorpolicy, siteviewable
			  FROM pages
			 WHERE pageid = '$pageid'";
	$page = get_db_row($SQL);
	if ($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_is_able($userid, "assign_roles", $pageid)) {
	  $params["can_add"] = true;
	}
  }

  echo use_template("tmp/page_ajax.template", $params, "change_subscription_template");
}

function add_request() {
global $CFG, $MYVARS;
  $userid = $USER->userid;
  $pageid = clean_myvar_opt("pageid", "int", get_pageid());
  $roleid = get_db_field("default_role", "pages", "pageid='$pageid'");
  $SQL = "INSERT INTO roles_assignment (userid, roleid, pageid, confirm)
			   VALUES($userid, $roleid, $pageid, 1)";

  $request_added = execute_db_sql($SQL);
  $params = ["request_added" => $request_added, "wwwroot" => $CFG->wwwroot, "pageid" => $pageid];
  echo use_template("tmp/page_ajax.template", $params, "add_remove_request_template");
}

function remove_request() {
global $CFG, $MYVARS;
  $userid = $USER->userid;
  $pageid = clean_myvar_opt("pageid", "int", get_pageid());

  $SQL = "DELETE FROM roles_assignment
				WHERE userid = '$userid'
				  AND pageid = '$pageid'
				  AND confirm = 1";
  $request_removed = execute_db_sql($SQL);

  $params = ["request_removed" => (!$request_removed), "wwwroot" => $CFG->wwwroot, "pageid" => $pageid];
  echo use_template("tmp/page_ajax.template", $params, "add_remove_request_template");
}
?>
