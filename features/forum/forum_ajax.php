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
global $CFG, $MYVARS;
	$forumid = $MYVARS->GET["forumid"];
	echo get_forum_categories($forumid);	
}

function get_shoutbox_ajax() {
global $CFG, $MYVARS;
	$forumid = $MYVARS->GET["forumid"];
	echo get_shoutbox($forumid);	
}

function get_forum_discussions() {
global $CFG, $MYVARS, $USER;
	$catid = $MYVARS->GET["catid"];
    $forumid = $MYVARS->GET["forumid"];
	$pageid = $MYVARS->GET["pageid"];
	$dpagenum = $MYVARS->GET["dpagenum"] ?? 0;

    if ($dpagenum !== "last" && !is_numeric($dpagenum)) {
        $dpagenum = 0;
    }

    $dpagenum = $dpagenum < 0 ? false : $dpagenum;
	$dpagenum2 = $dpagenum ?? false;

	$settings = fetch_settings("forum", $forumid, $pageid);
	
	$content = "";
	date_default_timezone_set(date_default_timezone_get());
	$category = get_db_row("SELECT * FROM forum_categories WHERE catid = '$catid'");
	$discussioncount = get_db_count("SELECT * FROM forum_discussions WHERE catid ='$catid' AND shoutbox = 0 AND bulletin = 0");
	$dpagenum = $dpagenum == "last" ? (ceil($discussioncount / $CFG->forum->discussionsperpage) - 1) : $dpagenum;
	$limit = $settings->forum->$forumid->discussionsperpage->setting * $dpagenum;
	
    $SQL = "SELECT *
			FROM forum_discussions
			WHERE catid = '$catid'
			AND bulletin = 0
			AND shoutbox = 0
			ORDER BY lastpost DESC
			LIMIT $limit," . $settings->forum->$forumid->discussionsperpage->setting;

    while ($dpagenum >= 0 && !$discussions = get_db_result($SQL)) { // Pagenum problem...aka deleted last post on page...go to previous page.
		$limit = $settings->forum->$forumid->discussionsperpage->setting * $dpagenum;
		$SQL = "SELECT *
				FROM forum_discussions
				WHERE catid = '$catid'
				AND bulletin = 0
				AND shoutbox = 0
				ORDER BY lastpost DESC
				LIMIT $limit," . $settings->forum->$forumid->discussionsperpage->setting;
		$dpagenum--;	
	} 
	
    //Abilities
	$lockability = user_is_able($USER->userid, "lockdiscussion", $category['pageid']);
	$deleteability = user_is_able($USER->userid, "deleteforumdiscussion", $category['pageid']);
	$bulletinability = user_is_able($USER->userid, "designateforumbulletin", $category['pageid']);
	$editability = user_is_able($USER->userid, "editforumcategory", $category['pageid']);

	$returnme = '<div class="forum_breadcrumb">
                    <a href="javascript: void(0);"
                       onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\'));
                                ajaxapi(\'/features/forum/forum_ajax.php\',
                                        \'get_forum_categories_ajax\',
                                        \'&forumid=' . $forumid . '\',
                                        function() {
                                            if (xmlHttp.readyState == 4) {
                                                simple_display(\'forum_div_' . $forumid . '\');
                                            }
                                        }, true); ">
                        Categories
                    </a>
                    <img style="margin: 0 5px" src="' . $CFG->wwwroot . '/images/calendarNext.gif" alt="breadcrumbarrow" />
                    ' . get_db_field("title", "forum_categories", "catid = '$catid'") . '
                </div>';
	
    //Create Discussion Link
	if (user_is_able($USER->userid, "createforumdiscussion", $pageid)) { 
		$discussionlinkparam = [
			"button" => "button",
			"title" => "New Discussion",
			"text" => '<img src="' . $CFG->wwwroot . '/images/discussion.gif" alt=""> New Discussion',
			"path" => action_path("forum") . "create_discussion_form&amp;pageid=$pageid&amp;forumid=$forumid&amp;catid=$catid",
			"width" => "750",
			"height" => "600",
			"iframe" => true,
			"runafter" => "forum_refresh_$forumid",
		];
        $returnme .= '  <div class="forum_newbutton">
                            ' . make_modal_links($discussionlinkparam) . '
                        </div>';
    }
	$returnme .= get_discussion_pages($forumid, $category, $dpagenum);
	
    // GET BULLETIN BOARDS
	$SQL = "SELECT * FROM forum_discussions WHERE catid = '$catid' AND bulletin = 1 AND shoutbox = 0 ORDER BY title";
	if ($bulletins = get_db_result($SQL)) {
		if ($bulletins != false) { $returnme .= '<table class="forum_discussions"><tr><td class="forum_headers"><b>Bulletins</b></td><td class="forum_headers" style="width:50px;">Replies</td><td class="forum_headers" style="width:50px;">Views</td><td  class="forum_headers" style="width:150px;">Last Posted</td></tr>';}
		while ($bulletin = fetch_row($bulletins)) {
			$posts_count = get_db_count("SELECT * FROM forum_posts WHERE discussionid=" . $bulletin["discussionid"]) - 1;
			$lastpost = get_db_row("SELECT * FROM forum_posts WHERE discussionid=" . $bulletin["discussionid"] . " ORDER BY posted DESC LIMIT 1");
			$notviewed = true;
			// Find if new posts are available
			if (is_logged_in()) {
				if (!$lastviewed = get_db_field("lastviewed", "forum_views", "discussionid=" . $bulletin["discussionid"] . " ORDER BY lastviewed DESC")) { $lastviewed = 0;}
				$notviewed =  $lastpost["posted"] > $lastviewed ? true : false;
			}
			$viewclass = $notviewed ? 'forum_bulletin' : 'forum_bulletin_viewed';
			$lock = $bulletin["locked"] == 1 ? '<img src="' . $CFG->wwwroot . '/images/lock.png" style="margin:-5px;" />&nbsp;&nbsp;' : '';
			$content .= '<tr>
                            <td class="' . $viewclass . '">
                                <span style="position:relative;float:left;max-width:95%;">
                                    ' . $lock . 'Bulletin:&nbsp;
                                    <a  href="javascript: void(0);" id="tester"
                                        onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\'));
                                                ajaxapi(\'/features/forum/forum_ajax.php\',
                                                        \'get_forum_posts\',
                                                        \'&discussionid=' . $bulletin['discussionid'] . '&pagenum=0&catid=' . $catid . '&forumid=' . $forumid . '&pageid=' . $bulletin["pageid"] . '\',
                                                        function() {
                                                            if (xmlHttp.readyState == 4) {
                                                                simple_display(\'forum_div_' . $forumid . '\');
                                                            }
                                                        }, true);">
                                    ' . stripslashes($bulletin["title"]) . '
                                    </a>
                                    <br />
                                    ' . get_post_pages($forumid, $bulletin, false, 3, false) . '
                                </span>';

			$content .= '<span style="position:relative;float:right;">';
			
            // UNPIN BULLETIN
			if ($bulletinability) { $content .= '<a class="forum_inline_buttons" alt="Undesignate as Bulletin" title="Undesignate as Bulletin" href="javascript: if (confirm(\'Are you sure you wish to unpin this bulletin?\')) { ajaxapi(\'/features/forum/forum_ajax.php\',\'unpin_bulletin\',\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $bulletin['discussionid'] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);}"><img src="' . $CFG->wwwroot . '/images/unpin.png" /></a>';}
			
            // LOCK/UNLOCK BULLETIN
			if ($lockability && $bulletin["locked"] == 1) { $content .= '<a class="forum_inline_buttons" alt="Unlock Bulletin" title="Unlock Bulletin" href="javascript: if (confirm(\'Are you sure you wish to unlock this bulletin?\')) { ajaxapi(\'/features/forum/forum_ajax.php\',\'unlock_discussion\',\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $bulletin['discussionid'] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);}"><img src="' . $CFG->wwwroot . '/images/unlock.png" /></a>';}
			if ($lockability && $bulletin["locked"] == 0) { $content .= '<a class="forum_inline_buttons" alt="Lock Bulletin" title="Lock Bulletin" href="javascript: if (confirm(\'Are you sure you wish to lock this bulletin?\')) { ajaxapi(\'/features/forum/forum_ajax.php\',\'lock_discussion\',\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $bulletin['discussionid'] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);}"><img src="' . $CFG->wwwroot . '/images/lock.png" /></a>';}
			
            // DELETE BULLETIN		
			if ($deleteability) {
				$content .= '<a class="forum_inline_buttons" alt="Delete Bulletin" title="Delete Bulletin" href="javascript: void(0)"
                                onclick="if (confirm(\'Are you sure you wish to delete this discussion? \nThis will also delete all posts inside this discussion.\')) {
                                            this.blur();
                                            ajaxapi(\'/features/forum/forum_ajax.php\',
                                                    \'delete_discussion\',
                                                    \'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $bulletin['discussionid'] . '\',
                                                    function() {
                                                        if (xmlHttp.readyState == 4) {
                                                            simple_display(\'forum_div_' . $forumid . '\');
                                                        }
                                                    },
                                                    true);
                                        }"><img src="' . $CFG->wwwroot . '/images/delete.png" />
                            </a>';
			}

			// EDIT BULLETIN
			if ($editability) {
				$editlinkparams = [
					"title" => "Edit Bulletin",
					"path" => action_path("forum") . "create_discussion_form&amp;pageid=$pageid&amp;forumid=$forumid&amp;catid=$catid&amp;discussionid=" . $bulletin['discussionid'],
					"width" => "750",
					"height" => "600",
					"iframe" => true,
					"runafter" => "forum_refresh_$forumid",
                    "class" => "forum_inline_buttons", 
					"image" => $CFG->wwwroot . '/images/edit.png',
				];
				$content .= make_modal_links($editlinkparams);
			}
			$content .= '</span></td>
						<td class="forum_col2">' . $posts_count . '</td>
						<td class="forum_col3">' . $bulletin["views"] . '</td>
						<td class="forum_col2" style="font-size:1em;">' . date("M j, Y, g:i a", $lastpost["posted"]) . "<br />" . get_user_name($lastpost["ownerid"]) . '</td>
						</tr>';
		}
		if ($bulletins != false) { $returnme .= $content . "</table>"; }
	}
    
	//GET REGULAR DISCUSSIONS
	$content = "";
	$returnme .= '<table class="forum_discussions">
                    <tr>
                        <td class="forum_headers">
                            Discussions
                        </td>
                        <td class="forum_headers" style="width:50px;">
                            Replies
                        </td>
                        <td class="forum_headers" style="width:50px;">
                            Views
                        </td>
                        <td  class="forum_headers" style="width:150px;">
                            Last Posted
                        </td>
                    </tr>';

	if ($discussions) {
		while ($discussion = fetch_row($discussions)) {
			$posts_count = get_db_count("SELECT * FROM forum_posts WHERE discussionid = '" . $discussion["discussionid"] . "'");
			$lastpost = get_db_row("SELECT * FROM forum_posts WHERE discussionid = '" . $discussion["discussionid"] . "' ORDER BY posted DESC LIMIT 1");
			$notviewed = true;
            $viewclass = $notviewed ? 'forum_col1' : 'forum_col1_viewed';
            $lastviewed = 0;

            if ($posts_count) {
                // Find if new posts are available
                if (is_logged_in()) {
                    if (!$lastviewed = get_db_field("lastviewed", "forum_views", "userid=" . $USER->userid." AND discussionid=" . $discussion["discussionid"] . " ORDER BY lastviewed DESC")) {
                        $lastviewed = 0;
                    }
                    $notviewed =  $lastpost["posted"] > $lastviewed ? true : false;
                }
                $viewclass = $notviewed ? 'forum_col1' : 'forum_col1_viewed';
                $lock = $discussion["locked"] == 1 ? '<img src="' . $CFG->wwwroot . '/images/lock.png" style="margin:-5px;" />&nbsp;&nbsp;' : '';
                $content .= '<tr>
                                <td class="' . $viewclass . '">
                                    <span style="position:relative;float:left;max-width:95%;">
                                        <a class="forum_inline_buttons" href="javascript: void(0);" onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\')); ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&discussionid=' . $discussion['discussionid'] . '&pagenum=0&catid=' . $catid . '&forumid=' . $forumid . '&pageid=' . $discussion["pageid"] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true); " >' . $lock.stripslashes($discussion["title"]) . '
                                        </a>
                                        <br />
                                        ' . get_post_pages($forumid, $discussion, false, 3, false) . '
                                    </span>';
                $editability = $editability || $USER->userid == $discussion["ownerid"] ? true : false;
                $content .= '<span style="position:relative;float:right;">';
                
                // PIN DISCUSSION
                if ($bulletinability) { $content .= '<a class="forum_inline_buttons"alt="Designate as Bulletin" title="Designate as Bulletin" href="javascript: void(0);" onclick="this.blur();if (confirm(\'Are you sure you wish to pin this as a bulletin?\')) { ajaxapi(\'/features/forum/forum_ajax.php\',\'pin_bulletin\',\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $discussion['discussionid'] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);}"><img src="' . $CFG->wwwroot . '/images/pin.png" /></a>';}
                
                // LOCK/UNLOCK BULLETIN
                if ($lockability && $discussion["locked"] == 1) { $content .= '<a class="forum_inline_buttons" alt="Unlock Discussion" title="Unlock Discussion" href="javascript: void(0);" onclick="this.blur();if (confirm(\'Are you sure you wish to unlock this discussion?\')) { ajaxapi(\'/features/forum/forum_ajax.php\',\'unlock_discussion\',\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $discussion['discussionid'] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);}"><img src="' . $CFG->wwwroot . '/images/unlock.png" /></a>';}
                if ($lockability && $discussion["locked"] == 0) { $content .= '<a class="forum_inline_buttons" alt="Lock Discussion" title="Lock Discussion" href="javascript: void(0);" onclick="this.blur();if (confirm(\'Are you sure you wish to lock this discussion?\')) { ajaxapi(\'/features/forum/forum_ajax.php\',\'lock_discussion\',\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $discussion['discussionid'] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);}"><img src="' . $CFG->wwwroot . '/images/lock.png" /></a>';}
                
                // DELETE DISCUSSION		
                if ($deleteability) { $content .= '<a class="forum_inline_buttons" alt="Delete Discussion" title="Delete Discussion" href="javascript: void(0);" onclick="this.blur(); if (confirm(\'Are you sure you wish to delete this discussion? \nThis will also delete all posts inside this discussion.\')) { ajaxapi(\'/features/forum/forum_ajax.php\',\'delete_discussion\',\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $discussion['discussionid'] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);}"><img src="' . $CFG->wwwroot . '/images/delete.png" /></a>';}		
                
                // EDIT DISCUSSION
                if ($editability) {
                    $editlinkparams = [
                        "title" => "Edit Discussion",
                        "path" => action_path("forum") . "create_discussion_form&amp;pageid=$pageid&amp;forumid=$forumid&amp;catid=$catid&amp;discussionid=" . $discussion['discussionid'],
                        "width" => "750",
                        "height" => "600",
                        "iframe" => true,
                        "class" => "forum_inline_buttons",
                        "runafter" => "forum_refresh_$forumid",
                        "image" => $CFG->wwwroot . '/images/edit.png',
                    ];
                    $content .= make_modal_links($editlinkparams);
                }

                $content .= '</span></td>
                <td class="forum_col2">' . $posts_count - 1 . '</td>
                <td class="forum_col3">' . $discussion["views"] . '</td>
                <td class="forum_col2" style="font-size:1em;">' . date("M j, Y, g:i a", $lastpost["posted"]) . "<br />" . get_user_name($lastpost["ownerid"]) . '</td>
                </tr>';
            } else {
                $content .= '<tr>
                                <td class="' . $viewclass . '">
                                    <span style="position:relative;float:left;max-width:95%;">
                                        <a href="javascript: void(0);" onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\')); ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&discussionid=' . $discussion['discussionid'] . '&pagenum=0&catid=' . $catid . '&forumid=' . $forumid . '&pageid=' . $discussion["pageid"] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true); " >
                                        ' . $lock.stripslashes($discussion["title"]) . '
                                        </a>
                                        <br />
                                        ' . get_post_pages($forumid, $discussion, false, 3, false) . '
                                    </span>
                                </td>
                                <td class="forum_col2">-</td>
                                <td class="forum_col3">-</td>
                                <td class="forum_col2" style="font-size:1em;">-</td>
                            </tr>';
            }
		}
	}
	$returnme .= $content == "" ? '<tr><td colspan="4" class="forum_col1">No Discussions Created.</td></tr>' : $content;
	$returnme .= "</table>";
	echo $returnme;
}

