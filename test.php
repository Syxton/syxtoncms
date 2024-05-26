<?php
/***************************************************************************
 * temp.php: Templating system tests.
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/

header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

if (!isset($CFG)) {
	include_once('config.php');
}

global $CFG, $USER;

$CFG->debugoverride = true;
$CFG->debug = 3;

if (isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip, ',' . $_SERVER['REMOTE_ADDR'] . ',')) {
	include($CFG->dirroot . $CFG->alternatepage);
} else {
	///////////////////////////////////////////// TESTING SETUP /////////////////////////////////////////////
	include_once($CFG->dirroot . '/lib/header.php');
	echo get_css_set("main");
	echo get_js_set("main");
	echo '<div style="padding: 0px 20px;">';

	if (!is_siteadmin($USER->userid)) {
		//trigger_error(error_string("no_permission", ["administrative"]), E_USER_WARNING);
		//return;
	}

	///////////////////////////////////////////// FUNCTION TESTS /////////////////////////////////////////////
	echo "<h2>Function Tests</h2><br />";
	echo "<h3>Clean INT Test</h3>";
	echo clean_var_opt("1", "int", false) === 1 ? "PASS " : "FAIL ";
	echo clean_var_opt("0", "int", false) === 0 ? "PASS " : "FAIL ";
	echo clean_var_opt(1, "int", false) === 1 ? "PASS " : "FAIL ";
	echo clean_var_opt(0, "int", false) === 0 ? "PASS " : "FAIL ";
	echo clean_var_opt(NULL, "int", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt('', "int", "default") === "default" ? "PASS " : "FAIL ";
	echo "<h3>Clean FLOAT Test</h3>";
	echo clean_var_opt("1", "float", false) === 1.0 ? "PASS " : "FAIL ";
	echo clean_var_opt("0", "float", false) === 0.0 ? "PASS " : "FAIL ";
	echo clean_var_opt(1, "float", false) === 1.0 ? "PASS " : "FAIL ";
	echo clean_var_opt(0, "float", false) === 0.0 ? "PASS " : "FAIL ";
	echo clean_var_opt("1.0", "float", false) === 1.0 ? "PASS " : "FAIL ";
	echo clean_var_opt("0.0", "float", false) === 0.0 ? "PASS " : "FAIL ";
	echo clean_var_opt(1.0, "float", false) === 1.0 ? "PASS " : "FAIL ";
	echo clean_var_opt(0.0, "float", false) === 0.0 ? "PASS " : "FAIL ";
	echo clean_var_opt(NULL, "float", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt('', "float", "default") === "default" ? "PASS " : "FAIL ";
	echo "<h3>Clean STRING Test</h3>";
	echo clean_var_opt("", "string", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt(NULL, "string", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt(false, "string", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt("false", "string", "default") === "false" ? "PASS " : "FAIL ";
	echo clean_var_opt(0, "string", "default") === "0" ? "PASS " : "FAIL ";
	echo "<h3>Clean BOOL Test</h3>";
	echo clean_var_opt(" true ", "bool", false) === true ? "PASS " : "FAIL ";
	echo clean_var_opt(" false ", "bool", true) === false ? "PASS " : "FAIL ";
	echo clean_var_opt(1, "bool", false) === true ? "PASS " : "FAIL ";
	echo clean_var_opt(0, "bool", true) === false ? "PASS " : "FAIL ";
	echo clean_var_opt("on", "bool", false) === true ? "PASS " : "FAIL ";
	echo clean_var_opt("off", "bool", "default") === false ? "PASS " : "FAIL ";
	echo clean_var_opt("1", "bool", "default") === true ? "PASS " : "FAIL ";
	echo clean_var_opt("0", "bool", "default") === false ? "PASS " : "FAIL ";
	echo clean_var_opt("", "bool", "default") === false ? "PASS " : "FAIL ";
	echo clean_var_opt(NULL, "bool", "default") === "default" ? "PASS " : "FAIL ";
	echo "<h3>Clean Array Test</h3>";
	echo clean_var_opt(["this"], "array", "default") === ["this"] ? "PASS " : "FAIL ";
	echo clean_var_opt((object)["this" => "that"], "array", "default") === ["this" => "that"] ? "PASS " : "FAIL ";
	echo clean_var_opt([], "array", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt("", "array", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt(NULL, "array", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt(false, "array", "default") === "default" ? "PASS " : "FAIL ";
	echo clean_var_opt("false", "array", "default") === ["false"] ? "PASS " : "FAIL ";
	echo clean_var_opt(0, "array", "default") === "default" ? "PASS " : "FAIL ";

	///////////////////////////////////////////// TEMPLATE TESTS /////////////////////////////////////////////
	echo "<br /><br />";
	echo "<h2>Template Tests</h2><br />";

	echo "<h3>Simple Variable Test</h3>";
	$result = fill_template("tmp/test.template", "fill_in_the_blank", false, ["variable" => "PASS"]);
	echo trim($result) == "This was a PASS." ? "PASS" : "FAIL";

	echo "<br /><br /><h3>No Variable Code Test</h3>";
	$result = fill_template("tmp/test.template", "print_r123");
	echo trim($result) == trim(print_r([1, 2, 3], true)) ? "PASS" : "FAIL" . trim($result);

	echo "<br /><br /><h3>Array Variable Test</h3>";
	$result = fill_template("tmp/test.template", "a+b=c", false, ["variable" => [2, 3, 5]]);
	echo trim($result) == "2 + 3 = 5" ? "PASS" : "FAIL";

	echo "<br /><br /><h3>Array Variable Code Test 1</h3>";
	$result = fill_template("tmp/test.template", "print_r", false, ["variable" => [5, 4, 3, 2, 1]]);
	echo trim($result) == trim(print_r([5, 4, 3, 2, 1], true)) ? "PASS" : "FAIL";

	echo "<br /><br /><h3>Array Variable Code Test 2</h3>";
	$result = fill_template("tmp/test.template", "get_max", false, ["variables" => [1, 2, 3, 4, 5]]);
	echo trim($result) == trim("5") ? "PASS" : "FAIL";

	echo "<br /><br /><h3>Objecct Variable Test</h3>";
	$result = fill_template("tmp/test.template", "list", false, ["variables" => (object) ['one' => '1', 'two' => '2', 'three' => '3']]);
	echo trim($result) == trim("1, 2, 3") ? "PASS" : "FAIL";

	echo "<br /><br /><h3>Objecct Variable Code Test</h3>";
	$result = fill_template("tmp/test.template", "min", false, ["variables" => (object) ['one' => '1', 'two' => '2', 'three' => '3']]);
	echo trim($result) == trim("1") ? "PASS" : "FAIL";

	echo "<br /><br /><h3>Optional Variable Test</h3>";
	$result = fill_template("tmp/test.template", "optional", false, ["one" => "1", "two" => "2", "four" => "4"]);
	echo trim($result) == trim("124") ? "PASS" : "FAIL";

	echo "<br /><br />";

	///////////////////////////////////////////// DATABASE TESTS /////////////////////////////////////////////
	echo "<br /><br />";
	echo "<h2>Database Tests</h2><br />";

	// Start fresh.
	execute_db_sql("DROP TABLE IF EXISTS `testing_table`");

	$SQL = 'CREATE TABLE IF NOT EXISTS `testing_table` (
			`test_id` int(11) NOT NULL AUTO_INCREMENT,
			`test_field` int(11) NOT NULL,
			`test_string` VARCHAR(250) NOT NULL DEFAULT "",
			PRIMARY KEY (`test_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1';

	echo "<h3>Create Test Table</h3>";
	if (execute_db_sql($SQL)) {
		echo "PASS";

		echo "<br /><br /><h3>Insecure Insert Test Data</h3>";
		$result = execute_db_sql("INSERT INTO testing_table (test_field) VALUES (100),(200),(300)");
		echo $result === 1 ? "PASS" : "FAIL: Expected 1, Received $result";

		echo "<br /><br /><h3>Secure Insert Test Data</h3>";
		$result = execute_db_sql("INSERT INTO testing_table (test_field) VALUES (||v1||),(||v2||),(||v3||)", ["v1" => 400, "v2" => 500, "v3" => 600]);
		echo $result === 4 ? "PASS" : "FAIL: Expected 4, Received $result";

		echo "<br /><br /><h3>Insecure Select Test Data</h3>";
		if ($results = execute_db_sql("SELECT * FROM testing_table WHERE test_id > 4 OR test_id = 4")) {
			$test = 0;
			while ($row = fetch_row($results)) {
				$test += $row["test_field"];
			}
			echo $test === 1500 ? "PASS" : "FAIL: Expected 1500, Received $test";
		} else {
			echo "FAIL";
		}

		echo "<br /><br /><h3>Secure Select Test Data</h3>";
		if ($results = execute_db_sql("SELECT * FROM testing_table WHERE test_id > ||id|| OR test_id = ||id||", ["id" => 4])) {
			$test = 0;
			while ($row = fetch_row($results)) {
				$test += $row["test_field"];
			}
			echo $test === 1500 ? "PASS" : "FAIL: Expected 1500, Received $test";
		} else {
			echo "FAIL";
		}

		echo "<br /><br /><h3>Insecure get_db_row()</h3>";
		$result = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
		echo $result["test_field"] == 400 ? "PASS" : "FAIL: Expected 400, Received " . $result["test_field"];

		echo "<br /><br /><h3>Secure get_db_row()</h3>";
		$result = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
		echo $result["test_field"] == 400 ? "PASS" : "FAIL: Expected 400, Received " . $result["test_field"];

		echo "<br /><br /><h3>Insecure Update</h3>";
		$result = execute_db_sql("UPDATE testing_table SET test_field = 500 WHERE test_id = 1");
		echo $result === 1 ? "PASS" : "FAIL: Expected 1, Received $result";

		echo "<br /><br /><h3>Secure Update</h3>";
		$result = execute_db_sql("UPDATE testing_table SET test_field = ||value|| WHERE test_id > ||id||", ["value" => 1000, "id" => 4]);
		echo $result === 2 ? "PASS" : "FAIL: Expected 2, Received $result";

		echo "<br /><br /><h3>Insecure SELECT From Template</h3>";
		$SQL = fill_template("tmp/test.template", "sql_template", false, ["id" => 1]);
		$result = get_db_row($SQL);
		echo $result["test_field"] == 500 ? "PASS" : "FAIL: Expected 500, Received " . $result["test_field"];

		echo "<br /><br /><h3>Secure SELECT From Template</h3>";
		$result = get_db_row(fetch_template("tmp/test.template", "sql_template"), ["id" => 1]);
		echo $result["test_field"] == 500 ? "PASS" : "FAIL: Expected 500, Received " . $result["test_field"];

		echo "<br /><br /><h3>Secure COPY 1x</h3>";
		$row = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 1]);
		copy_db_row($row, "testing_table", ["test_id" => NULL, "test_string" => 9999]);
		$copy = get_db_row("SELECT *, max(test_id) FROM testing_table");
		echo $copy["test_field"] == $row["test_field"] ? "PASS" : "FAIL: Expected 500, Received " . $copy["test_field"];

		echo "<br /><br /><h3>Secure COPY 3x</h3>";
		$row = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 1]);
		copy_db_row($row, "testing_table", [["test_id" => NULL, "test_string" => 9], ["test_id" => NULL, "test_string" => 99], ["test_id" => NULL, "test_string" => 9999]]);
		$copy = get_db_row("SELECT *, max(test_id) FROM testing_table");
		echo $copy["test_field"] == $row["test_field"] ? "PASS" : "FAIL: Expected 500, Received " . $copy["test_field"];

		echo "<br /><br /><h3>Insecure Multiple SQL Queries</h3>";
		try {
			start_db_transaction();
			$sum = 0;
			$templates = [["file" => "tmp/test.template", "subsection" => ["multiple_select_template1", "multiple_select_template2", "multiple_select_template3"]]];
			$results = execute_db_sqls(fill_template_set($templates, [["id" => 1], ["id" => 2], ["id" => 3]]));
			foreach ($results as $result) { $sum += $result->num_rows; }
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		}
		echo $sum == 6 ? "PASS" : "FAIL: Expected 6, Received $sum";

		echo "<br /><br /><h3>Secure Multiple SQL Queries</h3>";
		try {
			start_db_transaction();
			$sum = 0;
			$templates = [["file" => "tmp/test.template", "subsection" => ["multiple_select_template1", "multiple_select_template2", "multiple_select_template3"]]];
			$results = execute_db_sqls(fetch_template_set($templates), [["id" => 1], ["id" => 2], ["id" => 3]]);
			foreach ($results as $result) { $sum += $result->num_rows; }
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		}
		echo $sum == 6 ? "PASS" : "FAIL: Expected 6, Received $sum";
		
		echo "<br /><br /><h3>Insecure Insert then Update Queries</h3>";
		try {
			start_db_transaction();
			$sum = 0;
			$templates = [
				["file" => "tmp/test.template", "subsection" => "insert_update_delete1"],
				["file" => "tmp/test.template", "subsection" => "insert_update_delete2"],
				["file" => "tmp/test.template", "subsection" => "insert_update_delete3"],
			];
			$results = execute_db_sqls(fill_template_set($templates, [["test_field" => 3000, "test_string" => "'TEST'"], ["test_id" => "||result[0]||", "test_field" => 5000], ["test_id" => "||result[0]||", "test_field" => 3000]]));
			foreach ($results as $result) { $sum += $result; }
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		}
		echo $sum == 13 ? "PASS" : "FAIL: Expected 13, Received $sum";

		echo "<br /><br /><h3>Secure Insert then Update Queries</h3>";
		try {
			start_db_transaction();
			$sum = 0;
			$templates = [
				["file" => "tmp/test.template", "subsection" => "insert_update_delete1"],
				["file" => "tmp/test.template", "subsection" => "insert_update_delete2"],
				["file" => "tmp/test.template", "subsection" => "insert_update_delete3"],
			];
			$results = execute_db_sqls(fetch_template_set($templates), [["test_field" => 3000, "test_string" => "TEST"], ["test_id" => "||result[0]||", "test_field" => 5000], ["test_id" => "||result[0]||", "test_field" => 3000]]);
			foreach ($results as $result) { $sum += $result; }
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		}
		echo $sum == 14 ? "PASS" : "FAIL: Expected 14, Received $sum";

		echo "<br /><br /><h3>Insecure Multiple Update Queries</h3>";
		try {
			start_db_transaction();
			$sum = 0;
			$templates = [["file" => "tmp/test.template", "subsection" => ["working_update_templates1", "working_update_templates2", "working_update_templates3"]]];
			$results = execute_db_sqls(fill_template_set($templates, ["id" => 1, "value" => 2000]));
			foreach ($results as $result) { $sum += $result; }
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		}
		echo $sum == 3 ? "PASS" : "FAIL: Expected 3, Received $sum";

		echo "<br /><br /><h3>Secure Multiple Insert Queries</h3>";
		try {
			start_db_transaction();
			$sum = 0;
			$templates = [["file" => "tmp/test.template", "subsection" => ["working_update_templates1", "working_update_templates2", "working_update_templates3"]]];
			$results = execute_db_sqls(fetch_template_set($templates), ["id" => 1, "value" => 3000]);
			foreach ($results as $result) { $sum += $result; }
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
		} 
		echo $sum == 3 ? "PASS" : "FAIL: Expected 3, Received $sum";

		$CFG->debug = 0; // Testing Errors and Rollbacks which require errors, so we supress error output.
		echo "<br /><br /><h3>get_db_row() that returns more than 1 row</h3>";
		$result = get_db_row("SELECT * FROM testing_table WHERE test_id > ||id||", ["id" => 1]);
		echo $result["test_field"] == 3000 ? "PASS" : "FAIL: Expected 3000, Received " . $result["test_field"];

		echo "<br /><br /><h3>get_db_field() that returns more than 1 row</h3>";
		$result = get_db_field("test_field", "testing_table", "test_id > ||id||", ["id" => 1]);
		echo $result == 3000 ? "PASS" : "FAIL: Expected 3000, Received $result";

		echo "<br /><br /><h3>Insecure Multiple SQL Single Call Rollback Test</h3>";
		error_log("\n\nERROR EXPECTED:");
		try {
			start_db_transaction();
			$templates = [["file" => "tmp/test.template", "subsection" => ["broken_update_templates1", "broken_update_templates2", "broken_update_templates3"]]];
			$original = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
			$results = execute_db_sqls(fill_template_set($templates, [["id" => 1, "value" => 150], ["id" => 2, "value" => 250], ["id" => 3, "value" => 350]]));
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
			$after = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
		}
		echo $original["test_field"] == $after["test_field"] ? "PASS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"];

		echo "<br /><br /><h3>Secure Multiple SQL Single Call Rollback Test</h3>";
		error_log("\n\nERROR EXPECTED:");
		try {
			start_db_transaction();
			$templates = [["file" => "tmp/test.template", "subsection" => ["broken_update_templates1", "broken_update_templates2", "broken_update_templates3"]]];
			$original = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
			$results = execute_db_sqls(fetch_template_set($templates), [["id" => 1, "value" => 150], ["id" => 2, "value" => 250], ["id" => 3, "value" => 350]]);
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
			$after = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
		}
		echo $original["test_field"] == $after["test_field"] ? "PASS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"];

		echo "<br /><br /><h3>Insecure Multiple SQL Queries Rollback Test</h3>";
		error_log("\n\nERROR EXPECTED:");
		try {
			start_db_transaction();
			$original = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
			$result = execute_db_sql("UPDATE testing_table SET test_field = 1500 WHERE test_id = 4");
			$progress = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
			execute_db_sql("DELETE FROM testing_tables WHERE test_id = 4"); // FAILS
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
			$after = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
		}
		echo $original["test_field"] == $after["test_field"] ? "PASS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"];

		echo "<br /><br /><h3>Secure Multiple SQL Queries Rollback Test</h3>";
		error_log("\n\nERROR EXPECTED:");
		try {
			start_db_transaction();
			$original = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
			$result = execute_db_sql("UPDATE testing_table SET test_field = 1500 WHERE test_id = ||id||", ["id" => 4]);
			$progress = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
			execute_db_sql("DELETE FROM testing_tables WHERE test_id = ||id||", ["id" => 4]); // FAILS
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
			$after = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
		}
		echo $original["test_field"] == $after["test_field"] ? "PASS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"];
	} else {
		echo "FAIL";
	}
	echo "<br /><br />";
}
?>
