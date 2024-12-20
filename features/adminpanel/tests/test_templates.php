<?php
/***************************************************************************
 * temp.php: Template unit tests.
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/

// Initialize counters for tracking test results
$passCounter = 0;
$totalCounter = 0;
$tests = "";

// Test 1: Simple fill-in-the-blank with a single variable
// This test checks if the template correctly substitutes the placeholder with the value of the "variable" key.
$result = fill_template("tmp/test.template", "fill_in_the_blank", false, ["variable" => "PASS"]);
$tests .= testCheck("simple fill in blank", trim($result) == "This was a PASS." ? "PASS" : "FAIL", $passCounter, $totalCounter);

// Test 2: Fill-in with an array variable
// This test checks if the template can handle array values and format them correctly.
$result = fill_template("tmp/test.template", "a+b=c", false, ["variable" => [2, 3, 5]]);
$tests .= testCheck("fill in with array variable", trim($result) == "2 + 3 = 5" ? "PASS" : "FAIL", $passCounter, $totalCounter);

// Test 3: Embedded code with no variable
// This test verifies if the template can execute embedded PHP functions like print_r without any variables.
$result = fill_template("tmp/test.template", "print_r123");
$tests .= testCheck("embedded code with no variable", trim($result) == trim(print_r([1, 2, 3], true)) ? "PASS" : "FAIL" . trim($result), $passCounter, $totalCounter);

// Test 4: Use of print_r function with an array variable
// This test checks if the template can handle an array and pass it to a PHP function like print_r for formatting.
$result = fill_template("tmp/test.template", "print_r", false, ["variable" => [5, 4, 3, 2, 1]]);
$tests .= testCheck("print_r function with array variable", trim($result) == trim(print_r([5, 4, 3, 2, 1], true)) ? "PASS" : "FAIL", $passCounter, $totalCounter);

// Test 5: Using get_max function with an array variable
// This test checks if the template can handle a function (get_max) to return the maximum value from an array.
$result = fill_template("tmp/test.template", "get_max", false, ["variables" => [1, 2, 3, 4, 5]]);
$tests .= testCheck("get_max function with array variable", trim($result) == trim("5") ? "PASS" : "FAIL", $passCounter, $totalCounter);

// Test 6: Fill-in with an object variable
// This test verifies that the template can handle objects by accessing their properties during the substitution process.
$result = fill_template("tmp/test.template", "list", false, ["variables" => (object) ['one' => '1', 'two' => '2', 'three' => '3']]);
$tests .= testCheck("fill in with object variable", trim($result) == trim("1, 2, 3") ? "PASS" : "FAIL", $passCounter, $totalCounter);

// Test 7: Using min function with an object variable
// This test checks if the template can use the min function with an object property to return the minimum value.
$result = fill_template("tmp/test.template", "min", false, ["variables" => (object) ['one' => '1', 'two' => '2', 'three' => '3']]);
$tests .= testCheck("min function with object variable", trim($result) == trim("1") ? "PASS" : "FAIL", $passCounter, $totalCounter);

// Test 8: Fill-in with an optional variable
// This test ensures that optional variables can be handled by the template system without breaking the logic.
$result = fill_template("tmp/test.template", "optional", false, ["one" => "1", "two" => "2", "four" => "4"]);
$tests .= testCheck("fill in with optional variable", trim($result) == trim("124") ? "PASS" : "FAIL", $passCounter, $totalCounter);

// Output the results of the tests in a formatted HTML list
echo '
    <li>
        <h2 class="title">Template Tests ' . $passCounter . '/' . $totalCounter . '</h2>
        <div>
			' . $tests . '
		</div>
	</li>';
