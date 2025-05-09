<?php
/***************************************************************************
* dblib_mysql.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.1.1
***************************************************************************/

function get_mysql_array_type($type = "assoc") {
	switch($type) {
		case "num":
			return MYSQL_NUM;
			break;
		case "both":
			return MYSQL_BOTH;
			break;
		default:
			return MYSQL_ASSOC;
			break;
	}
}

function db_goto_row($result, $rownum = 0) {
	mysql_data_seek($result, $rownum);
}

function fetch_row($result, $type = false) {
	$type = get_mysql_array_type($type);
	return mysql_fetch_array($result, $type);
}

function db_prepare_statement($SQL, $vars) {
global $conn;
    $pattern = '/([\'\"]?)(\|\|)((?s).*?)(\|\|)([\'\"]?)/i'; //Look for stuff like ||xxx|| or '||xxx||'
    $variables = build_prepared_variables($SQL, $vars, $pattern);

	$SQL = preg_replace($pattern, '?', $variables["sql"]); // Replace all ||xxx|| and '||xxx||' with ?
	$statement = mysql_prepare($conn, $SQL);

	if (!empty($variables["typestring"]) && !empty($variables["data"])) {
		mysql_stmt_bind_param($statement, $variables["typestring"], ...$variables["data"]);
	}

	return $statement;
}

function get_prepared_result($statement, $select = false) {
	try {
		if ($result = mysql_stmt_execute($statement)) {
			if ($select) {
				$result = mysql_stmt_get_result($statement);
				if (mysql_num_rows($result) == 0) { // SELECT STATEMENTS ONLY, RETURN false on EMPTY selects
					return false;
				}
			}
			return $result;
		}
	} catch (\Throwable $e) {
		throw $e;
	}

	return false;
}

function count_db_result($results) {
	return mysql_num_rows($results);
}

function get_db_result($SQL, $vars = []) {
global $conn;
	if (!$conn) { $conn = reconnect(); }

	$statement = false;

	try {
		$select = is_select($SQL);
		if (!empty($vars)) {
			$statement = db_prepare_statement($SQL, $vars);
			$result = get_prepared_result($statement, $select);
		} else {
			$result = mysql_query($conn, $SQL);
			$result = ($select && mysql_num_rows($result) == 0) ? false : $result;
		}
		return $result;
	} catch (\Throwable $e) {
		trigger_error(getlang("generic_error") . "\n\n" . get_db_error(), E_USER_ERROR);
		throw $e;
	}
	return false;
}

function execute_db_sql($SQL, $vars = []) {
global $conn;
	if (!$conn) { $conn = reconnect(); }

	try {
		$update = preg_match('/^UPDATE/i', $SQL) ? true : false;
		$delete = preg_match('/^DELETE/i', $SQL) ? true : false;
		$insert = preg_match('/^INSERT/i', $SQL) ? true : false;
		$select = is_select($SQL);
		if (!empty($vars)) {
			$statement = db_prepare_statement($SQL, $vars);
			$result = get_prepared_result($statement, $select);
		} else {
			$result = mysql_query($conn, $SQL);
			$result = ($select && mysql_num_rows($result) == 0) ? false : $result;
		}

		if ($result) {
			if ($update) {
				$id = empty($vars) ? mysql_affected_rows($conn) : mysql_stmt_affected_rows($statement);
				if (!$id) { return true; }
			} elseif ($delete) {
				$id = empty($vars) ? mysql_affected_rows($conn) : mysql_stmt_affected_rows($statement);
				if (!$id) { return true; }
			} elseif ($insert) {
				$id = mysql_insert_id($conn);
				if (!$id) { return true; }
			} elseif ($select) {
				$id = $result;
			} else {
				$id = true;
			}
			return $id;
		}
	} catch (\Throwable $e) {
		trigger_error(getlang("generic_error") . "\n\n" . get_db_error(), E_USER_ERROR);
		throw $e;
	}

	return false;
}

function start_db_transaction() {
global $conn;
	mysql_begin_transaction($conn);
}

function commit_db_transaction() {
global $conn;
	mysql_commit($conn);
}

function rollback_db_transaction($message = false) {
global $conn;
    if (!empty($message)) { error_log("ROLLBACK " . $message); }
	mysql_rollback($conn);
}

function set_db_report_level($level = MYSQL_REPORT_ERROR | MYSQL_REPORT_STRICT) {
	mysql_report($level);
}

function get_db_error() {
global $conn;
	return mysql_error($conn);
}

function get_db_errorno() {
global $conn;
	return mysql_errno($conn);
}

function dbescape($val) {
	global $conn;
	if (!is_string($val)) {
		return $val;
	}
	return mysql_real_escape_string($val, $conn);
}

function db_free_result($result) {
  mysql_free_result($result);
}
?>
