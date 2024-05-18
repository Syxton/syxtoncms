<?php
/***************************************************************************
* forumlib.php - Forum function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.8.8
***************************************************************************/

if (!LIBHEADER) {
	$sub = './';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == './' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php'); 
}
define('FORUMLIB', true);

function display_forum($pageid, $area, $forumid) {
global $CFG, $USER, $ROLES;
	date_default_timezone_set(date_default_timezone_get());
    // Forum auto refresh.
    $refresh_time = 1 * 60000; // The 1 could be a setting for minutes

	$content = '<div id="forum_div_' . $forumid . '" style="margin: 10px;">';

    //get settings or create default settings if they don't exist
	if (!$settings = fetch_settings("forum", $forumid, $pageid)) {
		save_batch_settings(default_settings("forum", $pageid, $forumid));
		$settings = fetch_settings("forum", $forumid, $pageid);
	}

	$title = $settings->forum->$forumid->feature_title->setting;

	if ($area == "middle") { //This is a FORUM
		if (user_is_able($USER->userid, "viewforums", $pageid)) {
			$content .= get_forum_categories($forumid);
		} else {
			$content .= '<span class="centered_span">' . error_string("generic_permissions") . '</span>';
		}
		$content .= '</div><input type="hidden" name="forum_refresh_' . $forumid . '" id="forum_refresh_' . $forumid . '" value="ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_categories_ajax\',\'&amp;forumid=' . $forumid . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);" />';

        //Refresh Script
        $script ='var forum' . $forumid . '_interval = setInterval(function() { eval(stripslashes(unescape($("#forum_refresh_' . $forumid . '").val()))); },' . $refresh_time . ');';
		$content .= js_code_wrap($script);
	} else { //This is a SHOUTBOX
		if (user_is_able($USER->userid, "viewshoutbox", $pageid)) {
			$content .= get_shoutbox($forumid);
		} else {
			$content .= '<span class="centered_span">' . error_string("generic_permissions") . '</span>';
		}
		$content .= '</div><input type="hidden" name="forum_refresh_' . $forumid . '" id="forum_refresh_' . $forumid . '" value="ajaxapi(\'/features/forum/forum_ajax.php\',\'get_shoutbox_ajax\',\'&amp;forumid=' . $forumid . '\',function() {if (xmlHttp.readyState == 4) { simple_display(\'forum_div_' . $forumid . '\'); }}, true);" />';
	}

	$buttons = is_logged_in() ? get_button_layout("forum", $forumid, $pageid) : "";
	return get_css_box($title, $content, $buttons, "0px", "forum", $forumid);
}

