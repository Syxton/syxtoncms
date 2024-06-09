<?php
/***************************************************************************
* members_script.php - AJAX members search script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.6
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); }
update_user_cookie();

if (!is_siteadmin($USER->userid)) { trigger_error(error_string("generic_permissions"), E_USER_WARNING); return; }

callfunction();

function members_search() {
global $CFG, $MYVARS, $USER, $smarty;

    //Search variables
    $perpage=10;
    $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
    $firstonpage = $perpage * $pagenum;
    $LIMIT = " LIMIT $firstonpage," . $perpage;

    $name = false; $NAMESEARCHSQL = ""; $namesearch = [];
    $cfield = false; $FIELDSEARCHSQL = ""; $fieldsearch = [];
    $csort = false; $SORT = "u.lname,u.fname";
    $easteregg = false;

    //split the search words and find out what they mean
    $searchwords = trim($MYVARS->GET["search"]);
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

	//MAKE NAME SEARCH SQL
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

	if ($easteregg) {
		if ($easteregg == "fail") {
			echo '<iframe src="//failblog.org/" style="width:100%;border:none;height:800px;" />';
		}
	} else {
		$and1 = !empty($name) ? " AND " : "";
		$and2 = !empty($cfield) ? " AND " : "";

		$SQL = "SELECT * FROM users u WHERE userid!=0 $and1 $NAMESEARCHSQL $and2 $FIELDSEARCHSQL ORDER BY $SORT";

		  //Create the page limiter
		  $total = get_db_count($SQL); //get the total for all pages returned.
		  $count = $total > (($pagenum + 1) * $perpage) ? $perpage : $total - (($pagenum) * $perpage); //get the amount returned...is it a full page of results?
		$amountshown = $firstonpage + $perpage < $total ? $firstonpage + $perpage : $total;


		if (!isset($MYVARS->GET["mailman"]) && !isset($MYVARS->GET["csv"])) { $SQL .= $LIMIT; }//Limit to one page of return.
		$pages = get_db_result($SQL);
		$prev = $pagenum > 0 ? '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/adminpanel/members_script.php\',\'members_search\',\'&amp;pagenum=' . ($pagenum - 1) . '&amp;search=\'+encodeURIComponent(\'' . $searchwords . '\'),function() { if (xmlHttp.readyState == 4) { simple_display(\'mem_resultsdiv\'); }}, true);" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/arrow_left.gif" title="Previous Page" alt="Previous Page"></a>' : "";
		$next = $firstonpage + $perpage < $total ? '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/adminpanel/members_script.php\',\'members_search\',\'&amp;pagenum=' . ($pagenum + 1) . '&amp;search=\'+encodeURIComponent(\'' . $searchwords . '\'),function() { if (xmlHttp.readyState == 4) { simple_display(\'mem_resultsdiv\'); }}, true);" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/arrow_right.gif" title="Next Page" alt="Next Page"></a>' : "";

		//echo $SQL;
		$header = ""; $fileoutput = "";
		  $body = '<input type="hidden" id="searchwords" value="' . $searchwords . '" />
                <table style="border:1px solid gray;width:100%;border-collapse:collapse;">
					<tr style="border:1px solid gray;background-color:#BBD8EC;">
						<td style="text-align:left;font-size:.75em;"><b>Name</b></td>
						<td></td><td></td><td></td>
						<td style="text-align:center;font-size:.75em;"><b>Joined</b></td><td></td>
						<td style="text-align:center;font-size:.75em;"><b>Last Access</b></td>
						<td></td>
					</tr>';

	 		if ($count > 0) {
		      while ($row = fetch_row($pages)) {
				if (isset($MYVARS->GET["mailman"])) { //Export to Mailman
					$fileoutput .= $row["fname"] . " " . $row["lname"] . " <" . $row["email"] . ">\r\n";
				} elseif (isset($MYVARS->GET["csv"])) { //Export to CSV
					$fileoutput .= $row["fname"] . "," . $row["lname"] . "," . $row["email"] . "\n";
				} else {
					$lastip = get_db_field("ip", "logfile", "userid='" . $row["userid"] . "' ORDER BY timeline DESC");
                    $locate = $lastip ? '<a title="IP Location" href="javascript: void(0);" onclick="get_coordinates(\'' . $lastip . '\',\'display\');"><img src="' . $CFG->wwwroot . '/images/locate.png" alt="IP Location" /></a>' : "";
					$delete = !is_siteadmin($row["userid"]) ? '<a title="Delete User" href="javascript: void(0);" onclick="if (confirm(\'Do you want to delete ' . addslashes($row['fname']) . ' ' . addslashes($row['lname']) . '\\\'s account?\')) { ajaxapi(\'/ajax/site_ajax.php\',\'delete_user\',\'&userid=' . $row['userid'] . '\',function() { ajaxapi(\'/features/adminpanel/members_script.php\',\'members_search\',\'&amp;pagenum=' . $pagenum . '&amp;search=' . $searchwords . '\',function() { simple_display(\'mem_resultsdiv\'); });}); }" >' . icon("trash") . '</a>' : "";
                    $reset  = !is_siteadmin($row["userid"]) ? '<a title="Reset ' . $row['fname'] . ' ' . $row['lname'] . ' Password" href="javascript: void(0);" onclick="if (confirm(\'Do you want to reset ' . addslashes($row['fname']) . ' ' . addslashes($row['lname']) . '\\\'s password?\')) { ajaxapi(\'/ajax/site_ajax.php\',\'forgot_password\',\'&admin=true&userid=' . $row['userid'] . '\',function() { simple_display(\'reset_password_' . $row['userid'] . '\'); });}" ><img src="' . $CFG->wwwroot . '/images/reset.png" alt="Reset ' . $row['fname'] . ' ' . $row['lname'] . ' Password" /></a>' : "";
                    $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
				      if ($amountshown > 0) { $header = '<table style="width:100%;"><tr><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><br /><br />'; }
                    $body .= '<tr style="height:40px;background-color: white;" onmouseover="this.style.backgroundColor=\'#E7F1F8\';" onmouseout="this.style.backgroundColor=\'white\';">
                          		<td style="vertical-align:middle;overflow:hidden;font-size:.85em;white-space:nowrap;">
                                    <input type="hidden" id="' . $row['userid'] . '" value="' . $row['userid'] . '" />&nbsp;
                          			' . $row['fname'] . ' ' . $row['lname'] . ' ' . ($row['userid'] !== $USER->userid ? '(<a href="javascript: void(0)" onclick=loginas(' . $row['userid'] . ')>log in as</a>)' : '') . '
                          		</td>
                          		<td style="width:40px;white-space:nowrap;text-align:center;" onmouseover="this.style.backgroundColor=\'#BBD8EC\';" onmouseout="this.style.backgroundColor=\'\'">
                          			<span style="font-size:.6em" id="reset_password_' . $row['userid'] . '">
                                    ' . $reset . '
                                    </span>
                                </td>
                          		<td style="width:40px;white-space:nowrap;text-align:center;" onmouseover="this.style.backgroundColor=\'#BBD8EC\';" onmouseout="this.style.backgroundColor=\'\'">
                          			' . $locate . '
                          		</td>
                          		<td style="width:40px;white-space:nowrap;text-align:center;" onmouseover="this.style.backgroundColor=\'#BBD8EC\';" onmouseout="this.style.backgroundColor=\'\'" >
                          		 	<a title="User Logs" href="javascript: void(0);" onclick="ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'view_logfile\',\'&amp;userid=' . $row["userid"] . '\',function() { simple_display(\'display\');});"><img src="' . $CFG->wwwroot . '/images/graph.png" alt="User Logs" /></a>
                          		</td>
                          		<td style="width:10%;white-space:nowrap;text-align:left;">
                          			<span style="font-size:.6em">
                          				' . ago($row['joined']) . '
                          			</span>
                          		</td>
                          		<td style="text-align:left;width:10px;"></td>
                          		<td style="width:10%;white-space:nowrap;text-align:left;">
                          			<span style="font-size:.6em">
                          				' . ago($row['last_activity']) . '
                          			</span>
                          		</td>
                          		<td style="width:40px;white-space:nowrap;text-align:center;" onmouseover="this.style.backgroundColor=\'red\';" onmouseout="this.style.backgroundColor=\'\'">
                          			<span style="font-size:.6em">
                          			' . $delete . '
                                    </span>
                          		</td>
                            </tr>';
				}
			}

            $body .= "</table>";
 			      $export = '<div style="font-size:.65em;padding:2px;"><a href="javascript: void(0);" onclick="ajaxapi(\'/features/adminpanel/members_script.php\',\'members_search\',\'&amp;csv=1&amp;search=' . $searchwords . '\',function() { if (xmlHttp.readyState == 4) { run_this(); }}, true);" >Export to CSV</a>&nbsp;&nbsp;<a href="javascript: void(0);" onclick="ajaxapi(\'/features/adminpanel/members_script.php\',\'members_search\',\'&amp;mailman=1&amp;search=' . $searchwords . '\',function() { if (xmlHttp.readyState == 4) { run_this(); }}, true);" >Export to Mailman</a></div>';

		      if (!isset($MYVARS->GET["mailman"]) && !isset($MYVARS->GET["csv"])) { echo $header . $export . $body;
            } else {
					$filename = isset($MYVARS->GET["mailman"]) ? "users_export.txt" : "users_export.csv";
                echo get_download_link($filename, $fileoutput);
			}
		} else {
		      echo $body . '<tr><td colspan="9" style="font-size:.8em;text-align:center;"><b>No matches found.</b></td></tr></table>';
		  }
	}
}
?>