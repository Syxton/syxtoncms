<?php
/***************************************************************************
* forum.php - Forum modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.2.8
***************************************************************************/

if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

    if (!defined('FORUMLIB')) { include_once($CFG->dirroot . '/features/forum/forumlib.php'); }

    echo fill_template("tmp/page.template", "start_of_page_template");

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");
}

function forum_settings() {
	$featureid = clean_myvar_opt("featureid", "int", false); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$feature = "forum";

	//Default Settings
	$default_settings = default_settings($feature, $pageid, $featureid);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { forum_settings(); }
	}
}

function createcategory() {
global $CFG, $USER;
	if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }
	$forumid = clean_myvar_req("forumid", "int");
	$pageid = get_db_field("pageid", "forum", "forumid = ||forumid||", ["forumid" => $forumid]);

	$returnme = $error = "";
	try {
		if ($forumid && $pageid) {
			if (!user_is_able($USER->userid, "createforumcategory", $pageid)) {
				trigger_error(error_string("no_permission", ["createforumcategory"]), E_USER_WARNING);
				return;
			}
			if (!user_is_able($USER->userid, "createforumcategory", $pageid)) {
				trigger_error(error_string("generic_permissions"), E_USER_WARNING);
				return;
			}
			$script = ajaxapi([
				"url" => "/features/forum/forum_ajax.php",
				"data" => ["action" => "create_category", "forumid" => $forumid, "title" => "js||encodeURIComponent($('#title').val()) ||js"],
				"display" => "category_div_$forumid",
				"ondone" => "close_modal();",
			], "code");
			$returnme = create_validation_script("new_category_form" , $script);
			$params = [
				"formid" => "new_category_form",
				"forumid" => $forumid,
				"help" => get_help("new_category"),
			];
			$returnme .= '<h3>Create Category</h3>';
			$returnme .= fill_template("tmp/forum.template", "category_form", "forum", $params);
		}
	} catch (\Throwable $e) {
		throw new Exception($e->getMessage());
		$error = $e->getMessage();
	}

	ajax_return($returnme, $error);
}

function editcategory() {
global $CFG, $USER;
	if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }
	$catid = clean_myvar_req("catid", "int");
	try {
		$category = get_db_row("SELECT * FROM forum_categories WHERE catid = ||catid||", ["catid" => $catid]);
		$forumid = $category["forumid"];
		$pageid = $category["pageid"];

		$returnme = $error = "";

		if (!user_is_able($USER->userid, "editforumcategory", $pageid)) {
			trigger_error(error_string("no_permission", ["editforumcategory"]), E_USER_WARNING);
			return;
		}

		$script = ajaxapi([
			"url" => "/features/forum/forum_ajax.php",
			"data" => ["action" => "edit_category", "catid" => $catid, "title" => "js||encodeURIComponent($('#title').val()) ||js"],
			"display" => "category_div_$forumid",
			"ondone" => "close_modal();",
		], "code");
		$returnme = create_validation_script("edit_category_form" , $script);
		$params = [
			"formid" => "edit_category_form",
			"forumid" => $forumid,
			"title" => $category["title"],
			"help" => get_help("new_category"),
		];
		$returnme .= '<h3>Edit Category</h3>';
		$returnme .= fill_template("tmp/forum.template", "category_form", "forum", $params);
	} catch (\Throwable $e) {
		throw new Exception($e->getMessage());
		$error = $e->getMessage();
	}

	ajax_return($returnme, $error);
}

function edit_post_form() {
global $CFG;
	$postid = clean_myvar_opt("postid", "int", false);
	$message = get_db_field("message", "forum_posts", "postid='$postid'");

	$post = get_db_row(fetch_template("dbsql/forum.sql", "get_post", "forum"), ["postid" => $postid]);

	$editorcontent = get_editor_value_javascript();
	ajaxapi([
		"id" => "edit_post_submit",
		"if" => "$editorcontent.length > 0",
		"url" => "/features/forum/forum_ajax.php",
		"data" => ["action" => "edit_post", "postid" => $postid, "message" => "js||encodeURIComponent($editorcontent) ||js"],
		"ondone" => "close_modal();",
		"event" => "submit",
	]);

	$params = [
		"author" => ($post["userid"] ? get_user_name($post["userid"]) : ($post["alias"] ? $post["alias"] : "Anonymous")),
		"time" => ago($post["posted"], true),
		"formid" => "edit_post_submit",
		"editor" => get_editor_box(["initialvalue" => $message, "type" => "Forum", "height" => "calc(100% - 75px)"]),
	];
	ajax_return(fill_template("tmp/forum.template", "edit_post_form", "forum", $params));
}

function quote_post_form() {
global $CFG;
	$quotepost = clean_myvar_req("quotepost", "int");
	$post = get_db_row(fetch_template("dbsql/forum.sql", "get_post", "forum"), ["postid" => $quotepost]);

	$editorcontent = get_editor_value_javascript();
	ajaxapi([
		"id" => "quote_post_submit",
		"if" => "$editorcontent.length > 0",
		"url" => "/features/forum/forum_ajax.php",
		"data" => ["action" => "quote_post", "quotepost" => $quotepost, "message" => "js||encodeURIComponent($editorcontent) ||js"],
		"ondone" => "close_modal();",
		"event" => "submit",
	]);

	$quoteparams = [
		"author" => ($post["userid"] ? get_user_name($post["userid"]) : ($post["alias"] ? $post["alias"] : "Anonymous")),
		"time" => ago($post["posted"], true),
		"quotemessage" => truncate($post["message"], 300),
	];
	$params = [
		"quote" => fill_template("tmp/forum.template", "forum_quote", "forum", $quoteparams),
		"formid" => "quote_post_submit",
		"editor" => get_editor_box(["initialvalue" => '', "type" => "Forum", "height" => "calc(100% - 75px)"]),
	];

	ajax_return(fill_template("tmp/forum.template", "quote_post_form", "forum", $params));
}

