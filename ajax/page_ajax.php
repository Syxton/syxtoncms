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
global $CFG;
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
				"hidefromvisitors" => $hidefromvisitors,
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
	$hidefromvisitors = clean_myvar_opt("hidefromvisitors", "int", 0);

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
			"hidefromvisitors" => $hidefromvisitors,
		];
		$pageid = execute_db_sql(fetch_template("dbsql/pages.sql", "create_page"), $params);

		if ($menu_page) {
			// Create, Edit menu item.
			modify_menu_page($params);
		}

		$role = execute_db_sql(fetch_template("dbsql/roles.sql", "insert_role_assignment"),  ["pageid" => $pageid, "userid" => $USER->userid, "roleid" => $ROLES->creator, "confirm" => 0]);
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
global $CFG, $USER;
	$searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
	$pagenum = clean_myvar_opt("pagenum", "int", 0);

	// no search words given
	$dbsearchwords = $searchwords == "" ? "%" : $searchwords;

	// logged in
	$loggedin = is_logged_in() ? true : false;
	$userid = $loggedin ? $USER->userid : "";

	// is a site admin
	$admin = $loggedin && is_siteadmin($userid) ? true : false;

	// Begin main search sql param list.
	$searchparams = ["siteid" => $CFG->SITEID];

	$i = 0; $searchstring = "";
	$words = explode(" ", $dbsearchwords);
	while (isset($words[$i])) {
		$searchparams["words$i"] = "%" . $words[$i] . "%";
		$searchpart = "(p.name LIKE ||words$i|| OR p.keywords LIKE ||words$i|| OR p.description LIKE ||words$i||)";
		$searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
		$i++;
	}

	$checkrights = "";
	if ($loggedin) {
		$searchparams["userid"] = $USER->userid;
		$searchparams["roleid"] = user_role($userid, $CFG->SITEID);
		if (!$admin) { // Is my site role allowed to view pages.  REPLACE WITH user_has_ability_in_page?????
			$checkrights = fetch_template("dbsql/pages.sql", "page_search_checkrights");
		}
	}

	//restrict possible page listings
	$viewablepages = "";
	$viewablepages .= $loggedin ? ($admin ? "" : "")  : " AND p.siteviewable = 1";

	$sqlparams = [
		"admin" => $admin,
		"checkrights" => $checkrights,
		"viewablepages" => $viewablepages,
		"searchstring" => $searchstring,
	];
	$SQL = fill_template("dbsql/pages.sql", "page_search", false, $sqlparams, true);

	// Get the total for all pages returned.
	$total = get_db_count($SQL, $searchparams);

	//Create the page limiter
	$firstonpage = $CFG->sitesearch->perpage * $pagenum;
	$SQL .= " LIMIT $firstonpage," . $CFG->sitesearch->perpage; // only return a single page worth of results.

	$count = $total > (($pagenum + 1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
	$amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

	$resultsparams = [
		"resultsfound" => ($count > 0),
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
		"total" => $total,
	];

	if ($pages = get_db_result($SQL, $searchparams)) {
		if ($count > 0) {
			while ($page = fetch_row($pages)) {
				$linked = $must_request = true;
				$can_add_remove = $alreadyrequested = $isadd = false;

				$resultsparams["col3"] = "";
				if ($loggedin && !$admin) {
					// Can view without requests.
					if ($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_is_able($userid, "assign_roles", $page["pageid"])) {
						$can_add_remove = user_is_able($userid, "add_page", $CFG->SITEID);
						$isadd = $page["added"] == "1" ? false : true;
						$must_request = false;
					} else { // Must request access.
						$linked = false;
						$SQL = fetch_template("dbsql/roles.sql", "get_role_assignment");
						$alreadyrequested = get_db_row($SQL, ["userid" => $userid, "pageid" => $page["pageid"], "confirm" => 1]) ? true : false;
					}
				}

				if ($admin) {
					$must_request = false; // Admins can always enter pages, and don't need to request access.
					$isadd = false; // Admins can always enter pages and not need to add a role to enter a page.
					$can_add_remove = false; // Admins can always enter pages, and don't need to add or remove access.
				}

				$resultsparams["linked"] = $linked;
				$vars = [
					"must_request" => $must_request,
					"can_add_remove" => $can_add_remove,
					"alreadyrequested" => $alreadyrequested,
					"isadd" => $isadd,
					"wwwroot" => $CFG->wwwroot,
					"pagenum" => $pagenum,
					"searchwords" => $searchwords,
					"linked" => $linked,
					"pageid" => $page["pageid"],
					"admin" => is_siteadmin($userid),
					"name" => substr($page["name"], 0, 30),
				];
				$resultsparams["col1"] = fill_template("tmp/page_ajax.template", "search_pages_link_template", false, $vars);
				$resultsparams["col2"] = htmlentities(strip_tags(substr($page["description"], 0, 50)));
				$resultsparams["col3"] = fill_template("tmp/page_ajax.template", "search_pages_buttons_template", false, $vars);
				$resultsparams["searchresults"] = $resultsparams["searchresults"] . fill_template("tmp/page_ajax.template", "search_row_template", false, $resultsparams);
			}
		}
	}
	echo fill_template("tmp/page_ajax.template", "search_template", false, $resultsparams);
}

function usersearch() {
global $CFG, $USER;
	$userid = $USER->userid;
	$searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
	$pagenum = clean_myvar_opt("pagenum", "int", 0);

	// no search words given
	$dbsearchwords = $searchwords == "" ? "%" : $searchwords;

	echo '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';

	//is a site admin
	$admin = is_siteadmin($userid) ? true : false;

	//Create the page limiter
	$firstonpage = $CFG->sitesearch->perpage * $pagenum;
	$limit = " LIMIT $firstonpage," . $CFG->sitesearch->perpage;
	$words = explode(" ", $dbsearchwords);

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

	$params = [
		"resultsfound" => ($count > 0),
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
			$params["userid"] = $userid;
			$params["user"] = $user;
			$params["col1"] = $user["fname"] . " " . $user["lname"];
			$params["col2"] = $user["email"];
			$params["col3"] = "";

			if ($user["userid"] !== $userid && !is_siteadmin($user["userid"])) {
				$params["col3"] = fill_template("tmp/page_ajax.template", "search_users_buttons_template", false, $params);
			}

			$params["searchresults"] = $params["searchresults"] . fill_template("tmp/page_ajax.template", "search_row_template", false, $params);
		}
	}
	echo fill_template("tmp/page_ajax.template", "search_template", false, $params);
}

