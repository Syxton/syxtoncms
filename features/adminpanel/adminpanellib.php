<?php
/***************************************************************************
* adminpanellib.php - Admin Panel function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 7/2/2012
* Revision: 0.7.10
***************************************************************************/

if (!isset($LIBHEADER)) if (file_exists('./lib/header.php')) { include('./lib/header.php'); }elseif (file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif (file_exists('../../lib/header.php')) { include('../../lib/header.php'); }
$ADMINPANEL = true;

function display_adminpanel($pageid, $area, $featureid) {
global $CFG, $USER, $ROLES, $ABILITIES;

    if (!$settings = fetch_settings("adminpanel", $featureid, $pageid)) {
		make_or_update_settings_array(default_settings("adminpanel", $pageid, $featureid));
		$settings = fetch_settings("adminpanel", $featureid, $pageid);
	}

	$title = $settings->adminpanel->$featureid->feature_title->setting;
	$content = "";
	$site = $pageid == $CFG->SITEID ? "Site " : "Page ";
	$abilities = get_user_abilities($USER->userid, $pageid,"roles");

    // File Manager
	$content .= user_has_ability_in_page($USER->userid,"manage_files", $pageid) ? '<div style="padding:1px;"><a title="Manage files" href="javascript: void(0);" onclick="window.open(\'./scripts/tinymce/plugins/filemanager/dialog.php?type=0&editor=mce_0/\',\'File Mananger\',\'modal,width=850,height=600\')"><img alt="Manage Files" src="'.$CFG->wwwroot.'/images/kfm.gif" style="vertical-align:bottom;" /> Manage Files</a></div>' : "";

    // Roles & Abilities Manager
	$p = [
		"title" => "Roles & Abilites Manager",
		"text" => "Roles & Abilites Manager",
		"path" => $CFG->wwwroot . "/pages/roles.php?action=manager&amp;pageid=$pageid",
		"width" => "700",
		"height" => "600",
		"iframe" => "true",
		"image" => $CFG->wwwroot . "/images/key.png",
		"styles" => "padding:1px;display:block;",
	];
	$content .= !empty($abilities->edit_roles->allow) || !empty($abilities->assign_roles->allow) || !empty($abilities->edit_user_abilities->allow) ? make_modal_links($p) : "";

    // Site Admin Area
    if (is_siteadmin($USER->userid)) {
		$p = [
			"title" => "Admin Area",
			"text" => "Admin Area",
			"path" => $CFG->wwwroot . "/features/adminpanel/adminpanel.php?action=site_administration&amp;pageid=$pageid",
			"iframe" => "true",
			"width"=> "95%",
			"height"=> "95%",
			"iframe" => "true",
			"image" => $CFG->wwwroot . "/images/admin.gif",
			"styles" => "padding:1px;display:block;",
		];
        $content .= user_has_ability_in_page($USER->userid,"addevents", $pageid) ? make_modal_links($p) : "";
    }

	$directory = $CFG->dirroot . "/features";
	if ($handle = opendir($directory)) {
	    /* This is the correct way to loop over the directory. */
	    while (false !== ($dir = readdir($handle))) {
	        if (!strstr($dir,".") && is_dir($directory . "/" . $dir)) {
			    include_once($directory . "/" . $dir . '/' . $dir . "lib.php");
				$action = $dir . "_adminpanel";
				if (function_exists("$action")) {
                    $content .= $action($pageid);
                }
	        }
	    }
	    //close the directory handler
		closedir($handle);
	}

    //$panelid = get_db_field("id","pages_features","feature='adminpanel' and pageid='$pageid'");
	$buttons = get_button_layout("adminpanel", $featureid, $pageid);
	$returnme = $content != "" ? get_css_box($title, $content, $buttons,NULL,"adminpanel", $featureid) : "";
	return $returnme;
}

function adminpanel_delete($pageid, $featureid) {
	$params = [
		"pageid" => $pageid,
		"featureid" => $featureid,
		"feature" => "adminpanel",
	];

	$SQL = template_use("dbsql/features.sql", $params, "delete_feature");
    execute_db_sql($SQL);
    $SQL = template_use("dbsql/features.sql", $params, "delete_feature_settings");
    execute_db_sql($SQL);

	resort_page_features($pageid);
}

function adminpanel_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
	$returnme = "";
	return $returnme;
}

