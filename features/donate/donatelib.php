<?php
/***************************************************************************
* donatelib.php - donate feature library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/04/2013
* Revision: 1.1.5
***************************************************************************/
if (!isset($LIBHEADER)) {
	$sub = './';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == './' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php'); 
}
$donateLIB = true;
	
function display_donate($pageid, $area, $featureid) {
global $CFG, $USER, $donateSETTINGS;
	$abilities = user_abilities($USER->userid, $pageid,"donate", "donate", $featureid);
	if (!$settings = fetch_settings("donate", $featureid, $pageid)) {
		save_batch_settings(default_settings("donate", $pageid, $featureid));
		$settings = fetch_settings("donate", $featureid, $pageid);
	}
    
    if (!empty($abilities->makedonation->allow)) {
        return get_donate($pageid, $featureid, $settings, $abilities, $area);
    }
}

function get_donate($pageid, $featureid, $settings, $abilities, $area=false, $resultsonly=false) {
global $CFG, $USER;
	$returnme = ""; $rss = "";
    $SQL = use_template("dbsql/donate.sql", ["featureid" => $featureid], "get_donate_instance", "donate");
	if ($result = get_db_result($SQL)) {
		while ($row = fetch_row($result)) {
            //if viewing from rss feed
			if ($resultsonly) { 
                $returnme .= '<table style="width:100%;border:1px solid silver;padding:10px;"><tr><th>' . $settings->donate->$featureid->feature_title->setting . '</th></tr><tr><td><br /><br /><div class="htmlblock">' .get_donation_results($row["id"]) . '</div></td></tr></table>'; 
            } else { //regular donate feature viewing
                $buttons = get_button_layout("donate", $featureid, $pageid);
				$returnme .= get_css_box($settings->donate->$featureid->feature_title->setting,'<div class="htmlblock">' . donation_form($featureid, $settings) . '</div>', $buttons, null, 'donate', $featureid, false, false, false, false, false, false);
			}
		}
	}
	return $returnme;
}

function donation_form($featureid, $settings) {
global $CFG;
    $returnme = "";

    $protocol = get_protocol();
    $SQL = use_template("dbsql/donate.sql", ["featureid" => $featureid], "get_campaign", "donate");
    if ($campaign = get_db_row($SQL)) {
        if ($CFG->paypal) { 
            $paypal = 'www.paypal.com';
        } else { 
            $paypal = 'www.sandbox.paypal.com';
        }
        
        $SQL = use_template("dbsql/donate.sql", ["campaignid" => $campaign["campaign_id"]], "get_campaign_donations_total", "donate");
        if ($donations = get_db_row($SQL)) {
            $total = $donations["total"];
            $total = empty($total) ? "0" : $total;
        }

        $returnme .= get_css_tags(["features/donate/donate.css"]);
        $returnme .= get_js_tags(["features/donate/donate.js"]);

        $button = '
        <form action="https://' . $paypal . '/cgi-bin/webscr" method="post">
    	       <div style="width: 100%; text-align: center;">
    		      <input name="cmd" type="hidden" value="_donations" />
                  <input name="business" type="hidden" value="' . $campaign["paypal_email"] . '" />
                  <input name="item_name" type="hidden" value="' . $campaign["title"] . '" />
                  <input name="item_number" type="hidden" value="DONATE" />
                  <input name="custom" type="hidden" value="' . $campaign["campaign_id"] . '" />
                  <input name="no_shipping" type="hidden" value="1" />
                  <input name="return" type="hidden" value="' . $CFG->wwwroot . '/features/donate/donate.php?action=thankyou" />
                  <input name="notify_url" type="hidden" value="' . $protocol.$CFG->wwwroot . '/features/donate/ipn.php" />
                  <input name="currency_code" type="hidden" value="USD" />
                  <input name="tax" type="hidden" value="0" />
                  <input name="rm" type="hidden" value="2" />
                  <input name="lc" type="hidden" value="US" />
                  <input name="bn" type="hidden" value="Donate_WPS_US" />
                  <br />
                  <input alt="PayPal - The safer, easier way to pay online!" name="submit" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" style="border: 0px none ;" type="image" /> <img alt="" height="1" src="https://www.paypal.com/en_US/i/scr/pixel.gif" style="border: 0px none ;" width="1" />
            </div>
        </form>'; 
    
        $returnme .= donate_meter($campaign, $total, $button, $settings->donate->$featureid->metertype->setting);        
    } else { // Not setup yet
        $returnme .= 'You must first setup a donation campaign.<br />';    
    }

    
    return $returnme;    
}

