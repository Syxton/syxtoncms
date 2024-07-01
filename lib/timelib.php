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

function get_timestamp($timezone = "UTC") {
global $CFG;
	date_default_timezone_set($timezone);
	$time = time();
	date_default_timezone_set($CFG->timezone);
	return $time;
}

function get_offset() {
global $CFG;
	// Create two timezone objects, one for UTC and one for local timezone
	$LOCAL = new DateTimeZone($CFG->timezone);
	$timeLOCAL = new DateTime("now", $LOCAL);
	$timeOffset = timezone_offset_get($LOCAL, $timeLOCAL);
	return $timeOffset;
}

function ago($timestamp, $shorten = false) {
global $CFG;
    if (!$timestamp) { return "Never"; };
	$minutes = ""; $seconds = "";
	$difference = (get_timestamp()) - $timestamp;
	if ($difference == 0) { return "now"; }
	$ago = $difference >= 0 ? "ago" : "";
	$difference = abs($difference);

	if ($shorten) {
		if (floor($difference / 31449600) > 1) {
			return floor($difference/31449600) . " years ago";
		} elseif (floor($difference / 31449600) == 1) {
			return floor($difference/ 31449600) . " year ago";
		} elseif (floor($difference / 2628288) > 1) {
			return floor($difference / 2628288) . " months ago";
		} elseif (floor($difference / 2628288) == 1) {
			return floor($difference / 2628288) . " month ago";
		} elseif (floor($difference / 604800) > 1) {
			return floor($difference / 604800) . " weeks ago";
		} elseif (floor($difference / 604800) == 1) {
			return floor($difference / 604800) . " week ago";
		} elseif (floor($difference / 86400) > 1) {
			return floor($difference / 86400) . " days ago";
		} elseif (floor($difference / 86400) == 1) {
			return floor($difference / 86400) . " day ago";
		} elseif (floor($difference / 3600) > 1) {
			return floor($difference / 3600) . " hours ago";
		} elseif (floor($difference / 3600) == 1) {
			return floor($difference / 3600) . " hour ago";
		} elseif (floor($difference / 60) > 1) {
			return floor($difference / 60) . " minutes ago";
		} elseif (floor($difference / 60) == 1) {
			return floor($difference / 60) . " minute ago";
		} elseif (floor($difference) > 1) {
			return floor($difference / 60) . " seconds ago";
		} elseif (floor($difference / 60) == 1) {
			return floor($difference / 60) . " now";
		}
	}

	if ($difference > 31449600) {
        $years = floor($difference / 31449600) > 1 ? floor($difference/31449600) . " years" : floor($difference/31449600) . " year";
        $weeks = "";
        $difference = $difference - (floor($difference / 31449600) * 31449600);
	}
	if ($difference == 31449600) {
		$years = "1 year";
		$difference = 0;
	}
	if ($difference > 604800) {
        $weeks = floor($difference / 604800) > 1 ? floor($difference/604800) . " weeks" : floor($difference/604800) . " week";
        $days = "";
        $difference = $difference - (floor($difference / 604800) * 604800);
	}
	if ($difference == 604800) {
		$weeks = "1 week";
		$difference = 0;
	}
	if ($difference > 86400) {
        $days = floor($difference / 86400) > 1 ? floor($difference/86400) . " days" : floor($difference/86400) . " day";
        $hours = "";
        $difference = $difference - (floor($difference / 86400) * 86400);
	}
	if ($difference == 86400) {
		$days = "1 day";
		$difference = 0;
	}
	if ($difference > 3600) {
        $hours = floor($difference / 3600) > 1 ? floor($difference/3600) . " hrs" : floor($difference/3600) . " hr";
        $minutes = "";
        $difference = $difference - (floor($difference / 3600) * 3600);
	}
	if ($difference == 3600) {
		$hours = "1 hour";
		$difference = 0;
	}
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
global $CFG;
	$gradients = '
	<!-- Define svg gradients -->
    <svg width="0" height="0">
		<linearGradient id="gradient_active" x1="100%" y1="100%" x2="0%" y2="0%">
			<stop offset="0%" style="stop-color: rgb(186 241 58);stop-opacity:1"></stop>
			<stop offset="100%" style="stop-color: rgb(255 255 255);stop-opacity:1"></stop>
		</linearGradient>
		<linearGradient id="gradient_inactive" x1="100%" y1="100%" x2="0%" y2="0%" >
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
		["content" => date('jS', $timestamp), "style" => "top: 45%;font-weight: bold;", "transform" => "shrink-10"],
		["content" => date('Y', $timestamp), "style" => "top: 80%;left: 66%;font-weight: bold;", "transform" => "shrink-14"],
	]);

	$status = $inactive ? "inactive" : "active";
	$dategraphic = '
		<style> .dategraphic svg:first-child * { fill: url(#gradient_' . $status . '); } </style>
		<div class="dategraphic ' . $size . '">' . $icon . '</div>' . $gradients;

	return $dategraphic;
}

function convert_time($time) {
	date_default_timezone_set(date_default_timezone_get());
	$time = explode(":", $time);
    $time[1] = empty($time[1]) ? "00" : $time[1];
	if ($time[0] > 12) {
		return ($time[0]-12) . ":" . $time[1] . "pm";
	} else {
		if ($time[0] == "00") { return "12:" . $time[1] . "am"; }
		return $time[0] . ":" . $time[1] . "am";
	}
}
?>