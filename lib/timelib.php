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
 * @param int $timestamp
 * @param bool $shorten
 * @return string
 *
 * Returns a string describing the amount of time since the given timestamp.
 * If $shorten is true, the string will be limited to the highest unit that is not zero.
 * Otherwise, the string will be as verbose as possible.
 */
function ago($timestamp, $shorten = false) {
    if (!$timestamp) { return "Never"; };

    $minutes = "";
    $seconds = "";
    $difference = (get_timestamp()) - $timestamp;
    if ($difference == 0) { return "now"; }
    $ago = $difference >= 0 ? "ago" : "";
    $difference = abs($difference);

    // If shortened, return in the highest unit that is not zero
    if ($shorten) {
        // years
        if (floor($difference / 31449600) > 0) {
            return floor($difference/31449600) . " years " . $ago;
        }

        // months
        if (floor($difference / 2628288) > 0) {
            return floor($difference / 2628288) . " months " . $ago;
        }

        // weeks
        if (floor($difference / 604800) > 0) {
            return floor($difference / 604800) . " weeks " . $ago;
        }

        // days
        if (floor($difference / 86400) > 0) {
            return floor($difference / 86400) . " days " . $ago;
        }

        // hours
        if (floor($difference / 3600) > 0) {
            return floor($difference / 3600) . " hours " . $ago;
        }

        // minutes
        if (floor($difference / 60) > 0) {
            return floor($difference / 60) . " minutes " . $ago;
        }

        // seconds
        return floor($difference) . " seconds " . $ago;
    }

    // years
    if ($difference > 31449600) {
        $years = floor($difference / 31449600) > 1 ? floor($difference/31449600) . " years" : floor($difference/31449600) . " year";
        $weeks = "";
        $difference = $difference - (floor($difference / 31449600) * 31449600);
    }
    if ($difference == 31449600) {
        $years = "1 year";
        $difference = 0;
    }

    // weeks
    if ($difference > 604800) {
        $weeks = floor($difference / 604800) > 1 ? floor($difference/604800) . " weeks" : floor($difference/604800) . " week";
        $days = "";
        $difference = $difference - (floor($difference / 604800) * 604800);
    }
    if ($difference == 604800) {
        $weeks = "1 week";
        $difference = 0;
    }

    // days
    if ($difference > 86400) {
        $days = floor($difference / 86400) > 1 ? floor($difference/86400) . " days" : floor($difference/86400) . " day";
        $hours = "";
        $difference = $difference - (floor($difference / 86400) * 86400);
    }
    if ($difference == 86400) {
        $days = "1 day";
        $difference = 0;
    }

    // hours
    if ($difference > 3600) {
        $hours = floor($difference / 3600) > 1 ? floor($difference/3600) . " hrs" : floor($difference/3600) . " hr";
        $minutes = "";
        $difference = $difference - (floor($difference / 3600) * 3600);
    }
    if ($difference == 3600) {
        $hours = "1 hour";
        $difference = 0;
    }

    // minutes
    if ($difference > 60) {
        $minutes = floor($difference / 60) > 1 ? floor($difference/60) . " mins" : floor($difference/60) . " min";
        $seconds = "";
        $difference = $difference - (floor($difference / 60) * 60);
    }
    if ($difference == 60) {
        $minutes = "1 min";
    } else { $seconds = floor($difference) > 1 ? $difference . " secs" : $difference . " sec"; }

    if ($difference == 0) { $seconds = ""; }

    if (isset($years)) { return "$years $weeks $ago";
    } elseif (isset($weeks)) { return "$weeks $days $ago";
    } elseif (isset($days)) { return "$days $hours $ago";
    } elseif (isset($hours)) { return "$hours $minutes $ago";
    } else { return "$minutes $seconds $ago"; }
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