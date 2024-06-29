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

if (!is_siteadmin($USER->userid)) { trigger_error(error_string("generic_permissions"), E_USER_WARNING); return; }

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
    $easteregg = false;

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
            case "fail":
                $easteregg = $term;
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

    $fileoutput = $searchresult = $export = "";
    if ($count > 0) {
        ajaxapi([
            "id" => "members_delete_user",
            "if" => "confirm('Do you want to delete ' + $('#user_id_' + userid).next('.user_name').val() + '\'s account?')",
            "url" => "/ajax/site_ajax.php",
            "paramlist" => "userid",
            "data" => ["action" => "delete_user", "userid" => "js||userid||js"],
            "ondone" => "members_search();",
            "event" => "none",
        ]);

        ajaxapi([
            "id" => "members_reset_password",
            "if" => "confirm('Do you want to reset ' + $('#user_id_' + userid).next('.user_name').val() + '\'s password?')",
            "url" => "/ajax/site_ajax.php",
            "paramlist" => "userid",
            "data" => ["action" => "forgot_password", "admin" => "true", "userid" => "js||userid||js"],
            "ondone" => "jq_display('reset_password_' + userid, data);",
            "event" => "none",
        ]);

        ajaxapi([
            "id" => "ipmap",
            "url" => "/features/adminpanel/adminpanel_ajax.php",
            "data" => ["action" => "ipmap", "geodata" => "js||geodata||js"],
            "paramlist" => "geodata, display",
            "display" => "js||display||js",
            "event" => "none",
        ]);

        ajaxapi([
            "id" => "members_get_geodata",
            "external" => true,
            "datatype" => "jsonp",
            "callback" => "jsonpCallback: 'jsonCallback',",
            "url" => '//extreme-ip-lookup.com/json/${ip}',
            "data" => ["key" => $CFG->geolocationkey],
            "paramlist" => "ip, display",
            "ondone" => "if (data.status !== 'success') {
                            alert('Location could not be found.');
                        } else {
                            ipmap(JSON.stringify(data), display);
                        }",
            "event" => "none",
        ]);

        // Limit to one page of return.
        if (!$mailman && !$csv) {
            $SQL .= $LIMIT;
        }
        $pages = get_db_result($SQL);
        while ($row = fetch_row($pages)) {
            if ($mailman) { // Export to Mailman
                $fileoutput .= $row["fname"] . " " . $row["lname"] . " <" . $row["email"] . ">\r\n";
            } elseif ($csv) { // Export to CSV
                $fileoutput .= $row["fname"] . "," . $row["lname"] . "," . $row["email"] . "\n";
            } else {
                $delete = $locate = "";
                if (isset($USER->ip)) {
                    $locate = '
                        <button class="alike" title="IP Location" onclick="members_get_geodata(\'' . $USER->ip . '\', \'display\');">
                            ' . icon("compass", 2) . '
                        </button>';
                }

                if (!is_siteadmin($row["userid"])) {
                    $delete = '
                        <button class="alike" title="Delete ' . $row['fname'] . ' ' . $row['lname'] . '" onclick="members_delete_user(\'' . $row['userid'] . '\');">
                            ' . icon("trash", 2) . '
                        </button>';
                    $reset = '
                        <button class="alike" title="Reset ' . $row['fname'] . ' ' . $row['lname'] . ' Password" onclick="members_reset_password(\'' . $row['userid'] . '\');">
                            ' . icon("retweet", 2, "", "orange") . '
                        </button>';
                }

                $searchresult .= '
                    <tr style="font-size:.85em;">
                        <td style="vertical-align:middle;overflow:hidden;white-space:nowrap;">
                            <input type="hidden" class="user_id" id="user_id_' . $row['userid'] . '" value="' . $row['userid'] . '" />
                            <input type="hidden" class="user_name" value="' . $row['fname'] . ' ' . $row['lname'] . '" />
                            ' . $row['fname'] . ' ' . $row['lname'] . ' ' . ($row['userid'] !== $USER->userid ? '(<a href="javascript: void(0)" onclick=loginas(' . $row['userid'] . ')>log in as</a>)' : '') . '
                        </td>
                        <td style="width:40px;white-space:nowrap;text-align:center;" onmouseover="this.style.backgroundColor=\'#BBD8EC\';" onmouseout="this.style.backgroundColor=\'\'">
                            <span id="reset_password_' . $row['userid'] . '">
                                ' . $reset . '
                            </span>
                        </td>
                        <td style="width:40px;white-space:nowrap;text-align:center;" onmouseover="this.style.backgroundColor=\'#BBD8EC\';" onmouseout="this.style.backgroundColor=\'\'">
                            ' . $locate . '
                        </td>
                        <td style="width:40px;white-space:nowrap;text-align:center;" onmouseover="this.style.backgroundColor=\'#BBD8EC\';" onmouseout="this.style.backgroundColor=\'\'" >
                            <a title="User Logs" href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/adminpanel_ajax.php\',\'view_logfile\',\'&userid=' . $row["userid"] . '\',function() { simple_display(\'display\');});"><img src="' . $CFG->wwwroot . '/images/graph.png" alt="User Logs" /></a>
                        </td>
                        <td style="text-align:center;white-space:nowrap;padding: 0 10px;">
                            ' . ago($row['joined']) . '
                        </td>
                        <td style="text-align:center;white-space:nowrap;padding: 0 10px;">
                            <span>
                                ' . ago($row['last_activity']) . '
                            </span>
                        </td>
                        <td style="text-align:center;min-width:60px;">
                            ' . $delete . '
                        </td>
                    </tr>';
            }
        }
        $export = '<div style="font-size:.65em;padding:2px;"><a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/members_script.php\',\'members_search\',\'&csv=1&search=' . $searchwords . '\',function() { if (xmlHttp.readyState == 4) { run_this(); }}, true);" >Export to CSV</a>&nbsp;&nbsp;<a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/members_script.php\',\'members_search\',\'&mailman=1&search=' . $searchwords . '\',function() { if (xmlHttp.readyState == 4) { run_this(); }}, true);" >Export to Mailman</a></div>';
    }

    if (!$mailman && !$csv) {
        if (empty($searchresult)) {
            $searchresult = '<tr><td colspan="7" style="font-size:.8em;text-align:center;"><b>No matches found.</b></td></tr></table>';
        }

        $prev = $pagenum > 0 ? '<a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/members_script.php\',\'members_search\',\'&pagenum=' . ($pagenum - 1) . '&search=\'+encodeURIComponent(\'' . $searchwords . '\'),function() { if (xmlHttp.readyState == 4) { simple_display(\'mem_resultsdiv\'); }}, true);" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/arrow_left.gif" title="Previous Page" alt="Previous Page"></a>' : "";
        $next = $firstonpage + $perpage < $total ? '<a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/adminpanel/members_script.php\',\'members_search\',\'&pagenum=' . ($pagenum + 1) . '&search=\'+encodeURIComponent(\'' . $searchwords . '\'),function() { if (xmlHttp.readyState == 4) { simple_display(\'mem_resultsdiv\'); }}, true);" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/arrow_right.gif" title="Next Page" alt="Next Page"></a>' : "";
        $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";

        $return = '
        <table style="width:100%;">
            <tr>
                <td style="width:25%;text-align:left;">
                    ' . $prev . '
                </td>
                <td style="width:50%;text-align:center;font-size:.75em;color:green;">
                    ' . $info . '
                </td>
                <td style="width:25%;text-align:right;">
                    ' . $next . '
                </td>
            </tr>
        </table>
        <br /><br />
        ' . $export . '
        <input type="hidden" id="searchwords" value="' . $searchwords . '" />
        <table class="searchresults">
            <tr>
                <th style="text-align:left;width: 100%;">
                    <strong>Name</strong>
                </th>
                <th>
                </th>
                <th>
                </th>
                <th>
                </th>
                <th style="text-align:center;min-width: 100px;">
                    <strong>Joined</strong>
                </th>
                <th style="text-align:center;min-width: 100px;">
                    <strong>Last Access</strong>
                </th>
                <th style="text-align:center;min-width: 60px;">
                </th>
            </tr>
            ' . $searchresult . '
        </table>';
        ajax_return($return);
    } else {
        $filename = $mailman ? "users_export.txt" : "users_export.csv";
        ajax_return(get_download_link($filename, $fileoutput));
    }
}
?>