<?php
/***************************************************************************
 * temp.php: Database unit tests.
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/

// Initialize counters and results container
$passCounter = 0; // Number of passed tests
$totalCounter = 0; // Total number of tests
$tests = ""; // Store the test results

// Start fresh by dropping the test table if it already exists
execute_db_sql("DROP TABLE IF EXISTS `testing_table`");

// SQL query to create the test table with columns: test_id, test_field, and test_string
$SQL = 'CREATE TABLE IF NOT EXISTS `testing_table` (
		`test_id` int(11) NOT NULL AUTO_INCREMENT,
		`test_field` int(11) NOT NULL,
		`test_string` VARCHAR(250) NOT NULL DEFAULT "",
		PRIMARY KEY (`test_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1';

// Check if the table creation was successful
if (execute_db_sql($SQL)) {
	// Record the result of the table creation test
	$tests .= testCheck("create test table", "PASS" ? "PASS" : "FAIL", $passCounter, $totalCounter);

	// Test: Insert multiple records using unprepared SQL
	$result = execute_db_sql("INSERT INTO testing_table (test_field) VALUES (100),(200),(300)");
	$tests .= testCheck("insert of unprepared sql", $result === 1 ? "PASS" : "FAIL: Expected 1, Received $result", $passCounter, $totalCounter);

	// Test: Insert records using prepared statements
	$result = execute_db_sql("INSERT INTO testing_table (test_field) VALUES (||v1||),(||v2||),(||v3||)", ["v1" => 400, "v2" => 500, "v3" => 600]);
	$tests .= testCheck("insert of prepared statement", $result === 4 ? "PASS" : "FAIL: Expected 4, Received $result", $passCounter, $totalCounter);

	// Test: Select records with unprepared SQL and check the sum of 'test_field' values
	if ($results = execute_db_sql("SELECT * FROM testing_table WHERE test_id > 4 OR test_id = 4")) {
		$t = 0;
		while ($row = fetch_row($results)) {
			$t += $row["test_field"];
		}
		$tests .= testCheck("select of unprepared sql", $t === 1500 ? "PASS" : "FAIL: Expected 1500, Received $t", $passCounter, $totalCounter);
	} else {
		$tests .= testCheck("select of unprepared sql", "FAIL: Could not execute query", $passCounter, $totalCounter);
	}

	// Test: Select records with prepared SQL and check the sum of 'test_field' values
	if ($results = execute_db_sql("SELECT * FROM testing_table WHERE test_id > ||id|| OR test_id = ||id||", ["id" => 4])) {
		$t = 0;
		while ($row = fetch_row($results)) {
			$t += $row["test_field"];
		}
		$tests .= testCheck("select of prepared statement", $t === 1500 ? "PASS" : "FAIL: Expected 1500, Received $t", $passCounter, $totalCounter);
	} else {
		$tests .= testCheck("select of prepared statement", "FAIL: Could not execute query", $passCounter, $totalCounter);
	}

	// Test: Fetch a single row with unprepared SQL using get_db_row() and check 'test_field'
	$result = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
	$tests .= testCheck("get_db_row() unprepared sql", $result["test_field"] == 400 ? "PASS" : "FAIL: Expected 400, Received " . $result["test_field"], $passCounter, $totalCounter);

	// Test: Fetch a single row with prepared SQL using get_db_row() and check 'test_field'
	$result = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
	$tests .= testCheck("get_db_row() prepared statement", $result["test_field"] == 400 ? "PASS" : "FAIL: Expected 400, Received " . $result["test_field"], $passCounter, $totalCounter);

	// Test: Update a record using unprepared SQL and check the result
	$result = execute_db_sql("UPDATE testing_table SET test_field = 500 WHERE test_id = 1");
	$tests .= testCheck("update of unprepared sql", $result === 1 ? "PASS" : "FAIL: Expected 1, Received $result", $passCounter, $totalCounter);

	// Test: Update a record using prepared statements and check the result
	$result = execute_db_sql("UPDATE testing_table SET test_field = ||value|| WHERE test_id > ||id||", ["value" => 1000, "id" => 4]);
	$tests .= testCheck("update of prepared statement", $result === 2 ? "PASS" : "FAIL: Expected 2, Received $result", $passCounter, $totalCounter);

	// Test: Select data from a template using unprepared SQL
	$SQL = fill_template("tmp/test.template", "sql_template", false, ["id" => 1]);
	$result = get_db_row($SQL);
	$tests .= testCheck("select from template with unprepared sql", $result["test_field"] == 500 ? "PASS" : "FAIL: Expected 500, Received " . $result["test_field"], $passCounter, $totalCounter);

	// Test: Select data from a template using prepared SQL
	$result = get_db_row(fetch_template("tmp/test.template", "sql_template"), ["id" => 1]);
	$tests .= testCheck("select from template with prepared statement", $result["test_field"] == 500 ? "PASS" : "FAIL: Expected 500, Received " . $result["test_field"], $passCounter, $totalCounter);

	// Test: Copy a row and verify the copied data
	$row = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 1]);
	copy_db_row($row, "testing_table", ["test_id" => NULL, "test_string" => 9999]);
	$copy = get_db_row("SELECT test_field, MAX(test_id) as id FROM testing_table GROUP BY test_id");
	$tests .= testCheck("1x copy_db_row with prepared statement", $copy["test_field"] == $row["test_field"] ? "PASS" : "FAIL: Expected 500, Received " . $copy["test_field"], $passCounter, $totalCounter);

	// Test: Copy multiple rows and verify the copied data
	$row = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 1]);
	copy_db_row($row, "testing_table", [["test_id" => NULL, "test_string" => 9], ["test_id" => NULL, "test_string" => 99], ["test_id" => NULL, "test_string" => 9999]]);
	$copy = get_db_row("SELECT test_field, MAX(test_id) as id FROM testing_table GROUP BY test_id");
	$tests .= testCheck("3x copy_db_row with prepared statement", $copy["test_field"] == $row["test_field"] ? "PASS" : "FAIL: Expected 500, Received " . $copy["test_field"], $passCounter, $totalCounter);

	// Test: Execute multiple SQL statements in a transaction using unprepared SQL
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
	$tests .= testCheck("execute_db_sqls() with unprepared sql", $sum == 6 ? "PASS" : "FAIL: Expected 6, Received $sum", $passCounter, $totalCounter);

	// Test: Execute multiple SQL statements in a transaction using prepared statements
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
	$tests .= testCheck("execute_db_sqls() with prepared statements", $sum == 6 ? "PASS" : "FAIL: Expected 6, Received $sum", $passCounter, $totalCounter);

	// Test: Execute multiple SQL insert/update/delete operations with unprepared SQL
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
	$tests .= testCheck("multiple template sql insert/update of unprepared sql", $sum == 13 ? "PASS" : "FAIL: Expected 13, Received $sum", $passCounter, $totalCounter);

	// Test: Execute multiple SQL insert/update/delete operations with prepared SQL
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
	$tests .= testCheck("multiple template sql insert/update of prepared statements", $sum == 14 ? "PASS" : "FAIL: Expected 14, Received $sum", $passCounter, $totalCounter);

	// Test: Execute multiple SQL updates with unprepared SQL and verify
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
	$tests .= testCheck("multiple template sql updates of unprepared sql", $sum == 3 ? "PASS" : "FAIL: Expected 3, Received $sum", $passCounter, $totalCounter);

	// Test: Execute multiple SQL updates with prepared SQL and verify
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
	$tests .= testCheck("multiple template sql insert of prepared statements", $sum == 3 ? "PASS" : "FAIL: Expected 3, Received $sum", $passCounter, $totalCounter);

	// Test Errors and Rollbacks (with suppressed error output)
	$CFG->debug = 0;

	// Test: Retrieve a row that matches a condition (more than 1 row may match)
	$result = get_db_row("SELECT * FROM testing_table WHERE test_id > ||id||", ["id" => 1]);
	$tests .= testCheck("get_db_row() that returns more than 1 row", $result["test_field"] == 3000 ? "PASS" : "FAIL: Expected 3000, Received " . $result["test_field"], $passCounter, $totalCounter);

	// Test: Retrieve a specific field using get_db_field() and verify
	$result = get_db_field("test_field", "testing_table", "test_id > ||id||", ["id" => 1]);
	$tests .= testCheck("get_db_field() that returns more than 1 row", $result == 3000 ? "PASS" : "FAIL: Expected 3000, Received $result", $passCounter, $totalCounter);

	// Test: Rollback transaction after error in multiple SQL operations (unprepared SQL)
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
	$tests .= testCheck("rollback test of multiple unprepared sql template", $original["test_field"] == $after["test_field"] ? "PASS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"], $passCounter, $totalCounter);

	// Test: Rollback transaction after error in multiple SQL operations (prepared SQL)
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
	$tests .= testCheck("rollback test of multiple prepared statement template", $original["test_field"] == $after["test_field"] ? "PASS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"], $passCounter, $totalCounter);

  // Additional rollback tests with SQL operations
  error_log("\n\nERROR EXPECTED:");
  try {
    start_db_transaction();
    $original = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
    $result = execute_db_sql("UPDATE testing_table SET test_field = 1500 WHERE test_id = 4");
    $progress = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
    execute_db_sql("DELETE FROM wrong_table_name_here WHERE test_id = 4"); // FAILS
    commit_db_transaction();
  } catch (\Throwable $e) {
    rollback_db_transaction($e->getMessage());
    $after = get_db_row("SELECT * FROM testing_table WHERE test_id = 4");
  }
  $tests .= testCheck("rollback test of multiple unprepared sql", $original["test_field"] == $after["test_field"] ? "PASS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"], $passCounter, $totalCounter);

  // Final rollback test of prepared statements
  error_log("\n\nERROR EXPECTED:");
  try {
    start_db_transaction();
    $original = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
    $result = execute_db_sql("UPDATE testing_table SET test_field = 1500 WHERE test_id = ||id||", ["id" => 4]);
    $progress = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
    execute_db_sql("DELETE FROM wrong_table_name_here WHERE test_id = ||id||", ["id" => 4]); // FAILS
    commit_db_transaction();
  } catch (\Throwable $e) {
    rollback_db_transaction($e->getMessage());
    $after = get_db_row("SELECT * FROM testing_table WHERE test_id = ||id||", ["id" => 4]);
  }
  $tests .= testCheck("rollback test of multiple prepared statements", $original["test_field"] == $after["test_field"] ? "PASS" : "FAIL: Expected " . $original["test_field"] . ", Received " . $after["test_field"], $passCounter, $totalCounter);

} else {
  $tests .= "Could not setup test tables.";
}

echo '
    <li>
        <h2 class="title">Database Tests ' . $passCounter . '/' . $totalCounter . '</h2>
        <div>
      ' . $tests . '
    </div>
  </li>';
