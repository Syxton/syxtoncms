<?php
/***************************************************************************
* page_ajax.php - Page backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/11/2021
* Revision: 1.4.3
***************************************************************************/

include ('header.php');
update_user_cookie();

$CFG->sitesearch = new \stdClass;
$CFG->sitesearch->perpage = 8;

callfunction();

function edit_page() {
global $CFG, $MYVARS;
  $name = dbescape($MYVARS->GET["name"]);
  $description = dbescape($MYVARS->GET["description"]);
  $keywords = dbescape($MYVARS->GET["keywords"]);
  $defaultrole = dbescape($MYVARS->GET["defaultrole"]);
  $opendoor = dbescape($MYVARS->GET["opendoor"]);
  $siteviewable = dbescape($MYVARS->GET["siteviewable"]);
  $menu_page = dbescape($MYVARS->GET["menu_page"]);
  $hidefromvisitors = dbescape($MYVARS->GET["hidefromvisitors"]);
  $pageid = dbescape($MYVARS->GET["pageid"]);

  if ($menu_page == "1") { // Menu Page
    $SQL = "SELECT *
              FROM menus
             WHERE pageid = '$pageid'";
    if (get_db_row($SQL)) { //Page was already a menu...just run an update
      $SQL = "UPDATE menus
                 SET hidefromvisitors = '$hidefromvisitors',
                     link = '$pageid',
                     text = '$name'
               WHERE pageid = '$pageid'";
      execute_db_sql($SQL);
    } else { // New Menu Item
      $sort = get_db_field("sort", "menus", "id > 0 ORDER BY sort DESC");
      $sort++;
      $SQL = "INSERT INTO menus (pageid, text, link, sort, hidefromvisitors)
                   VALUES ('$pageid','$name','$pageid','$sort','$hidefromvisitors')";
      execute_db_sql($SQL);
    }
  } else {
    $SQL = "FROM menus WHERE pageid = '$pageid'";
    if (get_db_row("SELECT * $SQL")) { // Page is already a menu...just delete that row.
      execute_db_sql("DELETE $SQL");
    }
  }

  $shortname = substr(strtolower(preg_replace("/\W|_/", '', $name)), 0, 20);
  $SQL = "UPDATE pages
             SET description = '$description',
                 name = '$name',
                 short_name = '$shortname',
                 keywords = '$keywords',
                 siteviewable = '$siteviewable',
                 menu_page = '$menu_page',
                 default_role = '$defaultrole',
                 opendoorpolicy = '$opendoor'
           WHERE pageid = $pageid";
  if (execute_db_sql($SQL)) { echo "Page edited successfully"; }
}

