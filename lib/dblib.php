<?php
/***************************************************************************
* dblib.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/28/2021
* Revision: 1.7.7
***************************************************************************/

if (!isset($LIBHEADER)) { include ('header.php'); }
$DBLIB = true;


function reconnect() {
global $CFG;
  if ($CFG->dbtype == "mysqli" && function_exists('mysqli_connect')) {
    //mysqli is installed
    $CFG->dbtype = "mysqli";
    $conn = mysqli_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
    mysqli_select_db($conn, $CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . mysqli_errno($conn) . ") " . mysqli_error($conn));
  } else {
    $CFG->dbtype = "mysql";
    $conn = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
    mysql_select_db($CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . mysql_errno() . ") " . mysql_error());
  }
return $conn;
}

$conn = reconnect();

if ($CFG->dbtype == "mysqli") {
  require('dblib_mysqli.php');
} else {
  require('dblib_mysql.php');
}

function get_db_row($SQL, $type = false) {
global $CFG;
  $type = get_mysql_array_type($type);
	if ($result = get_db_result($SQL)) {
		return fetch_row($result, $type);
	}
  return false;
}

function get_db_field($field, $from, $where) {
global $CFG;
	$SQL = "SELECT $field FROM $from WHERE $where LIMIT 1";

	if ($result = get_db_result($SQL)) {
		$row = fetch_row($result);
		return $row[$field];
	}
	return false;
}

function execute_db_sqls($SQLS) {
global $conn;
  $sql_array = explode(";\r", $SQLS);
  $returns = array();
  foreach ($sql_array as $SQL) {
    $returns[] = execute_db_sql($SQL);
  }
  return $returns;
}

function authenticate($username, $password) {
global $CFG, $USER;
	$time = get_timestamp();
  $params = array("username" => $username, "password" => $password);
  $SQL = template_use("dbsql/db.sql", $params, "authenticate"); // Authenticate
	if (!$user = get_db_row($SQL)) { // COULD NOT AUTHENTICATE
    $SQL = template_use("dbsql/db.sql", $params, "authenticate_alt");
		if ($user = get_db_row($SQL)) { // Attempt authentication on alternate password field
      $_SESSION['userid'] = $user['userid'];
      $params = array("userid" => $user['userid'], "time" => $time, "ip" => $_SERVER['REMOTE_ADDR'], "isfirst" => false, "clear_alt" => false);
      $SQL = template_use("dbsql/db.sql", $params, "update_last_activity");
			execute_db_sql($SQL);
			return $user; // Password reset authentication successful
		}

		log_entry("user", $username, "Failed Login"); // Log
		return false; // Password authentication failed
	} else { // Regular authentication successful.
		if (strlen($user['temp']) > 0) { // on first ever login, switch temp password for actual password
      $SQL = template_use("dbsql/db.sql", array("user" => $user), "activate_account");
			execute_db_sql($SQL);

      // Send account activated email.
      $FROMUSER = new \stdClass;
  		$FROMUSER->fname = $CFG->sitename;
  		$FROMUSER->lname = '';
  		$FROMUSER->email = $CFG->siteemail;
      $params = array("user" => $user, "sitename" => $CFG->sitename, "siteowner" => $CFG->siteowner, "siteemail" => $CFG->siteemail);
      $message = template_use("tmp/page.template", $params, "account_activation_email");
      $subject = $CFG->sitename . ' Account Activation';

      send_email($user, $FROMUSER, false, $subject, $message);
      send_email($FROMUSER, $FROMUSER, false, $subject, $message);
		}

    $_SESSION['userid'] = $user['userid'];
    $params = array("userid" => $user['userid'], "time" => $time, "ip" => $_SERVER['REMOTE_ADDR'], "isfirst" => (!$user["first_activity"]), "clear_alt" => true);
    $SQL = template_use("dbsql/db.sql", $params, "update_last_activity");
    execute_db_sql($SQL);

		log_entry("user", $user['userid'], "Login");
		return $user;
	}
}

function key_login($key) {
global $CFG, $USER;
  $SQL = template_use("dbsql/db.sql", array("key" => $key), "authenticate_key");
	if ($user = get_db_row($SQL)) {
		$USER->userid = $user['userid'];
    $_SESSION['userid'] = $user['userid'];
		log_entry("user", $user['userid'], "Login");
	}
}

function copy_db_row($row, $table, $variablechanges) {
global $USER, $CFG, $MYVARS;
	$paired = explode(",", $variablechanges);
	$newkey = $newvalue = array();
	$keylist = $valuelist = "";
  $i=0;
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
  $message = preg_replace(array("\r,\t,\n"), "", $message);
  error_log($message);
  die($message);
}

function log_entry($feature = null, $info = null, $desc = null, $debug = null) {
global $CFG, $USER, $PAGE;
	$userid = is_logged_in() ? $USER->userid : 0;
	$pageid = isset($PAGE->id) ? $PAGE->id : $CFG->SITEID;
	$pageid = $pageid == $CFG->SITEID && isset($_GET['pageid']) ? $_GET['pageid'] : $pageid;
  if (!is_numeric($pageid)) { // Somebody could be playing with this variable.
    return;
  }
	if (!$userid && $desc == "Login") {
		$userid = $info;
		$info = null;
	}

  $params = array("userid" => $userid, "ip" => $_SERVER['REMOTE_ADDR'], "pageid" => $pageid, "time" => get_timestamp(),
                  "feature" => dbescape($feature), "info" => dbescape($info), "desc" => dbescape($desc), "debug" => dbescape($debug));
  $SQL = template_use("dbsql/db.sql", $params, "logsql");
	execute_db_sql($SQL);
}

?>
