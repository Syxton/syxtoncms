<?php
/***************************************************************************
* forum_ajax.php - Forum/Shoutbox feature ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.8.3
***************************************************************************/

if (!isset($CFG)) {
    $sub = '';
    while (!file_exists($sub . 'header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'header.php');
}

if (!defined('FORUMLIB')) { include_once($CFG->dirroot . '/features/forum/forumlib.php'); }

update_user_cookie();

callfunction();

function get_forum_categories_ajax() {
    $forumid = clean_myvar_req("forumid", "int");
    ajax_return(get_forum_categories($forumid));
}

function get_shoutbox_ajax() {
    $forumid = clean_myvar_req("forumid", "int");
    ajax_return(get_shoutbox($forumid));
}

function get_forum_discussions() {
global $CFG, $USER;
    $catid = clean_myvar_req("catid", "int");
    $dpagenum = clean_myvar_opt("dpagenum", "string", 0);

    $returnme = $error = "";
    try {
        $returnme = get_discussions($catid, $dpagenum);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    ajax_return($returnme, $error);
}

function get_forum_posts() {
    $discussionid = clean_myvar_req("discussionid", "int");
    $pagenum = clean_myvar_opt("pagenum", "int", 0);

    $return = $error = "";
    try {
        $return = get_posts($discussionid, $pagenum);
    } catch (\Throwable $e) {
        debugging($e);
        $error = $e->getMessage();
    }
    ajax_return($return, $error);
}

function post() {
global $USER;
    $message = clean_myvar_req("message", "html");
    $replyto = clean_myvar_opt("replyto", "int", false);
    $discussionid = clean_myvar_opt("discussionid", "int", false);

    $error = "";
    try {
        start_db_transaction();
        $time = get_timestamp();

        if (!$replyto && !$discussionid) {
            throw new Exception("Not enough information was provided to post.");
        }

        if ($replyto) {
            $post = get_db_row(fetch_template("dbsql/forum.sql", "get_post", "forum"), ["postid" => $replyto]);
            $discussionid = $post["discussionid"];
            $forumid = $post["forumid"];
            $pageid = $post["pageid"];
            $catid = $post["catid"];
        } else {
            $discussion = get_db_row(fetch_template("dbsql/forum.sql", "get_discussion", "forum"), ["discussionid" => $discussionid]);
            $forumid = $discussion["forumid"];
            $pageid = $discussion["pageid"];
            $catid = $discussion["catid"];
        }

        // Insert Post.
        $params = [
            "discussionid" => $discussionid,
            "catid" => $catid,
            "forumid" => $forumid,
            "pageid" => $pageid,
            "userid" => $USER->userid,
            "alias" => "",
            "message" => $message,
            "posted" => $time,
        ];
        execute_db_sql(fetch_template("dbsql/forum.sql", "insert_post", "forum"), $params);

        // Update Discussion.update_discussion_lastpost.
        update_discussion_lastpost($discussionid);

        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    ajax_return("", $error);
}

function quote_post() {
global $USER;
    $message = clean_myvar_req("message", "html");
    $quotepost = clean_myvar_req("quotepost", "int");
    $post = get_db_row(fetch_template("dbsql/forum.sql", "get_post", "forum"), ["postid" => $quotepost]);

    $error = "";
    try {
        start_db_transaction();
        $time = get_timestamp();

        $quoteparams = [
            "author" => ($post["userid"] ? get_user_name($post["userid"]) : ($post["alias"] ? $post["alias"] : "Anonymous")),
            "time" => ago($post["posted"], true),
            "quotemessage" => truncate($post["message"], 1000),
        ];
        $message = fill_template("tmp/forum.template", "forum_quote", "forum", $quoteparams) . $message;

        // Insert Post.
        $params = [
            "discussionid" => $post["discussionid"],
            "catid" => $post["catid"],
            "forumid" => $post["forumid"],
            "pageid" => $post["pageid"],
            "userid" => $USER->userid,
            "alias" => "",
            "message" => $message,
            "posted" => $time,
        ];
        execute_db_sql(fetch_template("dbsql/forum.sql", "insert_post", "forum"), $params);

        // Update Discussion.update_discussion_lastpost.
        update_discussion_lastpost($post["discussionid"]);

        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }
    ajax_return("", $error);
}

function edit_post() {
global $USER;
    $message = clean_myvar_req("message", "html");
    $postid = clean_myvar_req("postid", "int");
    $error = "";
    try {
        start_db_transaction();

        // Get timestamp.
        $time = get_timestamp();

        $params = [
            "message" => $message,
            "edited" => $time,
            "editedby" => $USER->userid,
            "postid" => $postid,
        ];
        // Update post.
        execute_db_sql(fetch_template("dbsql/forum.sql", "update_post", "forum"), $params);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }
    ajax_return("", $error);
}

function create_category() {
    $forumid = clean_myvar_req("forumid", "int");
    $title = clean_myvar_req("title", "html");

    $error = "";
    try {
        start_db_transaction();
        $pageid = get_db_field("pageid", "forum", "forumid = ||forumid||", ["forumid" => $forumid]);
        $sort = get_db_count(fetch_template("dbsql/forum.sql", "get_forum_categories", "forum"), ["forumid" => $forumid]) + 1;
        $params = ["forumid" => $forumid, "pageid" => $pageid, "title" => $title, "sort" => $sort, "shoutbox" => 0];
        $catid = execute_db_sql(fetch_template("dbsql/forum.sql", "insert_category", "forum"), $params);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    ajax_return("", $error);
}

function edit_category() {
    $title = clean_myvar_req("title", "html");
    $catid = clean_myvar_req("catid", "int");

    $error = "";
    try {
        start_db_transaction();
        execute_db_sql(fetch_template("dbsql/forum.sql", "update_category", "forum"), ["title" => $title, "catid" => $catid]);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }
    ajax_return("", $error);
}

function create_discussion() {
global $USER;
    $message = clean_myvar_req("message", "html");
    $title = clean_myvar_req("title", "html");
    $catid = clean_myvar_req("catid", "int");

    $time = get_timestamp();
    $error = "";
    try {
        start_db_transaction();
        $category = get_db_row(fetch_template("dbsql/forum.sql", "get_category", "forum"), ["catid" => $catid]);
        $params = [
            "message" => $message,
            "catid" => $catid,
            "forumid" => $category["forumid"],
            "pageid" => $category["pageid"],
            "userid" => $USER->userid,
            "posted" => $time,
            "title" => $title,
            "lastpost" => $time,
            "shoutbox" => 0,
            "alias" => "",
        ];

        $discussionid = execute_db_sql(fetch_template("dbsql/forum.sql", "insert_discussion", "forum"), $params);
        $params["discussionid"] = $discussionid;
        execute_db_sql(fetch_template("dbsql/forum.sql", "insert_post", "forum"), $params);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }
    ajax_return("", $error);
}

function edit_discussion() {
global $USER;
    $message = clean_myvar_req("message", "html");
    $title = clean_myvar_req("title", "html");
    $postid = clean_myvar_req("postid", "int");
    $discussionid = clean_myvar_req("discussionid", "int");

    $error = "";
    try {
        start_db_transaction();

        // Get timestamp.
        $time = get_timestamp();

        // Update discussion title.
        execute_db_sql(fetch_template("dbsql/forum.sql", "update_discussion_title", "forum"), ["discussionid" => $discussionid, "title" => $title]);

        $params = [
            "message" => $message,
            "edited" => $time,
            "editedby" => $USER->userid,
            "postid" => $postid,
        ];
        // Update discussion 1st post.
        execute_db_sql(fetch_template("dbsql/forum.sql", "update_post", "forum"), $params);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }
    ajax_return("", $error);
}
function move_category() {
global $CFG;
    $direction = clean_myvar_req("direction", "string");
    $catid = clean_myvar_req("catid", "int");
    $forumid = get_db_field("forumid", "forum_categories", "catid = ||catid||", ["catid" => $catid]);

    $returnme = $error = "";
    try {
        start_db_transaction();
        $current_position = get_db_field("sort", "forum_categories", "catid = ||catid||", ["catid" => $catid]);
        if ($direction == 'up') {
            $up_position = $current_position - 1;
            execute_db_sql("UPDATE forum_categories SET sort='$current_position' WHERE forumid='$forumid' AND shoutbox=0 AND sort='$up_position'");
            execute_db_sql("UPDATE forum_categories SET sort='$up_position' WHERE catid='$catid'");
        } elseif ($direction == 'down') {
            $down_position = $current_position + 1;
            execute_db_sql("UPDATE forum_categories SET sort='$current_position' WHERE forumid='$forumid' AND shoutbox=0 AND sort='$down_position'");
            execute_db_sql("UPDATE forum_categories SET sort='$down_position' WHERE catid='$catid'");
        }
        $returnme = get_forum_categories($forumid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    ajax_return($returnme, $error);
}

function delete_category() {
    $catid = clean_myvar_req("catid", "int");
    $forumid = get_db_field("forumid", "forum_categories", "catid = ||catid||", ["catid" => $catid]);
    $error = "";
    try {
        start_db_transaction();
        $templates = [
            [
                "feature" => "forum",
                "file" => "dbsql/forum.sql",
                "subsection" => [
                    "delete_category",
                    "delete_category_discussions",
                    "delete_category_posts",
                ]
            ],
        ];
        execute_db_sqls(fetch_template_set($templates), ["catid" => $catid]);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    // Make sure the sort columns are correct.
    resort_categories($forumid);
    ajax_return(get_forum_categories($forumid), $error);
}

function delete_discussion() {
    $discussionid = clean_myvar_req("discussionid", "int");

    try {
        start_db_transaction();
        $catid = get_db_field("catid", "forum_discussions", "discussionid = ||discussionid||", ["discussionid" => $discussionid]);
        $templates = [
            [
                "feature" => "forum",
                "file" => "dbsql/forum.sql",
                "subsection" => [
                    "delete_discussion",
                    "delete_discussion_posts",
                ]
            ],
        ];
        execute_db_sqls(fetch_template_set($templates), ["discussionid" => $discussionid]);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    ajax_return(get_discussions($catid), $error);
}

function delete_post() {
    $postid = clean_myvar_req("postid", "int");
    $pagenum = clean_myvar_opt("pagenum", "int", 0);

    $returnme = $error = "";
    try {
        $discussionid = get_db_field("discussionid", "forum_posts", "postid = ||postid||", ["postid" => $postid]);
        execute_db_sql(fetch_template("dbsql/forum.sql", "delete_post", "forum"), ["postid" => $postid]);
        $returnme = get_posts($discussionid, $pagenum);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    ajax_return($returnme, $error);
}

function pin_bulletin() {
    $returnme = $error = "";
    try {
        $discussionid = clean_myvar_req("discussionid", "int");
        execute_db_sql(fetch_template("dbsql/forum.sql", "pin_discussion", "forum"), ["discussionid" => $discussionid]);
        get_forum_discussions();

        $discussionid = get_db_field("discussionid", "forum_posts", "postid = ||postid||", ["postid" => $postid]);
        execute_db_sql(fetch_template("dbsql/forum.sql", "delete_post", "forum"), ["postid" => $postid]);
        $returnme = get_posts($discussionid, $pagenum);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    ajax_return($returnme, $error);
}

function unpin_bulletin() {
    $discussionid = clean_myvar_req("discussionid", "int");
    $dpagenum = clean_myvar_opt("dpagenum", "int", 0);
    $discussion = get_db_row(fetch_template("dbsql/forum.sql", "get_discussion", "forum"), ["discussionid" => $discussionid]);
    $catid = $discussion["catid"];

    execute_db_sql(fetch_template("dbsql/forum.sql", "unpin_discussion", "forum"), ["discussionid" => $discussionid]);

    ajax_return(get_discussions($catid, $dpagenum));
}

function lock_discussion() {
    $discussionid = clean_myvar_req("discussionid", "int");
    $dpagenum = clean_myvar_opt("dpagenum", "int", 0);
    $discussion = get_db_row(fetch_template("dbsql/forum.sql", "get_discussion", "forum"), ["discussionid" => $discussionid]);
    $catid = $discussion["catid"];

    execute_db_sql(fetch_template("dbsql/forum.sql", "lock_discussion", "forum"), ["discussionid" => $discussionid]);
    ajax_return(get_discussions($catid, $dpagenum));
}

function unlock_discussion() {
    $discussionid = clean_myvar_req("discussionid", "int");
    $dpagenum = clean_myvar_opt("dpagenum", "int", 0);
    $discussion = get_db_row(fetch_template("dbsql/forum.sql", "get_discussion", "forum"), ["discussionid" => $discussionid]);
    $catid = $discussion["catid"];

    execute_db_sql(fetch_template("dbsql/forum.sql", "unlock_discussion", "forum"), ["discussionid" => $discussionid]);
    ajax_return(get_discussions($catid, $dpagenum));
}

function shoutbox_post() {
    $message = clean_myvar_req("message", "html");
    $forumid = clean_myvar_req("forumid", "int");
    $alias = clean_myvar_opt("alias", "string", false);
    $userid = clean_myvar_opt("userid", "int", false);
    $error = "";
    try {
        start_db_transaction();
        // Get forum discussion where shoutbox = 1
        if (!$discussion = get_db_row(fetch_template("dbsql/forum.sql", "get_shoutbox", "forum"), ["forumid" => $forumid])) {
            throw new \Exception("Error: Could not find forum in database.  Post was not saved.");
        }

        // Owner userid not give, and alias not provided.
        if (!$userid && !$alias) { $alias = "Anonymous"; }

        // Save message
        $params = [
            "discussionid" => $discussion["discussionid"],
            "catid" => $discussion["catid"],
            "forumid" => $forumid,
            "pageid" => $discussion["pageid"],
            "userid" => $userid,
            "message" => $message,
            "posted" => get_timestamp(),
            "alias" => $alias
        ];
        execute_db_sql(fetch_template("dbsql/forum.sql", "insert_post", "forum"), $params);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    ajax_return("", $error);
}
?>