<?php
/***************************************************************************
* dblib.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.7.7
***************************************************************************/

if (!LIBHEADER) { include ('header.php'); }
define('DBLIB', true);

global $conn; // Database connection global;

if ($CFG->dbtype == "mysqli") {
	require('dblib_mysqli.php');
} else {
	require('dblib_mysql.php');
}

function reconnect() {
global $CFG;
	if ($CFG->dbtype == "mysqli" && function_exists('mysqli_connect')) { // mysqli is installed
		set_db_report_level();
		$conn = mysqli_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or trigger_error("Could not connect to database");
		mysqli_select_db($conn, $CFG->dbname) or trigger_error("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . get_db_errorno() . ") " . get_db_error());
	} elseif ($CFG->dbtype == "mysql" && function_exists('mysql_connect')) {
		set_db_report_level();
		$conn = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or trigger_error("Could not connect to database");
		mysql_select_db($CFG->dbname) or trigger_error("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . get_db_errorno() . ") " . get_db_error());
	}
	return $conn;
}

function get_db_row($SQL, $vars = [], $type = false) {
global $CFG;
	if (is_select($SQL)) {
		$count = get_db_count($SQL, $vars);
		if ($count > 1) {
			trigger_error("get_db_row: $SQL returned $count results. Expected 1", E_USER_WARNING);
		}
	}

	$type = get_mysql_array_type($type);
	if ($result = get_db_result($SQL, $vars)) {
		return fetch_row($result, $type);
	}
	return false;
}

function execute_db_sqls($SQLS, $vars = []) {
global $conn;
	$SQLS = trim($SQLS, ";\r\n");
	$sql_array = explode(";\r\n", $SQLS);
	$sql_array = array_filter($sql_array); // Remove any empty array elements.
	$returns = [];

	/* Start transaction */
	start_db_transaction($conn);
	try {
		$i = 0;
		foreach ($sql_array as $SQL) {
			$v = $vars[$i] ?? [];
			if ($result = execute_db_sql($SQL, $v)) {
				$returns[] = $result;
			}
			$i++;
		}
		commit_db_transaction($conn);
	} catch (\Throwable $e) {
		error_log("ROLLBACK: " . $e->getMessage());
		rollback_db_transaction($conn);
		return false;
	}
	return $returns;
}

function get_db_field($field, $from, $where, $vars = []) {
global $CFG;
	$SQL = "SELECT $field FROM $from WHERE $where";
	$count = get_db_count($SQL, $vars);
	if ($count > 1) {
		trigger_error("get_db_field -> $count: $SQL returned $count results. Expected 1", E_USER_WARNING);
	}

	if ($result = get_db_result($SQL, $vars)) {
		$row = fetch_row($result);
		return $row[$field];
	}
	return false;
}

function get_db_count($SQL, $vars = []) {
global $CFG;
	// Prepare SQL.
	$SQL = rtrim($SQL, '; ');
	$SQL = "SELECT COUNT(*) as count FROM ($SQL) as countable";
	if ($result = get_db_result($SQL, $vars)) {
		if ($row = fetch_row($result)) {
			return $row["count"];
		}
	}
	return 0;
}

function is_select($SQL) {
	return preg_match('/^(SELECT)/i', trim($SQL)) ? true : false;
}

/**
 * Authenticate a user by username and password.
 *
 * @param string $username The username of the user to authenticate.
 * @param string $password The password of the user to authenticate.
 * @return \array|false The user object if authentication is successful, false otherwise.
 */
