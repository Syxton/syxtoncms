<?php
/***************************************************************************
* onlineuserslib.php - Online Users function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/16/2016
* Revision: 0.4.1
***************************************************************************/
 
if(!isset($LIBHEADER)){ if(file_exists('./lib/header.php')){ include('./lib/header.php'); }elseif(file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif(file_exists('../../lib/header.php')){ include('../../lib/header.php'); }}
$ONLINEUSERSLIB = true;

function display_onlineusers($pageid,$area,$featureid){
global $CFG, $USER, $ROLES;
	$content=""; $feature = "onlineusers";
	
	if(!$settings = fetch_settings($feature,$featureid,$pageid)){
		make_or_update_settings_array(default_settings($feature,$pageid,$featureid));
		$settings = fetch_settings($feature,$featureid,$pageid);
	}
	
	$title = $settings->$feature->$featureid->feature_title->setting;
	
	if(is_logged_in()){
		if(user_has_ability_in_page($USER->userid,"seeusers",$pageid)){
    		$content .= '<div id="onlineusersfeature">'.get_onlineusers($pageid,$featureid,$settings). '</div>';
			$buttons = get_button_layout($feature,$featureid,$pageid); 
		}
	}else{
		if(role_has_ability_in_page($ROLES->visitor,"seeusers",$pageid)){
			$content .= '<div id="onlineusersfeature">'.get_onlineusers($pageid,$featureid,$settings). '</div>';
			$buttons = get_button_layout($feature,$featureid,$pageid); 
		}
	}
	
	$content .= '<script type="text/javascript">
					<!-- 
					var onlineuserstimeout = setInterval(function(){ ajaxapi("/features/onlineusers/onlineusers_ajax.php","run_lib_function","&amp;runthis=get_onlineusers&amp;var1='.$pageid.'&amp;var2='.$featureid.'",function() { if (xmlHttp.readyState == 4) { simple_display("onlineusersfeature"); }},true);},30000);
				 	// --> 
				 </script>';
				
	return get_css_box($title,$content,$buttons, NULL, "onlineusers", $featureid);
}

function get_onlineusers($pageid,$featureid,$settings=false){
global $CFG, $USER;
	$returnme = "";

    //Settings will usually come from display setting, but they could come from ajax call without settings
	if(!$settings && !$settings = fetch_settings("onlineusers",$featureid,$pageid)){
		make_or_update_settings_array(default_settings("onlineusers",$pageid,$featureid));
		$settings = fetch_settings("onlineusers",$featureid,$pageid);
	}
    
	$viewable_limit = $settings->onlineusers->$featureid->viewable_limit->setting;
	$show_total = $settings->onlineusers->$featureid->show_total->setting;
	$minutes_online = $settings->onlineusers->$featureid->minutes_online->setting;
	$timelimit = time() - ($minutes_online * 60);
	$limit = " LIMIT $viewable_limit";

	if(is_logged_in()){
		if($pageid == $CFG->SITEID){
			$SQL = "SELECT * FROM users u INNER JOIN logfile lf ON lf.userid = u.userid AND lf.timeline = (SELECT timeline FROM logfile WHERE userid=u.userid ORDER BY timeline DESC LIMIT 1) WHERE u.userid IN (SELECT l.userid FROM logfile l WHERE l.timeline > $timelimit ORDER BY l.timeline DESC) AND lf.description != 'Logout' GROUP BY u.userid ORDER BY u.last_activity DESC";
		}else{
			$SQL = "SELECT * FROM users u INNER JOIN logfile lf ON lf.userid = u.userid AND lf.timeline = (SELECT timeline FROM logfile WHERE userid=u.userid ORDER BY timeline DESC LIMIT 1) WHERE u.userid IN (SELECT l.userid FROM logfile l WHERE l.timeline > $timelimit AND l.pageid=$pageid ORDER BY l.timeline DESC) AND lf.description != 'Logout' GROUP BY u.userid ORDER BY u.last_activity DESC";
		}

		if($show_total){ 
			$onlineusers = get_db_count($SQL);
			if(!$onlineusers){ $onlineusers = 1;}
			$returnme .= '<div style="width:100%;text-align:center;font-size:.9em;">Online Users: '.$onlineusers.'</div>'; 
		}
		
		if($users = get_db_result($SQL.$limit)){
			while($user = fetch_row($users)){
				$returnme .= '<div style="width:100%;text-align:left;color:blue;font-size:.9em;overflow:auto;margin:2px;">
									<div title="'.ago($user["last_activity"]).'">'. $user["fname"] . " " . $user["lname"] . '</div>
							  </div>';
			}
		}
	}else{
		if($pageid == $CFG->SITEID){
			$SQL = "SELECT l.* FROM logfile l WHERE l.timeline > $timelimit GROUP BY l.ip ORDER BY l.timeline DESC";
		}else{
			$SQL = "SELECT l.* FROM logfile l WHERE l.timeline > $timelimit AND l.pagid=$pageid GROUP BY l.ip ORDER BY l.timeline DESC";			
		}
		
		$onlineusers = get_db_count($SQL);
		if(!$onlineusers){ $onlineusers = 1;}
		if($show_total){ $returnme .= '<div style="width:100%;text-align:center;font-size:.8em;">Online Visitors: '.$onlineusers.'</div>'; }
	}
	return $returnme;
}

function onlineusers_delete($pageid,$featureid,$sectionid){
	execute_db_sql("DELETE FROM pages_features WHERE feature='onlineusers' AND pageid='$pageid' AND featureid='$featureid'");
	execute_db_sql("DELETE FROM settings WHERE type='onlineusers' AND pageid='$pageid' AND featureid='$featureid'");
	resort_page_features($pageid);
}

function onlineusers_buttons($pageid,$featuretype,$featureid){
global $CFG,$USER;
	$returnme = "";
    return $returnme;
}

function onlineusers_default_settings($feature,$pageid,$featureid){
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","Online Users",false,"Online Users","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","viewable_limit","25",false,"25","Viewable Limit","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","minutes_online","30",false,"30","Show last active (min)","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","show_total","1",false,"1","Show Total","yes/no");
	return $settings_array;
}
?>