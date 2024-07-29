<?php
/***************************************************************************
* news_ajax.php - News backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.4.5
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

if (!defined('NEWSLIB')) { include_once($CFG->dirroot . '/features/news/newslib.php'); }

update_user_cookie();

callfunction();

function add_news() {
global $USER;
    $featureid = clean_myvar_req("featureid", "int");
    $html = clean_myvar_req("html", "html");
    $title = clean_myvar_req("title", "html");
    $summary = clean_myvar_req("summary", "html");
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    $error = "";
    try {
        $params = [
            "pageid" => $pageid,
            "content" => $html,
            "title" => $title,
            "summary" => $summary,
            "submitted" => get_timestamp(),
            "featureid" => $featureid,
            "userid" => $USER->userid,
        ];
        $SQL = fetch_template("dbsql/news.sql", "insert_news", "news");
        if (!execute_db_sql($SQL, $params)) {
            throw new Exception("Could not create news article");
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return("", $error);
}


function edit_news() {
    $newsid = clean_myvar_req("newsid", "int");
    $html = clean_myvar_req("html", "html");
    $title = clean_myvar_req("title", "html");
    $summary = clean_myvar_req("summary", "html");
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    $error = "";
    try {
        $params = [
            "content" => $html,
            "title" => $title,
            "summary" => $summary,
            "edited" => get_timestamp(),
            "newsid" => $newsid,
        ];
        $SQL = fetch_template("dbsql/news.sql", "update_news", "news");
        if (!execute_db_sql($SQL, $params)) {
            throw new Exception("Could not update news article");
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    ajax_return("", $error);
}

function update_archive_months() {
global $CFG, $USER, $MYVARS;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $featureid = clean_myvar_opt("featureid", "int", false);
    $year = clean_myvar_opt("year", "int", false);

    $userid = is_logged_in() ? $USER->userid : false;
    $months = months_with_news($userid, $year, false, $pageid, $featureid);
    $lastrow = get_array_count($months, -1);
    ajaxapi([
        "id" => "news_" . $featureid . "_archive_month",
        "url" => "/features/news/news_ajax.php",
        "data" => [
            "action" => "update_archive_articles",
            "featureid" => $featureid,
            "pageid" => $pageid,
            "year" => "js||$('#news_" . $featureid . "_archive_year').val()||js",
            "month" => "js||$('#news_" . $featureid . "_archive_month').val()||js",
        ],
        "event" => "change",
        "display" => "article_span_" . $featureid . "_archive",
    ]);

    $params = [
        "properties" => [
            "name" => "news_" . $featureid . "_archive_month",
            "id" => "news_" . $featureid . "_archive_month",
            "style" => "font-size:.8em;",
        ],
        "values" => $months,
        "valuename" => "month",
        "displayname" => "monthname",
        "selected" => $months->$lastrow->month,
    ];
    ajax_return(make_select($params));
}

function update_archive_articles() {
global $CFG, $USER, $MYVARS;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $featureid = clean_myvar_opt("featureid", "int", false);
    $month = clean_myvar_opt("month", "int", false);
    $year = clean_myvar_opt("year", "int", false);
    $userid = is_logged_in() ? $USER->userid : false;
    $newsarticles = get_month_news($userid, $year, $month, false, $pageid, $featureid);
    $params = [
        "properties" => [
            "name" => "news_" . $featureid . "_archive_news",
            "id" => "news_" . $featureid . "_archive_news",
            "style" => "font-size:.8em;",
        ],
        "values" => $newsarticles,
        "valuename" => "newsid",
        "displayname" => "title",
    ];
    ajax_return(make_select($params));
}
?>