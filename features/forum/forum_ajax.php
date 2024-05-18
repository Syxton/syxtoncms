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
	echo get_forum_categories($forumid);	
}

function get_shoutbox_ajax() {
	$forumid = clean_myvar_req("forumid", "int");
	echo get_shoutbox($forumid);	
}

function get_forum_discussions() {
global $CFG, $USER;
	$catid = clean_myvar_req("catid", "int");
    $forumid = clean_myvar_req("forumid", "int");
	$pageid = clean_myvar_req("pageid", "int");
	$dpagenum = clean_myvar_opt("dpagenum", "string", 0);

    if ($dpagenum !== "last" && !is_numeric($dpagenum)) {
        $dpagenum = 0;
    }

    $dpagenum = $dpagenum < 0 ? false : $dpagenum;
	$dpagenum2 = $dpagenum ?? false;

	$settings = fetch_settings("forum", $forumid, $pageid);
	
	$content = "";
	date_default_timezone_set(date_default_timezone_get());
	$category = get_db_row("SELECT * FROM forum_categories WHERE catid = ||catid||", ["catid" => $catid]);
	$discussioncount = get_db_count("SELECT * FROM forum_discussions WHERE catid = ||catid|| AND shoutbox = 0 AND bulletin = 0", ["catid" => $catid]);
	$dpagenum = $dpagenum == "last" ? (ceil($discussioncount / $CFG->forum->discussionsperpage) - 1) : $dpagenum;
	$limit = $settings->forum->$forumid->discussionsperpage->setting * $dpagenum;
	
    $SQL = "SELECT *
			FROM forum_discussions
			WHERE catid = ||catid||
			AND bulletin = 0
			AND shoutbox = 0
			ORDER BY lastpost DESC
			LIMIT $limit," . $settings->forum->$forumid->discussionsperpage->setting;

    while ($dpagenum >= 0 && !$discussions = get_db_result($SQL, ["catid" => $catid])) { // Pagenum problem...aka deleted last post on page...go to previous page.
		$limit = $settings->forum->$forumid->discussionsperpage->setting * $dpagenum;
		$SQL = "SELECT *
				FROM forum_discussions
				WHERE catid = ||catid||
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
			"path" => action_path("forum") . "create_discussion_form&pageid=$pageid&forumid=$forumid&catid=$catid",
			"width" => "750",
			"height" => "600",
			"iframe" => true,
			"runafter" => "forum_refresh_$forumid",
		];
        $returnme .= '<div class="forum_newbutton">
						' . make_modal_links($discussionlinkparam) . '
                    </div>';
    }
	$returnme .= get_discussion_pages($forumid, $category, $dpagenum);
	
    // GET BULLETIN BOARDS
	$SQL = "SELECT * FROM forum_discussions WHERE catid = ||catid|| AND bulletin = 1 AND shoutbox = 0 ORDER BY title";
	if ($bulletins = get_db_result($SQL, ["catid" => $catid])) {
		if ($bulletins) {
			$returnme .= '<table class="forum_discussions">
							<tr>
								<td class="forum_headers">
									<b>Bulletins</b>
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
							</tr>';}
		while ($bulletin = fetch_row($bulletins)) {
			$posts_count = get_db_count("SELECT * FROM forum_posts WHERE discussionid = ||discussionid||", ["discussionid" => $bulletin["discussionid"]]) - 1;
			$lastpost = get_db_row("SELECT * FROM forum_posts WHERE discussionid = ||discussionid|| ORDER BY posted DESC LIMIT 1", ["discussionid" => $bulletin["discussionid"]]);
			$notviewed = true;
			// Find if new posts are available
			if (is_logged_in()) {
				if (!$lastviewed = get_db_field("lastviewed", "forum_views", "discussionid = ||discussionid|| ORDER BY lastviewed DESC", ["discussionid" => $bulletin["discussionid"]])) { $lastviewed = 0;}
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
                                    ' . $bulletin["title"] . '
                                    </a>
                                    <br />
                                    ' . get_post_pages($forumid, $bulletin, false, 3, false) . '
                                </span>';

			$content .= '		<span style="position:relative;float:right;">';

            // UNPIN BULLETIN
			if ($bulletinability) {
				$content .= '<a class="forum_inline_buttons" alt="Undesignate as Bulletin" title="Undesignate as Bulletin" href="javascript: void(0);"
								onclick="if (confirm(\'Are you sure you wish to unpin this bulletin?\')) {
											ajaxapi(\'/features/forum/forum_ajax.php\',
													\'unpin_bulletin\',
													\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $bulletin['discussionid'] . '\',
													function() {
														if (xmlHttp.readyState == 4) {
															simple_display(\'forum_div_' . $forumid . '\');
														}
													},
													true);
										}">
								<img src="' . $CFG->wwwroot . '/images/unpin.png" />
							</a>';
			}

            // LOCK/UNLOCK BULLETIN
			if ($lockability && $bulletin["locked"] == 1) {
				$content .= '<a class="forum_inline_buttons" alt="Unlock Bulletin" title="Unlock Bulletin" href="javascript: void(0);"
								onclick="if (confirm(\'Are you sure you wish to unlock this bulletin?\')) {
											ajaxapi(\'/features/forum/forum_ajax.php\',
													\'unlock_discussion\',
													\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $bulletin['discussionid'] . '\',
													function() {
														if (xmlHttp.readyState == 4) {
															simple_display(\'forum_div_' . $forumid . '\');
														}
													},
													true);
										}">
								<img src="' . $CFG->wwwroot . '/images/unlock.png" />
							</a>';
			}

			if ($lockability && $bulletin["locked"] == 0) {
				$content .= '<a class="forum_inline_buttons" alt="Lock Bulletin" title="Lock Bulletin" href="javascript: void(0);"
								onclick="if (confirm(\'Are you sure you wish to lock this bulletin?\')) {
											ajaxapi(\'/features/forum/forum_ajax.php\',
													\'lock_discussion\',
													\'&dpagenum=' . $dpagenum . '&pageid=' . $pageid . '&forumid=' . $forumid . '&catid=' . $catid . '&discussionid=' . $bulletin['discussionid'] . '\',
													function() {
														if (xmlHttp.readyState == 4) {
															simple_display(\'forum_div_' . $forumid . '\');
														}
													},
													true);
										}">
								<img src="' . $CFG->wwwroot . '/images/lock.png" />
							</a>';
			}

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
			$content .= '</span>
					</td>
					<td class="forum_col2">
						' . $posts_count . '
					</td>
					<td class="forum_col3">
						' . $bulletin["views"] . '
					</td>
					<td class="forum_col2" style="font-size:1em;">
						' . date("M j, Y, g:i a", $lastpost["posted"]) . '
						<br />
						' . get_user_name($lastpost["userid"]) . '
					</td>
				</tr>';
		}

		if ($bulletins) { $returnme .= $content . "</table>"; }
	}

	// GET REGULAR DISCUSSIONS
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
			$posts_count = get_db_count("SELECT * FROM forum_posts WHERE discussionid = ||discussionid||", ["discussionid" => $discussion["discussionid"]]);
			$lastpost = get_db_row("SELECT * FROM forum_posts WHERE discussionid = ||discussionid|| ORDER BY posted DESC LIMIT 1", ["discussionid" => $discussion["discussionid"]]);
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
                                        <a class="forum_inline_buttons" href="javascript: void(0);" onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\')); ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&discussionid=' . $discussion['discussionid'] . '&pagenum=0&catid=' . $catid . '&forumid=' . $forumid . '&pageid=' . $discussion["pageid"] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true); " >' . $lock . $discussion["title"] . '
                                        </a>
                                        <br />
                                        ' . get_post_pages($forumid, $discussion, false, 3, false) . '
                                    </span>';
                $editability = $editability || $USER->userid == $discussion["userid"] ? true : false;
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
                <td class="forum_col2" style="font-size:1em;">' . date("M j, Y, g:i a", $lastpost["posted"]) . "<br />" . get_user_name($lastpost["userid"]) . '</td>
                </tr>';
            } else {
                $content .= '<tr>
                                <td class="' . $viewclass . '">
                                    <span style="position:relative;float:left;max-width:95%;">
                                        <a href="javascript: void(0);" onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\')); ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&discussionid=' . $discussion['discussionid'] . '&pagenum=0&catid=' . $catid . '&forumid=' . $forumid . '&pageid=' . $discussion["pageid"] . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true); " >
                                        ' . $lock . $discussion["title"] . '
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
global $CFG, $USER;
	$catid = clean_myvar_req("catid", "int");
	$forumid = clean_myvar_req("forumid", "int");
	$pageid = clean_myvar_req("pageid", "int");
	$discussionid = clean_myvar_req("discussionid", "int");
	$pagenum = clean_myvar_opt("pagenum", "string", 0);

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
                            <td class="forum_author">' . get_user_name($post["userid"]) . '<br />
                                Posts: ' . get_db_count("SELECT * FROM forum_posts WHERE userid=" . $post["userid"]) . '
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
                        ' . $post["message"] . '
                        </div>';
            $content .= $post["edited"] ? '<span class="centered_span" style="font-size:.9em; color:gray;">[edited by ' . get_user_name($post["editedby"]) . ' ' . ago($post["edited"]) . ']</span>' : '';

            // EDIT POST
            if (!$discussion["locked"] && (user_is_able($USER->userid, "editforumposts", $pageid) || $USER->userid == $post["userid"])) {
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
                    "path" => action_path("forum") . "show_forum_editor&quote=0&edit=0&pagenum=$pagenum&postid=" . $post["postid"],
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
                "path" => action_path("forum") . "show_forum_editor&pageid=$pageid&catid=$catid&forumid=$forumid&discussionid=$discussionid",
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
global $USER;
	$message = clean_myvar_req("message", "html");
	$quote = clean_myvar_opt("quote", "int", false);
	$postid = clean_myvar_opt("postid", "int", false);
    $edit = clean_myvar_opt("edit", "int", false);
    $pageid = clean_myvar_opt("pageid", "int", false);
    $forumid = clean_myvar_opt("forumid", "int", false);
    $catid = clean_myvar_opt("catid", "int", false);
    $discussionid = clean_myvar_opt("discussionid", "int", false);

	try {
		start_db_transaction();
		$time = get_timestamp();
		if (!$edit) {
			if ($postid) {
				$post = get_db_row("SELECT * FROM forum_posts WHERE postid = '$postid'");
				$discussionid = $post["discussionid"];
				$forumid = $post["forumid"];
				$pageid = $post["pageid"];
				$catid = $post["catid"];
			}
	
			if ($quote) {
				$message = '<blockquote class="forum_quote">
								[quoted from ' . get_user_name($post["userid"]) . ']
								<br />
								' . $post["message"] . '
							</blockquote>' . $message;
			}
	
			// Insert Post.
			$params = [
				"discussionid" => $discussionid,
				"catid" => $catid,
				"forumid" => $forumid,
				"pageid" => $pageid,
				"userid" => $USER->userid,
				"message" => $message,
				"posted" => $time,
			];
			execute_db_sql(fetch_template("dbsql/forum.sql", "insert_post", "forum"), $params);
	
			// Update Discussion.update_discussion_lastpost.
			$params = [
				"discussionid" => $discussionid,
				"lastpost" => $time,
			];
			execute_db_sql(fetch_template("dbsql/forum.sql", "update_discussion_lastpost", "forum"), $params);
		} else {
			$params = [
				"message" => $message,
				"edited" => $time,
				"editedby" => $USER->userid,
				"postid" => $postid,
			];
			execute_db_sql(fetch_template("dbsql/forum.sql", "update_post", "forum"), $params);
		}
		commit_db_transaction();
		echo "Post Successful.";
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
		echo "failed";
	}
}

