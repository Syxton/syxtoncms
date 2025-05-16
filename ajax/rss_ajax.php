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
	$rssname = clean_myvar_req("rssname", "string");
	$rssid = clean_myvar_req("rssid", "int", false);

	$return = $error = "";
	try {
		$SQL = fetch_template("dbsql/rss.sql", "update_rss_name");
		if (!execute_db_sql($SQL, ["rssname" => $rssname, "rssid" => $rssid])) {
			throw new \Exception("Failed to update rss record.");
		}
		$return = "Saved!";
	} catch (\Throwable $e) {
		$error = $e->getMessage();
	}

	ajax_return($return, $error);
}

function add_feed() {
global $CFG, $USER;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$type = clean_myvar_req("type", "string");
	$key = clean_myvar_req("key", "string");
	$featureid = clean_myvar_req("featureid", "int", false);
	$rssname = clean_myvar_req("rssname", "string");

	$return = $error = "";
	try {
		start_db_transaction();

		$SQL = fetch_template("dbsql/rss.sql", "create_rss");
		if (!$rssid = execute_db_sql($SQL, ["userid" => $USER->userid, "rssname" => $rssname])) {
			throw new \Exception("Failed to create new rss user record.");
		}

		$SQL = fetch_template("dbsql/rss.sql", "create_feed");
		if (!execute_db_sql($SQL, ["rssid" => $rssid, "type" => $type, "featureid" => $featureid, "pageid" => $pageid])) {
			throw new \Exception("Failed to create new rss feed.");
		}

		$p = [
			"wwwroot" => $CFG->wwwroot,
			"rssid" => $rssid,
			"userkey" => $key,
		];
		$return = fill_template("tmp/rss_ajax.template", "add_feed_template", false, $p);

		commit_db_transaction();
	} catch (\Throwable $e) {
		$error = $e->getMessage();
		rollback_db_transaction($error);
	}

	ajax_return($return, $error);
}

?>
