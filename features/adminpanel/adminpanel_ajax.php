<?php
/***************************************************************************
* adminpanel_ajax.php - Adminpanel backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 0.0.8
***************************************************************************/
if (!isset($CFG)) { include('../header.php'); }
update_user_cookie();

if (!empty($_SESSION["lia_original"])) {
    if (!is_siteadmin($_SESSION["lia_original"])) { debugging(error_string("generic_permissions"), 2); return;}
} else {
    if (!is_siteadmin($USER->userid)) { debugging(error_string("generic_permissions"), 2); return;}
}

callfunction();

function admin_email_tester() {
    echo '
    <strong>Send Test Email</strong><br /><br />
    Email Address: <input type="text" id="email" />
    <input type="button" value="Send Test" onclick="ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'admin_email_test\',\'&amp;email=\'+document.getElementById(\'email\').value,function() { simple_display(\'display\');});" />
    ';
}

function get_phpinfo() {
global $CFG;
    echo "<iframe style='width:100%;height:100%;border:none;' src='$CFG->wwwroot/features/adminpanel/adminpanel_ajax.php?action=phpinfo'></iframe>";
}

function camper_list() {
global $CFG;
    echo "<iframe style='width:100%;height:100%;border:none;' src='$CFG->wwwroot/features/adminpanel/camper_list.php'></iframe>";
}

function admin_email_test() {
global $CFG, $MYVARS;

    admin_email_tester(); //Send for again

    $touser = new \stdClass;
    $fromuser = new \stdClass;

    //Now output the last test.
    $touser->email = $MYVARS->GET["email"];
    $touser->fname = "Test";
    $touser->lname = "Email";

    $fromuser->email = $CFG->siteemail;
    $fromuser->fname = $CFG->sitename;
    $fromuser->lname = "";

    $subject = "SERVER: EMAIL TEST";
    $message = "This is a test message sent: " . date('l jS \of F Y h:i:s A');

    if (send_email($touser, $fromuser, $subject, $message)) {
        echo "<br />Email Success";
    } else {
        echo "<br />Email Failed";
    }
}

function user_admin() {
    include("members.php");
}

function site_versions() {
    echo '<table style="width:100%;font-size:.8em;">';

    //Site DB version
    echo '<tr><td colspan="2" style="text-align:center"><ins>Site db version</ins></td><td></tr><tr><td colspan="2" style="text-align:center">' . get_db_field("setting", "settings", "type='site' AND setting_name='version'") . '</td></tr>';

    //Feature versions
    echo '<tr><td colspan="2" style="text-align:center"></td></tr><tr><td colspan="2" style="text-align:center"><ins>Feature db version</ins></td><td></tr>';
    if ($result = get_db_result("SELECT * FROM features ORDER BY feature")) {
        while ($row = fetch_row($result)) {
            echo '<tr><td style="width:50%;text-align:right;border:1px solid silver;padding:3px;">' . $row["feature_title"] . '</td><td style="padding:3px;border:1px solid silver;text-align:left">' . $row["version"] . '</td></tr>';
        }
    }
    echo '</table>';
}

/**
 * View a user's log file
 */
function view_logfile() {
global $USER, $MYVARS;
    // Set the view type, default to all
    $viewtype = isset($MYVARS->GET["viewtype"]) ? $MYVARS->GET["viewtype"] : "all";
    
    // Set the year, default to current
    $year = isset($MYVARS->GET["year"]) ? $MYVARS->GET["year"] : date("Y");
    
    // Set the month, default to current
    $month = isset($MYVARS->GET["month"]) ? $MYVARS->GET["month"] : date("m");
    
    // Set the user id, default to current user
    $userid = isset($MYVARS->GET["userid"]) ? $MYVARS->GET["userid"] : $USER->userid;
    
    // Set the page number, default to 0
    $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
    
    // Set the previous and next years and months
    $prevyear = $year;
    $nextyear = $year;
    $prevmonth = $month - 1;
    $nextmonth = $month + 1;
    
    if ($month == 1) { // January.
        $prevyear--;
        $prevmonth = 12;
    } elseif ($month == 12) { // December.
        $nextyear++;
        $nextmonth = 1;
    }
    
    // Next and Previous Month links
    $next = date('Y') < $nextyear || (date('Y') == $nextyear && date('m') < $nextmonth) ? '' : '<div class="button" style="display: inline-block" onclick="ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'view_logfile\',\'&amp;year=' . $nextyear . '&amp;month=' . $nextmonth . '&amp;userid=' . $userid . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'display\'); }}, true);" onmouseup="this.blur()">View ' . date("F Y",mktime(0,0,0, $nextmonth,1, $nextyear)) . '</div>';
    $prev = '<div class="button" style="display: inline-block" onclick="ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'view_logfile\',\'&amp;year=' . $prevyear . '&amp;month=' . $prevmonth . '&amp;userid=' . $userid . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'display\'); }}, true);" onmouseup="this.blur()">View ' . date("F Y",mktime(0,0,0, $prevmonth,1, $prevyear)) . '</div>';
    
    echo '<div style="caret-color: transparent;">
            <div>
                <table style="font-size:.75em;width:98%;margin-right:auto;margin-left:auto;">
                    <tr>
                        <td style="text-align:left">
                            ' . $prev . '
                        </td>
                        <td style="text-align:right">
                        ' . $next . '
                        </td>
                    </tr>
                </table>
                <iframe src="members_log_graph.php?rnd=' . time() . '&userid=' . $userid . '&year=' . $year . '&month=' . $month . '" style="width:100%;height:425px;border:none;"></iframe>
                <br />
            </div>
            <div id="actions_div">
                ' . get_user_usage($userid, $pagenum, $year, $month) . '
            </div>
          </div>';
}

