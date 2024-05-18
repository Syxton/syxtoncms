<?php
/***************************************************************************
* html_ajax.php - HTML feature ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.9.6
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

if (!defined('HTMLLIB')) { include_once($CFG->dirroot . '/features/html/htmllib.php'); }

update_user_cookie();

callfunction();

function new_edition() {
global $CFG, $MYVARS;
	$htmlid = clean_myvar_req("htmlid", "int");
	$pageid = clean_myvar_req("pageid", "int");

    try {
        start_db_transaction();
        $html = get_db_row("SELECT * FROM html h JOIN pages_features pf ON pf.featureid = h.htmlid WHERE h.htmlid = ||htmlid|| AND pf.feature = 'html'", ["htmlid" => $htmlid]);
        if ($newhtmlid = execute_db_sql("INSERT INTO html (pageid, html, dateposted) VALUES(||pageid||, ||html||, ||timestamp||)", ["pageid" => $pageid, "html" => '', "timestamp" => get_timestamp()])) {
            if ($area = get_db_field("default_area", "features", "feature='html'")) {
                if ($sort = get_db_count("SELECT * FROM pages_features WHERE pageid = ||pageid|| AND area = ||area||", ["pageid" => $pageid, "area" => $area])) {
                    $sort++;
                    $SQL = "INSERT INTO pages_features (pageid, feature, sort, area, featureid) VALUES(||pageid||, 'html', ||sort||, ||area||, ||featureid||)";
                    execute_db_sql($SQL, ["pageid" => $pageid, "sort" => $sort, "area" => $area, "featureid" => $newhtmlid]);
                    // Move new html to the previous location
                    $SQL = "UPDATE pages_features SET area = ||area||, sort = ||sort|| WHERE feature = 'html' AND featureid = ||featureid||";
                    execute_db_sql($SQL, ["area" => $html["area"], "sort" => $html["sort"], "featureid" => $newhtmlid]);

                    // Move old html to the locker
                    $SQL = "UPDATE pages_features SET area = 'locker' WHERE feature = 'html' AND featureid = ||featureid||";
                    execute_db_sql($SQL, ["featureid" => $htmlid]);

                    // Set first edition field
                    $params = $html["firstedition"] ? ["firstedition" => $html["firstedition"], "htmlid" => $newhtmlid] : ["firstedition" => $htmlid, "htmlid" => $newhtmlid];
                    $SQL = "UPDATE html SET firstedition = ||firstedition|| WHERE htmlid = ||htmlid||";
                    execute_db_sql($SQL, $params);

                    // Copy settings
                    $SQL = "SELECT * FROM settings WHERE type = 'html' AND featureid = ||featureid||";
                    if ($result = get_db_result($SQL, ["featureid" => $htmlid])) {
                        while ($row = fetch_row($result)) {
                            copy_db_row($row, "settings", ["settingid" => NULL, "featureid" => $newhtmlid]);
                        }
                    }

                    // Refresh new edition title.
                    $SQL = "UPDATE settings SET setting = 'New HTML Edition' WHERE type = 'html' AND featureid = ||featureid|| AND setting_name = ||setting_name||";
                    execute_db_sql($SQL, ["featureid" => $newhtmlid, "setting_name" => "feature_title"]);

                    // Commit
                    commit_db_transaction();
                }
            }
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
}

function still_editing() {
global $MYVARS;
	$htmlid = clean_myvar_req("htmlid", "int");
	$userid = clean_myvar_req("userid", "int");

    try {
        start_db_transaction();
        // Update last edit time
        $params = [
            "userid" => $userid,
            "edit_time" => get_timestamp(),
            "htmlid" => $htmlid,
        ];
        execute_db_sql(fetch_template("dbsql/html.sql", "html_edit_time", "html"), $params);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
}

function stopped_editing() {
global $MYVARS;
	$htmlid = clean_myvar_req("htmlid", "int");

    try {
        start_db_transaction();
        // Update last edit time
        $params = [
            "userid" => 0,
            "edit_time" => 0,
            "htmlid" => $htmlid,
        ];
        execute_db_sql(fetch_template("dbsql/html.sql", "html_edit_time", "html"), $params);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
}

function edit_html() {
global $CFG, $MYVARS;
    $htmlid = clean_myvar_req("htmlid", "int");
    $html = clean_myvar_req("html", "html");

    // Update HTML
    try {
        start_db_transaction();
        $params = [
            "htmlid" => $htmlid,
            "html" => $html,
            "dateposted" => get_timestamp(),
            "edit_user" => 0,
            "edit_time" => 0,
        ];
        execute_db_sql(fetch_template("dbsql/html.sql", "html_edit", "html"), $params);
        log_entry("html", $htmlid, "Edited");
        commit_db_transaction();
        echo "HTML edited successfully";
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
}

function commentspage() {
global $CFG, $MYVARS;
	echo get_html_comments($MYVARS->GET["htmlid"], $MYVARS->GET["pageid"], false, $MYVARS->GET["perpage"], $MYVARS->GET["pagenum"], false);
}

function deletecomment() {
global $CFG, $MYVARS;
	$commentid = clean_myvar_req("commentid", "int");

    // Has replies, so don't delete it, just remove data.
    if (get_db_result("SELECT * FROM html_comments WHERE parentid = '$commentid'")) {
        execture_db_sql("UPDATE html_comments SET comment = 'Removed', userid = 0 WHERE commentid = '$commentid'");
    } else {
        execute_db_sql("DELETE FROM html_comments WHERE commentid = '$commentid'");
    }

	// Log
    log_entry("html", $commentid, "Delete Comment");
}

function comment() {
global $CFG, $MYVARS, $USER;
    $comment = clean_myvar_req("comment", "html");
    $commentid = clean_myvar_opt("commentid", "int", false);
    $replytoid = clean_myvar_opt("replytoid", "int", false);
    $htmlid = clean_myvar_opt("htmlid", "int", false);

    $time = get_timestamp();
    if ($commentid) {
        $SQL = fetch_template("dbsql/html.sql", "update_comment", "html");
        if (execute_db_sql($SQL, ["comment" => $comment, "commentid" => $commentid, "modified" => $time])) {
            log_entry("html", $commentid, "Blog Comment Edited");
            echo "Blog comment edited successfully";
        }
        return;
    }

    if ($replytoid) {
        $htmlid = get_db_field("htmlid", "html_comments", "commentid = '$replytoid'");
        $SQL = fetch_template("dbsql/html.sql", "insert_reply", "html");
        if ($commentid = execute_db_sql($SQL, ["parentid" => $replytoid, "comment" => $comment, "userid" => $USER->userid, "htmlid" => $htmlid, "created" => $time, "modified" => $time])) {
            log_entry("html", $commentid, "Blog Reply");
            echo "Blog reply made successfully";
        }
        return;
    }

    // New
    if ($htmlid) {
        $SQL = fetch_template("dbsql/html.sql", "insert_comment", "html");
        if ($commentid = execute_db_sql($SQL, ["comment" => $comment, "userid" => $USER->userid, "htmlid" => $htmlid, "created" => $time, "modified" => $time])) {
            log_entry("html", $commentid, "Blog Comment");
            echo "Blog comment made successfully";
        }
    }
}
?>