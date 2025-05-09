<?php
/***************************************************************************
* langlib.php - String library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/08/2025
* Revision: 0.0.1
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define("LANGLIB", true);

/**
 * Gets a language string.
 *
 * @param string $params The string to look for, with optional feature name and
 * language file name in the format "string:feature:file".
 *
 * @return string The translated string.
 */
function getlang($langString, $path = false, $vars = false) {
    global $CFG;

    if (!$path) {
        $path = "/lib";
    }

    // Create the path to the language file
    $langFilePath = $CFG->dirroot . $path . "/strings.php";

    // Check if the language file exists
    if (file_exists($langFilePath)) {
        // Include the language file
        $strings = include($langFilePath);

        // Return the translated string
        if (isset($strings->$langString)) {
            // Return the translated string
            if ($vars) {
                return fill_string($strings->$langString, $vars);
            }
            return $strings->$langString;
        }
    }

    // If the string is not found, return false
    return "";
}

/**
 * Fills a string with values from an associative array.
 *
 * @param string $string The string to fill in with values from the array.
 * @param array $vars An associative array of values to fill in the string.
 * @return string The filled-in string.
 */
function fill_string($string, $vars) {
    $vars = is_array($vars) ? $vars : [$vars];

    // Loop over the array and replace placeholders in the string with the values.
    foreach ($vars as $key => $value) {
        // Check if $value is array.
        if (is_array($value)) {
            // Convert the array to a string.
            $allvars = "";
            foreach ($value as $k => $v) {
                $allvars .= $v . " ";
            }
            // Replace the placeholder with the string.
            $string = str_replace("{" . $key . "}", $allvars, $string);
        } else {
            // Replace the placeholder with the value.
            $string = str_replace("{" . $key . "}", $value, $string);
        }
    }

    // Return the filled-in string.
    return $string;
}
?>