function authenticate(string $username, string $password) {
	global $CFG, $USER;
	$time = get_timestamp();
	$params = ["username" => $username, "password" => $password];
	$SQL = use_template("dbsql/db.sql", $params, "authenticate"); // Authenticate
	if (!$user = get_db_row($SQL)) { // COULD NOT AUTHENTICATE
		$SQL = use_template("dbsql/db.sql", $params, "authenticate_alt");
		if ($user = get_db_row($SQL)) { // Attempt authentication on alternate password field
			$_SESSION['userid'] = $user['userid'];
			$params = ["userid" => $user['userid'], "time" => $time, "ip" => $_SERVER['REMOTE_ADDR'], "isfirst" => false, "clear_alt" => false];
			$SQL = use_template("dbsql/db.sql", $params, "update_last_activity");
			execute_db_sql($SQL);
			return $user; // Password reset authentication successful
		}

		log_entry("user", $username, "Failed Login"); // Log
		return false; // Password authentication failed
	} else { // Regular authentication successful.
		if (strlen($user['temp']) > 0) { // on first ever login, switch temp password for actual password
			$SQL = use_template("dbsql/db.sql", ["user" => $user], "activate_account");
			execute_db_sql($SQL);

			// Send account activated email.
			$FROMUSER = new \stdClass;
			$FROMUSER->fname = $CFG->sitename;
			$FROMUSER->lname = '';
			$FROMUSER->email = $CFG->siteemail;
			$params = [
				"user" => $user,
				"sitename" => $CFG->sitename,
				"siteowner" => $CFG->siteowner,
				"siteemail" => $CFG->siteemail,
			];
			$message = use_template("tmp/page.template", $params, "account_activation_email");
			$subject = $CFG->sitename . ' Account Activation';

			send_email($user, $FROMUSER, $subject, $message);
			send_email($FROMUSER, $FROMUSER, $subject, $message);
		}

		$_SESSION['userid'] = $user['userid'];
		$params = ["userid" => $user['userid'], "time" => $time, "ip" => $_SERVER['REMOTE_ADDR'], "isfirst" => (!$user["first_activity"]), "clear_alt" => true];
		$SQL = use_template("dbsql/db.sql", $params, "update_last_activity");
		execute_db_sql($SQL);

		log_entry("user", $user['userid'], "Login");
		return $user;
	}
}

function key_login($key) {
global $CFG, $USER;
	$SQL = use_template("dbsql/db.sql", ["key" => $key], "authenticate_key");
	if ($user = get_db_row($SQL)) {
		$USER->userid = $user['userid'];
		$_SESSION['userid'] = $user['userid'];
		log_entry("user", $user['userid'], "Login");
	}
}

function copy_db_row($row, $table, $variablechanges) {
global $USER, $CFG, $MYVARS;
	$paired = explode(",", $variablechanges);
	$newkey = $newvalue = [];
	$keylist = $valuelist = "";
	$i = 0;
	while (isset($paired[$i])) {
		$split = explode("=", $paired[$i]);
		$newkey[$i] = $split[0];
		$newvalue[$i] = $split[1];
		$i++;
	}

	$keys = array_keys($row);
	foreach ($keys as $key) {
		$found = array_search($key, $newkey);
		$keylist .= $keylist == "" ? $key : "," . $key;
		if ($found === false) {
			$valuelist .= $valuelist == "" ? "'" . $row[$key] . "'" : ",'" . $row[$key] . "'";
		} else {
			$valuelist .= $valuelist == "" ? "'" . $newvalue[$found] . "'" : ",'" . $newvalue[$found] . "'";
		}
	}
	$SQL = "INSERT INTO $table ($keylist) VALUES($valuelist)";
	return execute_db_sql($SQL);
}

function is_unique($table, $where) {
	if (get_db_count("SELECT * FROM $table WHERE $where")) { return true; }
	return false;
}

function even($var) {
	return (!($var & 1));
}

function senderror($message) {
	$message = preg_replace(["\r,\t,\n"], "", $message);
	trigger_error($message, E_USER_ERROR);
	die($message);
}

function log_entry($feature = null, $info = null, $desc = null, $debug = null) {
global $CFG, $USER, $PAGE;
	$userid = is_logged_in() ? $USER->userid : 0;
	$pageid = $PAGE->id ?? $CFG->SITEID;
	$pageid = $pageid == $CFG->SITEID && isset($_GET['pageid']) ? $_GET['pageid'] : $pageid;

	if (!is_numeric($pageid)) { // Somebody could be playing with this variable.
		return;
	}
	if (!$userid && $desc == "Login") {
		$userid = $info;
		$info = null;
	}

	$params = [
		"userid" => $userid,
		"ip" => $_SERVER['REMOTE_ADDR'],
		"pageid" => $pageid,
		"time" => get_timestamp(),
		"feature" => dbescape($feature),
		"info" => dbescape($info),
		"desc" => dbescape($desc),
		"debug" => dbescape($debug),
	];
	$SQL = use_template("dbsql/db.sql", $params, "logsql");
	execute_db_sql($SQL);
}

?>