function create_page() {
global $CFG, $MYVARS;
  update_user_cookie();
  echo create_new_page((object) $MYVARS->GET); // Converts associative array to object.
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
      $roleid = get_user_role($userid, $CFG->SITEID);
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

  $count = $total > (($pagenum+1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
  $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

  $params = array("resultsfound" => ($count > 0), "searchresults" => "", "searchwords" => $searchwords, "searchtype" => "pagesearch", "isprev" => ($pagenum > 0), "isnext" => ($firstonpage + $CFG->sitesearch->perpage < $total), "wwwroot" => $CFG->wwwroot, "prev_pagenum" => ($pagenum - 1), "next_pagenum" => ($pagenum + 1),
                  "pagenum" => $pagenum, "viewing" => ($firstonpage + 1), "amountshown" => $amountshown, "total" => $total);

  if ($count > 0) {
    while ($page = fetch_row($pages)) {
      $linked = true;
      $params["col3"] = "";
      if ($loggedin && !$admin) {
        if ($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_has_ability_in_page($userid, "assign_roles", $page["pageid"])) {
          $params["col3"] = template_use("tmp/page_ajax.template", array("must_request" => false, "can_add_remove" => user_has_ability_in_page($userid, "add_page", $CFG->SITEID),
                                                                               "isadd" => ($page["added"] == 0), "wwwroot" => $CFG->wwwroot, "pagenum" => $pagenum,
                                                                               "searchwords" => $searchwords, "pageid" => $page["pageid"]), "search_pages_buttons_template");
        } else {
          $linked = false;
          $alreadyrequested = get_db_row("SELECT * FROM roles_assignment WHERE userid='$userid' AND pageid='" . $page["pageid"] . "' AND confirm=1") ? true : false;
          $params["col3"] = template_use("tmp/page_ajax.template", array("must_request" => true, "alreadyrequested" => $alreadyrequested, "wwwroot" => $CFG->wwwroot, "pagenum" => $pagenum,
                                                                               "searchwords" => $searchwords, "pageid" => $page["pageid"]), "search_pages_buttons_template");
        }
      }

      $params["linked"] = $linked;
      $params["col1"] = template_use("tmp/page_ajax.template", array("linked" => $linked, "pageid" =>  $page["pageid"],"name" => substr($page["name"], 0, 30)), "search_pages_link_template");
      $params["col2"] = substr($page["description"], 0, 50);
      $params["searchresults"] = $params["searchresults"] . template_use("tmp/page_ajax.template", $params, "search_row_template");
    }
  }

  echo template_use("tmp/page_ajax.template", $params, "search_template");
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
  $count = $total > (($pagenum+1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
  $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

  $params = array("resultsfound" => ($count > 0), "searchresults" => "", "searchwords" => $searchwords, "searchtype" => "usersearch", "isprev" => ($pagenum > 0), "isnext" => ($firstonpage + $CFG->sitesearch->perpage < $total), "wwwroot" => $CFG->wwwroot, "prev_pagenum" => ($pagenum - 1), "next_pagenum" => ($pagenum + 1),
                  "pagenum" => $pagenum, "viewing" => ($firstonpage + 1), "amountshown" => $amountshown, "total" => $total);

  if ($count > 0) {
    while ($user = fetch_row($users)) {
      $params["isuser"] = ($userid != $user["userid"] && !is_siteadmin($user["userid"]));
      $params["userid"] = $userid;
      $params["user"] = $user;
      $params["col1"] = $user["fname"] . " " . $user["lname"];
      $params["col2"] = $user["email"];
      $params["col3"] = template_use("tmp/page_ajax.template", $params, "search_users_buttons_template");
      $params["searchresults"] = $params["searchresults"] . template_use("tmp/page_ajax.template", $params, "search_row_template");
    }
  }

  echo template_use("tmp/page_ajax.template", $params, "search_template");
}

function get_new_link_form() {
global $MYVARS, $CFG, $USER;
  echo template_use("tmp/page_ajax.template", array("pageid" => $MYVARS->GET['pageid']), "new_link_form_template");
}

function get_link_manager() {
global $MYVARS, $CFG, $USER;
    $pageid = $MYVARS->GET['pageid'];
    $returnme = "";
    $i = 0;
    $params = array("pageid" => $pageid, "wwwroot" => $CFG->wwwroot, "haslinks" => false);

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
        $rowparams = array("wwwroot" => $CFG->wwwroot, "order" => $i, "nextorder" => ($i + 1), "pageid" => $pageid,
                           "linkdisplay" => stripslashes($link['linkdisplay']), "linkid" => $link["linkid"], "notfirstrow" =>  ($i > 0), "notlastrow" => ($i < ($count - 1)));
        $linkrows .= template_use("tmp/page_ajax.template", $rowparams, "sortable_links_template");
        $i++;
      }

      $params["links"] = $linkrows;
    }

    echo template_use("tmp/page_ajax.template", $params, "links_manager_template");
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
      $roleid = get_user_role($userid, $CFG->SITEID);
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

  $count = $total > (($pagenum+1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
  $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

  $params = array("resultsfound" => ($count > 0), "searchresults" => "", "searchwords" => $searchwords, "searchtype" => "linkpagesearch", "isprev" => ($pagenum > 0), "isnext" => ($firstonpage + $CFG->sitesearch->perpage < $total), "wwwroot" => $CFG->wwwroot, "prev_pagenum" => ($pagenum - 1), "next_pagenum" => ($pagenum + 1),
                  "pagenum" => $pagenum, "viewing" => ($firstonpage + 1), "amountshown" => $amountshown, "total" => $total, "loggedin" => $loggedin, "pageid" => $pageid);

  if ($count > 0) {
    while ($page = fetch_row($pages)) {
      $params["alreadylinked"] = ($loggedin && empty($page["alreadylinked"]));
      $params["confirmopen"] = ($page["siteviewable"] == 0);
      $params["linkpageid"] = $page["pageid"];

      $params["col1"] = substr(stripslashes($page["name"]), 0, 30);
      $params["col2"] = substr(stripslashes(strip_tags($page["description"])), 0, 100);
      $params["col3"] = ($loggedin) ? template_use("tmp/page_ajax.template", $params, "search_linkpagesearch_buttons_template") : "";
      $params["searchresults"] = $params["searchresults"] . template_use("tmp/page_ajax.template", $params, "search_row_template");
    }
  }

  echo template_use("tmp/page_ajax.template", $params, "search_template");
}

function make_page_link() {
global $MYVARS, $CFG, $USER;
    $pageid = $MYVARS->GET['pageid'];
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
    donothing();
}


function unlink_page() {
global $MYVARS;
  $linkpageid = $MYVARS->GET['linkpageid'];
  $pageid = $MYVARS->GET['pageid'];
  $SQL = "DELETE FROM pages_links
                WHERE hostpageid = '$pageid'
                  AND linkpageid = '$linkpageid'";
  execute_db_sql($SQL);
  resort_links($pageid);
  donothing();
}

function move_link() {
global $MYVARS;
  $linkid = dbescape($MYVARS->GET['linkid']);
  $linkdisplay = dbescape($MYVARS->GET['linkdisplay']);
  $pageid = $MYVARS->GET['pageid'];
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
  donothing();
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
  donothing();
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
  $pages = user_has_ability_in_pages($inviter, "invite", false, false); //list pages you have invite permissions in
  $notthese = user_has_ability_in_pages($invitee, "viewpages", false, false); //remove pages that the user already has access to

  $invite_button = template_use("tmp/page_ajax.template", array("invitee" => $invitee), "get_inviteable_button_template");
  echo make_select("page_invite_list", $pages, "pageid", "name", null, $invite_button, true, 1 , "width:150px;", "", $notthese);
}

function invite_user() {
global $CFG, $MYVARS;
  $userid = $MYVARS->GET["userid"];
  $pageid = $MYVARS->GET["pageid"];
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
  $pageid = $MYVARS->GET["pageid"];

  if (!isset($PAGELISTLIB)) { include_once ($CFG->dirroot . '/lib/pagelistlib.php'); }

  echo get_page_links($pageid, $userid);
}

function change_subscription() {
global $CFG, $MYVARS, $USER;
  $userid = $USER->userid;
  $pageid = $MYVARS->GET["pageid"];
  $pagenum = $MYVARS->GET["pagenum"];

  $params = array("wwwroot" => $CFG->wwwroot, "can_add" => false, "pageid" => $pageid, "pagenum" => $pagenum, "userid" => $userid);
  $subscription_added = subscribe_to_page($pageid, $userid, true);
  if (!$subscription_added) {
    $SQL = "SELECT added, opendoorpolicy, siteviewable
              FROM pages
             WHERE pageid = '$pageid'";
    $page = get_db_row($SQL);
    if ($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_has_ability_in_page($userid, "assign_roles", $pageid)) {
      $params["can_add"] = true;
    }
  }

  echo template_use("tmp/page_ajax.template", $params, "change_subscription_template");
}

function add_request() {
global $CFG, $MYVARS;
  $userid = $USER->userid;
  $pageid = $MYVARS->GET["pageid"];
  $roleid = get_db_field("default_role", "pages", "pageid='$pageid'");
  $SQL = "INSERT INTO roles_assignment (userid, roleid, pageid, confirm)
               VALUES($userid, $roleid, $pageid, 1)";

  $request_added = execute_db_sql($SQL);
  $params = array("request_added" => $request_added, "wwwroot" => $CFG->wwwroot, "pageid" => $pageid);
  echo template_use("tmp/page_ajax.template", $params, "add_remove_request_template");
}

function remove_request() {
global $CFG, $MYVARS;
  $userid = $USER->userid;
  $pageid = $MYVARS->GET["pageid"];

  $SQL = "DELETE FROM roles_assignment
                WHERE userid = '$userid'
                  AND pageid = '$pageid'
                  AND confirm = 1";
  $request_removed = execute_db_sql($SQL);

  $params = array("request_removed" => (!$request_removed), "wwwroot" => $CFG->wwwroot, "pageid" => $pageid);
  echo template_use("tmp/page_ajax.template", $params, "add_remove_request_template");
}
?>
