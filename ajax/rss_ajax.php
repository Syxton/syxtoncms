<?php
/***************************************************************************
* rss_ajax.php - RSS ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.2.3
***************************************************************************/

include ('header.php');
update_user_cookie();

if (!defined('RSSLIB')) { include_once($CFG->dirroot . '/lib/rsslib.php'); }

callfunction();

function edit_name() {
global $MYVARS;
	$rssname = dbescape($MYVARS->GET["rssname"]);
	$rssid = dbescape($MYVARS->GET["rssid"]);
	$SQL = "UPDATE rss
				 SET rssname = '$rssname'
			 WHERE rssid = '$rssid'";

	if (execute_db_sql($SQL)) {
		echo "Saved";
	}
}

function add_feed() {
global $CFG, $MYVARS, $USER;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$type = dbescape($MYVARS->GET["type"]);
	$featureid = dbescape($MYVARS->GET["featureid"]);
	$rssname = dbescape($MYVARS->GET["rssname"]);

	$SQL = "INSERT INTO rss (userid,rssname)
				 VALUES ('$USER->userid', '$rssname')";
	if ($rssid = execute_db_sql($SQL)) {
		$SQL = "INSERT INTO rss_feeds (rssid, type, featureid, pageid)
					 VALUES ('$rssid', '$type', '$featureid', '$pageid')";
		if (execute_db_sql($SQL)) {
			$p = [ "wwwroot" => $CFG->wwwroot,
					 "rssid" => $rssid,
					 "userkey" => $MYVARS->GET["key"],
			];
			echo fill_template("tmp/rss_ajax.template", "add_feed_template", false, $p);
		}
	}
}

?>
