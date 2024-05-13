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
$CFG->debug = 2;

if (isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip, ',' . $_SERVER['REMOTE_ADDR'] . ',')) {
	include($CFG->dirroot . $CFG->alternatepage);
} else {
	///////////////////////////////////////////// TESTING SETUP /////////////////////////////////////////////
	include_once($CFG->dirroot . '/lib/header.php');
	echo get_css_set("main");
	echo get_js_set("main");
	echo '<div style="padding: 0px 20px;">';

	if (!is_siteadmin($USER->userid)) {
		trigger_error(error_string("no_permission", ["administrative"]), E_USER_WARNING);
		return;
	}

	///////////////////////////////////////////// TEMPLATE TESTS /////////////////////////////////////////////
	echo "<br /><br />";
	echo "<h2>Template Tests</h2><br />";

	echo "<h3>Simple Variable Test</h3>";
	$result = use_template("tmp/test.template", ["variable" => "SUCCESS"], "fill_in_the_blank");
	echo trim($result) == "This was a SUCCESS." ? "SUCCESS" : "FAIL";

	echo "<br /><br /><h3>No Variable Code Test</h3>";
	$result = use_template("tmp/test.template", [], "print_r123");
	echo trim($result) == trim(print_r([1, 2, 3], true)) ? "SUCCESS" : "FAIL" . trim($result);

	echo "<br /><br /><h3>Array Variable Test</h3>";
	$result = use_template("tmp/test.template", ["variable" => [2, 3, 5]], "a+b=c");
	echo trim($result) == "2 + 3 = 5" ? "SUCCESS" : "FAIL";

	echo "<br /><br /><h3>Array Variable Code Test 1</h3>";
	$result = use_template("tmp/test.template", ["variable" => [5, 4, 3, 2, 1]], "print_r");
	echo trim($result) == trim(print_r([5, 4, 3, 2, 1], true)) ? "SUCCESS" : "FAIL";

	echo "<br /><br /><h3>Array Variable Code Test 2</h3>";
	$result = use_template("tmp/test.template", ["variables" => [1, 2, 3, 4, 5]], "get_max");
	echo trim($result) == trim("5") ? "SUCCESS" : "FAIL";

	echo "<br /><br /><h3>Objecct Variable Test</h3>";
	$result = use_template("tmp/test.template", ["variables" => (object) ['one' => '1', 'two' => '2', 'three' => '3']], "list");
	echo trim($result) == trim("1, 2, 3") ? "SUCCESS" : "FAIL";

	echo "<br /><br /><h3>Objecct Variable Code Test</h3>";
	$result = use_template("tmp/test.template", ["variables" => (object) ['one' => '1', 'two' => '2', 'three' => '3']], "min");
	echo trim($result) == trim("1") ? "SUCCESS" : "FAIL";

	echo "<br /><br /><h3>Optional Variable Test</h3>";
	$result = use_template("tmp/test.template", ["one" => "1", "two" => "2", "four" => "4"], "optional");
	echo trim($result) == trim("124") ? "SUCCESS" : "FAIL";

	echo "<br /><br />";

	///////////////////////////////////////////// DATABASE TESTS /////////////////////////////////////////////
	echo "<br /><br />";
	echo "<h2>Database Tests</h2><br />";

	// Start fresh.
	execute_db_sql("DROP TABLE testing_table");

	$SQL = 'CREATE TABLE IF NOT EXISTS `testing_table` (
			`test_id` int(11) NOT NULL AUTO_INCREMENT,
			`test_field` int(11) NOT NULL,
			PRIMARY KEY (`test_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1';

	echo "<h3>Create Test Table</h3>";
	if (execute_db_sql($SQL)) {
		echo "SUCCESS";

		echo "<br /><br /><h3>Insecure Insert Test Data</h3>";
		$result = execute_db_sql("INSERT INTO testing_table (test_field) VALUES (100),(200),(300)");
		echo $result === 1 ? "SUCCESS" : "FAIL: Expected 1, Received $result";

		echo "<br /><br /><h3>Secure Insert Test Data</h3>";
		$result = execute_db_sql("INSERT INTO testing_table (test_field) VALUES (||v1||),(||v2||),(||v3||)", ["v1" => 400, "v2" => 500, "v3" => 600]);
		echo $result === 4 ? "SUCCESS" : "FAIL: Expected 4, Received $result";

		echo "<br /><br /><h3>Insecure Select Test Data</h3>";
		if ($results = execute_db_sql("SELECT * FROM testing_table WHERE test_id > 4 OR test_id = 4")) {
			$test = 0;
			while ($row = fetch_row($results)) {
				$test += $row["test_field"];
			}
			echo $test === 1500 ? "SUCCESS" : "FAIL: Expected 1500, Received $test";
		} else {
			echo "FAIL";
		}

		echo "<br /><br /><h3>Secure Select Test Data</h3>";
		if ($results = execute_db_sql("SELECT * FROM testing_table WHERE test_id > ||id|| OR test_id = ||id||", ["id" => 4])) {
			$test = 0;
			while ($row = fetch_row($results)) {
				$test += $row["test_field"];
			}
			echo $test === 1500 ? "SUCCESS" : "FAIL: Expected 1500, Received $test";
		} else {
			echo "FAIL";
		}

		echo "<br /><br /><h3>Insecure get_db_row()</h3>";
		$result = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
		echo $result["test_field"] == 400 ? "SUCCESS" : "FAIL: Expected 400, Received " . $result["test_field"];

		echo "<br /><br /><h3>Secure get_db_row()</h3>";
		$result = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
		echo $result["test_field"] == 400 ? "SUCCESS" : "FAIL: Expected 400, Received " . $result["test_field"];

		echo "<br /><br /><h3>Insecure Update</h3>";
		$result = execute_db_sql("UPDATE testing_table SET test_field = 500 WHERE test_id = 1");
		echo $result === 1 ? "SUCCESS" : "FAIL: Expected 1, Received $result";

		echo "<br /><br /><h3>Secure Update</h3>";
		$result = execute_db_sql("UPDATE testing_table SET test_field = ||value|| WHERE test_id > ||id||", ["value" => 1000, "id" => 4]);
		echo $result === 2 ? "SUCCESS" : "FAIL: Expected 2, Received $result";

		echo "<br /><br /><h3>Insecure SELECT From Template</h3>";
		$SQL = use_template("tmp/test.template", ["id" => 1], "sql_template");
		$result = get_db_row($SQL);
		echo $result["test_field"] == 500 ? "SUCCESS" : "FAIL: Expected 500, Received " . $result["test_field"];

		echo "<br /><br /><h3>Secure SELECT From Template</h3>";
		$result = get_db_row(fetch_template("tmp/test.template", "sql_template"), ["id" => 1]);
		echo $result["test_field"] == 500 ? "SUCCESS" : "FAIL: Expected 500, Received " . $result["test_field"];

		echo "<br /><br /><h3>Insecure Multiple SQL Queries</h3>";
		$results = execute_db_sqls(use_template("tmp/test.template", ["id1" => 1, "id2" => 2, "id3" => 3], "multiple_sql_template"));
		$sum = 0;
		foreach ($results as $result) { $sum += $result->num_rows; }
		echo $sum == 6 ? "SUCCESS" : "FAIL: Expected 6, Received $sum";

		echo "<br /><br /><h3>Insecure Multiple SQL Queries</h3>";
		$results = execute_db_sqls(fetch_template("tmp/test.template", "multiple_sql_template"), [["id1" => 1], ["id2" => 2], ["id3" => 3]]);
		$sum = 0;
		foreach ($results as $result) { $sum += $result->num_rows; }
		echo $sum == 6 ? "SUCCESS" : "FAIL: Expected 6, Received $sum";

		$CFG->debug = 1; // Testing Rollbacks which require errors, so we supress error output.
		echo "<br /><br /><h3>Insecure Multiple SQL Single Call Rollback Test</h3>";
		$original = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
		$results = execute_db_sqls(use_template("tmp/test.template", ["id1" => 1, "value1" => 150, "id2" => 2, "value2" => 250, "id3" => 3, "value3" => 350], "fail_sqls_template"));
		$after = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
		echo $original["test_field"] == $after["test_field"] ? "SUCCESS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"];

		echo "<br /><br /><h3>Secure Multiple SQL Single Call Rollback Test</h3>";
		$original = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
		$results = execute_db_sqls(fetch_template("tmp/test.template", "fail_sqls_template"), [["id1" => 1, "value1" => 150], ["id2" => 2, "value2" => 250], ["id3" => 3, "value3" => 350]]);
		$after = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
		echo $original["test_field"] == $after["test_field"] ? "SUCCESS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"];

		echo "<br /><br /><h3>Insecure Multiple SQL Queries Rollback Test</h3>";
		start_db_transaction();
		try {
			$original = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
			$result = execute_db_sql("UPDATE testing_table SET test_field = 1500 WHERE test_id = 4");
			$progress = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
			execute_db_sql("DELETE FROM testing_tables WHERE test_id = 4"); // FAILS
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction();
		}
		$after = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
		echo $original["test_field"] == $after["test_field"] ? "SUCCESS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"];

		echo "<br /><br /><h3>Secure Multiple SQL Queries Rollback Test</h3>";
		start_db_transaction();
		try {
			$original = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
			$result = execute_db_sql("UPDATE testing_table SET test_field = 1500 WHERE test_id = ||id||", ["id" => 4]);
			$progress = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
			execute_db_sql("DELETE FROM testing_tables WHERE test_id = ||id||", ["id" => 4]); // FAILS
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction();
		}
		$after = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
		echo $original["test_field"] == $after["test_field"] ? "SUCCESS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"];
	} else {
		echo "FAIL";
	}
	echo "<br /><br />";
}
?>
