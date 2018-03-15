<?php
/***************************************************************************
* donatelib.php - donate feature library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/04/2013
* Revision: 1.1.5
***************************************************************************/
if(!isset($LIBHEADER)){ if(file_exists('./lib/header.php')){ include('./lib/header.php'); }elseif(file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif(file_exists('../../lib/header.php')){ include('../../lib/header.php'); }}
$donateLIB = true;
	
function display_donate($pageid, $area, $featureid){
global $CFG, $USER, $donateSETTINGS;
	$abilities = get_user_abilities($USER->userid,$pageid,"donate","donate",$featureid);
	if(!$settings = fetch_settings("donate",$featureid,$pageid)){
		make_or_update_settings_array(default_settings("donate",$pageid,$featureid));
		$settings = fetch_settings("donate",$featureid,$pageid);
	}
    
    if(!empty($abilities->makedonation->allow)){
        return get_donate($pageid, $featureid, $settings, $abilities, $area);
    }
}

function get_donate($pageid,$featureid,$settings,$abilities,$area=false,$resultsonly=false){
global $CFG,$USER;
	$SQL = "SELECT * FROM donate_instance WHERE donate_id=$featureid";
	$returnme = ""; $rss = "";
	if($result = get_db_result($SQL)){
		while($row = fetch_row($result)){
            //if viewing from rss feed
			if($resultsonly){ 
                $returnme .= '<table style="width:100%;border:1px solid silver;padding:10px;"><tr><th>'. $settings->donate->$featureid->feature_title->setting.'</th></tr><tr><td><br /><br /><div class="htmlblock">' .get_donation_results($row["id"]) .'</div></td></tr></table>'; 
            }else{ //regular donate feature viewing
                $buttons = get_button_layout("donate",$featureid,$pageid);
				$returnme .= get_css_box($settings->donate->$featureid->feature_title->setting,'<div class="htmlblock">'.donation_form($featureid,$settings).'</div>',$buttons, null, 'donate', $featureid, false, false, false, false, false, false);
			}
		}
	}
	return $returnme;
}

function donation_form($featureid,$settings){
global $CFG;
    $returnme = "";

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https:" : "http:";
    $protocol = strstr($CFG->wwwroot, "http") ? '' : $protocol;

    if ($campaign = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id IN (SELECT campaign_id FROM donate_instance WHERE donate_id='$featureid')")) {
        if ($CFG->paypal) { 
            $paypal = 'www.paypal.com';
        } else { 
            $paypal = 'www.sandbox.paypal.com';
        }
        
        if ($donations = get_db_row("SELECT SUM(amount) as total FROM donate_donations WHERE campaign_id = '".$campaign["campaign_id"]."'")) {
            $total = $donations["total"];
            $total = empty($total) ? "0" : $total;
        }
        $returnme .= '
        <link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'features/donate/donate.css" property="" />
        <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'features/donate/donate.js"></script>
        ';
        $button = '
        <form action="https://'.$paypal.'/cgi-bin/webscr" method="post">
    	       <div style="width: 100%; text-align: center;">
    		      <input name="cmd" type="hidden" value="_donations" />
                  <input name="business" type="hidden" value="'.$campaign["paypal_email"].'" />
                  <input name="item_name" type="hidden" value="'.$campaign["title"].'" />
                  <input name="item_number" type="hidden" value="DONATE" />
                  <input name="custom" type="hidden" value="'.$campaign["campaign_id"].'" />
                  <input name="no_shipping" type="hidden" value="1" />
                  <input name="return" type="hidden" value="'.$CFG->wwwroot.'/features/donate/donate.php?action=thankyou" />
                  <input type="hidden" name="notify_url" value="'.$protocol.$CFG->wwwroot.'/features/donate/ipn.php'.'">
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

function donate_meter($campaign, $total, $button, $type = "horizontal"){    
$returnme = "";
    if($campaign["metgoal"] == 1 || (round($total / $campaign["goal_amount"],2) * 100) > 100){
        $perc = "100";
    }else{
        $perc = round($total / $campaign["goal_amount"],2) * 100;    
    }
    
    switch ($type) {
        case "vertical":
            $graph = '
                <div id="thermometer" class="thermometer">
                    <div class="track">
                        <div class="goal">
                            <div class="amount"> '.$campaign["goal_amount"].' </div>
                        </div>
                        <div class="progress">
                            <div class="amount">'.$total.' </div>
                        </div>
                    </div>
                </div>';
            $returnme .= "
                <div style='text-align:center'>
                    <strong>".$campaign["title"]."</strong>
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
                                ".$campaign["goal_description"]."
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
                        <div class="amount"> '.$campaign["goal_amount"].' </div>
                    </div>
                    <div class="progress">
                        <div class="amount">'.$total.' </div>
                    </div>
                </div>
            </div>';
        $returnme .= "
            <div style='text-align:center'>
                <strong>".$campaign["title"]."</strong>
            </div><br />
            <div style='text-align:center'>
                ".$campaign["goal_description"]."
            </div>
            $graph
            <br /><div style='text-align: center;'><strong>$perc% complete</strong></div><br />
            $button";
            break;
    } 

    $returnme .= '
    <script type="text/javascript">
        $(document).ready(function(){
            //call without the parameters to have it read from the DOM
            thermometer("thermometer");        
        });
    </script>
    ';
    return $returnme;    
}

function insert_blank_donate($pageid,$settings = false){
global $CFG;
	if($featureid = execute_db_sql("INSERT INTO donate_instance (campaign_id) VALUES('0')")){
		$area = get_db_field("default_area", "features", "feature='donate'");
		$sort = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$area'") + 1;
		execute_db_sql("INSERT INTO pages_features (pageid,feature,sort,area,featureid) VALUES('$pageid','donate','$sort','$area','$featureid')");
		return $featureid;
	}
	return false;
}

function donate_delete($pageid,$featureid,$sectionid){
	execute_db_sql("DELETE FROM pages_features WHERE feature='donate' AND pageid='$pageid' AND featureid='$featureid'");
	execute_db_sql("DELETE FROM donate_instance WHERE id='$featureid'");
	resort_page_features($pageid);
}

function donate_buttons($pageid,$featuretype,$featureid){
global $CFG,$USER;
	$settings = fetch_settings("donate",$featureid,$pageid);
    $returnme = "";
	
    $donate_abilities = get_user_abilities($USER->userid,$pageid,"donate","donate",$featureid);
	$feature_abilities = get_user_abilities($USER->userid,$pageid,"features","donate",$featureid);
    
    $campaign = get_db_row("SELECT * FROM donate_campaign WHERE campaign_id IN (SELECT campaign_id FROM donate_instance WHERE donate_id='$featureid')");	
    $edit = get_db_row("SELECT * FROM donate_instance WHERE donate_id='$featureid' AND campaign_id IN (SELECT campaign_id FROM donate_campaign WHERE origin_page='$pageid')") ? true : false;
    
    if($campaign && $edit && $donate_abilities->adddonation->allow){ 
        $returnme .= make_modal_links(array("title"=> "Manage Donations","path"=>$CFG->wwwroot."/features/donate/donate.php?action=managedonations&amp;pageid=$pageid&amp;featureid=$featureid","refresh"=>"true","iframe"=>"true","validate"=>"true","width"=>"750","height"=>"600","image"=>$CFG->wwwroot."/images/money.png","class"=>"slide_menu_button"));
    }
    
    if($donate_abilities->managedonation->allow){ 
        $returnme .= make_modal_links(array("title"=> "Campaign Settings","path"=>$CFG->wwwroot."/features/donate/donate.php?action=editcampaign&amp;pageid=$pageid&amp;featureid=$featureid","refresh"=>"true","iframe"=>"true","validate"=>"true","width"=>"750","height"=>"600","image"=>$CFG->wwwroot."/images/edit.png","class"=>"slide_menu_button"));
    }
	return $returnme;
}

function select_campaign_forms($featureid, $pageid){
global $CFG, $MYVARS, $USER;
    $SQL = "SELECT * FROM donate_instance WHERE donate_id='$featureid' AND campaign_id IN (SELECT campaign_id FROM donate_campaign WHERE origin_page='$pageid')";

    $returnme = '<div style="text-align:center"><h1>Choose a Campaign</h1></div>';
    if($edit = get_db_row($SQL)){
        $current = '
                You are involved in a campaign you started called: <strong>'.get_db_field("title","donate_campaign","campaign_id='".$edit["campaign_id"]."'").'</strong><br />    
                <br />Would you like to edit the current campaign? <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'new_campaign_form\',\'&campaign_id='.$edit["campaign_id"].'&featureid='.$featureid.'&pageid='.$pageid.'\',function(){ simple_display(\'donation_display\'); loaddynamicjs(\'donation_script\');});">Edit Campaign</a>
        <br /><br /><br />';        
    }else{
        if($joined = get_db_row("SELECT * FROM donate_instance WHERE donate_id='$featureid' AND campaign_id != '0'")){
            $current = 'You are currently joined to a campaign called: <strong>'.get_db_field("title","donate_campaign","campaign_id='".$joined["campaign_id"]."'").'</strong><br />';    
        }else{
            $current = 'You are not currently associated with an active campaign.<br />';    
        }       
    }
    
    $returnme .= $current. '
            Would you like to start a new campaign or join an existing donation campaign?<br /><br />
            <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'new_campaign_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function(){ simple_display(\'donation_display\'); loaddynamicjs(\'donation_script\');});">Start New Campaign</a>
    '; 
    $returnme .= '    
        <br /><br />
        <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'join_campaign_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function(){ simple_display(\'donation_display\'); });">Join Existing Campaign</a>';
    
    return $returnme;
}

function add_or_manage_forms($featureid, $pageid){
global $CFG, $MYVARS, $USER;
    $returnme = '<div style="text-align:center"><h1>What would you like to do?</h1></div>';
    $returnme .= '
            Would you like to add offline donations to this campaign?<br /><br />
            <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'add_offline_donations_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function(){ simple_display(\'donation_display\'); loaddynamicjs(\'donation_script\');});">Add Offline Donations</a>
            <br /><br /><br />
            Would you like to manage all donations made to this campaign?<br /><br />
            <a href="javascript: void(0);" onclick="ajaxapi(\'/features/donate/donate_ajax.php\',\'manage_donations_form\',\'&featureid='.$featureid.'&pageid='.$pageid.'\',function(){ simple_display(\'donation_display\'); });">Manage Donations</a>
    ';        
    
    return $returnme;    
}
//function donate_rss($feed, $userid, $userkey){
//global $CFG;
//	$feeds = "";
//	
//	$settings = fetch_settings("donate",$feed["featureid"],$feed["pageid"]);
//	if($settings->donate->$feed["featureid"]->enablerss->setting){
//		if($settings->donate->$feed["featureid"]->blog->setting){
//			$donate = get_db_row("SELECT * FROM donate WHERE donateid='".$feed["featureid"]."'");
//			if($donate['firstedition']){ //this is not a first edition
//				$donateresults = get_db_result("SELECT * FROM donate WHERE donateid='".$donate["firstedition"]."' OR firstedition='".$donate["firstedition"]."' ORDER BY donateid DESC LIMIT 50");
//			}else{
//				$donateresults = get_db_result("SELECT * FROM donate WHERE donateid='".$donate["donateid"]."' OR firstedition='".$donate["donateid"]."' ORDER BY donateid DESC LIMIT 50");
//			}
//			
//			while($donate = fetch_row($donateresults)){
//				$settings = fetch_settings("donate",$donate["donateid"],$feed["pageid"]);
//				$feeds .= fill_feed($settings->donate->$donate["donateid"]->feature_title->setting . " " . date('d/m/Y',$donate["dateposted"]),substr($donate["donate"],0,100),$CFG->wwwroot.'/features/donate/donate.php?action=viewdonate&key='.$userkey.'&pageid='.$feed["pageid"].'&donateid='.$donate["donateid"],$donate["dateposted"]);
//			}
//		}else{
//			$donate = get_db_row("SELECT * FROM donate WHERE donateid='".$feed["featureid"]."'");
//			$feeds .= fill_feed($settings->donate->$feed["featureid"]->feature_title->setting,substr($donate["donate"],0,100),$CFG->wwwroot.'/features/donate/donate.php?action=viewdonate&key='.$userkey.'&pageid='.$feed["pageid"].'&donateid='.$feed["featureid"],$donate["dateposted"]);
//		}
//	}
//	return $feeds;
//}

function donate_default_settings($feature,$pageid,$featureid){
global $CFG;
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","Donate",false,"Donate","Feature Title","text");
    $orientation[] = array("selectvalue" => "horizontal", "selectname" => "Horizontal");
    $orientation[] = array("selectvalue" => "vertical", "selectname" => "Vertical");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","metertype","horizontal",$orientation,"horizontal","Thermometer Orientation","select_array",null,null,"Select the orientation of the donation thermometer.");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","enablerss","0",false,"0","Enable RSS","yes/no");

	return $settings_array;
}
?>