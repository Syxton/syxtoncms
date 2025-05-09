<?php
/***************************************************************************
* adminpanel_ajax.php - Adminpanel backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.8
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

update_user_cookie();

if (!empty($_SESSION["lia_original"])) {
    if (!is_siteadmin($_SESSION["lia_original"])) {
        trigger_error(getlang("generic_permissions", false, ["administrative"]), E_USER_WARNING);
        return;
    }
} else {
    if (!is_siteadmin($USER->userid)) {
        trigger_error(getlang("generic_permissions", false, ["administrative"]), E_USER_WARNING);
        return;
    }
}

callfunction();

function admin_email_tester() {
    ajax_return(admin_email_test_form());
}

function admin_email_test_form() {
    ajaxapi([
        "id" => "emailtester",
        "if" => "$('#email').val().length > 0",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => ["action" => "admin_email_test", "email" => "js||$('#email').val()||js"],
        "display" => "display",
    ]);

    return '
        <strong>Send Test Email</strong>
        <br /><br />
        Email Address: <input type="text" id="email" />
        <input id="emailtester" type="button" value="Send Test" />';
}

function get_phpinfo() {
global $CFG;
    $params = [
        "id" => "phpinfo",
        "src" => $CFG->wwwroot . "/features/adminpanel/adminpanel_ajax.php?action=phpinfo",
    ];
    ajax_return(fill_template("tmp/main.template", "admin_iframe", "adminpanel", $params));
}

function camper_list() {
global $CFG;
    $params = [
        "id" => "camperlist",
        "src" => $CFG->wwwroot . "/features/adminpanel/camper_list.php",
    ];
    ajax_return(fill_template("tmp/main.template", "admin_iframe", "adminpanel", $params));
}


function unit_tests() {
global $CFG;
    $params = [
        "id" => "unit_tests",
        "src" => $CFG->wwwroot . "/features/adminpanel/tests/unit_tests.php",
    ];
    ajax_return(fill_template("tmp/main.template", "admin_iframe", "adminpanel", $params));
}

function admin_email_test() {
global $CFG;
    $error = "";
    try {
        $returnme = admin_email_test_form();

        $touser = (object) [
            "email" => clean_myvar_req("email", "string"),
            "fname" => "Test",
            "lname" => "Email",
        ];

        $fromuser = (object) [
            "email" => $CFG->siteemail,
            "fname" => $CFG->sitename,
            "lname" => "",
        ];

        $subject = "SERVER: EMAIL TEST";
        $message = "This is a test message sent: " . date('l jS \of F Y h:i:s A');

        if (send_email($touser, $fromuser, $subject, $message)) {
            $returnme .= "<br />Email Success";
        } else {
            $returnme .= "<br />Email Failed";
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($returnme, $error);
}

function user_admin() {
    include("members.php");
}

function site_versions() {
    $versions = [];
    // Feature versions
    if ($result = get_db_result("SELECT * FROM features ORDER BY feature_title")) {
        while ($row = fetch_row($result)) {
            $versions[] = (object)["title" => $row["feature_title"], "version" => $row["version"]];
        }
    }

    $params = [
        "siteversion" => get_db_field("setting", "settings", "type='site' AND setting_name='version'"),
        "featureversions" => (object)$versions,
    ];
    ajax_return(fill_template("tmp/main.template", "admin_versions", "adminpanel", $params));
}

/**
 * View a user's log file
 */
function view_logfile() {
global $USER;
    // Set the view type, default to all
    $viewtype = clean_myvar_opt("viewtype", "string", "all");

    // Set the year, default to current
    $year = clean_myvar_opt("year", "int", date("Y"));

    // Set the month, default to current
    $month = clean_myvar_opt("month", "int", date("m"));

    // Set the user id, default to current user
    $userid = clean_myvar_opt("userid", "int", $USER->userid);

    // Set the page number, default to 0
    $pagenum = clean_myvar_opt("pagenum", "int", 0);

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
    $next = $prev = "";
    if ($nextyear <= date('Y') || ($nextyear === date('Y') && $nextmonth <= date('m'))) {
        ajaxapi([
            "id" => "nextmonthlog",
            "url" => "/features/adminpanel/adminpanel_ajax.php",
            "data" => ["action" => "view_logfile", "year" => $nextyear, "month" => $nextmonth, "userid" => $userid],
            "display" => "display",
        ]);
        $next = '<button id="nextmonthlog" onmouseup="this.blur()">View ' . date("F Y",mktime(0,0,0, $nextmonth,1, $nextyear)) . '</button>';
    }

    ajaxapi([
        "id" => "prevmonthlog",
        "url" => "/features/adminpanel/adminpanel_ajax.php",
        "data" => ["action" => "view_logfile", "year" => $prevyear, "month" => $prevmonth, "userid" => $userid],
        "display" => "display",
    ]);
    $prev = '<button id="prevmonthlog" onmouseup="this.blur()">View ' . date("F Y",mktime(0,0,0, $prevmonth,1, $prevyear)) . '</button>';

    $params = [
        "prev" => $prev,
        "next" => $next,
        "url" => "members_log_graph.php?rnd=" . time() . "&userid=" . $userid . "&year=" . $year . "&month=" . $month,
        "useractions" => get_user_usage($userid, $pagenum, $year, $month),
    ];
    $return = fill_template("tmp/main.template", "userlog", "adminpanel", $params);
    ajax_return($return);
}