function get_user_usage_page() {
global $MYVARS;
    echo get_user_usage($MYVARS->GET["userid"], $MYVARS->GET["pagenum"], $MYVARS->GET["year"], $MYVARS->GET["month"]);
}

function get_user_usage($userid, $pagenum, $year, $month) {
global $MYVARS, $USER;
    $returnme = ""; $perpage=20;
    $firstonpage = $perpage * $pagenum;
    $LIMIT = " LIMIT $firstonpage," . $perpage;
    $data = []; $i=0;

    $SQL = "SELECT *
              FROM logfile
             WHERE userid = $userid
               AND YEAR(FROM_UNIXTIME(timeline)) = $year
               AND MONTH(FROM_UNIXTIME(timeline)) = $month";
    $total = get_db_count($SQL);

    $SQL = "SELECT *,
                   YEAR(FROM_UNIXTIME(timeline)) as myyear,
                   MONTH(FROM_UNIXTIME(timeline)) as mymonth,
                   DAYOFMONTH(FROM_UNIXTIME(timeline)) as myday 
              FROM logfile
             WHERE userid = $userid
               AND YEAR(FROM_UNIXTIME(timeline)) = $year
               AND MONTH(FROM_UNIXTIME(timeline)) = $month
          ORDER BY timeline
              DESC $LIMIT";

    $next = $pagenum > 0 ? '<div class="button" style="display: inline-block" onclick="ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'get_user_usage_page\',\'&amp;pagenum=' . ($pagenum - 1) . '&amp;year=' . $year . '&amp;month=' . $month . '&amp;userid=' . $userid . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'actions_div\'); }}, true);" onmouseup="this.blur()">Later Actions</div>' : "";
    $prev = $firstonpage + $perpage < $total ? '<div class="button" style="display: inline-block" onclick="ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'get_user_usage_page\',\'&amp;pagenum=' . ($pagenum + 1) . '&amp;year=' . $year . '&amp;month=' . $month . '&amp;userid=' . $userid . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'actions_div\'); }}, true);" onmouseup="this.blur()">Previous Actions</div>' : "";

    $returnme .= '<table style="font-size:.75em;width: 100%;">
                    <tr>
                        <td style="text-align:left">
                            ' . $prev . '
                        </td>
                        <td style="text-align:right">
                            ' . $next . '
                        </td>
                    </tr>
                  </table>
                  <table class="datatable">
                    <thead>
                        <tr>
                            <th>
                                Date
                            </th>
                            <th style="width:35%;">
                                Page
                            </th>
                            <th style="width:10%;">
                                Feature
                            </th>
                            <th style="width:30%;">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>';

    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            $data[$i] = $row;
            $i++;
        }

        $data= array_reverse($data);
        $i = 0;
        while (isset($data[$i])) {
            $info = get_db_field("setting", "settings", "type='" . $data[$i]["feature"] . "' AND setting_name='feature_title' AND pageid='" . $data[$i]["pageid"] . "' AND featureid='" . $data[$i]["info"] . "'");
            $info = $info != "" ? $info : $data[$i]["feature"];
            $returnme .= '<tr>
                            <td>
                                ' . date("m/d/Y g:i a", $data[$i]["timeline"]) . '
                            </td>
                            <td>
                                ' . stripslashes(get_db_field("name", "pages", "pageid='" . $data[$i]["pageid"] . "'")) . '
                            </td>
                            <td>
                                ' . $info . '
                            </td>
                            <td>
                                ' . stripslashes($data[$i]["description"]) . '
                            </td>
                          </tr>';
            $i++;
        }
    } else {
        $returnme .= '<tr><td colspan="4">No Usage</td></tr>';
    }

    $returnme .= '</tbody></table></div>';
    return $returnme;
}

function ipmap() {
global $CFG, $MYVARS;
    $json = json_decode($MYVARS->GET["json"]);
    echo '<iframe style="height:100%;width:100%" src="https://www.google.com/maps/embed/v1/place?q=' . $json->lat . ',' . $json->lon . '&key=' . $CFG->googlemapsembedkey . '"></iframe>';
}

function loginas() {
global $MYVARS;
    $userid = $MYVARS->GET["userid"];
    if (!empty($userid)) {
        if (empty($_SESSION["lia_original"])) {
            $_SESSION["lia_original"] = $_SESSION["userid"];
        }
        $_SESSION["userid"] = $userid;
    }

    echo $_SESSION["pageid"];
}

function logoutas() {
    $_SESSION["userid"] = $_SESSION["lia_original"];
    unset($_SESSION["lia_original"]);
    echo $_SESSION["pageid"];
}
?>