function get_adminpanel_alerts($userid, $count_return = true) {
global $CFG;
	$alerts = 0;
	$display_alerts = "";

	//This section creates alerts for users who have requested entry into a page that the user has rights to add them to.
	if ($pages = user_has_ability_in_pages($userid, "assign_roles")) {
		while ($page = fetch_row($pages)) {
            $SQL = "SELECT * FROM roles_assignment WHERE pageid=".$page["pageid"]." AND confirm=1";
			if ($result = get_db_result($SQL)) {
			$alerts += get_db_count($SQL);
				if (!$count_return) {
                    $display_alerts .= $display_alerts == "" ? "<h2>The following people have requested page permission.</h2><br />" : "";
					while ($request = fetch_row($result)) { //Loops through all requests from a page.
						$display_alerts .= '<div id="userspan_'.$request["userid"].'_'.$request["pageid"].'">
                                                Allow '.get_user_name($request["userid"]) . " into " .get_db_field("name", "pages", "pageid=".$request["pageid"]) . "? ";
						$display_alerts .= '&nbsp; <a href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'allow_page_request\',\'&amp;requestid='.$request["assignmentid"].'&amp;approve=1\',function() { if (istrue()) { simple_display(\'userspan_'.$request["userid"].'_'.$request["pageid"].'\'); ajaxapi(\'/ajax/site_ajax.php\',\'refresh_user_alerts\',\'&amp;userid='.$userid.'\',function() {simple_display(\'user_alerts_div\');}); update_alerts(0); }}); " onmouseup="this.blur();"> [Yes]</a>';
				        $display_alerts .= '&nbsp; <a href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'allow_page_request\',\'&amp;requestid='.$request["assignmentid"].'&amp;approve=0\',function() { if (istrue()) { simple_display(\'userspan_'.$request["userid"].'_'.$request["pageid"].'\'); ajaxapi(\'/ajax/site_ajax.php\',\'refresh_user_alerts\',\'&amp;userid='.$userid.'\',function() {simple_display(\'user_alerts_div\');}); update_alerts(0); }});" onmouseup="this.blur();"> [No]</a></div>';
                        $display_alerts .= ' <br />';
                    }
				}
			}
		}
	}

	//This section creates alerts for invites that have been recieved by the user.
    $SQL = "SELECT *
			FROM roles_assignment
			WHERE userid = " . $userid.  "
			AND confirm = 2";
	if ($result = get_db_result($SQL)) {
		$alerts += get_db_count($SQL);
		if (!$count_return) {
            $display_alerts .= $display_alerts == "" ? "<h2>You have been invited!</h2><br />" : "";
			while ($invite = fetch_row($result)) { //Loops through all requests from a page.
				$display_alerts .= '<div id="pagespan_'.$invite["userid"].'_'.$invite["pageid"].'">
                                        Accept invitation to '.get_db_field("name", "pages", "pageid=" . $invite["pageid"]) . "? ";
				$display_alerts .= '&nbsp; <a href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'allow_page_request\',\'&amp;requestid='.$invite["assignmentid"].'&amp;approve=1\',function() { if (istrue()) { simple_display(\'userspan_'.$invite["userid"].'_'.$invite["pageid"].'\'); ajaxapi(\'/ajax/site_ajax.php\',\'refresh_user_alerts\',\'&amp;userid='.$userid.'\',function() {simple_display(\'user_alerts_div\');}); update_alerts(0); }}); " onmouseup="this.blur();"> [Yes]</a>';
				$display_alerts .= '&nbsp; <a href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'allow_page_request\',\'&amp;requestid='.$invite["assignmentid"].'&amp;approve=0\',function() { if (istrue()) { simple_display(\'userspan_'.$invite["userid"].'_'.$invite["pageid"].'\'); ajaxapi(\'/ajax/site_ajax.php\',\'refresh_user_alerts\',\'&amp;userid='.$userid.'\',function() {simple_display(\'user_alerts_div\');}); update_alerts(0); }});" onmouseup="this.blur();"> [No]</a></div>';
				$display_alerts .= ' <br />';
			}
		}
	}
	if ($count_return) {
	   return $alerts;
	} else {
	   return $display_alerts;
	}
}

function adminpanel_default_settings($type, $pageid, $featureid) {
	$settings = [
		[
			"type" => "$type",
			"pageid" => "$pageid",
			"featureid" => "$featureid",
			"setting_name" => "feature_title",
			"setting" => "Admin Panel",
			"extra" => false,
			"defaultsetting" => "Admin Panel",
			"display" => "Feature Title",
			"inputtype" => "text",
		],
	];

	return $settings;
}
?>
