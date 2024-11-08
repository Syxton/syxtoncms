<?php
/***************************************************************************
* dblib.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.7.7
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
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
		mysqli_select_db($conn, $CFG->dbname) or trigger_error("<strong>A fatal MySQL error occured</strong>.\n<br />\nError: (" . get_db_errorno() . ") " . get_db_error());
	} elseif ($CFG->dbtype == "mysql" && function_exists('mysql_connect')) {
		set_db_report_level();
		$conn = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or trigger_error("Could not connect to database");
		mysql_select_db($CFG->dbname) or trigger_error("<strong>A fatal MySQL error occured</strong>.\n<br />\nError: (" . get_db_errorno() . ") " . get_db_error());
	}
	return $conn;
}

function clean_param_req($params, $key, $type) {
	if (isset($params[$key])) {
		return clean_var_req($params[$key], $type, $key);
	} else {
		trigger_error("Missing required variable: $key", E_USER_ERROR);
		return NULL;
	}
}

function clean_param_opt($params, $key, $type, $default) {
	if (isset($params[$key])) {
		return clean_var_opt($params[$key], $type, $default);
	} else {
		return clean_var_opt($default, $type, $default);
	}
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
    if (is_null($var)) {
        trigger_error("Missing required variable: $name", E_USER_ERROR);
        throw new Exception("Missing required variable: $name");
    }
    return $var;
}

function clean_var_opt($var, $type, $default) {
global $CFG;
	if (is_null($var)) { return $default; }

	switch ($type) {
		case "int":
			if ($var === "0" || $var === 0) { return (int) 0; }
			$var = ltrim($var, "0"); // leading zeros should be removed.
			$var = filter_var($var, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;
			break;
		case "float":
			if ($var !== "" &&(float) $var === 0.0) { return 0.0; }
			$var = ltrim($var, "0"); // leading zeros should be removed.
			$var = filter_var($var, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? $default;
			break;
		case "string":
			$var = !strlen((string)$var) ? $default : urldecode((string)$var);
			break;
		case "array":
			$var = empty($var) ? $default : (array)$var;
			break;
		case "json":
			if (empty($var)) {
				$var = $default;
				break;
			}

			$var = json_decode($var);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$var = $default;
			}
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
			$var = filter_var($var, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
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
            $v = isMultiArray($vars) ? array_slice($vars, $i, 1)[0] : $vars;
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


/**
 * Checks if a given array is a multi-dimensional array.
 *
 * A multi-dimensional array is defined as an array where all elements are also arrays.
 *
 * @param array $a Array to check.
 *
 * @return bool True if the array is multi-dimensional, otherwise false.
 */
function isMultiArray($a) {
    // Check if $a is an array and has elements
    if (is_array($a) && count($a) > 0) {
        // Iterate over each element in the array
        foreach ($a as $value) {
            // If any element is not an array, return false
            if (!is_array($value)) {
                return false;
            }
        }
        return true;  // All elements are arrays
    }
    return false;  // $a is either not an array or is empty
}

function build_prepared_variables($SQL, $vars, $pattern) {
    $typestring = "";
    $data = [];
	preg_match_all($pattern, $SQL, $matches);
	foreach ($matches[0] as $match) {
        $variablename = trim($match, "|\"'"); // cuts off template tags leaving only xxx
        if (isset($vars[$variablename])) {
            $data[] = $vars[$variablename];
            $typestring .= find_var_type($vars[$variablename]);
        } else {
			if (strpos($variablename, "*") === false) { // check if it's an optional variable.
				throw new \Exception("No value found for variable: " . $variablename);
			}
			// Variable is optional and not sent so remove from SQL.
            $SQL = str_replace($match, "", $SQL);
        }
	}
    return ["data" => $data, "typestring" => $typestring, "sql" => $SQL];
}

function find_var_type($var) {
    return match (gettype($var)) {
        'string' => 's',
        'integer' => 'i',
        'double' => 'd',
        default => 'b',  // Fallback for other types (e.g., boolean, array, object, etc.)
    };
}

