<?php
/***************************************************************************
* rss.php - RSS page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 1.1.2
***************************************************************************/
include('header.php');

callfunction();

echo '</body></html>';

function rss_subscribe_feature(){
global $CFG,$MYVARS,$USER;
	echo '
	 <script type="text/javascript">
	 var dirfromroot = "'.$CFG->directory.'";
	 </script>
	 <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'ajax/siteajax.js"></script>
     <link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'styles/styles_main.css" />
	 <input id="lasthint" type="hidden" />';
	$pageid = $MYVARS->GET["pageid"];
	$feature = $MYVARS->GET["feature"];
	$featureid = $MYVARS->GET["featureid"];
	$userid = $USER->userid;
	$userkey = get_db_field("userkey","users","userid=$userid");
	
	//User has already created rssid...just needs the link for it again.
	if($feed = get_db_row("SELECT * FROM rss_feeds WHERE pageid=$pageid AND type='$feature' AND featureid=$featureid AND rssid IN (SELECT rssid FROM rss WHERE userid=$userid)")){
		$rss = get_db_row("SELECT * FROM rss WHERE rssid=".$feed["rssid"]);
		echo '<div id="add_feed_div">
		<table style="width:100%; border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
			<tr><td><span style="float:left;"><strong>Edit RSS feed</strong></span> <a type="application/rss+xml" style="float:right;" href="'.$CFG->wwwroot.'/scripts/rss/rss.php?rssid='.$feed["rssid"].'&key='.$userkey.'"><img src="'.$CFG->wwwroot.'/images/small_rss.png" alt="RSS Feed" /> Subscribe</a> <br /><br />
				<br />
				<table style="width:100%">
					<tr>
						<td style="text-align:right;width:100px;">
							Feed Title:
						</td>
						<td style="text-align:left; width:280px;">
							<input type="text" id="rssname" size="40" maxlength="50" value="'.$rss["rssname"].'"/>
							<input type="hidden" id="rssid" value="'.$rss["rssid"].'" />
						</td>
						<td style="text-align:left; width:50px;">
							<input type="button" value="Save Changes" onclick="ajaxapi(\'/ajax/rss_ajax.php\',\'edit_name\',\'&amp;rssname=\' + escape(document.getElementById(\'rssname\').value) + \'&amp;rssid=\' + escape(document.getElementById(\'rssid\').value),function(){simple_display(\'saved\'); setTimeout(function(){ clear_display(\'saved\');},5000);});" />
						</td>
						<td>
						<div id="saved"></div>
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
		</div>';
	}else{ //Need to create new rssid and feed
		$settings = fetch_settings($feature,$featureid,$pageid);
		$title = $settings->$feature->$featureid->feature_title->setting;
		echo '<div id="add_feed_div">
		<table style="width:100%; border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
			<tr><td><span style="float:left;"><strong>Add RSS feed</strong></span><br /><br />
				<br />
				<table style="width:100%">
					<tr>
						<td style="text-align:right;width:100px;">
							Feed Title:
						</td>
						<td style="text-align:left; width:280px;">
							<input type="text" id="rssname" size="40" maxlength="50" value="'.$title.'"/>
						</td>
						<td style="text-align:left; width:50px;">
							<input type="button" value="Add Feed" onclick="ajaxapi(\'/ajax/rss_ajax.php\',\'add_feed\',\'&amp;key='.$userkey.'&amp;pageid='.$pageid.'&amp;type='.$feature.'&amp;featureid='.$featureid.'&amp;rssname=\' + escape(document.getElementById(\'rssname\').value),function(){simple_display(\'add_feed_div\');});" />
						</td>
						<td>
						<div id="saved"></div>
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
		</div>';
	}
}
?>