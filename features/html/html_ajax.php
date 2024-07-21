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
    $htmlid = clean_myvar_req("htmlid", "int");
    $pageid = clean_myvar_req("pageid", "int");
    $type = "html";
    $error = "";
    try {
        start_db_transaction();
        $html = get_db_row("SELECT * FROM html h JOIN pages_features pf ON pf.featureid = h.htmlid WHERE h.htmlid = ||htmlid|| AND pf.feature = 'html'", ["htmlid" => $htmlid]);
        if ($newhtmlid = execute_db_sql(fetch_template("dbsql/html.sql", "insert_html", $type), ["pageid" => $pageid, "html" => '', "dateposted" => get_timestamp()])) {
            $area = get_db_field("default_area", "features", "feature = ||feature||", ["feature" => $type]);
            $sort = get_db_count(fetch_template("dbsql/features.sql", "get_features_by_page_area"), ["pageid" => $pageid, "area" => $area]) + 1;

            $params = ["feature" => $type, "pageid" => $pageid, "sort" => $sort, "area" => $area, "featureid" => $newhtmlid];
            execute_db_sql(fetch_template("dbsql/features.sql", "insert_page_feature"), $params);

            // Move new html to the previous location
            execute_db_sql(fetch_template("dbsql/features.sql", "update_pages_features_by_featureid"), ["area" => $html["area"], "sort" => $html["sort"], "feature" => $type, "featureid" => $newhtmlid]);

            // Move old html to the locker
            execute_db_sql(fetch_template("dbsql/features.sql", "update_pages_features_area_by_featureid"), ["area" => "locker", "feature" => $type, "featureid" => $htmlid]);

            // Set first edition field
            $params = $html["firstedition"] ? ["firstedition" => $html["firstedition"], "htmlid" => $newhtmlid] : ["firstedition" => $htmlid, "htmlid" => $newhtmlid];
            execute_db_sql(fetch_template("dbsql/html.sql", "html_set_firstedition", $type), $params);

            // Copy settings
            if ($result = get_db_result(fetch_template("dbsql/settings.sql", "get_settings_by_featureid"), ["type" => $type, "featureid" => $htmlid])) {
                while ($row = fetch_row($result)) {
                    copy_db_row($row, "settings", ["settingid" => NULL, "featureid" => $newhtmlid]);
                }
            }

            // Refresh new edition title.
            execute_db_sql(fetch_template("dbsql/settings.sql", "update_setting_by_featureid"), ["setting" => "New HTML Edition", "type" => $type, "featureid" => $newhtmlid, "setting_name" => "feature_title"]);

            // Commit
            commit_db_transaction();
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    ajax_return("", $error);
}

function still_editing() {
global $MYVARS;
    $htmlid = clean_myvar_req("htmlid", "int");
    $userid = clean_myvar_req("userid", "int");
    $error = "";
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
        $error = $e->getMessage();
    }
    ajax_return("", $error);
}

function stopped_editing() {
global $MYVARS;
    $htmlid = clean_myvar_req("htmlid", "int");
    $error = "";
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
        $error = $e->getMessage();
    }
    ajax_return("Closed editing session", $error);
}

function edit_html() {
    $htmlid = clean_myvar_req("htmlid", "int");
    $html = clean_myvar_req("html", "html");

    $error = "";
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
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }
    ajax_return("", $error);
}

function commentspage() {
global $CFG, $MYVARS;
    $comments = get_html_comments(clean_myvar_req("htmlid", "int"), clean_myvar_opt("pageid", "int", get_pageid()), false, clean_myvar_opt("perpage", "int", false), clean_myvar_opt("pagenum", "int", false), false);
    ajax_return($comments);
}

function deletecomment() {
    $commentid = clean_myvar_req("commentid", "int");
    $comment = get_db_row("SELECT * FROM html_comments WHERE commentid = ||commentid||", ["commentid" => $commentid]);
    $htmlid = $comment["htmlid"];
    $pageid = get_db_field("pageid", "html", "htmlid = ||htmlid||", ["htmlid" => $htmlid]);
    $area = get_feature_area("html", $htmlid);

    if (!$settings = fetch_settings("html", $htmlid, $pageid)) {
        save_batch_settings(default_settings("html", $pageid, $htmlid));
        $settings = fetch_settings("html", $htmlid, $pageid);
    }

    $perpage = $area == "side" ? $settings->html->$htmlid->sidecommentlimit->setting : $settings->html->$htmlid->middlecommentlimit->setting;

    // Has replies, so don't delete it, just remove data.
    if (get_db_result("SELECT * FROM html_comments WHERE parentid = ||parentid||", ["parentid" => $commentid])) {
        $SQL = fetch_template("dbsql/html.sql", "update_comment", "html");
        execute_db_sql($SQL, ["comment" => "Removed", "commentid" => $commentid, "modified" => get_timestamp()]);
    } else {
        $SQL = fetch_template("dbsql/html.sql", "delete_comment", "html");
        execute_db_sql($SQL, ["commentid" => $commentid]);
    }

    // Log
    log_entry("html", $commentid, "Delete Comment");


    $return = get_html_comments($htmlid, $pageid, false, $perpage, 0, false);

    ajax_return($return);
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