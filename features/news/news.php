<?php
/***************************************************************************
* news.php - News thickbox page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.4.0
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
        $sub = '';
        while (!file_exists($sub . 'header.php')) {
            $sub = $sub == '' ? '../' : $sub . '../';
        }
        include($sub . 'header.php');
    }

    if (!defined('NEWSLIB')) { include_once($CFG->dirroot . '/features/news/newslib.php');}

    echo fill_template("tmp/page.template", "start_of_page_template");

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");
}

function news_settings() {
global $CFG, $MYVARS, $USER;
    $featureid = clean_myvar_opt("featureid", "int", false); $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $feature = "news";

    //Default Settings
    $default_settings = default_settings($feature, $pageid, $featureid);

    //Check if any settings exist for this feature
    if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
    } else { //No Settings found...setup default settings
        if (save_batch_settings($default_settings)) { news_settings(); }
    }
}

function addeditnews() {
global $CFG, $MYVARS, $USER;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $featureid= empty($MYVARS->GET["featureid"]) ? false : $MYVARS->GET["featureid"];
    $newsid= empty($MYVARS->GET["newsid"]) ? false : $MYVARS->GET["newsid"];

    $title = $caption = $content = "";
    if ($newsid) {
        if (!user_is_able($USER->userid, "editnews", $pageid,"news", $featureid)) {
            trigger_error(getlang("no_permission", false, ["editnews"]), E_USER_WARNING);
            return;
        }

        $row = get_db_row("SELECT * FROM news WHERE newsid='$newsid'");
        $title = $row["title"];
        $summary = $row["caption"];
        $content = $row["content"];

        ajaxapi([
            "id" => "news_" . $featureid . "_form",
            "url" => "/features/news/news_ajax.php",
            "data" => [
                "action" => "edit_news",
                "newsid" => $newsid,
                "pageid" => $pageid,
                "html" => "js||encodeURIComponent(" . get_editor_value_javascript() . ")||js",
                "summary" => "js||encodeURIComponent($('#news_summary').val())||js",
                "title" => "js||encodeURIComponent($('#news_title').val())||js",
            ],
            "event" => "submit",
            "ondone" => "close_modal();",
        ]);
    } else {
        if (!user_is_able($USER->userid, "addnews", $pageid,"news", $featureid)) {
            trigger_error(getlang("no_permission", false, ["addnews"]), E_USER_WARNING);
            return;
        }

        $title = $summary = $content = "";
        ajaxapi([
            "id" => "news_" . $featureid . "_form",
            "url" => "/features/news/news_ajax.php",
            "data" => [
                "action" => "add_news",
                "featureid" => $featureid,
                "pageid" => $pageid,
                "html" => "js||encodeURIComponent(" . get_editor_value_javascript() . ")||js",
                "summary" => "js||encodeURIComponent($('#news_summary').val())||js",
                "title" => "js||encodeURIComponent($('#news_title').val())||js",
            ],
            "event" => "submit",
            "ondone" => "close_modal();",
        ]);
    }

    $editor = get_editor_box(["initialvalue" => $content, "type" => "News", "height" => "230"]);
    echo fill_template("tmp/news.template", "news_form", "news", ["featureid" => $featureid, "title" => $title, "summary" => $summary, "editor" => $editor]);
}

function viewnews() {
global $CFG, $MYVARS, $USER, $ROLES;
    $newsid = $MYVARS->GET['newsid'];
    $pageid = clean_myvar_req("pageid", "int");
    $newsonly = isset($MYVARS->GET['newsonly']) ? true : false;
    if (is_logged_in()) {
          if (!user_is_able($USER->userid, "viewnews", $pageid, "news", $newsid)) {
            trigger_error(getlang("no_permission", false, ["viewnews"]), E_USER_WARNING);
            return;
        } else {
            echo news_wrapper($newsid, $pageid, $newsonly);
        }
    } else {
        if (get_db_field("siteviewable", "pages", "pageid=$pageid") && role_is_able($ROLES->visitor, 'viewnews', $pageid)) {
            echo news_wrapper($newsid, $pageid, $newsonly);
        } else {
            $url = "/features/news/news.php?action=viewnews&pageid=$pageid&newsid=$newsid";
            $content = fill_template("tmp/pagelib.template", "reroute_after_login", false, ["url" => $url]);
            echo simple_page($content, false, false);
        }
    }
}

function news_wrapper($newsid, $pageid, $newsonly) {
global $CFG;
    $news = get_db_row("SELECT * FROM news WHERE newsid=||newsid||", ["newsid" => $newsid]);
    if (!$news) {
        trigger_error("News item not found.", E_USER_WARNING);
        return;
    }

    $news["content"] = fill_template("tmp/news.template", "news_wrapper", "news", ["news" => $news]);

    if ($newsonly) {
        return $news["content"];
    } else {
        $params = [
            "mainmast" => page_masthead(false, false),
            "middlecontents" => $news["content"],
        ];
        return  get_js_tags(["siteajax"]) .
                get_css_set("main") .
                fill_template("tmp/index.template", "simplelayout_template", false, $params);
    }
}
?>