function get_new_link_form() {
    $pageid = clean_myvar_req("pageid", "int");
    echo fill_template("tmp/page_ajax.template", "new_link_form_template", false, ["pageid" => $pageid]);
}

function get_link_manager() {
global $CFG;
	$pageid = clean_myvar_req("pageid", "int");
	$returnme = "";
	$i = 0;
	$params = ["pageid" => $pageid, "wwwroot" => $CFG->wwwroot, "haslinks" => false];

	$SQL = "SELECT *
			  FROM pages_links
			 WHERE hostpageid = ||hostpageid||
		  ORDER BY sort";
    if ($links = get_db_result($SQL, ["hostpageid" => $pageid])) {
        $count = get_db_count($SQL, ["hostpageid" => $pageid]);
        $params["haslinks"] = true;
		  $linkrows = "";
        while ($link = fetch_row($links)) {
            $rowparams = [
                "wwwroot" => $CFG->wwwroot,
                "order" => $i,
                "nextorder" => ($i + 1),
                "pageid" => $pageid,
                "linkdisplay" => stripslashes($link['linkdisplay']),
                "linkid" => $link["linkid"],
                "notfirstrow" =>  ($i > 0),
                "notlastrow" => ($i < ($count - 1)),
            ];
            $linkrows .= fill_template("tmp/page_ajax.template", "sortable_links_template", false, $rowparams);
            $i++;
        }

	  $params["links"] = $linkrows;
	}

	echo fill_template("tmp/page_ajax.template", "links_manager_template", false, $params);
}