function post_form() {
global $CFG;
	$replyto = clean_myvar_opt("replyto", "int", false);
	$discussionid = clean_myvar_opt("discussionid", "int", false);

	$error = "";
	try {
		if (!$replyto && !$discussionid) {
			throw new \Exception("Not enough information was provided to post.");
		}

		$editorcontent = get_editor_value_javascript();
		$data = ["action" => "post", "message" => "js||encodeURIComponent($editorcontent) ||js"];

		if ($replyto) {
			$data["replyto"] = $replyto;
		} elseif ($discussionid) {
			$data["discussionid"] = $discussionid;
		}

		ajaxapi([
			"id" => "post_submit",
			"if" => "$editorcontent.length > 0",
			"url" => "/features/forum/forum_ajax.php",
			"data" => $data,
			"ondone" => "close_modal();",
			"event" => "submit",
		]);

		$params = [
			"formid" => "post_submit",
			"editor" => get_editor_box(["initialvalue" => '', "type" => "Forum", "height" => "calc(100% - 75px)"]),
		];
		ajax_return(fill_template("tmp/forum.template", "post_form", "forum", $params));
	} catch (\Exception $e) {
		ajax_return("", debugging($e->getMessage()));
	}
}

function shoutbox_editor() {
global $CFG, $USER;
	$forumid = clean_myvar_req("forumid", "int");
	$userid = isset($USER->userid) && $USER->userid > 0 ? $USER->userid : "";
	$editorcontent = get_editor_value_javascript();
	ajaxapi([
		"id" => "shoutbox_post_submit",
		"if" => "$editorcontent.length > 0",
		"url" => "/features/forum/forum_ajax.php",
		"data" => ["action" => "shoutbox_post", "forumid" => $forumid, "alias" => "js|| $('#alias').val() ||js", "userid" => "js|| $('#userid').val() ||js", "message" => "js||encodeURIComponent($editorcontent) ||js"],
		"ondone" => "close_modal();",
		"event" => "submit",
	]);
	$params = [
		"editor" => get_editor_box(["type" => "Shoutbox", "height" => "calc(100% - 75px)", "charlimit" => 500]),
		"formid" => "shoutbox_post_submit",
		"userid" => $userid,
		"alias" => empty($userid),
		"wwwroot" => $CFG->wwwroot,
	];
    ajax_return(fill_template("tmp/forum.template", "shoutbox_form", "forum", $params));
}

function edit_discussion_form() {
global $CFG;
	$discussionid = clean_myvar_req("discussionid", "int");
	$discussion = get_db_row(fetch_template("dbsql/forum.sql", "get_discussion", "forum"), ["discussionid" => $discussionid]);
	$catid = $discussion["catid"];

	$postid = first_post($discussionid);
	$message = get_db_field("message", "forum_posts", "postid = ||postid|| ORDER BY postid", ["postid" => $postid]);
	$title = $discussion["title"];

	$editor = get_editor_box(["initialvalue" => $message, "type" => "Forum", "height" => "calc(100% - 75px)"]);
	$editorcontent = get_editor_value_javascript();

	ajaxapi([
		"id" => "edit_discussion_submit",
		"if" => "$editorcontent.length > 0 && $('#discussion_title').val().length > 0",
		"url" => "/features/forum/forum_ajax.php",
		"data" => ["action" => "edit_discussion", "postid" => $postid, "discussionid" => $discussionid, "message" => "js||encodeURIComponent($editorcontent) ||js", "title" => "js||encodeURIComponent($('#discussion_title').val()) ||js"],
		"ondone" => "close_modal();",
		"event" => "submit",
	]);

	$params = [
		"formid" => "edit_discussion_submit",
		"wwwroot" => $CFG->wwwroot,
		"editor" => $editor,
		"title" => $title,
	];
	echo fill_template("tmp/forum.template", "discussion_form", "forum", $params);
}

function create_discussion_form() {
global $CFG;
	$catid = clean_myvar_req("catid", "int");

	$editor = get_editor_box(["initialvalue" => "", "type" => "Forum", "height" => "calc(100% - 75px)"]);
	$editorcontent = get_editor_value_javascript();
	ajaxapi([
		"id" => "create_discussion_submit",
		"if" => "$editorcontent.length > 0 && $('#discussion_title').val().length > 0",
		"url" => "/features/forum/forum_ajax.php",
		"data" => ["action" => "create_discussion", "catid" => $catid, "message" => "js||encodeURIComponent($editorcontent) ||js", "title" => "js||encodeURIComponent($('#discussion_title').val()) ||js"],
		"ondone" => "close_modal();",
		"event" => "submit",
	]);
	$params = [
		"formid" => "create_discussion_submit",
		"wwwroot" => $CFG->wwwroot,
		"editor" => $editor,
		"title" => "",
	];
	echo fill_template("tmp/forum.template", "discussion_form", "forum", $params);
}

?>
