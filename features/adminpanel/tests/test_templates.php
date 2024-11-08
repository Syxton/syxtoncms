<?php
/***************************************************************************
 * temp.php: Template unit tests.
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/

$passCounter = 0;
$totalCounter = 0;
$tests = "";

$result = fill_template("tmp/test.template", "fill_in_the_blank", false, ["variable" => "PASS"]);
$tests .= testCheck("simple fill in blank", trim($result) == "This was a PASS." ? "PASS" : "FAIL", $passCounter, $totalCounter);

$result = fill_template("tmp/test.template", "a+b=c", false, ["variable" => [2, 3, 5]]);
$tests .= testCheck("fill in with array variable", trim($result) == "2 + 3 = 5" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$result = fill_template("tmp/test.template", "print_r123");
$tests .= testCheck("embedded code with no variable ", trim($result) == trim(print_r([1, 2, 3], true)) ? "PASS" : "FAIL" . trim($result), $passCounter, $totalCounter);

$result = fill_template("tmp/test.template", "print_r", false, ["variable" => [5, 4, 3, 2, 1]]);
$tests .= testCheck("print_r function with array variable", trim($result) == trim(print_r([5, 4, 3, 2, 1], true)) ? "PASS" : "FAIL", $passCounter, $totalCounter);

$result = fill_template("tmp/test.template", "get_max", false, ["variables" => [1, 2, 3, 4, 5]]);
$tests .= testCheck("get_max function with array variable", trim($result) == trim("5") ? "PASS" : "FAIL", $passCounter, $totalCounter);

$result = fill_template("tmp/test.template", "list", false, ["variables" => (object) ['one' => '1', 'two' => '2', 'three' => '3']]);
$tests .= testCheck("fill in with object variable", trim($result) == trim("1, 2, 3") ? "PASS" : "FAIL", $passCounter, $totalCounter);

$result = fill_template("tmp/test.template", "min", false, ["variables" => (object) ['one' => '1', 'two' => '2', 'three' => '3']]);
$tests .= testCheck("min function with object variable", trim($result) == trim("1") ? "PASS" : "FAIL", $passCounter, $totalCounter);

$result = fill_template("tmp/test.template", "optional", false, ["one" => "1", "two" => "2", "four" => "4"]);
$tests .= testCheck("fill in with optional variable", trim($result) == trim("124") ? "PASS" : "FAIL", $passCounter, $totalCounter);

echo '
    <li>
        <h2 class="title">Template Tests ' . $passCounter . '/' . $totalCounter . '</h2>
        <div>
			' . $tests . '
		</div>
	</li>';