function linkpagesearch() {
global $CFG, $USER;
	$searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$pagenum = clean_myvar_opt("pagenum", "int", 0);

	// no search words given
	$dbsearchwords = $searchwords == "" ? "%" : $searchwords;

	// logged in
	$loggedin = is_logged_in() ? true : false;
	$userid = $loggedin ? $USER->userid : "";

	// is a site admin
	$admin = $loggedin && is_siteadmin($userid) ? true : false;

	//restrict possible page listings
	$siteviewableonly = $loggedin ? "" : " AND p.siteviewable=1";
	$opendoorpolicy = $admin ? "" : " AND (p.opendoorpolicy=1 OR p.siteviewable=1)";

	//Create the page limiter
	$firstonpage = $CFG->sitesearch->perpage * $pagenum;
	$limit = " LIMIT $firstonpage," . $CFG->sitesearch->perpage;
	$words = explode(" ", $dbsearchwords);

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
				WHERE p.pageid <> '$CFG->SITEID'
					AND ($searchstring)
					AND p.pageid <> '$pageid'
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
		$params["col3"] = ($loggedin) ? fill_template("tmp/page_ajax.template", "search_linkpagesearch_buttons_template", false, $params) : "";
		$params["searchresults"] = $params["searchresults"] . fill_template("tmp/page_ajax.template", "search_row_template", false, $params);
		}
	}

	echo fill_template("tmp/page_ajax.template", "search_template", false, $params);
}

function make_page_link() {
global $CFG, $USER;
	$pageid = clean_myvar_req("pageid", "int");
	$linkpageid = clean_myvar_req("linkpageid", "int");
	$SQL = "SELECT *
			  FROM pages_links
			 WHERE hostpageid = '$pageid'";
	$sort = get_db_count($SQL);
	$sort++;
	$page_name = get_db_field("name", "pages", "pageid='$linkpageid'");

	$SQL = "INSERT INTO pages_links (hostpageid, linkpageid, sort, linkdisplay)
				 VALUES($pageid, $linkpageid, $sort, '$page_name')";
	execute_db_sql($SQL);
	emptyreturn();
}


function unlink_page() {
  $linkpageid = clean_myvar_req("linkpageid", "int");
  $pageid = clean_myvar_req("pageid", "int");
  $SQL = "DELETE FROM pages_links
				WHERE hostpageid = '$pageid'
				  AND linkpageid = '$linkpageid'";
  execute_db_sql($SQL);
  resort_links($pageid);
  emptyreturn();
}

function move_link() {
	$linkid = clean_myvar_req("linkid", "int");
	$linkdisplay = clean_myvar_req("linkdisplay", "string");
	$pageid = clean_myvar_req("pageid", "int");
	$direction = clean_myvar_req("direction", "string");

	$change = $direction == "up" ? -1 : 1;

	$SQL = fetch_template("dbsql/pages.sql", "get_pagelink");
	$link1 = get_db_row($SQL, ["linkid" => $linkid]);

	$originalsort = $link1["sort"]; // original position.
	$newsort = $originalsort + $change; // new position.

	// Get link that is currently in the position we want to move to.
	$SQL = fetch_template("dbsql/pages.sql", "get_pagelink_in_position");
	$link2 = get_db_row($SQL, ["hostpageid" => $pageid, "sort" => $newsort]);

	$SQL = "UPDATE pages_links
				SET sort = $newsort, linkdisplay = '$linkdisplay'
			WHERE linkid = '$linkid'";
	execute_db_sql($SQL);

	$SQL = "UPDATE pages_links
				SET sort = $originalsort
			WHERE linkid = " . $link2["linkid"];
	execute_db_sql($SQL);

	resort_links($pageid);
	emptyreturn();
}

function rename_link() {
	$linkid = clean_myvar_req("linkid", "int");
	$linkdisplay = clean_myvar_req("linkdisplay", "string");
	$SQL = fetch_template("dbsql/pages.sql", "update_pagelink_name");
	execute_db_sql($SQL, ["linkid" => $linkid, "linkdisplay" => $linkdisplay]);
	emptyreturn();
}

