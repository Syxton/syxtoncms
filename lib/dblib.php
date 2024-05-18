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

function clean_myvar_req($key, $type) {
global $MYVARS;
    if (isset($MYVARS->GET[$key])) {
        return clean_var_req($MYVARS->GET[$key], $type, $key);
    }
    trigger_error("Missing required variable: $key", E_USER_ERROR);
    return NULL;
}

function clean_myvar_opt($key, $type, $default) {
global $MYVARS;
    if (isset($MYVARS->GET[$key])) {
        return clean_var_opt($MYVARS->GET[$key], $type, $default);
    }
    return clean_var_opt($default, $type, $default);
}

function clean_var_req($var, $type, $name = "") {
    $var = clean_var_opt($var, $type, NULL);
    if ($var === NULL) {
        trigger_error("Missing required variable: $name", E_USER_ERROR);
        throw new Exception("Missing required variable: $name");
    }
    return $var;
}

function clean_var_opt($var, $type, $default) {
global $CFG;
    if (empty($var)) { return $default; }

    switch ($type) {
        case "int":
            $var = empty($var) ? $default : (int)$var;
            break;
        case "float":
            $var = empty($var) ? $default : (float)$var;
            break;
        case "string":
            $var = empty($var) ? $default : urldecode((string)$var);
            break;
		case "array":
			$var = empty($var) ? $default : (array)$var;
			break;
        case "html":
            //MS Word Cleaner HTMLawed
	        //http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/more.htm
	        include_once ($CFG->dirroot . '/scripts/wordcleaner.php');
            $params = [
                'comment' => 1,
                'clean_ms_char' => 1,
                'css_expression' => 1,
                'keep_bad' => 0,
                'make_tag_strict' => 1,
                'schemes' => '*:*',
                'valid_xhtml' => 1,
                'balance' => 1,
            ];
            $var = empty($var) ? $default : urldecode(htmLawed((string)$var, $params));
            break;
        case "bool":
            $var = trim((string)strtolower($var)) === "false" ? false : $var;
            $var = trim((string)$var) === "0" ? false : $var;
            $var = (bool)$var;
            break;
        default:
            return $default;
            break;
    }

    return $var;
}

function execute_db_sqls($SQLS, $vars = []) {
global $conn;
    if (is_array($SQLS)) {
        $sql_array = $SQLS;
    } else { // BAD METHOD....
        $SQLS = trim($SQLS, ";\r\n");
        $sql_array = explode(";\r\n", $SQLS);
        $sql_array = array_filter($sql_array); // Remove any empty array elements.
    }

	$result = [];
    $i = 0;
    foreach ($sql_array as $SQL) {
        $v = $vars;
        if (!empty($vars)) {
            // If an array of arrays is passed, use each array as a variable set for each SQL independantly.
            // Otherwise, use all the variables passed for each SQL.
            $v = ismultiarray($vars) ? array_slice($vars, $i, 1)[0] : $vars;
            $v = insert_result_data_as_var($v, $result);
        } else {
            $SQL = insert_result_data_as_template($SQL, $result);
        }
        // Execute SQL
        $result[] = execute_db_sql($SQL, $v);
        $i++;
    }
	return $result;
}

function insert_result_data_as_var($vars, $result) {
    $pattern = '/\|\|result\[([0-9]+)\]\|\|/i'; //Look for stuff between ||xxx||
    foreach ($vars as $key => $value) {
        preg_match_all($pattern, $value, $matches);
        foreach ($matches[1] as $match) {
            if (is_numeric($match)) {
                if (isset($result[$match])) {
                    $vars[$key] = $result[$match];
                }
            }
        }
    }
    return $vars;
}

function insert_result_data_as_template($SQL, $result) {
    $pattern = '/(\|\|result\[[0-9]+\]\|\|)/i'; //Look for stuff between ||xxx||
    preg_match_all($pattern, $SQL, $matches);
    foreach ($matches[1] as $match) {
        $pattern = '/\|\|result\[([0-9]+)\]\|\|/i'; //Look for stuff between ||xxx||
        preg_match_all($pattern, $match, $keys);
        foreach ($keys[1] as $key) {
            if (is_numeric($key)) {
                if (isset($result[$key])) {
                    $SQL = str_replace($match, "'" . $result[$key] . "'", $SQL);
                }
            }
        }
    }
    return $SQL;
}

