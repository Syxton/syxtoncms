<?php
/***************************************************************************
* rss.php - RSS page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.1.3
***************************************************************************/

if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}

echo fill_template("tmp/roles.template", "roles_header_script");

callfunction();

echo fill_template("tmp/page.template", "end_of_page_template");

function rss_subscribe_feature() {
global $CFG, $MYVARS, $USER;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = $MYVARS->GET["feature"];
	$featureid = clean_myvar_opt("featureid", "int", false);
	$userid = $USER->userid;
	$userkey = get_db_field("userkey", "users", "userid='$userid'");

	// User has already created rssid...just needs the link for it again.
	$SQL = fetch_template("dbsql/users.sql", "lookup_user_rss");
	if ($feed = get_db_row($SQL, ["pageid" => $pageid, "type" => $feature, "featureid" => $featureid, "userid" => $userid])) {
		$params = [
			'wwwroot' => $CFG->wwwroot,
			'feed' => true,
			'rss' => get_db_row(fetch_template("dbsql/users.sql", "get_rss"), ["rssid" => $feed["rssid"]]),
			'userkey' => $userkey,
		];
		echo fill_template("tmp/rss.template", "rss_subscribe_feature_template", false, $params);
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
		echo fill_template("tmp/rss.template", "rss_subscribe_feature_template", false, $params);
	}
}
?>