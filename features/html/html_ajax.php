<?php
/***************************************************************************
* html_ajax.php - HTML feature ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.9.6
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

if (!defined('HTMLLIB')) { include_once($CFG->dirroot . '/features/html/htmllib.php'); }

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
			copy_db_row($row, "settings", "settingid=null,featureid=$newhtmlid");
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
	$html = htmLawed($html, ['comment' => 1, 'clean_ms_char' => 1, 'css_expression' => 1, 'keep_bad' => 0, 'make_tag_strict' => 1, 'schemes' => '*:*', 'valid_xhtml' => 1, 'balance' => 1]);
	
	$html = dbescape(urldecode($html));
    $SQL = "UPDATE html SET html='$html', dateposted='" . get_timestamp() . "',edit_user=0,edit_time=0 WHERE htmlid='$htmlid'";
	if (execute_db_sql($SQL)) {
		// Log
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

    // Has replies, so don't delete it, just remove data.
    if (get_db_result("SELECT * FROM html_comments WHERE parentid = '$commentid'")) {
        execture_db_sql("UPDATE html_comments SET comment = 'Removed', userid = 0 WHERE commentid = '$commentid'");
    } else {
        execute_db_sql("DELETE FROM html_comments WHERE commentid = '$commentid'");
    }

	// Log
    log_entry("html", $commentid, "Delete Comment");
}

function comment() {
global $CFG, $MYVARS, $USER;
    $commentid = $MYVARS->GET["commentid"] ?? false;
    $replytoid = $MYVARS->GET["replytoid"] ?? false;
    $comment = dbescape(urldecode($MYVARS->GET["comment"]));
    $time = get_timestamp();

    if ($commentid) {
        $SQL = "UPDATE html_comments
                SET comment = '$comment', modified = $time
                WHERE commentid = '$commentid'";
        if (execute_db_sql($SQL)) {
            log_entry("html", $commentid, "Blog Comment Edited");
            echo "Blog comment edited successfully";
        }
        return;
    }

    if ($replytoid) {
        $htmlid = get_db_field("htmlid", "html_comments", "commentid = '$replytoid'");
        $SQL = "INSERT INTO html_comments (parentid, comment, userid, htmlid, created, modified)
                VALUES ('$replytoid', '$comment', '" . $USER->userid . "', '$htmlid', $time, $time)";
        if ($commentid = execute_db_sql($SQL)) {
            log_entry("html", $commentid, "Blog Reply");
            echo "Blog reply made successfully";
        }
        return;
    }

    // New
    $htmlid = $MYVARS->GET["htmlid"] ?? false;
    if ($htmlid) {
        $SQL = "INSERT INTO html_comments (comment, userid, htmlid, created, modified)
        VALUES ('$comment', '" . $USER->userid . "', '$htmlid', $time, $time)";
        if ($commentid = execute_db_sql($SQL)) {
            log_entry("html", $commentid, "Blog Comment");
            echo "Blog comment made successfully";
        }
    }
}
?>