function donate_meter($campaign, $total, $button, $type = "horizontal") {    
$returnme = "";
    if ($campaign["metgoal"] == 1 || (round($total / $campaign["goal_amount"],2) * 100) > 100) {
        $perc = "100";
    } else {
        $perc = round($total / $campaign["goal_amount"],2) * 100;    
    }
    
    switch ($type) {
        case "vertical":
            $graph = '
                <div id="thermometer" class="thermometer">
                    <div class="track">
                        <div class="goal">
                            <div class="amount"> ' . $campaign["goal_amount"] . ' </div>
                        </div>
                        <div class="progress">
                            <div class="amount">' . $total . ' </div>
                        </div>
                    </div>
                </div>';
            $returnme .= "
                <div style='text-align:center'>
                    <strong>" . $campaign["title"] . "</strong>
                </div><br />
                <table>
                    <tr>
                        <td style='text-align:center'>
                            $graph
                            <br />
                            <div style='margin-top: 300px;'><strong>$perc% complete</strong></div>
                        </td>
                        <td style='vertical-align:top'>
                            <div style='text-align:left;padding:4px;'>
                                " . $campaign["goal_description"] . "
                            </div>
                        </td>
                    </tr>
                </table>
                <br />
                $button";
            break;
        case "horizontal":
        $graph = '
            <div id="thermometer" class="thermometer horizontal">
                <div class="track">
                    <div class="goal">
                        <div class="amount"> ' . $campaign["goal_amount"] . ' </div>
                    </div>
                    <div class="progress">
                        <div class="amount">' . $total . ' </div>
                    </div>
                </div>
            </div>';
        $returnme .= "
            <div style='text-align:center'>
                <strong>" . $campaign["title"] . "</strong>
            </div><br />
            <div style='text-align:center'>
                " . $campaign["goal_description"] . "
            </div>
            $graph
            <br /><div style='text-align: center;'><strong>$perc% complete</strong></div><br />
            $button";
            break;
    } 

    $returnme .= js_code_wrap('thermometer("thermometer");', "", true);

    return $returnme;    
}

function insert_blank_donate($pageid, $settings = false) {
global $CFG;
	if ($featureid = execute_db_sql("INSERT INTO donate_instance (campaign_id) VALUES('0')")) {
		$area = get_db_field("default_area", "features", "feature='donate'");
		$sort = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$area'") + 1;
		execute_db_sql("INSERT INTO pages_features (pageid,feature,sort,area,featureid) VALUES('$pageid','donate','$sort','$area','$featureid')");
		return $featureid;
	}
	return false;
}

function donate_delete($pageid, $featureid) {
    $params = [
		"pageid" => $pageid,
		"featureid" => $featureid,
		"feature" => "donate",
	];

	$SQL = use_template("dbsql/features.sql", $params, "delete_feature");
	execute_db_sql($SQL);
	$SQL = use_template("dbsql/features.sql", $params, "delete_feature_settings");
	execute_db_sql($SQL);
	$SQL = use_template("dbsql/donate.sql", $params, "delete_donate_instance", "donate");
	execute_db_sql($SQL);

	resort_page_features($pageid);
}

