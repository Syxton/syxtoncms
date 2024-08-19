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

$CFG->sitesearch = (object)[];
$CFG->sitesearch->perpage = 8;

callfunction();

function edit_page() {
    $pageid = clean_myvar_req("pageid", "int");
    $text = clean_myvar_req("name", "string");
    $description = clean_myvar_opt("description", "string", "");
    $keywords = clean_myvar_opt("keywords", "string", "");
    $defaultrole = clean_myvar_opt("defaultrole", "int", DEFAULT_PAGEROLE);
    $opendoor = clean_myvar_opt("opendoor", "int", 0);
    $siteviewable = clean_myvar_opt("siteviewable", "int", 0);
    $menu_page = clean_myvar_opt("menu_page", "int", 0);
    $hidefromvisitors = clean_myvar_opt("hidefromvisitors", "int", 0);

    $error = "";
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
                "link" => $pageid,
                "menu_page" => $menu_page,
                "hidefromvisitors" => $hidefromvisitors,
            ];

            if ($menu_page) { // Menu Page
                // Create, Edit menu item.
                modify_menu_page($params);
            } else {
                // If not a menu, delete from menu table.
                if (!execute_db_sql(fetch_template("dbsql/pages.sql", "delete_page_menus"), ["pageid" => $pageid])) {
                    throw new Exception("Could not remove from menu");
                }
            }

            if (!execute_db_sql(fetch_template("dbsql/pages.sql", "edit_page"), $params)) {
                throw new Exception("Settings could not be saved");
            }
        }
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    ajax_return("", $error);
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

    $return = $error = "";
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
        $return = $pageid;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    ajax_return($return, $error);
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
            // Actions.
            page_search_actions();

            while ($page = fetch_row($pages)) {
                $linked = $must_request = true;
                $can_add_remove = $alreadyrequested = $isadd = false;

                $resultsparams["col3"] = "";
                if ($loggedin && !$admin) {
                    // Has subscription.
                    $SQL = fetch_template("dbsql/roles.sql", "get_role_assignment");
                    if ($subscribed = get_db_row($SQL, ["userid" => $userid, "pageid" => $page["pageid"], "confirm" => 0])) {
                        $must_request = false;
                        $isadd = false;
                        $can_add_remove = user_is_able($userid, "removeownrole", $page["pageid"]);
                    } else {
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
                }

                if ($admin) {
                    $must_request = false; // Admins can always enter pages, and don't need to request access.
                    $isadd = false; // Admins can always enter pages and not need to add a role to enter a page.
                    $can_add_remove = false; // Admins can always enter pages, and don't need to add or remove access.
                }

                $resultsparams["linked"] = $linked;
                $resultsparams["pageid"] = $page["pageid"];

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

    ajax_return(search_template($resultsparams));
}

function page_search_actions() {
    ajaxapi([
        "id" => "remove_request",
        "url" => "/ajax/page_ajax.php",
        "paramlist" => "pageid",
        "data" => [
            "action" => "remove_request",
            "pageid" => "js||pageid||js",
        ],
        "display" => "rowactions_js||pageid||js",
        "event" => "none",
    ]);
    ajaxapi([
        "id" => "add_request",
        "url" => "/ajax/page_ajax.php",
        "paramlist" => "pageid",
        "data" => [
            "action" => "add_request",
            "pageid" => "js||pageid||js",
        ],
        "display" => "rowactions_js||pageid||js",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "change_subscription",
        "if" => "added || confirm('Are you sure you want to remove yourself from this page? \n You might not be able to get into this page again.')",
        "url" => "/ajax/page_ajax.php",
        "paramlist" => "added, pageid, pagenum",
        "data" => [
            "action" => "change_subscription",
            "pageid" => "js||pageid||js",
            "pagenum" => "js||pagenum||js",
        ],
        "display" => "rowactions_js||pageid||js",
        "ondone" => "pagesearch(pageid, pagenum);",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "pagesearch",
        "url" => "/ajax/page_ajax.php",
        "paramlist" => "pageid, pagenum",
        "data" => [
            "action" => "pagesearch",
            "pageid" => "js||pageid||js",
            "pagenum" => "js||pagenum||js",
            "searchwords" => "js||encodeURIComponent($('#searchwords').val())||js",
        ],
        "display" => "searchcontainer_pagesearch",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "delete_page",
        "if" => "confirm('Are you sure you want to delete this page completely? \n There is no going back from this!')",
        "url" => "/ajax/page_ajax.php",
        "paramlist" => "pageid, pagenum",
        "data" => [
            "action" => "delete_page_ajax",
            "pageid" => "js||pageid||js",
            "pagenum" => "js||pagenum||js",
        ],
        "display" => "rowactions_js||pageid||js",
        "ondone" => "if (data.message === 'deleted') { $('#rowactions_' + pageid).closest('tr').remove(); }",
        "event" => "none",
    ]);
}

function usersearch() {
global $CFG, $USER, $PAGE;
    $userid = $USER->userid;
    $pageid = $PAGE->id;
    $searchwords = trim(clean_myvar_opt("searchwords", "string", ""));
    $pagenum = clean_myvar_opt("pagenum", "int", 0);

    $return = $error = "";
    try {
        // no search words given
        $dbsearchwords = $searchwords == "" ? "%" : $searchwords;

        $return = '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';

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
            "pageid" => $pageid,
            "viewing" => ($firstonpage + 1),
            "amountshown" => $amountshown,
            "total" => $total,
        ];

        if ($count > 0) {
            ajaxapi([
                "id" => "get_inviteable_pages",
                "url" => "/ajax/page_ajax.php",
                "paramlist" => "invitee",
                "data" => [
                    "action" => "get_inviteable_pages",
                    "invitee" => "js||invitee||js",
                ],
                "display" => "pagelist_js||invitee||js",
                "event" => "none",
            ]);

            ajaxapi([
                "id" => "invite_user",
                "if" => "$('#page_invite_list').val() != '' && confirm('Do you wish to send an invitation to this user?')",
                "url" => "/ajax/page_ajax.php",
                "paramlist" => "invitee",
                "data" => [
                    "action" => "invite_user",
                    "pageid" => "js||$('#page_invite_list').val()||js",
                    "userid" => "js||invitee||js",
                ],
                "display" => "pagelist_js||invitee||js",
                "ondone" => "console.log($(this));",
                "event" => "none",
            ]);

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
        $return .= search_template($params);
    } catch (\Thowable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function get_new_link_form() {
    $pageid = clean_myvar_req("pageid", "int");

    ajaxapi([
        "id" => "linkpagesearch",
        "url" => "/ajax/page_ajax.php",
        "data" => [
            "action" => "linkpagesearch",
            "pageid" => $pageid,
            "searchwords" => "js||encodeURIComponent($('#searchbox').val())||js",
        ],
        "display" => "searchcontainer_linkpagesearch",
        "event" => "none",
    ]);
    $return = fill_template("tmp/page_ajax.template", "new_link_form_template", false, ["pageid" => $pageid]);
    ajax_return($return);
}

function get_link_manager() {
global $CFG;
    $pageid = clean_myvar_req("pageid", "int");
    $return = $error = "";
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

        ajaxapi([
            "id" => "move_link",
            "url" => "/ajax/page_ajax.php",
            "paramlist" => "linkid, direction",
            "data" => [
                "action" => "move_link",
                "pageid" => $pageid,
                "direction" => "js||direction||js",
                "linkid" => "js||linkid||js",
                "linkdisplay" => "js||encodeURIComponent($('#linkdisplay' + linkid).val())||js",
            ],
            "ondone" => "get_link_manager(linkid);",
            "event" => "none",
        ]);

        ajaxapi([
            "id" => "get_link_manager",
            "url" => "/ajax/page_ajax.php",
            "paramlist" => "linkid",
            "data" => [
                "action" => "get_link_manager",
                "pageid" => $pageid,
                "linkid" => "js||linkid||js",
            ],
            "display" => "links_mode_span",
            "event" => "none",
        ]);

        ajaxapi([
            "id" => "rename_link",
            "url" => "/ajax/page_ajax.php",
            "paramlist" => "linkid",
            "data" => [
                "action" => "rename_link",
                "linkid" => "js||linkid||js",
                "linkdisplay" => "js||encodeURIComponent($('#linkdisplay' + linkid).val())||js",
            ],
            "ondone" => "$('#linkdisplay' + linkid + '_hidden').val($('#linkdisplay' + linkid).val());",
            "event" => "none",
        ]);

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
    $return = fill_template("tmp/page_ajax.template", "links_manager_template", false, $params);
    ajax_return($return, $error);
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
    $limit = " LIMIT ||first||, ||perpage||";
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
                                AND pl.hostpageid = ||pageid|| LIMIT 1) as alreadylinked
                    FROM pages p
                WHERE p.pageid <> ||siteid||
                    AND ($searchstring)
                    AND p.pageid <> ||pageid||
                ORDER BY p.name";
    }

    $total = get_db_count($SQL, ["pageid" => $pageid, "siteid" => $CFG->SITEID]); //get the total for all pages returned.
    $SQL .= $limit; //Limit to one page of return.
    $pages = get_db_result($SQL, ["pageid" => $pageid, "siteid" => $CFG->SITEID, "first" => $firstonpage, "perpage" => $CFG->sitesearch->perpage]);

    $count = $total > (($pagenum + 1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
    $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

    $params = [
        "resultsfound" => ($count > 0),
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
        ajaxapi([
            "id" => "make_page_link",
            "paramlist" => "linkpageid",
            "url" => "/ajax/page_ajax.php",
            "data" => [
                "action" => "make_page_link",
                "linkpageid" => "js||linkpageid||js",
                "pageid" => $pageid,
            ],
            "event" => "none",
            "ondone" => "linkpagesearch();",
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
            "ondone" => "linkpagesearch();",
        ]);

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

    ajax_return(search_template($params));
}

function make_page_link() {
    $pageid = clean_myvar_req("pageid", "int");
    $linkpageid = clean_myvar_req("linkpageid", "int");

    $error = "";
    try {
        $SQL = "SELECT *
                FROM pages_links
                WHERE hostpageid = ||pageid||";
        $sort = get_db_count($SQL, ["pageid" => $pageid]);
        $sort++;
        $page_name = get_db_field("name", "pages", "pageid = ||linkpageid||", ["linkpageid" => $linkpageid]);

        $SQL = "INSERT INTO pages_links (hostpageid, linkpageid, sort, linkdisplay)
                VALUES(||pageid||, ||linkpageid||, ||sort||, ||linkdisplay||)";
        execute_db_sql($SQL, ["pageid" => $pageid, "linkpageid" => $linkpageid, "sort" => $sort, "linkdisplay" => $page_name]);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return("", $error);
}


function unlink_page() {
    $linkpageid = clean_myvar_req("linkpageid", "int");
    $pageid = clean_myvar_req("pageid", "int");

    $error = "";
    try {
        $SQL = "DELETE FROM pages_links WHERE hostpageid = ||pageid|| AND linkpageid = ||linkpageid||";
        execute_db_sql($SQL, ["pageid" => $pageid, "linkpageid" => $linkpageid]);
        resort_links($pageid);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return("", $error);

}

function move_link() {
    $linkid = clean_myvar_req("linkid", "int");
    $linkdisplay = clean_myvar_req("linkdisplay", "string");
    $pageid = clean_myvar_req("pageid", "int");
    $direction = clean_myvar_req("direction", "string");

    $return = $error = "";
    try {
        start_db_transaction();
        $change = $direction == "up" ? -1 : 1;

        $SQL = fetch_template("dbsql/pages.sql", "get_pagelink");
        $link1 = get_db_row($SQL, ["linkid" => $linkid]);

        $originalsort = $link1["sort"]; // original position.
        $newsort = $originalsort + $change; // new position.

        // Get link that is currently in the position we want to move to.
        $SQL = fetch_template("dbsql/pages.sql", "get_pagelink_in_position");
        $link2 = get_db_row($SQL, ["hostpageid" => $pageid, "sort" => $newsort]);

        $SQL = fetch_template("dbsql/pages.sql", "update_pagelink_sort_and_name");
        execute_db_sql($SQL, ["sort" => $newsort, "linkdisplay" => $linkdisplay, "linkid" => $linkid]);

        $SQL = fetch_template("dbsql/pages.sql", "update_pagelink_sort");
        execute_db_sql($SQL, ["sort" => $originalsort, "linkid" => $link2["linkid"]]);

        resort_links($pageid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        rollback_db_transaction($error);
    }

    ajax_return($return, $error);
}

function rename_link() {
    $linkid = clean_myvar_req("linkid", "int");
    $linkdisplay = clean_myvar_req("linkdisplay", "string");

    $return = $error = "";
    try {
        $SQL = fetch_template("dbsql/pages.sql", "update_pagelink_name");
        execute_db_sql($SQL, ["linkid" => $linkid, "linkdisplay" => $linkdisplay]);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
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
global $USER;
    $inviter = $USER->userid;
    $invitee = clean_myvar_req("invitee", "int");

    $return = $error = "";

    try {
        $params = [
            "properties" => [
                "name" => "page_invite_list",
                "id" => "page_invite_list",
                "style" => "width:150px;",
                "onchange" => "invite_user($invitee)",
            ],
            "values" => pages_user_is_able($inviter, "invite", false, false),
            "valuename" => "pageid",
            "displayname" => "name",
            "firstoption" => "",
            "exclude" => pages_user_is_able($invitee, "viewpages", false, false),
        ];
        $return = make_select($params);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function invite_user() {
    $userid = clean_myvar_req("userid", "int");
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $defaultrole = get_default_role($pageid);

    $return = $error = "";
    try {
        $SQL = fetch_template("dbsql/roles.sql", "get_role_assignment");
        $invite_received = get_db_row($SQL, ["userid" => $userid, "pageid" => $pageid, "roleid" => $defaultrole, "confirm" => 2]);

        if ($invite_received) {
            $return = "Already Invited";
        } else {
            $SQL = fetch_template("dbsql/roles.sql", "insert_role_assignment");
            $params = ["userid" => $userid, "pageid" => $pageid, "roleid" => $defaultrole, "confirm" => 2];

            if ($invite_received || execute_db_sql($SQL, $params)) {
                $return = "Invite Sent";
            } else {
                throw new \Throwable("Failed to send invite.");
            }
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}

function refresh_page_links() {
global $CFG, $USER;
    $userid = $USER->userid;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    if (!defined('PAGELISTLIB')) { include_once ($CFG->dirroot . '/lib/pagelistlib.php'); }

    ajax_return(get_page_links($pageid, $userid));
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

    $params = [
        "must_request" => true,
        "can_add_remove" => user_is_able($userid, "removeownrole", $pageid),
        "pageid" => $pageid,
        "pagenum" => $pagenum,
        "userid" => $userid,
    ];

    $subscribed = change_page_subscription($pageid, $userid, true);
    if (!$subscribed) {
        $params["alreadyrequested"] = false;
        $SQL = fetch_template("dbsql/pages.sql", "get_page");
        $page = get_db_row($SQL, ["pageid" => $pageid]);
        if ($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_is_able($userid, "assign_roles", $pageid)) {
            $params["can_add_remove"] = true;
            $params["must_request"] = false;
        }
    }

    ajax_return(fill_template("tmp/page_ajax.template", "search_pages_buttons_template", false, $params));
}

function add_request() {
global $USER;
    $userid = $USER->userid;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $roleid = get_default_role($pageid);

    // Insert role request. (requires confirmation)
    $SQL = fetch_template("dbsql/roles.sql", "insert_role_assignment");
    $requested = execute_db_sql($SQL, ["userid" => $userid, "pageid" => $pageid, "roleid" => $roleid, "confirm" => 1]);

    $params = [
        "must_request" => true,
        "pageid" => $pageid,
        "alreadyrequested" => $requested,
    ];

    ajax_return(fill_template("tmp/page_ajax.template", "search_pages_buttons_template", false, $params));
}

function remove_request() {
global $USER;
    $userid = $USER->userid;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    // Remove any requests.
    $SQL = fetch_template("dbsql/roles.sql", "remove_role_assignment_request");
    execute_db_sql($SQL, ["userid" => $userid, "pageid" => $pageid, "confirm" => 1]);

    $params = [
        "must_request" => true,
        "pageid" => $pageid,
        "alreadyrequested" => false,
    ];

    ajax_return(fill_template("tmp/page_ajax.template", "search_pages_buttons_template", false, $params));
}
?>
