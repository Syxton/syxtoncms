<?php
/***************************************************************************
* timelib.php - Time Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.3.2
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define("TIMELIB", true);

/**
 * Gets the current timestamp in the specified timezone
 *
 * @param string $timezone The timezone to retrieve the timestamp in
 * @return int The current timestamp in the specified timezone
 */
function get_timestamp($timezone = "UTC") {
global $CFG;
	date_default_timezone_set($timezone);
	$time = time();
	date_default_timezone_set($CFG->timezone);
	return $time;
}

/**
 * Returns the offset from UTC in seconds
 *
 * @return int
 */
function get_offset() {
global $CFG;
	// Create two timezone objects, one for UTC and one for local timezone
	$LOCAL = new DateTimeZone($CFG->timezone);
	$timeLOCAL = new DateTime("now", $LOCAL);
	$timeOffset = timezone_offset_get($LOCAL, $timeLOCAL);
	// Convert the result to seconds
	return $timeOffset;
}

/**
 * ago
 *
 * @param int $timestamp The timestamp to get the time ago for
 * @param bool $shorten Whether to shorten the output to the highest unit that is not zero
 * @return string A string describing the time since the given timestamp
 *
 * Returns a string describing the time since the given timestamp.
 * If $shorten is true, the string will be limited to the highest unit that is not zero.
 * Otherwise, the string will be as verbose as possible.
 */
function ago($timestamp, $shorten = false) {
    if (!$timestamp) {
        // Return "Never" if the timestamp is empty
        return "Never";
    }

    $difference = (get_timestamp()) - $timestamp;
    if ($difference == 0) {
        // Return "now" if the difference is zero
        return "now";
    }
    $ago = $difference >= 0 ? "ago" : ""; // Append "ago" to the end of the string if the difference is positive
    $difference = abs($difference); // Make the difference positive for easier calculations

    // Array of time units and their respective durations in seconds
    $seconds = [
        "year" => 31449600,
        "month" => 2628288,
        "week" => 604800,
        "day" => 86400,
        "hour" => 3600,
        "minute" => 60,
        "second" => 1,
    ];

    // Array of time units and their respective values
    $agoarray = [
        "year" => floor($difference / $seconds["year"]),
        "month" => floor($difference / $seconds["month"]),
        "week" => floor($difference / $seconds["week"]),
        "day" => floor($difference / $seconds["day"]),
        "hour" => floor($difference / $seconds["hour"]),
        "minute" => floor($difference / $seconds["minute"]),
        "second" => $difference,
    ];

    // If shortened, return in the highest unit that is not zero only
    if ($shorten) {
        foreach ($agoarray as $key => $value) {
            if (!empty($value) && $value > 0) {
                $term = $value > 1 ? "s" : ""; // Append "s" to the end of the unit if the value is greater than one
                return $value . " " . $key . $term . " " . $ago; // Return the value and unit with the "ago" suffix
            }
        }
    }

    // Otherwise, return in the most verbose format
    $returnme = "";
    $count = 0;
    $previous_term = false;
    foreach ($agoarray as $key => $value) {
        if ($count >= 2) {
            // Stop after the second unit
            break;
        }

        if (!empty($value) && $value > 0) {
            $value = $previous_term ? floor($difference / $seconds[$key]) : $value;
            $term = $value > 1 ? "s" : ""; // Append "s" to the end of the unit if the value is greater than one
            $returnme .= $value . " " . $key . $term . " "; // Append the value and unit to the string
            $difference -= ($value * $seconds[$key]); // Subtract the value from the difference
            $count++;
        }
        $previous_term = $key;
    }

    // Return the final string with the "ago" suffix
    return $returnme . $ago;
}

function get_date_graphic($timestamp = false, $newday = false, $alter = false, $small = false, $inactive = false) {
	$uniqueid = uniqid("graphic_");
	$gradients = '
	<!-- Define svg gradients -->
    <svg width="0" height="0">
		<linearGradient id="' . $uniqueid . 'gradient_active" x1="100%" y1="100%" x2="0%" y2="0%">
			<stop offset="0%" style="stop-color: rgb(186 241 58);stop-opacity:1"></stop>
			<stop offset="100%" style="stop-color: rgb(255 255 255);stop-opacity:1"></stop>
		</linearGradient>
		<linearGradient id="' . $uniqueid . 'gradient_inactive" x1="100%" y1="100%" x2="0%" y2="0%" >
			<stop offset="0%" style="stop-color: rgb(151 151 151);stop-opacity:1"></stop>
			<stop offset="100%" style="stop-color: rgb(255 255 255);stop-opacity:1"></stop>
		</linearGradient>
	</svg>
	';

	date_default_timezone_set("UTC");
	$timestamp = !$timestamp ? get_timestamp() : $timestamp;

	$size = $small ? "fa-5x" : "fa-7x";
	if (!$newday) {
		return '<div class="dategraphic ' . $size . '"></div>';
	}

	$icon = icon([
		["icon" => "square", "class" => "dropshadow"],
		["content" => date('F', $timestamp), "style" => "top: 20%;font-weight: bold;", "transform" => "shrink-14"],
		["content" => date('jS', $timestamp), "style" => "top: 45%;font-weight: bold;", "transform" => "shrink-11"],
		["content" => date('Y', $timestamp), "style" => "top: 80%;left: 66%;font-weight: bold;", "transform" => "shrink-14"],
	]);

	$status = $inactive ? "inactive" : "active";
	$dategraphic = '
		<style> .' . $uniqueid . 'dategraphic svg:first-child * { fill: url(#' . $uniqueid . 'gradient_' . $status . '); } </style>
		<div class="' . $uniqueid . 'dategraphic dategraphic ' . $size . '">' . $icon . '</div>' . $gradients;

	return $dategraphic;
}

/**
 * Convert a time in 24-hour format to 12-hour format.
 * @param string $time The time in 24-hour format.
 * @return string The time in 12-hour format.
 */
function twelvehourtime($time) {
	date_default_timezone_set(date_default_timezone_get());
	return date('g:i a', strtotime($time));
}
?>