function ismultiarray($a) {
    if (is_array($a) && !empty($a)) {
        foreach ($a as $v) { 
            if (!is_array($v)) {
                return false;
            }
        }
        return true;
    }
    return false;
}

function build_prepared_variables($SQL, $vars, $pattern) {
    $typestring = "";
    $data = [];
	preg_match_all($pattern, $SQL, $matches);
	foreach ($matches[0] as $match) {
        $match = trim($match, "|\"'"); // cuts off template tags leaving only xxx
        if (isset($vars[$match])) {
            $data[] = $vars[$match];
            $typestring .= find_var_type($vars[$match]);
        } else {
            throw new \Exception("No value found for variable: " . $match);
        }
	}
    return ["data" => $data, "typestring" => $typestring];
}

function find_var_type($var) {
    switch(gettype($var)) {
        case "string":
            return "s";
            break;
        case "integer":
            return "i";
            break;
        case "double":
            return "d";
            break;
        default:
            return "b";
            break;
    }
}

function get_db_field($field, $from, $where, $vars = []) {
	$SQL = "SELECT $field FROM $from WHERE $where";
    $SQL = place_sql_limit($SQL, 1);
	if ($result = get_db_result($SQL, $vars)) {
        if ($result->num_rows > 1) {
            trigger_error("get_db_field: $SQL returned $result->num_rows results. Expected 1", E_USER_NOTICE);
        }
		$row = fetch_row($result);
		return $row[$field];
	}
	return false;
}

function get_db_row($SQL, $vars = [], $type = false) {
    $SQL = place_sql_limit($SQL, 1);
    if ($result = get_db_result($SQL, $vars)) {
        if (is_select($SQL) && $result->num_rows > 1) {
            trigger_error("get_db_row: $SQL returned $result->num_rows results. Expected 1", E_USER_NOTICE);
        }
        return fetch_row($result, get_mysql_array_type($type));
    }
    return false;
}

function place_sql_limit($SQL, $limit) {
    if (!is_select($SQL)) { return $SQL; }
    $p = strrpos(strtoupper($SQL), "LIMIT"); // last "LIMIT" found in SQL.
    if ($p !== false) { // "LIMIT" was found in SQL;
        $q = strpos($SQL, ")", $p); // ")" found after "LIMIT" denoting a subquery clause.
        $r = strpos($SQL, "'", $p); // "'" found after "LIMIT" denoting a string and not clause.
        $s = strpos($SQL, "\"", $p); // '"' found after "LIMIT" denoting a string and not clause.
        if ($q === false && $r === false && $s === false) {
            $SQL = substr($SQL, 0, $p); // Remove LIMIT clause from SQL.
        }
    }

    return $SQL . " LIMIT $limit";
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

function copy_db_row($row, $table, $copychanges) {
    // Check if first variable is an array (denotes multiple copies).
    // If not, make it an array of a single array for consistency.
    if (!ismultiarray($copychanges)) {
        $copychanges = [$copychanges];
    }

    $data = [];
    // Loop through each key in the table row.
    foreach ($row as $key => $value) {
        $i = 0;
        foreach ($copychanges as $varchanges) { // Loop through each set of copy changes.
            if (array_key_exists($key, $varchanges)) { // If the key exists in the set of changes.
                if ($varchanges[$key] !== NULL) { // NULL values are skipped.
                    $data[$i][$key] = $varchanges[$key];
                }
            } else {
                $data[$i][$key] = $value;
            }
            $i++;
        }
    }

    $SQLS = [];
    foreach ($data as $d) {
        $keylist = $values = "";
        foreach ($d as $k => $v) {
            $keylist .= $keylist == "" ? $k : ", $k";
            $values .= $values == "" ? "||$k||" : ", ||$k||";
        }
        $SQLS[] = "INSERT INTO $table ($keylist) VALUES($values)";
    }

    return execute_db_sqls($SQLS, $data);
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