function get_user_usage_page() {
    $userid = clean_myvar_req("userid", "int");
    $pagenum = clean_myvar_opt("pagenum", "int", 0);
    $year = clean_myvar_req("year", "int");
    $month = clean_myvar_req("month", "int");

    $data = $error = "";
    try {
        $data = get_user_usage($userid, $pagenum, $year, $month);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return($data, $error);
}

function get_user_usage($userid, $pagenum, $year, $month) {
global $USER;
    $perpage = 20;
    $firstonpage = $perpage * $pagenum;
    $LIMIT = " LIMIT $firstonpage," . $perpage;
    $data = []; $i = 0;

    $sqlvalues = ["userid" => $userid, "year" => $year, "month" => $month];

    $SQL = fill_template("dbsql/adminpanel.sql", "useractions", "adminpanel", ["fields" => "userid"], true);
    $total = get_db_count($SQL, $sqlvalues);

    $sqlparams = [
        "fields" => "*, YEAR(FROM_UNIXTIME(timeline)) as myyear, MONTH(FROM_UNIXTIME(timeline)) as mymonth, DAYOFMONTH(FROM_UNIXTIME(timeline)) as myday",
        "order" => "ORDER BY timeline",
        "limit" => "DESC $LIMIT",
    ];
    $SQL = fill_template("dbsql/adminpanel.sql", "useractions", "adminpanel", $sqlparams, true);

    $actions = "";
    if ($result = get_db_result($SQL, $sqlvalues)) {
        while ($row = fetch_row($result)) {
            $data[$i] = $row;
            $i++;
        }

        $data= array_reverse($data);
        $i = 0;
        while (isset($data[$i])) {
            $info = get_db_field("setting", "settings", "type='" . $data[$i]["feature"] . "' AND setting_name='feature_title' AND pageid='" . $data[$i]["pageid"] . "' AND featureid='" . $data[$i]["info"] . "'");
            $info = $info != "" ? $info : $data[$i]["feature"];

            $params = [
                "date" => date("m/d/Y g:i a", $data[$i]["timeline"]),
                "page" => stripslashes(get_db_field("name", "pages", "pageid='" . $data[$i]["pageid"] . "'")),
                "info" => $info,
                "action" => stripslashes($data[$i]["description"]),
                "ip" => $data[$i]["ip"],
            ];
            $actions .= fill_template("tmp/main.template", "useractionrow", "adminpanel", $params);
            $i++;
        }
    } else {
        $actions = fill_template("tmp/main.template", "useractionemptyrow", "adminpanel");
    }

    $next = $prev = "";
    if ($pagenum > 0) {
        ajaxapi([
            "id" => "nextuseractions",
            "url" => "/features/adminpanel/adminpanel_ajax.php",
            "data" => [
                "action" => "get_user_usage_page",
                "year" => $year,
                "month" => $month,
                "userid" => $userid,
                "pagenum" => $pagenum - 1
            ],
            "display" => "actions_div",
        ]);
        $next = '<button id="nextuseractions">Later Actions</button>';
    }

    if ($firstonpage + $perpage < $total) {
        ajaxapi([
            "id" => "prevuseractions",
            "url" => "/features/adminpanel/adminpanel_ajax.php",
            "data" => [
                "action" => "get_user_usage_page",
                "year" => $year,
                "month" => $month,
                "userid" => $userid,
                "pagenum" => $pagenum + 1
            ],
            "display" => "actions_div",
        ]);
        $prev = '<button id="prevuseractions">Previous Actions</button>';
    }

    $params = [
        "actions" => $actions,
        "prev" => $prev,
        "next" => $next,
    ];
    return fill_template("tmp/main.template", "useractionstable", "adminpanel", $params);
}

function ipmap() {
global $CFG;
    $geodata = clean_myvar_req("geodata", "json");
    $googlemapsiframe = '<iframe id="ip_map" onload="resizeCaller(this.id);" style="width:100%;height: 100vh;border:none;" src="https://www.google.com/maps/embed/v1/place?q=' . $geodata->lat . ',' . $geodata->lon . '&key=' . $CFG->googlemapsembedkey . '"></iframe>';
    ajax_return($googlemapsiframe);
}

function loginas() {
    $userid = clean_myvar_opt("userid", "int", false);
    if (!empty($userid)) {
        if (empty($_SESSION["lia_original"])) {
            $_SESSION["lia_original"] = $_SESSION["userid"];
        }
        $_SESSION["userid"] = $userid;
    }

    ajax_return($_SESSION["pageid"]);
}

function logoutas() {
    $_SESSION["userid"] = $_SESSION["lia_original"];
    unset($_SESSION["lia_original"]);
    ajax_return($_SESSION["pageid"]);
}
?>
