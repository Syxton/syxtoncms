<?php
/***************************************************************************
* rss_ajax.php - RSS ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 05/18/2021
* Revision: 1.2.3
***************************************************************************/

include ('header.php');
update_user_cookie();

if (!isset($RSSLIB)) { include_once($CFG->dirroot . '/lib/rsslib.php'); }

callfunction();

function edit_name() {
global $MYVARS;
	$rssname = dbescape($MYVARS->GET["rssname"]);
	$rssid = $MYVARS->GET["rssid"];
  $SQL = "UPDATE rss
						 SET rssname = '$rssname'
					 WHERE rssid = '$rssid'";

	if (execute_db_sql($SQL)) {
		echo "Saved";
	}
}

function add_feed() {
global $CFG, $MYVARS, $USER;
	$pageid = $MYVARS->GET["pageid"];
	$type = $MYVARS->GET["type"];
	$featureid = $MYVARS->GET["featureid"];
	$userkey = $MYVARS->GET["key"];
	$rssname = dbescape($MYVARS->GET["rssname"]);

	$SQL = "INSERT INTO rss (userid,rssname)
							 VALUES ('$USER->userid', '$rssname')";
	if ($rssid = execute_db_sql($SQL)) {
		$SQL = "INSERT INTO rss_feeds (rssid, type, featureid, pageid)
								 VALUES ('$rssid', '$type', '$featureid', '$pageid')";
		if (execute_db_sql($SQL)) {
			echo template_use("tmp/rss_ajax.template", array("wwwroot" => $CFG->wwwroot, "rssid" => $rssid, "userkey" => $userkey), "add_feed_template");
		}
	}
}
?>