function get_db_field($field, $from, $where, $vars = []) {
	$SQL = "SELECT $field FROM `$from` WHERE $where";
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
    // Check if the SQL query is a SELECT query
    if (!is_select($SQL)) {
        return $SQL; // Return as is if not a SELECT query
    }

    // Regular expression to find the LIMIT clause and check if it's not inside subqueries or strings
    $pattern = '/\sLIMIT\s+\d+/i'; // Pattern to match "LIMIT" followed by a number

    // If a LIMIT clause is already present, remove it
    if (preg_match($pattern, $SQL)) {
        $SQL = preg_replace($pattern, '', $SQL); // Remove the LIMIT clause if found
    }

    // Append the new LIMIT clause to the SQL query
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
	$ip = get_ip_address();
	$params = ["username" => $username, "password" => $password];
	// Authenticate
	if (!$user = get_db_row(fetch_template("dbsql/db.sql", "authenticate"), $params)) { // COULD NOT Authenticate
		// Check alternate password field.
		if ($user = get_db_row(fetch_template("dbsql/db.sql", "authenticate_alt"), $params)) { // Attempt authentication on alternate password field
			$_SESSION['userid'] = $user['userid'];
			$params = ["userid" => $user['userid'], "time" => $time, "ip" => $ip];

			execute_db_sql(fetch_template("dbsql/db.sql", "update_last_activity", false, ["isfirst" => false, "clear_alt" => false]), $params);
			return $user; // Password reset authentication successful
		}

		log_entry("user", $username, "Failed Login"); // Log
		return false; // Password authentication failed
	} else { // Regular authentication successful.
		if (strlen($user['temp']) > 0) { // on first ever login, switch temp password for actual password
			$SQL = fetch_template("dbsql/db.sql", "activate_account");
			execute_db_sql($SQL, ["user" => $user]);

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
			$message = fill_template("tmp/page.template", "account_activation_email", false, $params);
			$subject = $CFG->sitename . ' Account Activation';

			send_email($user, $FROMUSER, $subject, $message);
			send_email($FROMUSER, $FROMUSER, $subject, $message);
		}

		$_SESSION['userid'] = $user['userid'];
		$params = ["userid" => $user['userid'], "time" => $time, "ip" => $ip];
		$SQL = fetch_template("dbsql/db.sql", "update_last_activity", false, ["isfirst" => (!$user["first_activity"]), "clear_alt" => true]);
		execute_db_sql($SQL, $params);

		log_entry("user", $user['userid'], "Login");
		return $user;
	}
}

function key_login($key) {
global $CFG, $USER;
	if ($user = get_db_row(fetch_template("dbsql/db.sql", "authenticate_key"), ["key" => $key])) {
		$USER->userid = $user['userid'];
		$_SESSION['userid'] = $user['userid'];
		log_entry("user", $user['userid'], "Login");
	}
}

function copy_db_row($row, $table, $copychanges) {
    // Check if first variable is an array (denotes multiple copies).
    // If not, make it an array of a single array for consistency.
    if (!isMultiArray($copychanges)) {
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

function log_entry($feature = '', $info = '', $desc = '', $debug = '') {
global $CFG, $USER, $PAGE;
	$userid = is_logged_in() ? $USER->userid : 0;
	$pageid = $PAGE->id ?? $CFG->SITEID;
	$pageid = $pageid == $CFG->SITEID && isset($_GET['pageid']) ? $_GET['pageid'] : $pageid;

	if (!is_numeric($pageid)) { // Somebody could be playing with this variable.
		return;
	}
	if (!$userid && $desc == "Login") {
		$userid = $info;
	}

	$info ??= '';
	$desc ??= '';
	$debug ??= '';

	$params = [
		"userid" => $userid,
		"ip" => get_ip_address(),
		"pageid" => $pageid,
		"time" => get_timestamp(),
		"feature" => $feature,
		"info" => $info,
		"desc" => $desc,
		"debug" => $debug,
	];

	execute_db_sql(fetch_template("dbsql/db.sql", "logsql"), $params);
}

?>