function edit_category() {
    $title = clean_myvar_req("catname", "html");
	$catid = clean_myvar_req("catid", "int");
	execute_db_sql("UPDATE forum_categories SET title='$title' WHERE catid=$catid");
	echo "Edit Successful.";
}

function create_category() {
    $title = clean_myvar_req("catname", "html");
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$forumid = clean_myvar_req("forumid", "int"); 
	$sort = get_db_count("SELECT * FROM forum_categories WHERE forumid=$forumid AND shoutbox=0");
	$sort++;
	execute_db_sql("INSERT INTO forum_categories (forumid,pageid,title,sort) VALUES($forumid, $pageid,'$title', $sort)");
	echo "Category Creation Successful.";
}

function create_discussion() {
global $USER;
	$message = clean_myvar_req("message", "html");
	$title = clean_myvar_req("title", "html");
	$pageid = clean_myvar_req("pageid", "int");
	$forumid = clean_myvar_req("forumid", "int");
	$catid = clean_myvar_req("catid", "int");
	$discussionid = clean_myvar_opt("discussionid", "int", false);
	$postid = clean_myvar_opt("postid", "int", false);
	$time = get_timestamp();

	try {
		start_db_transaction();
		$params = [
			"message" => $message,
			"catid" => $catid,
			"forumid" => $forumid,
			"pageid" => $pageid,
			"userid" => $USER->userid,
			"posted" => $time,
			"title" => $title,
			"lastpost" => $time,
			"alias" => '',
		];

		if ($discussionid) {
			$params["discussionid"] = $discussionid;
			execute_db_sql(fetch_template("dbsql/forum.sql", "update_discussion_title"), ["discussionid" => $discussionid, "title" => $title]);
			execute_db_sql(fetch_template("dbsql/forum.sql", "update_post"), $params);
			echo "Discussion Edited Successful";
		} else {
			$discussionid = execute_db_sql(fetch_template("dbsql/forum.sql", "insert_discussion", "forum"), $params);
			$params["discussionid"] = $discussionid;
			execute_db_sql(fetch_template("dbsql/forum.sql", "insert_post", "forum"), $params);
			echo "Discussion Creation Successful";
		}
		commit_db_transaction();
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
	}
}