function get_forum_posts() {
global $CFG, $MYVARS, $USER;
    $pagenum = $MYVARS->GET["pagenum"] ?? 0;
	$pageid = $MYVARS->GET["pageid"]; 
    $forumid = $MYVARS->GET["forumid"];
	$catid = $MYVARS->GET["catid"];
    $discussionid = $MYVARS->GET["discussionid"];

	if (is_logged_in()) {
        update_user_views($catid, $discussionid, $USER->userid);
    }

	$settings = fetch_settings("forum", $forumid, $pageid);
	$postcount = get_db_count("SELECT * 
                               FROM forum_posts
                               WHERE discussionid = '$discussionid'");

    if ($pagenum !== "last" && !is_numeric($pagenum)) {
        $pagenum = 0;
    }

	$pagenum = $pagenum == "last" ? (ceil($postcount / $settings->forum->$forumid->postsperpage->setting) - 1): $pagenum;

	//Add to the discussion view field
	execute_db_sql("UPDATE forum_discussions
                    SET views = views + 1
                    WHERE discussionid = '$discussionid'");

	$discussion = get_db_row("SELECT *
                              FROM forum_discussions
                              WHERE discussionid = '$discussionid'");

    $limit = $settings->forum->$forumid->postsperpage->setting * $pagenum;
    $SQL = "SELECT *
            FROM forum_posts
            WHERE discussionid = '$discussionid'
            ORDER BY posted
            LIMIT $limit," . $settings->forum->$forumid->postsperpage->setting;

	while ($pagenum >= 0 && !$posts = get_db_result($SQL)) { // Pagenum problem...aka deleted last post on page...go to previous page.
		$pagenum--;
		$limit = $settings->forum->$forumid->postsperpage->setting * $pagenum;
		$SQL = "SELECT *
                FROM forum_posts
                WHERE discussionid = '$discussionid'
                ORDER BY posted
                LIMIT $limit," . $settings->forum->$forumid->postsperpage->setting;	
	}

	$returnme = '<div class="forum_breadcrumb">
                    <a href="javascript: void(0);"
                       onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\'));
                                ajaxapi(\'/features/forum/forum_ajax.php\',
                                        \'get_forum_categories_ajax\',
                                        \'&forumid=' . $forumid . '\',
                                        function() {
                                            if (xmlHttp.readyState == 4) {
                                                simple_display(\'forum_div_' . $forumid . '\');
                                            }
                                        }, true);" >
                        Categories
                    </a>
                    <img style="margin: 0 5px" src="' . $CFG->wwwroot . '/images/calendarNext.gif" alt="breadcrumbarrow" />
                    <a href="javascript: void(0);"
                       onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\'));
                                ajaxapi(\'/features/forum/forum_ajax.php\',
                                        \'get_forum_discussions\',
                                        \'&forumid=' . $forumid . '&catid=' . $catid . '&pageid=' . $pageid . '\',
                                        function() {
                                            if (xmlHttp.readyState == 4) {
                                                simple_display(\'forum_div_' . $forumid . '\');
                                            }
                                        }, true);" >
                    ' . get_db_field("title", "forum_categories", "catid=$catid") . '
                    </a>
                    <img style="margin: 0 5px" src="' . $CFG->wwwroot . '/images/calendarNext.gif" alt="breadcrumbarrow" />
                    ' . get_db_field("title", "forum_discussions", "discussionid = '$discussionid'") . '
                </div> ';
	
    if (user_is_able($USER->userid, "createforumdiscussion", $pageid)) { 
		$creatediscussionparam = [
			"button" => "button",
			"title" => "New Discussion",
			"text" => '<img src="' . $CFG->wwwroot . '/images/discussion.gif" alt=""> New Discussion',
			"path" => action_path("forum") . "create_discussion_form&amp;pageid=$pageid&amp;forumid=$forumid&amp;catid=$catid",
			"width" => "750",
			"height" => "600",
			"iframe" => true,
			"runafter" => "forum_refresh_$forumid",
		];
        $returnme .= '<div class="forum_newbutton">
                        ' . make_modal_links($creatediscussionparam) . '
                      </div>';
    }
	
    $returnme .= get_post_pages($forumid, $discussion, $pagenum);
	$returnme .= '<table class="forum_discussions"><tr><td class="forum_headers" style="width:125px;">Author</td><td class="forum_headers">Message</td></tr>';
	$firstpost = first_post($discussionid);
	$content = "";

    if ($posts) {
        while ($post = fetch_row($posts)) {
            $content .= '<tr>
                            <td class="forum_author">' . get_user_name($post["ownerid"]) . '<br />
                                Posts: ' . get_db_count("SELECT * FROM forum_posts WHERE ownerid=" . $post["ownerid"]) . '
                            </td>
                            <td class="forum_message">';
            
            // QUOTE
            if (!$discussion["locked"] && user_is_able($USER->userid, "forumreply", $pageid)) {
                $params = [
                    "title" => "Quote",
                    "path" => action_path("forum") . "show_forum_editor&amp;quote=1&amp;edit=0&amp;pagenum=$pagenum&amp;catid=$catid&amp;postid=" . $post["postid"],
                    "width" => "750",
                    "height" => "600",
                    "iframe" => true,
                    "runafter" => "forum_refresh_$forumid",
                ];
                $content .= '<span class="forum_post_actions" style="">
                                ' . make_modal_links($params) . '
                            </span>';   
            } 
            
            // POST MESSAGE
            $content .= '<span class="forum_post_actions" style="float:right;">
                            Posted: ' . ago($post["posted"]) . '
                        </span>
                        <div class="forum_post_message">
                        ' . stripslashes($post["message"]) . '
                        </div>';
            $content .= $post["edited"] ? '<span class="centered_span" style="font-size:.9em; color:gray;">[edited by ' . get_user_name($post["editedby"]) . ' ' . ago($post["edited"]) . ']</span>' : '';
            
            // EDIT POST
            if (!$discussion["locked"] && (user_is_able($USER->userid, "editforumposts", $pageid) || $USER->userid == $post["ownerid"])) {
                $params = [
                    "title" => "Edit",
                    "path" => action_path("forum") . "show_forum_editor&amp;quote=0&amp;edit=1&amp;pagenum=$pagenum&amp;postid=" . $post["postid"],
                    "width" => "750",
                    "height" => "600",
                    "iframe" => true,
                    "runafter" => "forum_refresh_$forumid",
                ];
                $content .= '<span class="forum_post_actions" style="">
                                ' . make_modal_links($params) . '
                            </span>';
            }
                
            // DELETE POST
            if (!$discussion["locked"] && user_is_able($USER->userid, "deleteforumpost", $pageid)) {
                $content .= '<span class="forum_post_actions" style="">
                                <a href="javascript: void(0);"
                                   onclick="if (confirm(\'Are you sure you want to delete this post?\')) {
                                                ajaxapi(\'/features/forum/forum_ajax.php\',
                                                        \'delete_post\',
                                                        \'&pagenum=' . $pagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $discussionid . '&postid=' . $post["postid"] . '\',
                                                        function() {
                                                            if (xmlHttp.readyState == 4) {
                                                                simple_display(\'forum_div_' . $forumid . '\');
                                                            }
                                                        },
                                                        true);
                                            }" >
                                    Delete
                                </a>
                            </span>';  
            }
    
            // REPLY
            if (!$discussion["locked"] && user_is_able($USER->userid, "forumreply", $pageid)) {
                $params = [
                    "title" => "Reply",
                    "path" => action_path("forum") . "show_forum_editor&amp;quote=0&amp;edit=0&amp;pagenum=$pagenum&amp;postid=" . $post["postid"],
                    "width" => "750",
                    "height" => "600",
                    "iframe" => true,
                    "runafter" => "forum_refresh_$forumid",
                ];
                $content .= '<span class="forum_post_actions" style="float:right;">
                                ' . make_modal_links($params) . '
                            </span>';   
            }
            
            $content .= '</td></tr>';
        }
    }

    $postlink = "";
    if ($content == "") { // Post the first message to a discussion.  Only possible if only post has been deleted.
        if (!$discussion["locked"] && user_is_able($USER->userid, "forumreply", $pageid)) {
            $params = [
                "title" => "Post",
                "path" => action_path("forum") . "show_forum_editor&amp;pageid=$pageid&amp;catid=$catid&amp;forumid=$forumid&amp;discussionid=$discussionid",
                "width" => "750",
                "height" => "600",
                "iframe" => true,
                "runafter" => "forum_refresh_$forumid",
            ];
            $postlink = '<span class="forum_post_actions" style="float:right;">
                            ' . make_modal_links($params) . '
                        </span>';   
        }
    }
	$returnme .= $content == "" ? '<tr><td colspan="4" class="forum_col1" style="text-align: center">No Posts Yet.' . $postlink . '</td></tr>' : $content;
	$returnme .= "</table>";
	$returnme .= get_post_pages($forumid, $discussion, $pagenum);
	echo $returnme;
}

function post() {
global $CFG, $MYVARS, $USER;
	$message = addslashes(urldecode($MYVARS->GET["message"])); $quote = $MYVARS->GET["quote"];
	$postid = $MYVARS->GET["postid"] ?? false;
    $edit = $MYVARS->GET["edit"] ?? false;
    $pageid = $MYVARS->GET["pageid"] ?? false;
    $forumid = $MYVARS->GET["forumid"] ?? false;
    $catid = $MYVARS->GET["catid"] ?? false;
    $discussionid = $MYVARS->GET["discussionid"] ?? false;

	$time = get_timestamp();
	if (!$edit) {
        if ($postid) {
            $post = get_db_row("SELECT * FROM forum_posts WHERE postid = '$postid'");
            $discussionid = $post["discussionid"];
            $forumid = $post["forumid"];
            $pageid = $post["pageid"];
            $catid = $post["catid"];
        }
		
		if ($quote == 1) {
			$message = '<blockquote class="forum_quote">[quoted from ' . get_user_name($post["ownerid"]) . ']<br />' . $post["message"] . '</blockquote>' . $message;
		}

        if (execute_db_sql("INSERT INTO forum_posts (discussionid, catid, forumid, pageid, ownerid, message, posted) VALUES('$discussionid', '$catid', '$forumid', '$pageid', " . $USER->userid.", '" . $message."', '$time')")) {
            execute_db_sql("UPDATE forum_discussions SET lastpost = '$time' WHERE discussionid = '$discussionid'");
        }
	} else {
        execute_db_sql("UPDATE forum_posts SET message = '" . $message . "', edited = '$time', editedby = '" . $USER->userid . "' WHERE postid = '$postid'");
    }
	echo "Post Successful.";		
}

function edit_category() {
global $CFG, $MYVARS, $USER;
	$title = addslashes(urldecode($MYVARS->GET["catname"])); $catid = $MYVARS->GET["catid"];
	execute_db_sql("UPDATE forum_categories SET title='$title' WHERE catid=$catid");
	echo "Edit Successful.";
}

function create_category() {
global $CFG, $MYVARS, $USER;
	$title = addslashes(urldecode($MYVARS->GET["catname"]));
	$pageid = $MYVARS->GET["pageid"]; $forumid = $MYVARS->GET["forumid"]; 
	$sort = get_db_count("SELECT * FROM forum_categories WHERE forumid=$forumid AND shoutbox=0");
	$sort++;
	execute_db_sql("INSERT INTO forum_categories (forumid,pageid,title,sort) VALUES($forumid, $pageid,'$title', $sort)");
	echo "Category Creation Successful.";
}

function create_discussion() {
global $CFG, $MYVARS, $USER;
	$message = urldecode($MYVARS->GET["message"]); $title = urldecode($MYVARS->GET["title"]);
	$pageid = $MYVARS->GET["pageid"]; $forumid = $MYVARS->GET["forumid"]; $catid = $MYVARS->GET["catid"];
	$time = get_timestamp();
	if (isset($MYVARS->GET["discussionid"])) {
		execute_db_sql("UPDATE forum_discussions SET title='" . addslashes($title) . "' WHERE discussionid=" . $MYVARS->GET["discussionid"]);
		execute_db_sql("UPDATE forum_posts SET message='" . addslashes($message) . "' WHERE postid=" . $MYVARS->GET["postid"]);
		echo "Discussion Edited Successful";
	} else {
		if ($discussionid = execute_db_sql("INSERT INTO forum_discussions (catid,forumid,pageid,ownerid,title,lastpost) VALUES(" . $catid.", " . $forumid.", " . $pageid.", " . $USER->userid.",'" . addslashes($title) . "', $time)")) {
			execute_db_sql("INSERT INTO forum_posts (discussionid,catid,forumid,pageid,ownerid,message,posted) VALUES(" . $discussionid.", " . $catid.", " . $forumid.", " . $pageid.", " . $USER->userid.",'" . addslashes($message) . "', $time)");
		}
		echo "Discussion Creation Successful";
	}
}

function move_category() {
global $CFG, $MYVARS;
	$direction = $MYVARS->GET["direction"]; $forumid = $MYVARS->GET["forumid"];
	$catid = $MYVARS->GET["catid"];
	$current_position = get_db_field("sort", "forum_categories", "catid=$catid"); 
	if ($direction == 'up') {
		$up_position = $current_position - 1;
		execute_db_sql("UPDATE forum_categories SET sort='$current_position' WHERE forumid='$forumid' AND shoutbox=0 AND sort='$up_position'");
		execute_db_sql("UPDATE forum_categories SET sort='$up_position' WHERE catid='$catid'");
	} elseif ($direction == 'down') {
		$down_position = $current_position + 1;
		execute_db_sql("UPDATE forum_categories SET sort='$current_position' WHERE forumid='$forumid' AND shoutbox=0 AND sort='$down_position'");
		execute_db_sql("UPDATE forum_categories SET sort='$down_position' WHERE catid='$catid'");
	}
	echo get_forum_categories($forumid);	
}

function delete_category() {
global $CFG, $MYVARS;
	$forumid = $MYVARS->GET["forumid"];
	$catid = $MYVARS->GET["catid"];
	execute_db_sql("DELETE FROM forum_categories WHERE catid='$catid'");
	execute_db_sql("DELETE FROM forum_discussions WHERE catid='$catid'");
	execute_db_sql("DELETE FROM forum_posts WHERE catid='$catid'");
	//Make sure the sort columns are correct
	resort_categories($forumid);
	echo get_forum_categories($forumid);	
}

function delete_discussion() {
global $CFG, $MYVARS;
	$discussionid = $MYVARS->GET["discussionid"];
	execute_db_sql("DELETE FROM forum_discussions WHERE discussionid='$discussionid'");
	execute_db_sql("DELETE FROM forum_posts WHERE discussionid='$discussionid'");
	get_forum_discussions();		
}

function delete_post() {
global $CFG, $MYVARS;
	$postid = $MYVARS->GET["postid"];
	execute_db_sql("DELETE FROM forum_posts WHERE postid = '$postid'");
	get_forum_posts();		
}

function pin_bulletin() {
global $CFG, $MYVARS;
	$discussionid = $MYVARS->GET["discussionid"];
	execute_db_sql("UPDATE forum_discussions SET bulletin=1 WHERE discussionid=" . $discussionid);
	get_forum_discussions();	
}

function unpin_bulletin() {
global $CFG, $MYVARS;
	$discussionid = $MYVARS->GET["discussionid"];
	execute_db_sql("UPDATE forum_discussions SET bulletin=0 WHERE discussionid=" . $discussionid);
	get_forum_discussions();	
}

function lock_discussion() {
global $CFG, $MYVARS;
	$discussionid = $MYVARS->GET["discussionid"];
	execute_db_sql("UPDATE forum_discussions SET locked=1 WHERE discussionid=" . $discussionid);
	get_forum_discussions();	
}

function unlock_discussion() {
global $CFG, $MYVARS;
	$discussionid = $MYVARS->GET["discussionid"];
	execute_db_sql("UPDATE forum_discussions SET locked=0 WHERE discussionid=" . $discussionid);
	get_forum_discussions();	
}

function shoutbox_post() {
global $CFG, $MYVARS;
	$message = $MYVARS->GET["message"];
	$alias = $MYVARS->GET["alias"];
	$forumid = $MYVARS->GET["forumid"];
	$discussion = get_db_row("SELECT * FROM forum_discussions WHERE shoutbox=1 and forumid=$forumid");
	$ownerid = $MYVARS->GET["ownerid"];
	$pageid = $discussion["pageid"];
	$discussionid = $discussion["discussionid"];
	$catid = $discussion["catid"];
	$posted = get_timestamp();
	if ($ownerid == "" && $alias == "") { $alias = "Anonymous";}	
	if (!execute_db_sql("INSERT INTO forum_posts (discussionid,catid,forumid,pageid,ownerid,message,posted,alias) VALUES('$discussionid','$catid','$forumid','$pageid','$ownerid','$message','$posted','$alias')")) {
		echo "Could not save message inside shoutbox.";
	}
	echo get_shoutbox($forumid);
}
?>