<?php
/***************************************************************************
* html_ajax.php - HTML feature ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 1.9.6
***************************************************************************/

if (!isset($CFG)) { include('../header.php'); } 
if (!isset($HTMLLIB)) { include_once($CFG->dirroot . '/features/html/htmllib.php'); }

update_user_cookie();

callfunction();

function new_edition() {
global $CFG, $MYVARS;
	$htmlid = $MYVARS->GET["htmlid"];
	$pageid = $MYVARS->GET["pageid"];
	
	$html = get_db_row("SELECT * FROM html h JOIN pages_features pf ON pf.featureid=h.htmlid WHERE h.htmlid=$htmlid AND pf.feature='html'");
	$settings = fetch_settings("html", $htmlid, $pageid);
	$newhtmlid = insert_blank_html($pageid, $settings->html->$htmlid);	
	
	//Move new html to the previous location
	$SQL = "UPDATE pages_features SET area='" . $html["area"] . "',sort='" . $html["sort"] . "' WHERE feature='html' AND featureid=$newhtmlid";
	execute_db_sql($SQL);
	
	//Move old html to the locker
	$SQL = "UPDATE pages_features SET area='locker' WHERE feature='html' AND featureid=$htmlid";
	execute_db_sql($SQL);
	
	if (!$html["firstedition"]) { //This is the first edition
		//Set first edition field
		$SQL = "UPDATE html SET firstedition='$htmlid' WHERE htmlid=$newhtmlid";
		execute_db_sql($SQL);
	} else { //This is not a first edition
		//Set first edition field
		$SQL = "UPDATE html SET firstedition='" . $html["firstedition"] . "' WHERE htmlid=$newhtmlid";
		execute_db_sql($SQL);
	}
	
	//Copy settings
	$SQL = "SELECT * FROM settings WHERE type='html' AND featureid=$htmlid";
	if ($result = get_db_result($SQL)) {
		while ($row = fetch_row($result)) {
			copy_db_row($row,"settings","settingid=null,featureid=$newhtmlid");
		}
	}
}

function still_editing() {
global $CFG, $MYVARS;
	$htmlid = $MYVARS->GET["htmlid"];
	$userid = $MYVARS->GET["userid"];
	$now = get_timestamp();
	$SQL = "UPDATE html SET edit_user=$userid,edit_time=$now WHERE htmlid=$htmlid";
	execute_db_sql($SQL);
}

function stopped_editing() {
global $CFG, $MYVARS;
	$htmlid = $MYVARS->GET["htmlid"];
	$userid = $MYVARS->GET["userid"];
	$SQL = "UPDATE html SET edit_user=$userid,edit_time=0 WHERE htmlid=$htmlid";
	execute_db_sql($SQL);  
}

function edit_html() {
global $CFG, $MYVARS;
	$htmlid = $MYVARS->GET["htmlid"];
	$html = stripslashes($MYVARS->GET["html"]);
	
	//MS Word Cleaner HTMLawed
	//http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/more.htm
	
	include_once ($CFG->dirroot . '/scripts/wordcleaner.php');
	$html = htmLawed($html, array('comment' => 1, 'clean_ms_char' => 1, 'css_expression' => 1, 'keep_bad' => 0, 'make_tag_strict' => 1, 'schemes' => '*:*', 'valid_xhtml' => 1, 'balance' => 1));
	
	$html = dbescape(urldecode($html));
    $SQL = "UPDATE html SET html='$html', dateposted='" . get_timestamp() . "',edit_user=0,edit_time=0 WHERE htmlid='$htmlid'";
	if (execute_db_sql($SQL)) {
		//Log
		log_entry("html", $htmlid, "Edited");
		echo "HTML edited successfully";
	}
}

function commentspage() {
global $CFG, $MYVARS;
	echo get_html_comments($MYVARS->GET["htmlid"], $MYVARS->GET["pageid"], false, $MYVARS->GET["perpage"], $MYVARS->GET["pagenum"], false);
}

function deletecomment() {
global $CFG, $MYVARS;
	$commentid = $MYVARS->GET["commentid"];

	execute_db_sql("DELETE FROM html_replies WHERE commentid=$commentid");
	execute_db_sql("DELETE FROM html_comments WHERE commentid=$commentid");
	//Log
	log_entry("html", $commentid, "Delete Comment");
}

function makecomment() {
global $CFG, $MYVARS, $USER;
	$htmlid = $MYVARS->GET["htmlid"];
	$comment = dbescape(urldecode($MYVARS->GET["comment"]));
    $SQL = "INSERT INTO html_comments (comment,htmlid,userid) VALUES ('$comment', $htmlid,'" . $USER->userid . "')";
	if ($commentid = execute_db_sql($SQL)) {
		//Log
		log_entry("html", $commentid, "Blog Comment");
		echo "Blog comment made successfully";
	}
}

function editcomment() {
global $CFG, $MYVARS;
	$commentid = $MYVARS->GET["commentid"];
	$comment = dbescape(urldecode($MYVARS->GET["comment"]));
    $SQL = "UPDATE html_comments SET comment='$comment' WHERE commentid=$commentid";
	if (execute_db_sql($SQL)) {
		//Log
		log_entry("html", $replyid, "Blog Comment Edited");
		echo "Blog comment edited successfully";
	}
}

function makereply() {
global $CFG, $MYVARS, $USER;
	$commentid = $MYVARS->GET["commentid"];
	$reply = dbescape(urldecode($MYVARS->GET["reply"]));
    $SQL = "INSERT INTO html_replies (commentid,reply,userid) VALUES ($commentid,'$reply','" . $USER->userid . "')";
	if ($replyid = execute_db_sql($SQL)) {
		//Log
		log_entry("html", $replyid, "Blog Reply");
		echo "Blog reply made successfully";
	}
}

function editreply() {
global $CFG, $MYVARS;
	$replyid = $MYVARS->GET["replyid"];
	$reply = dbescape(urldecode($MYVARS->GET["reply"]));
	$SQL = "UPDATE html_replies SET reply='$reply' WHERE replyid=$replyid";
    if (execute_db_sql($SQL)) {
		//Log
		log_entry("html", $replyid, "Blog Reply Edited");
		echo "Blog reply edited successfully";
	}
}

function deletereply() {
global $CFG, $MYVARS;
	$replyid = $MYVARS->GET["replyid"];
    $SQL = "DELETE FROM html_replies WHERE replyid=$replyid";
	execute_db_sql($SQL);
	//Log
	log_entry("html", $replyid, "Delete Reply");
}
?>