function move_category() {
global $CFG, $MYVARS;
	$direction = clean_myvar_req("direction", "string");
	$forumid = clean_myvar_req("forumid", "int");
    $catid = clean_myvar_req("catid", "int");
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
	echo get_forum_categories($forumid);	
}

function delete_category() {
	$catid = clean_myvar_req("catid", "int");
	$forumid = get_db_field("forumid", "forum_categories", "catid = ||catid||", ["catid" => $catid]);

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
		trigger_error("Failed to delete category", E_USER_WARNING);
	}

	// Make sure the sort columns are correct.
	resort_categories($forumid);
	echo get_forum_categories($forumid);
}

function delete_discussion() {
	$discussionid = clean_myvar_req("discussionid", "int");
	try {
		start_db_transaction();
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
		trigger_error("Failed to delete discussion", E_USER_WARNING);
	}
	get_forum_discussions();		
}

function delete_post() {
	$postid = clean_myvar_req("postid", "int");
	execute_db_sql(fetch_template("dbsql/forum.sql", "delete_post"), ["postid" => $postid]);
	get_forum_posts();		
}

function pin_bulletin() {
	$discussionid = clean_myvar_req("discussionid", "int");
	execute_db_sql(fetch_template("dbsql/forum.sql", "pin_discussion"), ["discussionid" => $discussionid]);
	get_forum_discussions();	
}