function donate_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
	$settings = fetch_settings("donate", $featureid, $pageid);
    $returnme = "";
	
    $donate_abilities = user_abilities($USER->userid, $pageid,"donate", "donate", $featureid);
	$feature_abilities = user_abilities($USER->userid, $pageid,"features", "donate", $featureid);
    
    $campaign = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id IN (SELECT campaign_id FROM donate_instance WHERE donate_id='$featureid')");	
    $edit = get_db_row("SELECT * FROM donate_instance WHERE donate_id='$featureid' AND campaign_id IN (SELECT campaign_id FROM donate_campaign WHERE origin_page='$pageid')") ? true : false;
    
    if ($campaign && $edit && $donate_abilities->adddonation->allow) {
        $p = [
            "title" => "Manage Donations",
            "path" => action_path("donate") . "managedonations&amp;pageid=$pageid&amp;featureid=$featureid",
            "refresh" => "true",
            "iframe" => true,
            "validate" => "true",
            "width" => "750",
            "height" => "600",
            "image" => $CFG->wwwroot . "/images/money.png",
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($p);
    }
    
    if ($donate_abilities->managedonation->allow) {
        $p = [
            "title" => "Campaign Settings",
            "path" => action_path("donate") . "editcampaign&amp;pageid=$pageid&amp;featureid=$featureid",
            "refresh" => "true",
            "iframe" => true,
            "validate" => "true",
            "width" => "750",
            "height" => "600",
            "image" => $CFG->wwwroot . "/images/edit.png",
            "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($p);
    }
	return $returnme;
}

function select_campaign_forms($featureid, $pageid) {
global $CFG, $MYVARS, $USER;
    $SQL = "SELECT * FROM donate_instance WHERE donate_id='$featureid' AND campaign_id IN (SELECT campaign_id FROM donate_campaign WHERE origin_page='$pageid')";

    $returnme = '<div style="text-align:center"><h1>Choose a Campaign</h1></div><br />';
    if ($edit = get_db_row($SQL)) {
        $current = '
                You are involved in a campaign you started called: <strong>' . get_db_field("title", "donate_campaign", "campaign_id='" . $edit["campaign_id"] . "'") . '</strong><br />    
                <br />Would you like to edit the current campaign? <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'new_campaign_form\',\'&campaign_id=' . $edit["campaign_id"] . '&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); loaddynamicjs(\'donation_script\');});">Edit Campaign</a>
        <br /><br /><br />';        
    } else {
        if ($joined = get_db_row("SELECT * FROM donate_instance WHERE donate_id='$featureid' AND campaign_id != '0'")) {
            $current = 'You are currently joined to a campaign called: <strong>' . get_db_field("title", "donate_campaign", "campaign_id='" . $joined["campaign_id"] . "'") . '</strong><br />';    
        } else {
            $current = 'You are not currently associated with an active campaign.<br />';    
        }       
    }
    
    $returnme .= $current. '
            Would you like to start a new campaign or join an existing donation campaign?<br /><br />
            <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'new_campaign_form\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); loaddynamicjs(\'donation_script\');});">Start New Campaign</a>
    '; 
    $returnme .= '    
        <br /><br />
        <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'join_campaign_form\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); });">Join Existing Campaign</a>';
    
    return $returnme;
}

function add_or_manage_forms($featureid, $pageid) {
global $CFG, $MYVARS, $USER;
    $returnme = '<div style="text-align:center"><h1>What would you like to do?</h1></div>';
    $returnme .= '
            Would you like to add offline donations to this campaign?<br /><br />
            <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'add_offline_donations_form\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); loaddynamicjs(\'donation_script\');});">Add Offline Donations</a>
            <br /><br /><br />
            Would you like to manage all donations made to this campaign?<br /><br />
            <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'manage_donations_form\',\'&featureid=' . $featureid . '&pageid=' . $pageid . '\',function() { simple_display(\'donation_display\'); });">Manage Donations</a>
    ';        
    
    return $returnme;    
}

function donate_default_settings($type, $pageid, $featureid) {
global $CFG;
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "Donate",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "defaultsetting" => "horizontal",
            "display" => "Thermometer Orientation",
            "setting_name" => "metertype",
            "inputtype" => "select_array",
            "extraforminfo" => [
                ["selectvalue" => "horizontal", "selectname" => "Horizontal"],
                ["selectvalue" => "vertical", "selectname" => "Vertical"],
            ],
            "numeric" => null,
            "validation" => null,
            "warning" => "Select the orientation of the donation thermometer.",
        ],
        [
            "setting_name" => "enablerss",
            "defaultsetting" => "0",
            "display" => "Enable RSS",
            "inputtype" => "yes/no",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
	return $settings;
}
?>