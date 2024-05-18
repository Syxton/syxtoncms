<?php
/***************************************************************************
* rss.php - RSS page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.1.3
***************************************************************************/
include('header.php');

echo use_template("tmp/page.template", ["dirroot" => $CFG->directory], "page_js_css");

callfunction();

echo use_template("tmp/page.template", [], "end_of_page_template");

function rss_subscribe_feature() {
global $CFG, $MYVARS, $USER;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = $MYVARS->GET["feature"];
	$featureid = clean_myvar_opt("featureid", "int", false);
	$userid = $USER->userid;
	$userkey = get_db_field("userkey", "users", "userid='$userid'");

	// User has already created rssid...just needs the link for it again.
	$SQL = "SELECT *
            FROM rss_feeds
			WHERE pageid = '$pageid'
			AND type = '$feature'
            AND featureid = '$featureid'
            AND rssid IN (SELECT rssid
                          FROM rss
                          WHERE userid = '$userid'
                         )";

	if ($feed = get_db_row($SQL)) {
		$SQL = "SELECT *
                FROM rss
				WHERE rssid = '" . $feed["rssid"] . "'";

		$params = [
            'wwwroot' => $CFG->wwwroot,
            'feed' => true,
            'rss' => get_db_row($SQL),
            'userkey' => $userkey,
        ];
		echo use_template("tmp/rss.template", $params, "rss_subscribe_feature_template");
	} else { // Need to create new rssid and feed
		$settings = fetch_settings($feature, $featureid, $pageid);
		$title = $settings->$feature->$featureid->feature_title->setting;

		$params =[
            'wwwroot' => $CFG->wwwroot,
            'feed' => false,
            'title' => $title,
            'userkey' => $userkey,
            'pageid' => $pageid,
            'feature' => $feature,
            'featureid' => $featureid,
        ];
		echo use_template("tmp/rss.template", $params, "rss_subscribe_feature_template");
	}
}
?>