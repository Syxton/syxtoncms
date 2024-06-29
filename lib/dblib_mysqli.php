<?php
/***************************************************************************
* dblib_mysqli.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.1.1
***************************************************************************/

function get_mysql_array_type($type = "assoc") {
	switch($type) {
		case "num":
			return MYSQLI_NUM;
			break;
		case "both":
			return MYSQLI_BOTH;
			break;
		default:
			return MYSQLI_ASSOC;
			break;
	}
}

function db_goto_row($result, $rownum = 0) {
	mysqli_data_seek($result, $rownum);
}

function fetch_row($result, $type = false) {
    $type = get_mysql_array_type($type);
	return mysqli_fetch_array($result, $type);
}

function db_prepare_statement($SQL, $vars) {
global $conn;
    $pattern = '/([\'\"]?)(\|\|)((?s).*?)(\|\|)([\'\"]?)/i'; //Look for stuff like ||xxx|| or '||xxx||'
    $variables = build_prepared_variables($SQL, $vars, $pattern);

	$SQL = preg_replace($pattern, '?', $variables["sql"]); // Replace all ||xxx|| and '||xxx||' with ?
	$statement = mysqli_prepare($conn, $SQL);

	if (!empty($variables["typestring"]) && !empty($variables["data"])) {
		mysqli_stmt_bind_param($statement, $variables["typestring"], ...$variables["data"]);
	}

	return $statement;
}

function get_prepared_result($statement, $select = false) {
	try {
		if ($result = mysqli_stmt_execute($statement)) {
			if ($select) {
				$result = mysqli_stmt_get_result($statement);
				if (mysqli_num_rows($result) == 0) { // SELECT STATEMENTS ONLY, RETURN false on EMPTY selects
					return false;
				}
			}
			return $result;
		}
	} catch (\Throwable $e) {
		debugging($e->getMessage());
		throw new Exception(error_string("generic_db_error") . "\n\n" . get_db_error());
	}

	return false;
}

function count_db_result($results) {
	return mysqli_num_rows($results);
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
			$result = mysqli_query($conn, $SQL);
			$result = ($select && mysqli_num_rows($result) == 0) ? false : $result;
		}
		return $result;
	} catch (\Throwable $e) {
		debugging($e->getMessage());
		throw new Exception(error_string("generic_db_error") . "\n\n" . get_db_error());
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
			$result = mysqli_query($conn, $SQL);
			$result = ($select && mysqli_num_rows($result) == 0) ? false : $result;
		}

		if ($result) {
			if ($update) {
				$id = empty($vars) ? mysqli_affected_rows($conn) : mysqli_stmt_affected_rows($statement);
				if (!$id) { return true; }
			} elseif ($delete) {
				$id = empty($vars) ? mysqli_affected_rows($conn) : mysqli_stmt_affected_rows($statement);
				if (!$id) { return true; }
			} elseif ($insert) {
				$id = mysqli_insert_id($conn);
				if (!$id) { return true; }
			} elseif ($select) {
				$id = $result;
			} else {
				$id = true;
			}
			return $id;
		}
	} catch (\Throwable $e) {
		debugging($e->getMessage());
		throw new Exception(error_string("generic_db_error") . "\n\n" . get_db_error());
	}

	return false;
}

function start_db_transaction() {
global $conn;
	mysqli_begin_transaction($conn);
}

function commit_db_transaction() {
global $conn;
	mysqli_commit($conn);
}

function rollback_db_transaction($message = false) {
global $conn;
    if (!empty($message)) { error_log("ROLLBACK " . $message); }
	mysqli_rollback($conn);
}

function set_db_report_level($level = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT) {
	mysqli_report($level);
}

function get_db_error() {
global $conn;
	return mysqli_error($conn);
}

function get_db_errorno() {
global $conn;
	return mysqli_errno($conn);
}

function dbescape($val) {
	global $conn;
	if (!is_string($val)) {
		return $val;
	}
	return mysqli_real_escape_string($conn, $val);
}

function db_free_result($result) {
	mysqli_free_result($result);
}
?>
