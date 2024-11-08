<?php
/***************************************************************************
 * temp.php: Variable unit tests.
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/

$passCounter = 0;
$totalCounter = 0;
$tests = "";

$tests .= "<h3>Clean INT Test</h3>";
$tests .= testCheck("'1' is int", clean_var_opt(1, "int", false) === 1 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'0' is int", clean_var_opt(0, "int", false) === 0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("NULL is int with default", clean_var_opt(NULL, "int", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("Empty string is int with default", clean_var_opt('', "int", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= "<h3>Clean FLOAT Test</h3>";
$tests .= testCheck("'1' is float", clean_var_opt("1", "float", false) === 1.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'0' is float", clean_var_opt("0", "float", false) === 0.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("1 is float", clean_var_opt(1, "float", false) === 1.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("0 is float", clean_var_opt(0, "float", false) === 0.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'.0' is float", clean_var_opt(".0", "float", false) === 0.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'1.0' is float", clean_var_opt("1.0", "float", false) === 1.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'0.1' is float", clean_var_opt("0.1", "float", false) === 0.1 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'01.1' is float", clean_var_opt("01.1", "float", false) === 1.1 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'.1' is float", clean_var_opt(".1", "float", false) === 0.1 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("1.0 is float", clean_var_opt(1.0, "float", false) === 1.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("0.0 is float", clean_var_opt(0.0, "float", false) === 0.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck(".0 is float", clean_var_opt(.0, "float", false) === 0.0 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("0.1 is float", clean_var_opt(0.1, "float", false) === 0.1 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("01.1 is float", clean_var_opt(01.1, "float", false) === 1.1 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck(".1 is float", clean_var_opt(.1, "float", false) === 0.1 ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("NULL is float with default", clean_var_opt(NULL, "float", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("Empty string is float with default", clean_var_opt('', "float", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= "<h3>Clean STRING Test</h3>";
$tests .= testCheck("Empty string is string with default", clean_var_opt("", "string", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("NULL is string with default", clean_var_opt(NULL, "string", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("false is string with default", clean_var_opt(false, "string", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'false' is string", clean_var_opt("false", "string", "default") === "false" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("0 is string", clean_var_opt(0, "string", "default") === "0" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= "<h3>Clean BOOL Test</h3>";
$tests .= testCheck("' true ' is bool", clean_var_opt(" true ", "bool", false) === true ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("' false ' is bool", clean_var_opt(" false ", "bool", true) === false ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("1 is bool", clean_var_opt(1, "bool", false) === true ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("0 is bool", clean_var_opt(0, "bool", true) === false ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'on' is bool", clean_var_opt("on", "bool", false) === true ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'off' is bool with default", clean_var_opt("off", "bool", "default") === false ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'1' is bool with default", clean_var_opt("1", "bool", "default") === true ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("'0' is bool with default", clean_var_opt("0", "bool", "default") === false ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("Empty string is bool with default", clean_var_opt("", "bool", "default") === false ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("NULL is bool with default", clean_var_opt(NULL, "bool", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= "<h3>Clean Array Test</h3>";
$tests .= testCheck("Array is array with default", clean_var_opt(["this"], "array", "default") === ["this"] ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("Object is array with default", clean_var_opt((object)["this" => "that"], "array", "default") === ["this" => "that"] ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("Empty array is array with default", clean_var_opt([], "array", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("Empty string is array with default", clean_var_opt("", "array", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("NULL is array with default", clean_var_opt(NULL, "array", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("False is array with default", clean_var_opt(false, "array", "default") === "default" ? "PASS" : "FAIL", $passCounter, $totalCounter);

echo '
    <li>
        <h2 class="title">Variable Tests ' . $passCounter . '/' . $totalCounter . '</h2>
        <div>
			' . $tests . '
		</div>
	</li>';