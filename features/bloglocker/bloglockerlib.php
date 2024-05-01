<?php
/***************************************************************************
* bloglockerlib.php - Blog Locker function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2013
* Revision: 0.9.1
***************************************************************************/

if (!isset($LIBHEADER)) {
	$sub = './';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == './' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php'); 
}
$BLOGLOCKERLIB = true;

//BLOGLOCKERLIB Config
$CFG->bloglocker = new \stdClass; 
$CFG->bloglocker->viewable_limit = 20;

function display_bloglocker($pageid, $area, $featureid) {
global $CFG, $USER, $ROLES;

	$content="";

	if (!$settings = fetch_settings("bloglocker", $featureid, $pageid)) {
		make_or_update_settings_array(default_settings("bloglocker", $pageid, $featureid));
		$settings = fetch_settings("bloglocker", $featureid, $pageid);
	}

	$title = $settings->bloglocker->$featureid->feature_title->setting;
	$viewable_limit = $settings->bloglocker->$featureid->viewable_limit->setting;
	
	if (get_db_count("SELECT * FROM pages_features pf WHERE pf.pageid='$pageid' AND pf.feature='html' AND pf.area='locker'")) {
		if (user_has_ability_in_page($USER->userid, "viewbloglocker", $pageid)) {
			$lockeritems = get_bloglocker($pageid);
			$i = 0;
			foreach ($lockeritems as $lockeritem) {
				if (++$i > $viewable_limit) { break; }
				$content .= '<span style="color:gray;font-size:.75em;">' .
								date('m/d/Y', $lockeritem->dateposted) .
							' </span>';
				$p = [
					"title" => $lockeritem->title,
					"path" => $CFG->wwwroot . "/features/bloglocker/bloglocker.php?action=view_locker&amp;pageid=$pageid&amp;htmlid=" . $lockeritem->htmlid,
				];
				$content .= make_modal_links($p);
				if (!$lockeritem->blog && is_logged_in() && user_has_ability_in_page($USER->userid, "addtolocker", $pageid)) {
					$content .= '<a title="Move back to its original state" href="#" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=html&amp;featureid=' . $lockeritem->htmlid . '&amp;direction=middle\',function() { update_login_contents(' . $pageid . ');});">
									<img src="' . $CFG->wwwroot . '/images/undo.png" alt="Move feature to the middle area" />
								 </a>';
				}	
				$content .= '<br />';
			}
			$buttons = get_button_layout("bloglocker", $featureid, $pageid); 
			return get_css_box($title, $content, $buttons, NULL, "bloglocker", $featureid);
		}
	} else {
		$buttons = get_button_layout("bloglocker", $featureid, $pageid); 
		return get_css_box($title,"The blog locker is empty at this time.", $buttons,NULL,"bloglocker", $featureid);
	}
}

function get_bloglocker($pageid) {
global $CFG;
	$SQL = "SELECT * FROM pages_features pf INNER JOIN html h ON h.htmlid=pf.featureid WHERE pf.pageid='$pageid' AND pf.feature='html' AND pf.area='locker' ORDER BY h.dateposted DESC";

	$i=0;
	if ($result = get_db_result($SQL)) {
        $lockeritems = new \stdClass; 
		while ($row = fetch_row($result)) {
			$featureid = $row["htmlid"];
			
			if (!$settings = fetch_settings("html", $featureid, $pageid)) {
				make_or_update_settings_array(default_settings("html", $pageid, $featureid));
				$settings = fetch_settings("html", $featureid, $pageid);
			}
            
            $lockeritems->$i = new \stdClass;
			$lockeritems->$i->htmlid = $featureid;
			$lockeritems->$i->blog = $settings->html->$featureid->blog->setting;
			$lockeritems->$i->title = $settings->html->$featureid->feature_title->setting;
			$lockeritems->$i->dateposted = $row["dateposted"];
			$i++;	
		}
		return $lockeritems;
	}
	return false;
}

function bloglocker_delete($pageid, $featureid) {
	$params = [
		"pageid" => $pageid,
		"featureid" => $featureid,
		"feature" => "bloglocker",
	];

	$SQL = template_use("dbsql/features.sql", $params, "delete_feature");
    execute_db_sql($SQL);
    $SQL = template_use("dbsql/features.sql", $params, "delete_feature_settings");
    execute_db_sql($SQL);

	resort_page_features($pageid);
}

function bloglocker_buttons($pageid, $featuretype, $featureid) {
	global $CFG, $USER;
	$returnme = "";
	return $returnme;
}

function bloglocker_default_settings($type, $pageid, $featureid) {
	$settings = [
		[
			"type" => "$type",
			"pageid" => "$pageid",
			"featureid" => "$featureid",
			"setting_name" => "feature_title",
			"setting" => "Blog Locker",
			"extra" => false,
			"defaultsetting" => "Blog Locker",
			"display" => "Feature Title",
			"inputtype" => "text",
		],
        [
            "type" => "$type",
            "pageid" => "$pageid",
            "featureid" => "$featureid",
            "setting_name" => "viewable_limit",
            "setting" => "20",
            "extra" => false,
            "defaultsetting" => "20",
            "display" => "Viewable Blog Limit",
            "inputtype" => "text",
			"numeric" => true,
			"validation" => "<=0",
			"warning" => "Must be greater than 0.",
        ]
	];

	return $settings;
}
?>