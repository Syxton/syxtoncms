<?php
/***************************************************************************
* members_script.php - AJAX members search script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.6
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

update_user_cookie();

if (!is_siteadmin($USER->userid)) { trigger_error(getlang("generic_permissions"), E_USER_WARNING); return; }

callfunction();

function members_search() {
global $CFG, $MYVARS, $USER;

    //Search variables
    $perpage = 10;
    $searchwords = clean_myvar_opt("search", "string", "");
    $pagenum = clean_myvar_opt("pagenum", "int", 0);
    $mailman = clean_myvar_opt("mailman", "int", false);
    $csv = clean_myvar_opt("csv", "int", false);

    $firstonpage = $perpage * $pagenum;
    $LIMIT = " LIMIT $firstonpage," . $perpage;

    $name = false; $NAMESEARCHSQL = ""; $namesearch = [];
    $cfield = false; $FIELDSEARCHSQL = ""; $fieldsearch = [];
    $csort = false; $SORT = "u.lname, u.fname";

    //split the search words and find out what they mean
    $searcharray = explode('/', $searchwords);
    if ($searcharray[0] == "" && isset($searcharray[1])) {
        array_shift($searcharray);
    }

    foreach ($searcharray as $term) {
        $switch = strpos($term, " ") ? substr($term, 0, strpos($term, " ")) : trim($term);
        switch ($switch) {
            case "n":
                $name = true;
                $namesearch[] = substr($term, (strpos($term, " ")));
                break;
            case "s":
                $csort = true;
                $customsort[] = substr($term, (strpos($term, " ")));
                break;
            case "f":
                $cfield = true;
                $fieldsearch[] = substr($term, (strpos($term, " ")));
                break;
            default:
                $name = true;
                $namesearch[] = trim($term);
        }
    }

    // MAKE NAME SEARCH SQL
    if ($name) {
        foreach ($namesearch as $name) {
            $not = "";
            //userid search
            if (is_numeric(trim($name))) {
                $x = trim($name);
                $NAMESEARCHSQL .= $NAMESEARCHSQL == "" ? "(u.userid = $x)" : " AND (u.userid = $x)";
            } else {
                preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', trim(stripslashes($name)), $temp);
                foreach ($temp[0] as $x) {
                    if (strstr($x," ")) {
                        $tx = explode(" ",trim($x,'\"\''));
                        $NAMESEARCHSQL .= $NAMESEARCHSQL == "" ? "((u.fname = '$tx[0]') AND (u.lname = '$tx[1]')) OR ((u.fname = '$tx[1]') AND (u.lname = '$tx[0]'))" : " AND ((u.fname = '$tx[1]') OR (u.lname = '$tx[0]')) OR ((u.fname = '$tx[0]') AND (u.lname = '$tx[1]'))";
                    } elseif (strstr($x,"\"") || strstr($x,"\'")) {
                        $x = trim($x,'\"\'');
                        $NAMESEARCHSQL .= $NAMESEARCHSQL == "" ? "((u.fname = '$x') OR (u.lname = '$x'))" : " AND ((u.fname = '$x') OR (u.lname = '$x'))";
                    } else {
                        $NAMESEARCHSQL .= $NAMESEARCHSQL == "" ? "((u.fname LIKE '%$x%') OR (u.lname LIKE '%$x%'))" : " AND ((u.fname LIKE '%$x%') OR (u.lname LIKE '%$x%'))";
                    }
                  }
            }

        }
        $NAMESEARCHSQL = empty($NAMESEARCHSQL) ? "" : "($NAMESEARCHSQL)";
    }

    //MAKE CUSTOM FIELD SEARCH SQL
    if ($cfield) {
        foreach ($fieldsearch as $field) {
            $temp = explode(" ", trim($field));
            $customfield = strtolower(array_shift($temp));
            $test = strtolower(trim($temp[0]));
            if ($test != '=' && $test != '!=' && $test != '>' && $test != '<' && $test != '>=' && $test != '<=' && $test != 'like' && $test != '!like') { $customtype = '=';
            } else { $customtype = strtolower(trim(array_shift($temp))); }
            if (!empty($temp)) {
                foreach ($temp as $x) {
                    if ($customtype == 'like') {
                        $FIELDSEARCHSQL .= $FIELDSEARCHSQL == "" ? "($customfield LIKE '%$x%')" : " AND ($customfield LIKE '%$x%')";
                    } elseif ($customtype == '!like') {
                        $FIELDSEARCHSQL .= $FIELDSEARCHSQL == "" ? "($customfield NOT LIKE '%$x%')" : " AND ($customfield NOT LIKE '%$x%')";
                    } else {
                        $FIELDSEARCHSQL .= $FIELDSEARCHSQL == "" ? "($customfield $customtype '$x')" : " AND ($customfield $customtype '$x')";
                    }
                }
            } else {
                $cfield = false;
            }

        }
        $FIELDSEARCHSQL = empty($FIELDSEARCHSQL) ? "" : "($FIELDSEARCHSQL)";
    }

    //MAKE CUSTOM SORT SQL
    if ($csort) {
        $SORT = "";
        foreach ($customsort as $sortby) {
            $temp = explode(" ",trim($sortby));
            foreach ($temp as $x) {
                $x = strstr($x,"-") ? str_replace("-", "", $x) . " DESC" : "$x";
                $SORT .= $SORT == "" ? "$x" : ", $x";
            }
        }

        //Back to default if empty
        $SORT = $SORT == "" ? "u.lname,u.fname" : $SORT;
    }

    $ANDNAME = !empty($name) ? " AND " : "";
    $ANDCUSTOMFIELD = !empty($cfield) ? " AND " : "";

    $SQL = "SELECT * FROM users u WHERE userid != 0 $ANDNAME $NAMESEARCHSQL $ANDCUSTOMFIELD $FIELDSEARCHSQL ORDER BY $SORT";

    // Create the page limiter
    $total = get_db_count($SQL); //get the total for all pages returned.
    $count = $total > (($pagenum + 1) * $perpage) ? $perpage : $total - (($pagenum) * $perpage); // get the amount returned...is it a full page of results?
    $amountshown = $firstonpage + $perpage < $total ? $firstonpage + $perpage : $total;

    $export = false;
    $searchresult = false;
    if ($count > 0) {
        // Mailman output file.
        $fileoutput = "";

        // CSV output file.
        $fileoutput = [];

        get_members_search_ajax();

        // Limit to one page of return.
        if (!$mailman && !$csv) {
            $SQL .= $LIMIT;
        }
        $pages = get_db_result($SQL);
        while ($row = fetch_row($pages)) {
            $fullname = $row['fname'] . ' ' . $row['lname'];
            if ($mailman) { // Export to Mailman
                $fileoutput .= "$fullname <" . $row["email"] . ">\r\n";
            } elseif ($csv) { // Export to CSV
                $fileoutput[] = ["fname" => $row["fname"], "lname" => $row["lname"], "email" => $row["email"]];
            } else {
                $searchresult .= fill_template("tmp/main.template", "members_search_row", "adminpanel", [
                    "fullname" => $fullname,
                    "notadmin" => !is_siteadmin($row["userid"]),
                    "hasip" => !empty($row["ip"]),
                    "user" => $row,
                    "loginas" => ($USER->userid !== $row['userid']),
                    "joined" => ago($row["joined"], true),
                    "active" => ago($row["last_activity"], true),
                ]);
            }
        }
    }

    if (!$mailman && !$csv) {
        $prev = $pagenum > 0 ? '<button class="alike" title="Previous Page" onclick="members_search(\'' . $searchwords . '\', ' . ($pagenum - 1) . ');">' . icon("circle-chevron-left", 2) . '</button>' : "";
        $next = $firstonpage + $perpage < $total ? '<button class="alike" title="Next Page" onclick="members_search(\'' . $searchwords . '\', ' . ($pagenum + 1) . ');">' . icon("circle-chevron-right", 2) . '</button>' : "";
        $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";

        $return = fill_template("tmp/main.template", "pagination_bar", "adminpanel", [
            "prev" => $prev,
            "next" => $next,
            "info" => $info,
        ]);

        $return .= fill_template("tmp/main.template", "members_search_table", "adminpanel", [
            "searchwords" => $searchwords,
            "results" => $searchresult,
        ]);

        ajax_return($return);
    } else {
        $filename = !empty($mailman) ? "users_export.txt" : "users_export.csv";
        ajax_return('<iframe src="' . $CFG->wwwroot . '/scripts/download.php?file=' . create_file($filename, $fileoutput, empty($mailman)) . '"></iframe>');
    }
}

function get_members_search_ajax() {
    global $CFG;

    ajaxapi([
        "id" => "members_delete_user",
        "if" => "confirm('Do you want to delete ' + $('#user_id_' + userid).next('.user_name').val() + '\'s account?')",
        "url" => "/ajax/site_ajax.php",
        "paramlist" => "userid",
        "data" => [
            "action" => "delete_user",
            "userid" => "js||userid||js"
        ],
        "ondone" => "members_search();",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "members_reset_password",
        "if" => "confirm('Do you want to reset ' + $('#user_id_' + userid).next('.user_name').val() + '\'s password?')",
        "url" => "/ajax/site_ajax.php",
        "paramlist" => "userid",
        "data" => [
            "action" => "forgot_password",
            "admin" => "true",
            "userid" => "js||userid||js"
        ],
        "ondone" => "jq_display('reset_password_' + userid, data);",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "ipmap",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "ipmap",
            "geodata" => "js||geodata||js"
        ],
        "paramlist" => "geodata, display",
        "display" => "js||display||js",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "members_get_geodata",
        "external" => true,
        "method" => "GET",
        "url" => 'https://api.ipgeolocation.io/v2/ipgeo',
        "data" => [
            "fields" => "location",
            "ip" => "js||ip||js",
            "apiKey" => $CFG->geolocationkey,
        ],
        "paramlist" => "ip, display",
        "ondone" => "if (data.location === undefined) {
                        alert('Location could not be found.');
                    } else {
                        ipmap(JSON.stringify(data), display);
                    }",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "loginas",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "loginas",
            "userid" => "js||userid||js",
        ],
        "paramlist" => "userid",
        "ondone" => "getRoot()[0].go_to_page(data.message);",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "view_logfile",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => [
            "action" => "view_logfile",
            "userid" => "js||userid||js",
        ],
        "paramlist" => "userid",
        "display" => "display",
        "event" => "none",
    ]);
}
?>