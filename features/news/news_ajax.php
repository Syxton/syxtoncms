<?php
/***************************************************************************
* news_ajax.php - News backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/03/2013
* Revision: 2.4.5
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); } 
if (!isset($NEWSLIB)) { include_once($CFG->dirroot . '/features/news/newslib.php'); }

update_user_cookie();

callfunction();

function add_news() {
global $CFG,$USER,$MYVARS;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $html = dbescape(urldecode($MYVARS->GET["html"]));
    $title = dbescape(urldecode($MYVARS->GET["title"]));
    $summary = dbescape(urldecode($MYVARS->GET["summary"]));
    $section = get_db_field("section_title","news_features","featureid='$featureid' AND pageid='$pageid'");
    $submitted = get_timestamp();
    $SQL = "INSERT INTO news (pageid,featureid,section,title,caption,content,submitted,userid) VALUES('$pageid','$featureid','$section','$title','$summary','$html','$submitted','".$USER->userid."')";
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
    $lastrow = get_array_count($months,-1);
    echo make_select_from_array("news_".$featureid."_archive_month", $months, "month", "monthname", $months->$lastrow->month ,NULL, 'onchange="ajaxapi(\'/features/news/news_ajax.php\',\'update_archive_articles\',\'&amp;year=\'+document.getElementById(\'news_'.$featureid.'_archive_year\').value+\'&month=\'+this.value+\'&pageid='.$pageid.'&featureid='.$featureid.'\',function() { simple_display(\'article_span_'.$featureid.'_archive\');});"',NULL,NULL,'font-size:.8em;');
}

function update_archive_articles() {
global $CFG, $USER, $MYVARS;
    $pageid = $MYVARS->GET["pageid"];
    $featureid = $MYVARS->GET["featureid"];
    $month = $MYVARS->GET["month"];
    $year = $MYVARS->GET["year"];
    $userid = is_logged_in() ? $USER->userid : false;	
    $newsarticles = get_month_news($userid, $year, $month, false, $pageid, $featureid);
    echo make_select_from_array("news_".$featureid."_archive_news", $newsarticles, "newsid", "title", NULL ,NULL, '',NULL,NULL,'font-size:.8em;');
}

function edit_news() {
global $CFG,$MYVARS;
    $newsid = $MYVARS->GET["newsid"];
    $html = dbescape(urldecode($MYVARS->GET["html"]));
    $title = dbescape(urldecode($MYVARS->GET["title"]));
    $summary = dbescape(urldecode($MYVARS->GET["summary"]));
    $pageid = $MYVARS->GET["pageid"];
    $edited = get_timestamp();
    $SQL = "UPDATE news SET content='$html', title='$title', caption='$summary', edited='$edited' WHERE newsid='$newsid'";
    if (execute_db_sql($SQL)) { 
    	create_rss($pageid);
    	create_rss($pageid, get_db_field("featureid","news","newsid='$newsid'"));
    	echo "$pageid News edited successfully";
    } 
}

function edit_news_section() {
global $CFG,$MYVARS;
    $featureid = $MYVARS->GET["featureid"];
    $limit = dbescape(urldecode($MYVARS->GET["limit"]));
    $title = dbescape(urldecode($MYVARS->GET["title"]));
    $SQL1 = "UPDATE news_features SET limit_viewable='$limit', section_title='$title' WHERE featureid='$featureid'";
    $SQL2 = "UPDATE news SET section='$title' WHERE featureid='$featureid'";
    if (execute_db_sql($SQL1) && execute_db_sql($SQL2)) { echo "News Section edited successfully"; }
}
?>