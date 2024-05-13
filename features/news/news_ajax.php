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
global $CFG, $USER, $MYVARS;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $html = dbescape(urldecode($MYVARS->GET["html"]));
    $title = dbescape(urldecode($MYVARS->GET["title"]));
    $summary = dbescape(urldecode($MYVARS->GET["summary"]));

    $submitted = get_timestamp();
    $SQL = "INSERT INTO news (pageid,featureid,title,caption,content,submitted,userid) VALUES('$pageid', '$featureid', '$title', '$summary', '$html', '$submitted', '" . $USER->userid . "')";
	if (execute_db_sql($SQL)) { 
		create_rss($pageid);
		create_rss($pageid, $featureid);
		echo "News posted successfully";
	}
}

function update_archive_months() {
global $CFG, $USER, $MYVARS;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $year = $MYVARS->GET["year"];	
    $userid = is_logged_in() ? $USER->userid : false;
    $months = months_with_news($userid, $year, false, $pageid, $featureid);
    $lastrow = get_array_count($months, -1);
    $params = [
        "properties" => [
            "name" => "news_" . $featureid . "_archive_month",
            "id" => "news_" . $featureid . "_archive_month",
            "style" => "font-size:.8em;",
            "onchange" => 'ajaxapi(\'/features/news/news_ajax.php\',
                                    \'update_archive_articles\',
                                    \'&amp;year=\' + $(\'#news_' . $featureid . '_archive_year\').val() + \'&month=\' + this.value + \'&pageid=' . $pageid . '&featureid=' . $featureid . '\',
                                    function() { simple_display(\'article_span_' . $featureid . '_archive\'); });',
        ],
        "values" => $months,
        "valuename" => "month",
        "displayname" => "monthname",
        "selected" => $months->$lastrow->month,
    ];
    echo make_select($params);
}

function update_archive_articles() {
global $CFG, $USER, $MYVARS;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $month = $MYVARS->GET["month"];
    $year = $MYVARS->GET["year"];
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
    echo make_select($params);
}

function edit_news() {
global $CFG, $MYVARS;
    $newsid = dbescape($MYVARS->GET["newsid"]);
    $html = dbescape(urldecode($MYVARS->GET["html"]));
    $title = dbescape(urldecode($MYVARS->GET["title"]));
    $summary = dbescape(urldecode($MYVARS->GET["summary"]));
    $pageid = dbescape($MYVARS->GET["pageid"]);
    $edited = get_timestamp();
    $SQL = "UPDATE news SET content='$html', title='$title', caption='$summary', edited='$edited' WHERE newsid='$newsid'";

    if (execute_db_sql($SQL)) { 
  		echo "$pageid News edited successfully";
    } 
}

?>