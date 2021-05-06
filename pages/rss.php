<?php
/***************************************************************************
* rss.php - RSS page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/05/2021
* Revision: 1.1.3
***************************************************************************/
include('header.php');

$params = array("dirroot" => $CFG->directory, "directory" => (empty($CFG->directory) ? '' : $CFG->directory . '/'), "wwwroot" => $CFG->wwwroot);
echo template_use("templates/page.template", $params, "page_js_css");

callfunction();

echo template_use("templates/page.template", array(), "end_of_page_template");

function rss_subscribe_feature(){
global $CFG, $MYVARS, $USER;
	$pageid = $MYVARS->GET["pageid"];
	$feature = $MYVARS->GET["feature"];
	$featureid = $MYVARS->GET["featureid"];
	$userid = $USER->userid;
	$userkey = get_db_field("userkey","users","userid='$userid'");

	// User has already created rssid...just needs the link for it again.
	$SQL = "SELECT *
						FROM rss_feeds
					 WHERE pageid='$pageid'
					   AND type='$feature'
						 AND featureid='$featureid'
						 AND rssid IN (SELECT rssid
							 							 FROM rss
														WHERE userid='$userid')";

	if ($feed = get_db_row($SQL)) {
		$SQL = "SELECT *
						  FROM rss
						 WHERE rssid='".$feed["rssid"]."'";

		$params = array('wwwroot' => $CFG->wwwroot, 'feed' => true, 'rss' => get_db_row($SQL), 'userkey' => $userkey);
		echo template_use("templates/rss.template", $params, "rss_subscribe_feature_template");
	} else { //Need to create new rssid and feed
		$settings = fetch_settings($feature,$featureid,$pageid);
		$title = $settings->$feature->$featureid->feature_title->setting;

		$params = array('wwwroot' => $CFG->wwwroot, 'feed' => false, 'title' => $title, 'userkey' => $userkey, 'pageid' => $pageid, 'feature' => $feature, 'featureid' => $featureid);
		echo template_use("templates/rss.template", $params, "rss_subscribe_feature_template");
	}
}
?>
