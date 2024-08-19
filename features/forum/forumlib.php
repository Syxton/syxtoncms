<?php
/***************************************************************************
* forumlib.php - Forum function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.8.8
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('FORUMLIB', true);
define('FORUM_REFRESH', 60000); // Refresh auto time in milliseconds. (60,000 = 1 minute)

function display_forum($pageid, $area, $forumid) {
global $USER;
    date_default_timezone_set(date_default_timezone_get());

    // Get settings or create default settings if they don't exist
    if (!$settings = fetch_settings("forum", $forumid, $pageid)) {
        save_batch_settings(default_settings("forum", $pageid, $forumid));
        $settings = fetch_settings("forum", $forumid, $pageid);
    }

    $title = $settings->forum->$forumid->feature_title->setting;
    $scriptid = false;

    if ($area == "middle") { //This is a FORUM
        $content = '<div id="forum_div_' . $forumid . '">';
        if (!user_is_able($USER->userid, "viewforums", $pageid)) {
            $content .= '<span class="centered_span">' . error_string("generic_permissions") . '</span>';
        } else {
            $content .= get_forum_categories($forumid);
            $scriptid = "get_categories_$forumid";
            ajaxapi([
                "id" => $scriptid,
                "url" => "/features/forum/forum_ajax.php",
                "data" => [
                    "action" => "get_forum_categories_ajax",
                    "forumid" => $forumid,
                ],
                "display" => "forum_div_$forumid",
                "event" => "none",
                "intervalid" => "forum_$forumid",
                "interval" => FORUM_REFRESH,
            ]);
        }
        $content .= "</div>";
    } else { //This is a SHOUTBOX
        $content = '<div id="forum_div_' . $forumid . '">';
        if (!user_is_able($USER->userid, "viewshoutbox", $pageid)) {
            $content .= '<span class="centered_span">' . error_string("generic_permissions") . '</span>';
        } else {
            $content .= get_shoutbox($forumid);
            $scriptid = "get_shoutbox_$forumid";
            ajaxapi([
                "id" => $scriptid,
                "url" => "/features/forum/forum_ajax.php",
                "data" => [
                    "action" => "get_shoutbox_ajax",
                    "forumid" => $forumid,
                ],
                "display" => "forum_div_$forumid",
                "event" => "none",
                "intervalid" => "forum_$forumid",
                "interval" => FORUM_REFRESH,
            ]);
        }
        $content .= "</div>";
    }

    // Execute Refresh Script
    if ($scriptid) {
        $content .= js_code_wrap(preg_replace('/\s+/S', " ", "$scriptid();"));
    }

    $buttons = is_logged_in() ? get_button_layout("forum", $forumid, $pageid) : "";
    return get_css_box($title, $content, $buttons, "0px", "forum", $forumid);
}

function get_discussions($catid, $dpagenum = 0) {
global $CFG, $USER;
    $category = get_db_row(fetch_template("dbsql/forum.sql", "get_category", "forum"), ["catid" => $catid]);
    $forumid = $category["forumid"];
    $pageid = $category["pageid"];

    date_default_timezone_set(date_default_timezone_get());

    // Get forum settings.
    $settings = fetch_settings("forum", $forumid, $pageid);

    // Discussion pagenum should either be a positive number or "last"
    $dpagenum = !is_numeric($dpagenum) && $dpagenum !== "last" ? 0 : $dpagenum; // is numeric or last
    $dpagenum = is_numeric($dpagenum) && $dpagenum < 0 ? 0 : $dpagenum; // is more than 0 or last

    $SQL = fetch_template("dbsql/forum.sql", "get_category_discussions", "forum");
    $sqlparams = ["catid" => $catid, "bulletin" => 0, "perpage" => $settings->forum->$forumid->discussionsperpage->setting];
    $discussioncount = get_db_count($SQL, $sqlparams);
    $dpagenum = $dpagenum == "last" ? (int)(ceil($discussioncount / $sqlparams["perpage"]) - 1) : $dpagenum; // if last, set to last page int

    // Add limits onto query.
    $SQL .= ' LIMIT ||limit||, ||perpage||';
    $sqlparams["limit"] = $sqlparams["perpage"] * $dpagenum;

    // Pagenum problem...aka deleted last post on page...go to previous page.
    while ($dpagenum >= 0 && !$discussions = get_db_result($SQL, $sqlparams)) {
        $sqlparams["limit"] = $sqlparams["perpage"] * $dpagenum;
        $dpagenum--;
    }

    ajaxapi([
        "id" => "get_categories_$forumid",
        "url" => "/features/forum/forum_ajax.php",
        "data" => ["action" => "get_forum_categories_ajax", "forumid" => $forumid],
        "display" => "forum_div_$forumid",
        "intervalid" => "forum_$forumid",
        "interval" => FORUM_REFRESH,
    ]);
    $returnme = fill_template("tmp/forum.template", "forum_breadcrumb", "forum", ["forumid" => $forumid, "title" => $category["title"]]);

    // Create Discussion Link
    $returnme .= make_discussion_link($pageid, $forumid, $catid);

    // Get Discussion Pages
    $returnme .= get_discussion_pages($forumid, $category, $dpagenum);

    // GET BULLETIN BOARDS
    if ($bulletins = get_db_result(fetch_template("dbsql/forum.sql", "get_category_discussions", "forum"), ["catid" => $catid, "bulletin" => 1])) {
        $returnme .= get_discussions_list($bulletins, "Bulletin", "forum_bulletins");
    }

    // GET DISCUSSIONS
    $returnme .= get_discussions_list($discussions);

    return $returnme;
}

function get_discussions_list($discussions, $title = "Discussion", $classprefix = "forum_discussions") {
    $returnme = '
        <table class="' . $classprefix . '_header">
            <tr>
                <th class="forum_headers">
                    <strong>' . $title . 's</strong>
                </th>
                <th class="forum_headers" style="width:50px;">
                    Replies
                </th>
                <th class="forum_headers" style="width:50px;">
                    Views
                </th>
                <th  class="forum_headers" style="width:150px;">
                    Last Posted
                </th>
            </tr>';
    if (!$discussions) { return $returnme . '<tr><td colspan="4" class="' . $classprefix . '" style="text-align:center;">No ' . $title . 's</td></tr></table>'; }

    while ($discussion = fetch_row($discussions)) {
        $posts_count = get_db_count("SELECT * FROM forum_posts WHERE discussionid = ||discussionid||", ["discussionid" => $discussion["discussionid"]]) - 1;
        $lastpost = get_db_row("SELECT * FROM forum_posts WHERE discussionid = ||discussionid|| ORDER BY posted DESC LIMIT 1", ["discussionid" => $discussion["discussionid"]]);
        $notviewed = true;
        $forumid = $discussion['forumid'];
        // Find if new posts are available
        if (is_logged_in()) {
            if (!$lastviewed = get_db_field("lastviewed", "forum_views", "discussionid = ||discussionid|| ORDER BY lastviewed DESC", ["discussionid" => $discussion["discussionid"]])) { $lastviewed = 0;}
            $notviewed =  $lastpost["posted"] > $lastviewed ? true : false;
        }
        $viewclass = $notviewed ? 'forum_notviewed' : '';
        $lock = $discussion["locked"] == 1 ? icon('lock') . '&nbsp;&nbsp;' : '';
        ajaxapi([
            "id" => "get_discussion_posts_" . $discussion['discussionid'],
            "url" => "/features/forum/forum_ajax.php",
            "data" => ["action" => "get_forum_posts", "discussionid" => $discussion['discussionid']],
            "display" => "forum_div_$forumid",
            "intervalid" => "forum_$forumid",
            "interval" => FORUM_REFRESH,
        ]);
        $returnme .= '
            <tr>
                <td class="col_' . $classprefix . '">
                    <div class="' . $classprefix . '">
                        <div class="' . $viewclass . '">
                            ' . $lock . '
                            <button id="get_discussion_posts_' . $discussion['discussionid'] . '" class="alike">
                            ' . $discussion["title"] . '
                            </button>
                        </div>
                        ' . get_discussion_buttons($discussion) . '
                    </div>
                    ' . get_post_pages($forumid, $discussion, false, 10, false) . '
            </td>
            <td class="forum_postscol col_' . $classprefix . '">
                ' . $posts_count . '
            </td>
            <td class="forum_viewscol col_' . $classprefix . '">
                ' . $discussion["views"] . '
            </td>
            <td class="forum_postedcol col_' . $classprefix . '">
                ' . ago($lastpost["posted"], true) . '
                <br />
                ' . get_user_name($lastpost["userid"]) . '
            </td>
        </tr>';
    }

    $returnme .= "</table>";
    return $returnme;
}

function get_discussion_buttons($discussion, $pagenum = 0) {
global $CFG, $USER;
    $discussionid = $discussion['discussionid'];
    $pageid = $discussion['pageid'];
    $forumid = $discussion['forumid'];

    $buttons = '';
    // PIN BULLETIN
    if ($discussion["bulletin"] == 0 && user_is_able($USER->userid, "designateforumbulletin", $pageid)) {
        ajaxapi([
            "id" => "unpin_bulletin_$discussionid",
            "url" => "/features/forum/forum_ajax.php",
            "data" => ["action" => "pin_bulletin", "discussionid" => $discussionid, "dpagenum" => $pagenum],
            "display" => "forum_div_$forumid",
        ]);
        $buttons .= '
            <button id="unpin_bulletin_' . $discussionid . '" class="alike" title="Designate as Bulletin">
                ' . icon("thumbtack") . '
            </button>';
    }

    // UNPIN BULLETIN
    if ($discussion["bulletin"] == 1 && user_is_able($USER->userid, "designateforumbulletin", $pageid)) {
        ajaxapi([
            "id" => "unpin_bulletin_$discussionid",
            "url" => "/features/forum/forum_ajax.php",
            "data" => ["action" => "unpin_bulletin", "discussionid" => $discussionid, "dpagenum" => $pagenum],
            "display" => "forum_div_$forumid",
        ]);
        $buttons .= '
            <button id="unpin_bulletin_' . $discussionid . '" class="alike" title="Undesignate as Bulletin">
                ' . icon("thumbtack", 1, "", "", "rotate--180") . '
            </button>';
    }

    // LOCK/UNLOCK DISCUSSION
    if (user_is_able($USER->userid, "lockdiscussion", $pageid)) {
        if ($discussion["locked"] == 1) {
            ajaxapi([
                "id" => "unlock_discussion_$discussionid",
                "if" => "confirm('Are you sure you wish to unlock this bulletin?')",
                "url" => "/features/forum/forum_ajax.php",
                "data" => ["action" => "unlock_discussion", "discussionid" => $discussionid, "dpagenum" => $pagenum],
                "display" => "forum_div_$forumid",
            ]);
            $buttons .= '
            <button id="unlock_discussion_' . $discussionid . '" class="alike" title="Unlock Discussion">
                ' . icon("lock-open") . '
            </button>';
        } else {
            ajaxapi([
                "id" => "lock_discussion_$discussionid",
                "if" => "confirm('Are you sure you wish to lock this bulletin?')",
                "url" => "/features/forum/forum_ajax.php",
                "data" => ["action" => "lock_discussion", "discussionid" => $discussionid, "dpagenum" => $pagenum],
                "display" => "forum_div_$forumid",
            ]);
            $buttons .= '
                <button id="lock_discussion_' . $discussionid . '" class="alike" title="Lock Discussion">
                    ' . icon("lock") . '
                </button>';
        }
    }

    // DELETE DISCUSSION
    if (user_is_able($USER->userid, "deleteforumdiscussion", $pageid)) {
        ajaxapi([
            "id" => "delete_discussion_" . $discussionid,
            "if" => "confirm('Are you sure you wish to delete this discussion?\\nThis will also delete all posts inside this discussion.')",
            "url" => "/features/forum/forum_ajax.php",
            "data" => ["action" => "delete_discussion", "discussionid" => $discussionid, "dpagenum" => $pagenum],
            "display" => "forum_div_$forumid",
        ]);
        $buttons .= '
            <button id="delete_discussion_' . $discussionid . '" class="alike" title="Delete Discussion">
                ' . icon("trash-can") . '
            </button>';
    }

    // EDIT DISCUSSION
    if (user_is_able($USER->userid, "editforumcategory", $pageid)) {
        $editlinkparams = [
            "title" => "Edit Discussion",
            "path" => action_path("forum") . "edit_discussion_form&discussionid=$discussionid",
            "width" => "750",
            "height" => "600",
            "iframe" => true,
            "onExit" => "getIntervals()['forum_$forumid'].script();",
            "class" => "alike",
            "icon" => icon("pencil"),
        ];
        $buttons .= make_modal_links($editlinkparams);
    }

    if (!empty($buttons)) {
        $class = $discussion["bulletin"] == 1 ? "forum_bulletins_buttons" : "forum_discussions_buttons";
        $buttons = '<span class="' . $class . '">' . $buttons . '</span>';
    }
    return $buttons;
}

function get_posts($discussionid, $pagenum = 0) {
global $CFG, $USER;
    $discussion = get_db_row(fetch_template("dbsql/forum.sql", "get_discussion", "forum"), ["discussionid" => $discussionid]);

    $catid = $discussion["catid"];
    $forumid = $discussion["forumid"];
    $pageid = $discussion["pageid"];

    if (is_logged_in()) {
        update_user_views($catid, $discussionid, $USER->userid);
    }

    $settings = fetch_settings("forum", $forumid, $pageid);
    $postcount = get_db_count(fetch_template("dbsql/forum.sql", "get_discussion_posts", "forum"), ["discussionid" => $discussionid]);

    if ($pagenum !== "last" && !is_numeric($pagenum)) {
        $pagenum = 0;
    }

    $pagenum = $pagenum == "last" ? (ceil($postcount / $settings->forum->$forumid->postsperpage->setting) - 1): $pagenum;

    //Add to the discussion view field
    execute_db_sql(fetch_template("dbsql/forum.sql", "update_discussion_views", "forum"), ["discussionid" => $discussionid]);

    $limit = $settings->forum->$forumid->postsperpage->setting * $pagenum;
    $SQL = fetch_template("dbsql/forum.sql", "get_discussion_posts", "forum") . ' ORDER BY posted LIMIT ||limit||,||perpage||';
    while ($pagenum >= 0 && !$posts = get_db_result($SQL, ["discussionid" => $discussionid, "limit" => $limit, "perpage" => $settings->forum->$forumid->postsperpage->setting])) { // Pagenum problem...aka deleted last post on page...go to previous page.
        $pagenum--;
        $limit = $settings->forum->$forumid->postsperpage->setting * $pagenum;
    }

    ajaxapi([
        "id" => "get_categories_$forumid",
        "url" => "/features/forum/forum_ajax.php",
        "data" => ["action" => "get_forum_categories_ajax", "forumid" => $forumid],
        "display" => "forum_div_$forumid",
        "intervalid" => "forum_$forumid",
        "interval" => FORUM_REFRESH,
    ]);

    ajaxapi([
        "id" => "get_forum_discussions_$forumid",
        "url" => "/features/forum/forum_ajax.php",
        "data" => ["action" => "get_forum_discussions", "catid" => $catid],
        "display" => "forum_div_$forumid",
        "intervalid" => "forum_$forumid",
        "interval" => FORUM_REFRESH,
    ]);

    $content = "";
    $can_reply = user_is_able($USER->userid, "forumreply", $pageid);

    if ($posts) {
        $can_edit = user_is_able($USER->userid, "editforumposts", $pageid);
        if ($can_delete = user_is_able($USER->userid, "deleteforumpost", $pageid)) {
            // DELETE POST
            ajaxapi([
                "id" => 'delete_post',
                "if" => "confirm('Are you sure you want to delete this post?')",
                "paramlist" => "postid",
                "url" => "/features/forum/forum_ajax.php",
                "data" => [
                    "action" => "delete_post",
                    "postid" => "js||postid||js",
                    "pagenum" => $pagenum,
                ],
                "display" => "forum_div_$forumid",
                "event" => "none",
            ]);
        }

        while ($post = fetch_row($posts)) {
            $quote = $reply = $edit = $delete = $edited = false;
            // QUOTE
            if (!$discussion["locked"] && $can_reply) {
                $params = [
                    "title" => "Quote",
                    "path" => action_path("forum") . "quote_post_form&quotepost=" . $post["postid"],
                    "width" => "750",
                    "height" => "600",
                    "iframe" => true,
                    "onExit" => "getIntervals()['forum_$forumid'].script();",
                ];
                $quote = make_modal_links($params);
            }

            if ($post["edited"]) {
                $edited = true;
            }

            // EDIT POST
            if (!$discussion["locked"] && ($can_edit || $USER->userid == $post["userid"])) {
                $params = [
                    "title" => "Edit",
                    "path" => action_path("forum") . "edit_post_form&postid=" . $post["postid"],
                    "width" => "750",
                    "height" => "600",
                    "iframe" => true,
                    "onExit" => "getIntervals()['forum_$forumid'].script();",
                ];
                $edit = make_modal_links($params);
            }


            if (!$discussion["locked"] && $can_delete) {
                $delete = true;
            }

            // REPLY
            if (!$discussion["locked"] && $can_reply) {
                $params = [
                    "title" => "Reply",
                    "path" => action_path("forum") . "post_form&replyto=" . $post["postid"],
                    "width" => "750",
                    "height" => "600",
                    "iframe" => true,
                    "onExit" => "getIntervals()['forum_$forumid'].script();",
                ];
                $reply = make_modal_links($params);
            }

            $params = [
                "forumpost" => $post,
                "quote" => $quote,
                "reply" => $reply,
                "edit" => $edit,
                "delete" => $delete,
                "edited" => $edited,
                "postcount" => get_db_count("SELECT * FROM forum_posts WHERE userid=" . $post["userid"]),
            ];
            $content .= fill_template("tmp/forum.template", "forum_post", "forum", $params);
        }
    } else {
        $postlink = false;
        if (!$discussion["locked"] && $can_reply) {
            $params = [
                "title" => "Post",
                "path" => action_path("forum") . "post_form&discussionid=$discussionid",
                "width" => "750",
                "height" => "600",
                "iframe" => true,
                "onExit" => "getIntervals()['forum_$forumid'].script();",
            ];
            $postlink = make_modal_links($params);
        }
        $content = fill_template("tmp/forum.template", "no_forum_post", "forum", ["postlink" => $postlink]);
    }

    // Create Discussion Link
    $discussionlink = make_discussion_link($pageid, $forumid, $catid);

    // Get Post Pages
    $postspage = get_post_pages($forumid, $discussion, $pagenum);

    $params = [
        "forumid" => $forumid,
        "cattitle" => get_db_field("title", "forum_categories", "catid = ||catid||", ["catid" => $catid]),
        "distitle" => get_db_field("title", "forum_discussions", "discussionid = ||discussionid||", ["discussionid" => $discussionid]),
        "wwwroot" => $CFG->wwwroot,
        "postspage" => $postspage,
        "discussionlink" => $discussionlink,
        "content" => $content,
    ];
    return fill_template("tmp/forum.template", "forum_template", "forum", $params);
}

function get_forum_categories($forumid) {
global $USER, $CFG;
    $returnme = '<table class="forum_category">
                    <tr>
                        <th class="forum_headers">
                            Category Name
                        </th>
                        <th class="forum_headers" style="width:70px;">
                            Discussions
                        </th>
                        <th  class="forum_headers" style="width:70px;">
                            Posts
                        </th>
                    </tr>';
    $content = "";
    if ($categories = get_db_result(fetch_template("dbsql/forum.sql", "get_forum_categories", "forum"), ["forumid" => $forumid])) {
        while ($category = fetch_row($categories)) {
            $notviewed = true;
            //Find if new posts are available
            if (is_logged_in()) {
                $SQL = 'SELECT *
                        FROM forum_posts f
                        WHERE f.catid = "' . $category["catid"] . '"
                        AND (
                            discussionid IN (
                                SELECT a.discussionid
                                FROM forum_discussions a
                                INNER JOIN forum_views b ON a.discussionid = b.discussionid
                                WHERE b.userid = "' . $USER->userid . '"
                                AND a.lastpost > b.lastviewed
                            )
                            OR discussionid NOT IN (
                                SELECT discussionid
                                FROM forum_views
                                WHERE catid = "' . $category["catid"] . '"
                                AND userid = "' . $USER->userid . '"
                            )
                        )';
                $notviewed = $newposts = get_db_result($SQL) ? true : false;
            }
            $discussion_count = get_db_count("SELECT * FROM forum_discussions WHERE catid=" . $category["catid"] . " AND shoutbox=0");
            $posts_count = get_db_count("SELECT * FROM forum_posts WHERE catid=" . $category["catid"]);
            $viewclass = $notviewed ? 'forum_col1' : 'forum_col1_viewed';
            ajaxapi([
                "id" => "get_forum_discussions_" . $category['catid'],
                "url" => "/features/forum/forum_ajax.php",
                "data" => ["action" => "get_forum_discussions", "catid" => $category['catid']],
                "display" => "forum_div_$forumid",
                "intervalid" => "forum_$forumid",
                "interval" => FORUM_REFRESH,
            ]);
            $content .= '
                <tr>
                    <td class="' . $viewclass . '">
                        <button id="get_forum_discussions_' . $category['catid'] . '" class="alike" title="Get Forum Discussions">
                            ' . $category["title"] . '
                        </button>';
            $edit = user_is_able($USER->userid, "editforumcategory", $category['pageid']);
            $content .= '<span class="forum_inline_buttons">';

            if ($edit) {
                if ($category["sort"] > 1) {
                    ajaxapi([
                        "id" => "move_category_up_" . $category['catid'],
                        "url" => "/features/forum/forum_ajax.php",
                        "data" => ["action" => "move_category", "catid" => $category['catid'], "direction" => "up"],
                        "display" => "forum_div_$forumid",
                    ]);
                    $content .= '
                        <button id="move_category_up_' . $category['catid'] . '" class="alike" title="Move Up">
                            ' . icon("arrow-up") . '
                        </button>';
                }
                if ($category["sort"] != get_db_field("MAX(sort)", "forum_categories", "forumid = '$forumid' AND shoutbox = '0'")) {
                    ajaxapi([
                        "id" => "move_category_down_" . $category['catid'],
                        "url" => "/features/forum/forum_ajax.php",
                        "data" => ["action" => "move_category", "catid" => $category['catid'], "direction" => "down"],
                        "display" => "forum_div_$forumid",
                    ]);
                    $content .= '
                        <button id="move_category_down_' . $category['catid'] . '" class="alike" title="Move Down">
                            ' . icon("arrow-down") . '
                        </button>';
                }
            }

            if (user_is_able($USER->userid, "deleteforumcategory", $category['pageid'])) {
                ajaxapi([
                    "id" => "delete_category_" . $category['catid'],
                    "if" => "confirm('Are you sure you wish to delete this category?\\nThis will delete all discussions and posts inside this category.')",
                    "url" => "/features/forum/forum_ajax.php",
                    "data" => ["action" => "delete_category", "catid" => $category['catid']],
                    "display" => "forum_div_$forumid",
                ]);
                $content .= '
                    <button id="delete_category_' . $category['catid'] . '" class="alike" title="Delete Category">
                        ' . icon("trash") . '
                    </button>';
            }

            if ($edit) {
                $params = [
                    "title" => "Edit Category",
                    "path" => action_path("forum") . "editcategory&catid=" . $category['catid'],
                    "onExit" => "getIntervals()['forum_$forumid'].script();",
                    "width" => "500",
                    "validate" => "true",
                    "icon" => icon("pencil"),
                ];
                $content .= make_modal_links($params);
            }

            $content .= '</span>
                    </td>
                    <td class="forum_postscol">
                    ' . $discussion_count . '
                    </td>
                    <td class="forum_viewscol">
                    ' . ($posts_count-$discussion_count) . '
                    </td>
                </tr>';
        }
    }
    $returnme .= $content == "" ? '<tr><td colspan="3" class="forum_col1">No Categories Created.</td></tr>' : $content;
    $returnme .= "</table>";
    return $returnme;
}

function update_user_views($catid, $discussionid, $userid) {
global $CFG;
    $time = get_timestamp();
    if (!get_db_row("SELECT * FROM forum_views WHERE userid='$userid' AND discussionid='$discussionid'")) {
         execute_db_sql("INSERT INTO forum_views (userid,catid,discussionid,lastviewed) VALUES('$userid','$catid','$discussionid','$time')");
    } else {
         execute_db_sql("UPDATE forum_views SET lastviewed='$time' WHERE userid='$userid' AND discussionid='$discussionid'");
    }
}

function update_discussion_lastpost($discussionid) {
    // Update Discussion.update_discussion_lastpost.
    $params = [
        "discussionid" => $discussionid,
        "lastpost" => get_timestamp(),
    ];
    execute_db_sql(fetch_template("dbsql/forum.sql", "update_discussion_lastpost", "forum"), $params);
}

function make_discussion_link($pageid, $forumid, $catid) {
global $USER, $CFG;
    if (!user_is_able($USER->userid, "createforumdiscussion", $pageid)) {
        return "";
    }

    $discussionbutton = make_modal_links([
        "button" => true,
        "title" => "New Discussion",
        "text" => '<img src="' . $CFG->wwwroot . '/images/discussion.gif" alt=""> New Discussion',
        "path" => action_path("forum") . "create_discussion_form&catid=$catid",
        "width" => "750",
        "iframe" => true,
        "onExit" => "getIntervals()['forum_$forumid'].script();",
    ]);
    return '
        <div class="forum_newbutton">
            ' . $discussionbutton . '
        </div>';
}

function get_shoutbox($forumid) {
global $USER, $CFG;
    date_default_timezone_set(date_default_timezone_get());
    $pageid = get_db_field("pageid", "forum", "forumid = '$forumid'");
    $settings = fetch_settings("forum", $forumid, $pageid);
    $shoutboxlimit = isset($settings->forum->$forumid->shoutboxlimit->setting) ? " LIMIT " . $settings->forum->$forumid->shoutboxlimit->setting : "";
    $userid = is_logged_in() ? $USER->userid : "";

    $shoutboxid = get_db_field("discussionid", "forum_discussions", "forumid=$forumid AND shoutbox=1");
    $shouts = "";
    if ($posts = get_db_result("SELECT * FROM forum_posts WHERE discussionid=$shoutboxid ORDER BY posted DESC $shoutboxlimit")) {
        while ($post = fetch_row($posts)) {
            $params = [
                "message" => strip_tags($post["message"], "<img><a>"),
                "alias" => $post["userid"] != 0 ? get_user_name($post["userid"]): $post["alias"],
                "posted" => ago($post["posted"], true),
            ];
            $shouts .= fill_template("tmp/forum.template", "shoutbox_posts", "forum", $params);
        }
    }

    $params = [
        "tab" => make_modal_links([
                    "title" => "Shout",
                    "path" => action_path("forum") . "shoutbox_editor&userid=$userid&forumid=$forumid",
                    "width" => "600",
                    "height" => "600",
                    "iframe" => true,
                    "onExit" => "getIntervals()['forum_$forumid'].script();",
                ]),
        "shouts" => $shouts,
    ];
    $returnme = fill_template("tmp/forum.template", "shoutbox", "forum", $params);

    return $returnme;
}

function get_post_pages($forumid, $discussion, $pagenum = false, $beforeskipping = 10, $buttons = true) {
global $CFG;
    $discussionid = $discussion["discussionid"];
    $catid = $discussion["catid"];
    $pageid = $discussion["pageid"];

    // No posts, so we don't need page links.
    if (!$post_count = get_db_count(fetch_template("dbsql/forum.sql", "get_discussion_posts", "forum"), ["discussionid" => $discussionid])) {
        return "";
    }

    if ($pagenum !== false) {
        $pagenum = clean_var_opt($pagenum, "int", 0);
    }

    $settings = fetch_settings("forum", $forumid, $pageid);
    $perpage = $settings->forum->$forumid->postsperpage->setting;

    // Only 1 page of posts, so we don't need page links.
    if ($post_count <= $perpage) {
        return "";
    }

    // Lastpage is just like $pagenum.  pages start at 0.
    $lastpage = (int)(ceil($post_count / $perpage) - 1);

    $previous = "";
    $next = "";

    if ($buttons) { // Pagenum isn't 0, so there's a previous page.
        $disabled = "disabled";
        if ($pagenum !== false && $pagenum !== 0) {
            ajaxapi([
                "id" => "get_forum_posts_previous_$forumid",
                "url" => "/features/forum/forum_ajax.php",
                "data" => ["action" => "get_forum_posts", "discussionid" => $discussionid, "pagenum" => $pagenum - 1],
                "display" => "forum_div_$forumid",
                "intervalid" => "forum_$forumid",
                "interval" => FORUM_REFRESH,
            ]);
            $disabled = "";
        }

        $previous = '
        <button id="get_forum_posts_previous_' . $forumid . '" class="pagelistbutton alike ' . $disabled . '" title="Get previous page of forum posts">
            ' . icon("backward-step") . '
        </button>';
    }

    if ($buttons) { // Pagenum < lastpage (the largest possible pagenum), so there's a next page.
        $disabled = "disabled";

        if ($pagenum !== false && $pagenum !== $lastpage) {
            ajaxapi([
                "id" => "get_forum_posts_next_$forumid",
                "url" => "/features/forum/forum_ajax.php",
                "data" => ["action" => "get_forum_posts", "discussionid" => $discussionid, "pagenum" => $pagenum + 1],
                "display" => "forum_div_$forumid",
                "intervalid" => "forum_$forumid",
                "interval" => FORUM_REFRESH,
            ]);
            $disabled = "";
        }

        $next = '
        <button id="get_forum_posts_next_' . $forumid . '" class="pagelistbutton alike ' . $disabled . '" title="Get next page of forum posts">
            ' . icon("forward-step") . '
        </button>';
    }

    $page_counter = 0;
    $pagelinks = "";
    while ($post_count > 0) { // We are going to make a link for every $perpage worth of $post_count.
        $page_counter++; // starts at 1.  So page_count 1 is the same page as pagenum 0.
        if ($page_counter > $beforeskipping) { // if $beforeskipping is 5,Page links will go Ex. (1, 2, 3, 4, 5, Last)
            if ($pagenum === false || $pagenum < $lastpage) { // Not currently on last page, so the Last button will have an action.
                ajaxapi([
                    "id" => "get_forum_posts_last_$forumid",
                    "url" => "/features/forum/forum_ajax.php",
                    "data" => ["action" => "get_forum_posts", "discussionid" => $discussionid, "pagenum" => $lastpage],
                    "display" => "forum_div_$forumid",
                    "intervalid" => "forum_$forumid",
                    "interval" => FORUM_REFRESH,
                ]);
            }
            $pagelinks .= '
                <button id="get_forum_posts_last_' . $forumid . '" class="pagelistbutton alike" title="Get last page of forum posts">
                    ' . icon("forward-fast") . '
                </button>';
            $post_count = 0; // Subtract the rest of $post_count to end loop since the Last page link was created.
        } else {
            $current = "disabled";
            if ($pagenum === false || $pagenum !== ($page_counter - 1)) { // Not on currently viewing page of posts. Add page action.
                $current = "";
                ajaxapi([
                    "id" => "get_forum_posts_page_$forumid" . "_$page_counter",
                    "url" => "/features/forum/forum_ajax.php",
                    "data" => ["action" => "get_forum_posts", "discussionid" => $discussionid, "pagenum" => ($page_counter - 1)],
                    "display" => "forum_div_$forumid",
                    "intervalid" => "forum_$forumid",
                    "interval" => FORUM_REFRESH,
                ]);
            }
            $pagelinks .= '
                <button id="get_forum_posts_page_' . $forumid . "_$page_counter" . '" class="pagelistbutton alike ' . $current . '" title="Get page ' . $page_counter . ' of forum posts">
                    ' . $page_counter . '
                </button>';
            $post_count -= $perpage; // Subtract the page worth of $post_count.
        }
    }

    return '<div class="forum_page_links">Page: ' . $previous . $pagelinks . $next . '</div>';
}

function get_discussion_pages($forumid, $category, $pagenum, $beforeskipping = 20, $buttons = true) {
global $CFG;
    $catid = $category['catid'];
    $pageid = $category['pageid'];
    $settings = fetch_settings("forum", $forumid, $pageid);
    $perpage = $settings->forum->$forumid->discussionsperpage->setting;

    $pagenum = clean_var_opt($pagenum, "int", 0);
    $previous = "";
    $next = "";
    $discussion_count = get_db_count(fetch_template("dbsql/forum.sql", "get_category_discussions", "forum"), ["catid" => $catid, "bulletin" => 0]);
    if ($discussion_count) {
        $page_counter = 1;
        $lastpage = (int)(ceil($discussion_count / $perpage) - 1);
        $params = [
            "forumid" => $forumid,
            "catid" => $catid,
            "pageid" => $pageid,
        ];

        $disabled = "disabled";
        if ($buttons && $pagenum > 0) {
            ajaxapi([
                "id" => "get_forum_discussions_previous_$forumid",
                "url" => "/features/forum/forum_ajax.php",
                "data" => ["action" => "get_forum_discussions", "catid" => $catid, "dpagenum" => $pagenum - 1],
                "display" => "forum_div_$forumid",
                "intervalid" => "forum_$forumid",
                "interval" => FORUM_REFRESH,
            ]);
            $disabled = "";
        }
        $previous = '
        <button id="get_forum_discussions_previous_' . $forumid . '" class="pagelistbutton alike ' . $disabled . '" title="Get previous page of discussions">
           ' . icon("backward-step") . '
        </button>';

        $disabled = "disabled";
        if ($buttons && $pagenum < $lastpage) {
            ajaxapi([
                "id" => "get_forum_discussions_next_$forumid",
                "url" => "/features/forum/forum_ajax.php",
                "data" => ["action" => "get_forum_discussions", "catid" => $catid, "dpagenum" => $pagenum + 1],
                "display" => "forum_div_$forumid",
                "intervalid" => "forum_$forumid",
                "interval" => FORUM_REFRESH,
            ]);
            $disabled = "";
        }
        $next = '
        <button id="get_forum_discussions_next_' . $forumid . '" class="pagelistbutton alike ' . $disabled . '" title="Get next page of discussions">
            ' . icon("forward-step") . '
        </button>';

        // Wittle down the discussions until we have no discussions left to create page links.
        $pagelinks = "";
        while ($discussion_count > 0) {
            // At some point we will not show page links and just allow a jump to last page.
            if ($page_counter > $beforeskipping) {
                if ($pagenum !== $lastpage) {
                    ajaxapi([
                        "id" => "get_forum_discussions_last_$forumid",
                        "url" => "/features/forum/forum_ajax.php",
                        "data" => ["action" => "get_forum_discussions", "catid" => $catid, "dpagenum" => $lastpage],
                        "display" => "forum_div_$forumid",
                        "intervalid" => "forum_$forumid",
                        "interval" => FORUM_REFRESH,
                    ]);
                }
                $pagelinks .= '
                    <button id="get_forum_discussions_last_' . $forumid . '" class="pagelistbutton alike" title="Get last page of discussions">
                        ' . icon("forward-fast") . '
                    </button>';
                $discussion_count = 0; // Remove the rest of the discussions from counter.
            } else {
                $currentpage = "disabled";
                if ($pagenum !== ($page_counter - 1)) {
                    $currentpage = "";
                    ajaxapi([
                        "id" => "get_forum_discussions_$forumid" . "_$page_counter",
                        "url" => "/features/forum/forum_ajax.php",
                        "data" => ["action" => "get_forum_discussions", "catid" => $catid, "dpagenum" => $page_counter - 1],
                        "display" => "forum_div_$forumid",
                        "intervalid" => "forum_$forumid",
                        "interval" => FORUM_REFRESH,
                    ]);
                }
                $pagelinks .= '
                    <button id="get_forum_discussions_' . $forumid .  "_$page_counter" . '" class="pagelistbutton alike ' . $currentpage . '" title="Get page ' . $page_counter . ' of discussions">
                        ' . $page_counter . '
                    </button>';
                $discussion_count -= $perpage; // Remove a page worth of discussions from counter.
            }
            $page_counter++;
        }

        if ($page_counter == 2) { // Only one page, so we don't need a page number link.
            return "";
        }

        return '<div class="forum_page_links">Page: ' . $previous . $pagelinks . $next . '</div>';

    } else {
        return "";
    }
}

function first_post($discussionid) {
    $postid = get_db_field("MIN(postid)", "forum_posts", "discussionid = ||discussionid||", ["discussionid" => $discussionid]);
    return $postid;
}

function resort_categories($forumid) {
    if ($result = get_db_result(fetch_template("dbsql/forum.sql", "get_forum_categories", "forum"), ["forumid" => $forumid])) {
        $i = 1;
        while ($row = fetch_row($result)) {
            execute_db_sql(fetch_template("dbsql/forum.sql", "set_category_sort", "forum"), ["sort" => $i, "catid" => $row['catid']]);
            $i++;
        }
    }
}

function insert_blank_forum($pageid) {
    $type = "forum";
    try {
        start_db_transaction();
        if ($featureid = execute_db_sql(fetch_template("dbsql/forum.sql", "insert_forum", $type), ["pageid" => $pageid])) {
            $area = get_db_field("default_area", "features", "feature = ||feature||", ["feature" => $type]);
            $sort = get_db_count(fetch_template("dbsql/features.sql", "get_features_by_page_area"), ["pageid" => $pageid, "area" => $area]) + 1;
            $params = [
                "pageid" => $pageid,
                "feature" => $type,
                "featureid" => $featureid,
                "forumid" => $featureid,
                "sort" => $sort,
                "area" => $area,
                "lastpost" => 0,
            ];
            execute_db_sql(fetch_template("dbsql/features.sql", "insert_page_feature"), $params);

            // Every forum gets a shoutbox category.
            $catid = execute_db_sql(fetch_template("dbsql/forum.sql", "insert_category", $type), ["forumid" => $featureid, "pageid" => $pageid, "title" => "Shoutbox", "sort" => 0, "shoutbox" => 1]);

            $params["catid"] = $catid;
            execute_db_sql(fetch_template("dbsql/forum.sql", "insert_discussion", $type), $params);
            commit_db_transaction();
            return $featureid;
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
    return false;
}

function forum_delete($pageid, $featureid) {
    try {
        start_db_transaction();
        $sql = [];
        $sql[] = ["file" => "dbsql/forum.sql", "feature" => "forum", "subsection" => "delete_forum"];
        $sql[] = ["file" => "dbsql/forum.sql", "feature" => "forum", "subsection" => "delete_categories"];
        $sql[] = ["file" => "dbsql/forum.sql", "feature" => "forum", "subsection" => "delete_discussions"];
        $sql[] = ["file" => "dbsql/forum.sql", "feature" => "forum", "subsection" => "delete_posts"];

        execute_db_sqls(fetch_template_set($sql), ["forumid" => $featureid]);

        $sql = [];
        $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature"];
        $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature_settings"];

        // Delete feature
        execute_db_sqls(fetch_template_set($sql), ["featureid" => $featureid, "feature" => "forum", "pageid" => $pageid]);

        resort_page_features($pageid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        return false;
    }
}

function forum_buttons($pageid, $featuretype, $forumid) {
global $CFG, $USER;
    $returnme = "";
    if (user_is_able($USER->userid, "createforumcategory", $pageid)) {
        $returnme .= make_modal_links([
            "title" => "Create Forum Category",
            "path" => action_path("forum") . "createcategory&forumid=$forumid",
            "width" => "500",
            "validate" => "true",
            "onExit" => "getIntervals()['forum_$forumid'].script();",
            "icon" => icon("plus"),
            "class" => "slide_menu_button",
        ]);
    }
    return $returnme;
}

function forum_default_settings($type, $pageid, $featureid) {
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "Forum",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "setting_name" => "discussionsperpage",
            "defaultsetting" => "10",
            "display" => "Discussions Per Page",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
        [
            "setting_name" => "postsperpage",
            "defaultsetting" => "10",
            "display" => "Posts Per Page",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
        [
            "setting_name" => "shoutboxlimit",
            "defaultsetting" => "10",
            "display" => "Shoutbox Posts Shown",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}
?>