function unpin_bulletin() {
	$discussionid = clean_myvar_req("discussionid", "int");
	execute_db_sql(fetch_template("dbsql/forum.sql", "unpin_discussion"), ["discussionid" => $discussionid]);
	get_forum_discussions();	
}

function lock_discussion() {
	$discussionid = clean_myvar_req("discussionid", "int");
	execute_db_sql(fetch_template("dbsql/forum.sql", "lock_discussion"), ["discussionid" => $discussionid]);
	get_forum_discussions();	
}

function unlock_discussion() {
	$discussionid = clean_myvar_req("discussionid", "int");
	execute_db_sql(fetch_template("dbsql/forum.sql", "unlock_discussion"), ["discussionid" => $discussionid]);
	get_forum_discussions();	
}

function shoutbox_post() {
	$message = clean_myvar_req("message", "html");
	$forumid = clean_myvar_req("forumid", "int");
	$alias = clean_myvar_opt("alias", "string", false);
	$userid = clean_myvar_opt("userid", "int", false);

	try {
		start_db_transaction();
		$SQL = "SELECT *
				FROM forum_discussions
				WHERE shoutbox = 1 AND forumid = ||forumid||";
		if (!$discussion = get_db_row($SQL, ["forumid" => $forumid])) {
			trigger_error("Could not find discussion.", E_USER_ERROR);
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
		echo "<p>Could not save post. Please try again later.</p>";
	}

	echo get_shoutbox($forumid);
}
?>