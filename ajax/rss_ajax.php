<?php
/***************************************************************************
* rss_ajax.php - RSS ajax backend script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 1.2.2
***************************************************************************/

include ('header.php');
update_user_cookie();

if(!isset($RSSLIB)){ include_once($CFG->dirroot . '/lib/rsslib.php'); }

callfunction();

function edit_name(){
global $MYVARS;
	
	$rssname = dbescape($MYVARS->GET["rssname"]);
	$rssid = $MYVARS->GET["rssid"];
    $SQL = "UPDATE rss SET rssname='$rssname' WHERE rssid=$rssid";
	if(execute_db_sql($SQL)){
		echo "Saved";
	}
}

function add_feed(){
global $CFG, $MYVARS, $USER;
	$pageid = $MYVARS->GET["pageid"];
	$type = $MYVARS->GET["type"];
	$featureid = $MYVARS->GET["featureid"];
	$userkey = $MYVARS->GET["key"];
	$rssname = dbescape($MYVARS->GET["rssname"]);
	
	if($rssid = execute_db_sql("INSERT INTO rss (userid,rssname) VALUES(".$USER->userid.",'".$rssname."')")){
		if(execute_db_sql("INSERT INTO rss_feeds (rssid,type,featureid,pageid) VALUES($rssid,'$type',$featureid,$pageid)")){
			echo '<div style="width:100%;text-align:center;">You have created an RSS feed.  Please click the \'Subscribe\' link to add the feed to your RSS reader. <br /><br /><a href="'.$CFG->wwwroot.'/scripts/rss/rss.php?rssid='.$rssid.'&key='.$userkey.'"><img src="'.$CFG->wwwroot.'/images/small_rss.png" alt="RSS Feed" /> Subscribe</a></div>';
		}
	}
}
?>