function get_forum_categories($forumid) {
global $USER, $CFG;
	$returnme = '<table class="forum_category">
					<tr>
						<td class="forum_headers">
							Category Name
						</td>
						<td class="forum_headers" style="width:70px;">
							Discussions
						</td>
						<td  class="forum_headers" style="width:70px;">
							Posts
						</td>
					</tr>';
	$content = "";
	if ($categories = get_db_result("SELECT * FROM forum_categories WHERE forumid=$forumid AND shoutbox=0 ORDER BY sort")) {
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
			$content .= '	<tr>
                                <td class="' . $viewclass . '">
                                    <span style="position:relative;float:left;">
                                        <a title="Get Forum Discussions"
                                           href="javascript: void(0);"
                                           onclick="save_action($(this), $(\'#forum_refresh_' . $forumid . '\'));
                                                    ajaxapi(\'/features/forum/forum_ajax.php\',
                                                            \'get_forum_discussions\',
                                                            \'&amp;dpagenum=0&amp;pageid=' . $category['pageid'] . '&amp;forumid=' . $forumid . '&amp;catid=' . $category['catid'] . '\',
                                                            function() {
                                                                if (xmlHttp.readyState == 4) {
                                                                    simple_display(\'forum_div_' . $forumid . '\');
                                                                }
                                                            },
                                                            true);">
                                            ' . $category["title"] . '
                                        </a>
                                    </span>';
			$edit = user_is_able($USER->userid, "editforumcategory", $category['pageid']);
			$content .= '<span style="position:relative;float:right;">';

            if ($edit) {
				if ($category["sort"] > 1) {
                    $content .= '<a title="Move Up" 
                                    href="javascript: void(0);"
                                    onclick="this.blur();
                                            ajaxapi(\'/features/forum/forum_ajax.php\',
                                                    \'move_category\',
                                                    \'&amp;catid=' . $category['catid'] . '&amp;forumid=' . $forumid . '&amp;pageid=' . $category['pageid'] . '&amp;direction=up\',
                                                    function() {
                                                        if (xmlHttp.readyState == 4) {
                                                            simple_display(\'forum_div_' . $forumid . '\');
                                                        }
                                                    }, 
                                                    true);">
                                    <img alt="Move Up" src="' . $CFG->wwwroot . '/images/up.gif" />
                                </a>';
                } 
				if ($category["sort"] != get_db_field("MAX(sort)", "forum_categories", "forumid = '$forumid' AND shoutbox = '0'")) {
                    $content .= '<a title="Move Down"
                                    href="javascript: void(0);"
                                    onclick="this.blur();
                                            ajaxapi(\'/features/forum/forum_ajax.php\',
                                                    \'move_category\',
                                                    \'&amp;catid=' . $category['catid'] . '&amp;forumid=' . $forumid . '&amp;pageid=' . $category['pageid'] . '&amp;direction=down\',
                                                    function() {
                                                        if (xmlHttp.readyState == 4) {
                                                            simple_display(\'forum_div_' . $forumid . '\');
                                                        }
                                                    },
                                                    true);">
                                    <img alt="Move Down" src="' . $CFG->wwwroot . '/images/down.gif" />
                                </a>';
                }
			}

            if (user_is_able($USER->userid, "deleteforumcategory", $category['pageid'])) {
                $content .= '<a title="Delete Category" href="javascript: void(0);" class="forum_inline_buttons"
                                onclick="if (confirm(\'Are you sure you wish to delete this category? \nThis will delete all discussions and posts inside this category.\')) {
                                            this.blur();
                                            ajaxapi(\'/features/forum/forum_ajax.php\',
                                                    \'delete_category\',
                                                    \'&catid=' . $category['catid'] . '\',
                                                    function() {
                                                        if (xmlHttp.readyState == 4) {
                                                            simple_display(\'forum_div_' . $forumid . '\');
                                                        }
                                                    },
                                                    true);
                                        }">
                                <img alt="Delete Category" src="' . $CFG->wwwroot . '/images/delete.png" />
                            </a>';
            }

            if ($edit) {
				$params = [
					"title" => "Edit Category",
					"path" => action_path("forum") . "createforumcategory&amp;catid=" . $category['catid'] . '&amp;pageid=' . $category['pageid'] . '&amp;forumid=' . $forumid,
					"runafter" => "forum_refresh_$forumid",
					"height" => "200",
					"width" => "640",
                    "class" => "forum_inline_buttons",
					"validate" => "true", "image" => $CFG->wwwroot . "/images/edit.png",
				];
                $content .= make_modal_links($params);
            }

			$content .= '</span>
					</td>
					<td class="forum_col2">
					' . $discussion_count . '
					</td>
					<td class="forum_col3">
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

function get_shoutbox($forumid) {
global $USER, $CFG;
	date_default_timezone_set(date_default_timezone_get());
    $pageid = get_db_field("pageid", "forum", "forumid = '$forumid'");
	$settings = fetch_settings("forum", $forumid, $pageid);
	$shoutboxlimit = isset($settings->forum->$forumid->shoutboxlimit->setting) ? " LIMIT " . $settings->forum->$forumid->shoutboxlimit->setting : "";
	$userid = is_logged_in() ? "&amp;userid=" . $USER->userid : "";
	$returnme = '
	<table class="shoutbox">
        <tr>
            <td>
                <img class="shoutbox_tableft" src="' . $CFG->wwwroot . '/images/shouttab_left.gif" alt="shout tab left image" /><img class="shoutbox_tabcenter" src="' . $CFG->wwwroot . '/images/shouttab_background.gif" alt="shout tab background image"/><img class="shoutbox_tabright" src="' . $CFG->wwwroot . '/images/shouttab_right.gif" alt="shout tab right image" />
            </td>
        </tr>
	<tr>
        <td>
            <span class="shoutbox_tabtext">
                ' . make_modal_links([
					"title" => "Shout",
					"path" => action_path("forum") . "shoutbox_editor$userid&amp;forumid=$forumid",
					"width" => "600",
					"height" => "600",
					"iframe" => true,
					"refresh" => "true",
					"runafter" => "forum_refresh_$forumid",
				]) . '
            </span>
        </td>
    </tr>';

	$shoutboxid = get_db_field("discussionid", "forum_discussions", "forumid=$forumid AND shoutbox=1");
	if ($posts = get_db_result("SELECT * FROM forum_posts WHERE discussionid=$shoutboxid ORDER BY posted DESC $shoutboxlimit")) {
		while ($post = fetch_row($posts)) {
			$alias = $post["userid"] != 0 ? get_user_name($post["userid"]): $post["alias"];
			$posted = date("m.d.y g:ia", $post["posted"]);
            $message = strip_tags($post["message"], "<img><a>");
			$returnme .= '  <tr>
                                <td class="shoutbox_post">
                                    <span style="color: black">' . $alias . ' at ' . $posted . '</span><br />' . $message . '<br /><br />
                                </td>
                            </tr>';
		}
	}
	$returnme .= "</table>";
	return $returnme;
}

function get_post_pages($forumid, $discussion, $pagenum, $beforeskipping=10, $buttons = true) {
global $CFG;
	$settings = fetch_settings("forum", $forumid, $discussion["pageid"]);
	$perpage = isset($settings->forum->$forumid->postsperpage->setting) ? " LIMIT " . $settings->forum->$forumid->postsperpage->setting : "";

	$perpage = $settings->forum->$forumid->postsperpage->setting;
	$pagenum = $pagenum === false ? false : $pagenum;
	$previous = "";
    $next = "";
    $params = [
        "forumid" => $discussion["forumid"],
        "catid" => $discussion["catid"],
        "pageid" => $discussion["pageid"],
        "discussionid" => $discussion["discussionid"],
    ];

    $post_count = get_db_count("SELECT * FROM forum_posts WHERE discussionid=" . $discussion["discussionid"]);
	if ($post_count) {
		$page_counter = 1;
		$lastpage = (ceil($post_count / $perpage) - 1);
		if ($buttons && !($pagenum === false) && $pagenum > 0) {
            $params = array_merge($params, [
                "pagenum" => $pagenum - 1,
                "title" => "Previous Page",
                "display" => "Prev",
            ]);
            $previous = use_template("tmp/forum.template", $params, "get_posts_page_link", "forum");
        }
		if ($buttons && !($pagenum === false) && $pagenum < $lastpage) {
            $params = array_merge($params, [
                "pagenum" => $pagenum + 1,
                "title" => "Next Page",
                "display" => "Next",
            ]);
            $next = use_template("tmp/forum.template", $params, "get_posts_page_link", "forum");
        }

        $pagelinks = "";
		while ($post_count > 0) {
			if ($page_counter > $beforeskipping) {
                if ($pagenum === false || $pagenum != $lastpage) {
                    $params = array_merge($params, [
                        "pagenum" => $lastpage,
                        "title" => "Last Page",
                        "display" => "Last",
                    ]);
                    $pagelinks .= use_template("tmp/forum.template", $params, "get_posts_page_link", "forum");
                } else {
                    $pagelinks .= " Last ";
                }
				$post_count = 0;
			} else {
                if ($pagenum === false || $pagenum != ($page_counter - 1)) {
                    $params = array_merge($params, [
                        "pagenum" => $page_counter - 1,
                        "title" => "Page $page_counter",
                        "display" => $page_counter,
                    ]);
                    $pagelinks .= use_template("tmp/forum.template", $params, "get_posts_page_link", "forum");
                } else {
                    $pagelinks .= " $page_counter ";
                }
				$post_count -= $perpage;
			}
			$page_counter++;
		}
		if ($page_counter == 2) { return "";}
	} else { return ""; }

    return '<div class="forum_page_links">Page: ' . $previous . $pagelinks . $next . '</div>';
}

function get_discussion_pages($forumid, $category, $pagenum, $beforeskipping = 20, $buttons = true) {
global $CFG;

	$settings = fetch_settings("forum", $forumid, $category['pageid']);
	$perpage = $settings->forum->$forumid->discussionsperpage->setting;

	$pagenum = $pagenum === false ? false : $pagenum;
	$previous = "";
    $next = "";
    $discussion_count = get_db_count("SELECT *
                                      FROM forum_discussions
                                      WHERE bulletin = 0
                                      AND shoutbox = 0
                                      AND catid = '" . $category["catid"] . "'");
    if ($discussion_count) {
		$page_counter = 1;
		$lastpage = (ceil($discussion_count / $perpage) - 1);
		$params = [
            "forumid" => $forumid,
            "catid" => $category['catid'],
            "pageid" => $category['pageid'],
        ];

        if ($buttons && !($pagenum === false) && $pagenum > 0) {
            $params = array_merge($params, [
                "dpagenum" => $pagenum - 1,
                "title" => "Previous Page",
                "display" => "Prev",
            ]);
            $previous = use_template("tmp/forum.template", $params, "get_discussions_page_link", "forum");
        }

		if ($buttons && !($pagenum === false) && $pagenum < $lastpage) {
            $params = array_merge($params, [
                "dpagenum" => $pagenum + 1,
                "title" => "Next Page",
                "display" => "Next",
            ]);
            $next = use_template("tmp/forum.template", $params, "get_discussions_page_link", "forum");
        }

        // Wittle down the discussions until we have no discussions left to create page links.
        $pagelinks = "";
		while ($discussion_count > 0) {
            // At some point we will not show page links and just allow a jump to last page.
			if ($page_counter > $beforeskipping) {
                if ($pagenum === false || $pagenum !== $lastpage) {
                    $params = array_merge($params, [
                        "dpagenum" => $lastpage,
                        "title" => "Last Page",
                        "display" => "Last",
                    ]);
                    $pagelinks .= use_template("tmp/forum.template", $params, "get_discussions_page_link", "forum");
                } else {
                    $pagelinks .= " Last ";
                }
				$discussion_count = 0; // Remove the rest of the discussions from counter.
			} else {
                if ($pagenum === false || $pagenum !== ($page_counter - 1)) {
                    $params = array_merge($params, [
                        "dpagenum" => $page_counter - 1,
                        "title" => "Page " . $page_counter,
                        "display" => $page_counter,
                    ]);
                    $pagelinks .= use_template("tmp/forum.template", $params, "get_discussions_page_link", "forum");
                } else {
                    $pagelinks .= " $page_counter ";
                }
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
	$postid = get_db_field("MIN(postid)", "forum_posts", "discussionid='$discussionid'");
	return $postid;
}

function resort_categories($forumid) {
	if ($result = get_db_result("SELECT * FROM forum_categories WHERE forumid='$forumid' AND shoutbox=0 ORDER BY sort")) {
		$i = 1;
		while ($row = fetch_row($result)) {
			execute_db_sql("UPDATE forum_categories SET sort='$i' WHERE catid='" . $row['catid'] . "'");
			$i++;
		}
	}
}

function insert_blank_forum($pageid) {
	$title = "Forum";
    $type = "forum";
	if ($featureid = execute_db_sql("INSERT INTO forum (pageid) VALUES('$pageid')")) {
		$area = get_db_field("default_area", "features", "feature='forum'");
		$sort = get_db_count("SELECT *
                              FROM pages_features
                              WHERE pageid = '$pageid'
                              AND area = '$area'") + 1;
		execute_db_sql("INSERT INTO pages_features (pageid, feature, sort, area, featureid)
                                             VALUES('$pageid', 'forum', '$sort', '$area', '$featureid')");
	
        $catid = execute_db_sql("INSERT INTO forum_categories (forumid, pageid, title, shoutbox) 
                                                        VALUES('$featureid', '$pageid', 'Shoutbox', 1)");
		$discussionid = execute_db_sql("INSERT INTO forum_discussions (catid, forumid, pageid, title, shoutbox)
                                                                VALUES('$catid', '$featureid', '$pageid', 'Shoutbox', 1)");
		execute_db_sql("INSERT INTO settings (type, pageid, featureid, setting_name, setting, extra, defaultsetting)
                                       VALUES('$type', '$pageid', '$featureid', 'feature_title', '$title', NULL, '$title'),
                                             ('$type', '$pageid', '$featureid', 'shoutboxlimit', '10', NULL, '10'),
                                             ('$type', '$pageid', '$featureid', 'postsperpage', '10', NULL, '10'),
                                             ('$type', '$pageid', '$featureid', 'discussionsperpage', '10', NULL, '10')");

		return $featureid;
	}
	return false;
}

function forum_delete($pageid, $featureid) {
	try {
		start_db_transaction();
        $templates = [];
        $templates[] = [
            "file" => "dbsql/forum.sql",
            "feature" => "forum",
            "subsection" => [
                "delete_forum",
                "delete_categories",
                "delete_discussions",
                "delete_posts",
            ],
        ];
        execute_db_sqls(fetch_template_set($templates), ["forumid" => $featureid]);

        $templates = [];
        $templates[] = [
            "file" => "dbsql/features.sql",
            "subsection" => [
                "delete_feature",
                "delete_feature_settings",
            ],
        ];
        execute_db_sqls(fetch_template_set($templates), ["featureid" => $featureid, "feature" => "forum", "pageid" => $pageid]);
		commit_db_transaction();
	} catch (\Throwable $e) {
		rollback_db_transaction($e->getMessage());
		trigger_error("Failed to delete forum.", E_USER_WARNING);
	}

	resort_page_features($pageid);
}

function forum_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
	$returnme = "";
	if (user_is_able($USER->userid, "createforumcategory", $pageid)) {
        $returnme .= make_modal_links([
			"title" => "Create Forum Category",
			"path" => action_path("forum") . "createforumcategory&amp;pageid=$pageid&amp;forumid=$featureid",
			"width" => "350",
			"height" => "200",
			"validate" => "true",
			"runafter" => "forum_refresh_$featureid",
			"image" => $CFG->wwwroot . '/images/add.png',
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