function resort_links($pageid) {
	$SQL = fetch_template("dbsql/pages.sql", "get_pagelinks");
	if ($links = get_db_result($SQL, ["pageid" => $pageid])) {
		$i = 1;
		while ($link = fetch_row($links)) {
			$SQL = fetch_template("dbsql/pages.sql", "update_pagelink_sort");
			execute_db_sql($SQL, ["sort" => $i, "linkid" => $link["linkid"]]);
			$i++;
		}
	}
}

function get_inviteable_pages() {
	$inviter = clean_myvar_req("inviter", "int");
	$invitee = clean_myvar_req("invitee", "int");

	$params = [
		"properties" => [
			"name" => "page_invite_list",
			"id" => "page_invite_list",
			"style" => "width:150px;",
			"onchange" => fill_template("tmp/page_ajax.template", "get_inviteable_button_template", false, ["invitee" => $invitee]),
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
	$userid = clean_myvar_req("userid", "int");
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$defaultrole = get_default_role($pageid);

	$SQL = fetch_template("dbsql/roles.sql", "get_role_assignment");
	$invite_received = get_db_row($SQL, ["userid" => $userid, "pageid" => $pageid, "roleid" => $defaultrole, "confirm" => 2]);

	if ($invite_received) {
		echo "Already Invited";
		return;
	} else {
		$SQL = fetch_template("dbsql/roles.sql", "insert_role_assignment");
		$params = ["userid" => $userid, "pageid" => $pageid, "roleid" => $defaultrole, "confirm" => 2];

		if ($invite_received || execute_db_sql($SQL, $params)) {
			echo "Invite Sent";
			return;
		}
	}

	echo "Invite Not Sent";
}

function refresh_page_links() {
global $CFG, $USER;
	$userid = $USER->userid;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	if (!defined('PAGELISTLIB')) { include_once ($CFG->dirroot . '/lib/pagelistlib.php'); }

	echo get_page_links($pageid, $userid);
}

function delete_page_ajax() {
	$pageid = clean_myvar_req("pageid", "int");
	if (delete_page($pageid)) {
		ajax_return("deleted");
		exit();
	}

	ajax_return("error", "error");
}

function change_subscription() {
global $CFG, $USER;
	$userid = $USER->userid;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$pagenum = clean_myvar_opt("pagenum", "int", 0);

	$params = ["wwwroot" => $CFG->wwwroot, "can_add" => false, "pageid" => $pageid, "pagenum" => $pagenum, "userid" => $userid];
	$subscription_added = subscribe_to_page($pageid, $userid, true);
	if (!$subscription_added) {
		$SQL = fetch_template("dbsql/pages.sql", "get_page");
		$page = get_db_row($SQL, ["pageid" => $pageid]);
		if ($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_is_able($userid, "assign_roles", $pageid)) {
			$params["can_add"] = true;
		}
	}

	ajax_return(fill_template("tmp/page_ajax.template", "change_subscription_template", false, $params));
}

function add_request() {
global $CFG, $USER;
	$userid = $USER->userid;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$roleid = get_default_role($pageid);

	$SQL = fetch_template("dbsql/roles.sql", "insert_role_assignment");
	$request_added = execute_db_sql($SQL, ["userid" => $userid, "pageid" => $pageid, "roleid" => $roleid, "confirm" => 1]);
	$params = ["request_added" => $request_added, "wwwroot" => $CFG->wwwroot, "pageid" => $pageid];
	ajax_return(fill_template("tmp/page_ajax.template", "add_remove_request_template", false, $params));
}

function remove_request() {
global $CFG;
	$userid = $USER->userid;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());

	$SQL = fetch_template("dbsql/roles.sql", "remove_role_assignment_request");
	$request_removed = execute_db_sql($SQL, ["userid" => $userid, "pageid" => $pageid, "confirm" => 1]);

	$params = ["request_removed" => (!$request_removed), "wwwroot" => $CFG->wwwroot, "pageid" => $pageid];
	ajax_return(fill_template("tmp/page_ajax.template", "add_remove_request_template", false, $params));
}
?>
