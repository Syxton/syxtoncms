<?php
/***************************************************************************
* eventslib.php - Events function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 1/28/2016
* Revision: 2.8.3
***************************************************************************/

if(!isset($LIBHEADER)){ if(file_exists('./lib/header.php')){ include('./lib/header.php'); }elseif(file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif(file_exists('../../lib/header.php')){ include('../../lib/header.php'); }}
$EVENTSLIB = true;
if(empty($MYVARS)){ $MYVARS = new stdClass(); }
$MYVARS->bgcyears = 5; // Background checks are invalid after x years
$MYVARS->staffappmonths = 7; // Staff applications checks are invalid after x months

function display_events($pageid, $area, $featureid){
global $CFG, $USER, $ROLES;
    $content = "";

	if(!$settings = fetch_settings("events",$featureid,$pageid)){
		make_or_update_settings_array(default_settings("events",$pageid,$featureid));
		$settings = fetch_settings("events",$featureid,$pageid);
	}

	$title = $settings->events->$featureid->feature_title->setting;
	$upcomingdays = $settings->events->$featureid->upcomingdays->setting;
	$recentdays = $settings->events->$featureid->recentdays->setting;
	$archivedays = $settings->events->$featureid->archivedays->setting;
	$showpastevents = $settings->events->$featureid->showpastevents->setting;
    $allowrequests = $settings->events->$featureid->allowrequests->setting;
	
	if($area == "middle"){               
        //Get calendar of events
        return get_calendar_of_events($title, $pageid, $featureid, false, $showpastevents, $content, $allowrequests);
    }else{
        if(is_logged_in()){ //Logged in user will see...
            if(get_db_row("SELECT eventid FROM events WHERE workers=1 AND event_begin_date > " .time())) {
                if(user_has_ability_in_page($USER->userid, "staffapply", $pageid, "events", $featureid)){ 
                    $content .= get_staff_application_button();    
                }    
            }

            if(user_has_ability_in_page($USER->userid, "viewevents", $pageid, "events", $featureid)){                
                //Get events that must be confirmed
                if($pageid == $CFG->SITEID){
                    if(user_has_ability_in_page($USER->userid, "confirmevents", $pageid, "events", $featureid) && $section = get_confirm_events()){ $content .= $section . "<br />";}
                }
                
                //Get events that can be edited
                if(user_has_ability_in_page($USER->userid, "editevents", $pageid, "events", $featureid) && $section = get_editable_events($pageid)){ $content .= $section . "<br />";}
                
                //Get current events
                if($section = get_current_events($pageid)){ $content .= $section . "<br />"; }
                
                //Get upcoming events
                if($section = get_upcoming_events($pageid, $upcomingdays)){ $content .= $section . "<br />"; }
                
                //Get events that are registerable
                if($section = get_open_enrollment_events($pageid)){ $content .= $section . ""; }
                
                //No events
                if($content == ""){ $content .= "There are no current or upcoming events."; }
                
                //Get link for request form
                if($allowrequests){ $content = get_event_request_link($area,$featureid) . $content; }
                
                //Get recent events
                if($section = get_recent_events($pageid, $recentdays, $archivedays)){ $content .= $section; }
                            
                //Get feature layout
                $buttons = get_button_layout("events", $featureid, $pageid);
                return get_css_box($title, $content, $buttons, NULL, "events", $featureid);
            }
        }elseif(role_has_ability_in_page($ROLES->visitor, "viewevents", $pageid)){ //If unlogged in users can see...
            //Get current events
            if($section = get_current_events($pageid)){ $content .= $section . "<br />";}
            
            //Get upcoming events
            if($section = get_upcoming_events($pageid, $upcomingdays)){ $content .= $section . "<br />";}
            
            //Get registerable events
            if($section = get_open_enrollment_events($pageid)){ $content .= $section . "";}
            
            //No events
            if($content == ""){ $content .= "There are no current or upcoming events.";}
            
            //Get link for request form
            if($allowrequests){ $content = get_event_request_link($area,$featureid) . $content; }
            
            //Show past events
            if($section = get_recent_events($pageid, $recentdays, $archivedays)){ $content .= $section;}
            
            //Get feature layout
            return get_css_box($title, $content, NULL, NULL, "events", $featureid);
        }
    }
}

function get_staff_application_button(){
global $CFG;
    return '<div style="margin:5px;text-align:right;">'.make_modal_links(array("title"=>"Staff Application/Renewal Form","path"=>$CFG->wwwroot."/features/events/events.php?action=staff_application","validate"=>"true","width"=>"600","height"=>"650","image"=>$CFG->wwwroot."/images/staff.png","confirmexit"=>"true")).'</div>'; 
}

function get_event_request_link($area,$featureid){
global $CFG;
    if($area == "middle"){
        return '<div style="text-align:right;">'.make_modal_links(array("title"=>"Request an Event","path"=>$CFG->wwwroot."/features/events/events.php?action=event_request_form&amp;featureid=$featureid","validate"=>"true","width"=>"550","height"=>"650","image"=>$CFG->wwwroot."/images/request.gif")).'</div>'; 
    }else{
        return '<div style="text-align:right;">'.make_modal_links(array("title"=>"Request an Event","path"=>$CFG->wwwroot."/features/events/events.php?action=event_request_form&amp;featureid=$featureid","validate"=>"true","width"=>"550","height"=>"650","image"=>$CFG->wwwroot."/images/request.gif")).'</div><br />';  
    }
}

function get_calendar_of_events($title, $pageid, $featureid, $year = false, $showpastevents = true, $content = "",$allowrequests=false){
global $CFG, $USER, $ROLES;
    $time = get_timestamp();
    $year = $year ? $year : date("Y", $time);

    $begincurrentyear = mktime(0, 0, 0, 1, 1, $year); //Beginning of current year
    $endcurrentyear = mktime(0, 0, 0, 12, 32, $year); //End of current year
    //GET ABILITIES FOR EVENTS//////////////////////////////
    $canconfirm = false;
    $canedit = false;
    $canview = false;
    $site = $pageid == $CFG->SITEID ? "((e.pageid != $pageid AND siteviewable=1) OR (e.pageid = $pageid))" : "e.pageid = $pageid";

    if(is_logged_in()){
        if(get_db_row("SELECT eventid FROM events WHERE workers=1 AND event_begin_date > " .time())) {
            if(user_has_ability_in_page($USER->userid, "staffapply", $pageid, "events", $featureid)){ 
                $content .= get_staff_application_button($featureid,$pageid);    
            }
        }
        if(user_has_ability_in_page($USER->userid, "viewevents", $pageid, "events", $featureid)){
            $canview = true;
            $canconfirm = user_has_ability_in_page($USER->userid, "confirmevents", $CFG->SITEID, "events", $featureid) ? true : false;
            $buttons = get_button_layout("events", $featureid, $pageid);
            if($canconfirm){ 
                $SQL = "SELECT * FROM events e WHERE $site AND $begincurrentyear < e.event_begin_date AND $endcurrentyear > e.event_begin_date ORDER BY e.event_begin_date, e.event_begin_time";
            }else{  
                $SQL = "SELECT * FROM events e WHERE $site AND $begincurrentyear < e.event_begin_date AND $endcurrentyear > e.event_begin_date AND e.confirmed=1 ORDER BY e.event_begin_date, e.event_begin_time";
            }
        }
    }else{
        $canview = role_has_ability_in_page($ROLES->visitor, "viewevents", $pageid) ? true : false;
        if($canview && $pageid == $CFG->SITEID){ 
            $SQL = "SELECT * FROM events e WHERE $site AND $begincurrentyear < e.event_begin_date AND $endcurrentyear > e.event_begin_date AND e.confirmed=1 ORDER BY e.event_begin_date, e.event_begin_time";
        }elseif($canview){ 
            $SQL = "SELECT * FROM events e WHERE $site AND $begincurrentyear < e.event_begin_date AND $endcurrentyear > e.event_begin_date AND e.confirmed=1 ORDER BY e.event_begin_date, e.event_begin_time";
        }$buttons = null;
    }
    //END ABILITIES CHECK///////////////////////////////////
    if($canview && $result = get_db_result($SQL)){
        $lastday = false;
        while($event = fetch_row($result)){
            if($showpastevents || ($event["event_end_date"] >= ($time - 86400))){
				$newday = true;
	            $newday = $lastday == date("n/d/Y", $event["event_begin_date"]) ? false : true;
	            $lastday = date("n/d/Y", $event["event_begin_date"]);
	            $canedit = user_has_ability_in_page($USER->userid, "editevents", $event["pageid"], "events", $featureid) ? true : false;
	            $event_buttons = get_event_button_layout($pageid, $event, $canedit, $canconfirm);
	            $needsconfirmed = $canconfirm && $event["confirmed"] != 1 && $pageid == $CFG->SITEID ? true : false;
	            $dategraphic = $needsconfirmed || ($event["event_end_date"] < ($time - 86400)) ? get_date_graphic($event["event_begin_date"], $newday, null, true, true) : get_date_graphic($event["event_begin_date"], $newday, null, true);
	            $content .= make_calendar_table($pageid, $dategraphic, $event, $event_buttons, $needsconfirmed);
            }
        }
    }
    if($content == ""){ $content .= "There are no events for this calendar year.";}
    
    //Get link for request form
    if($allowrequests){ $content = get_event_request_link("middle",$featureid) . $content; }

    return get_css_box($title, $content, $buttons, NULL, "events", $featureid);
}

function make_calendar_table($pageid, $daygraphic, $event, $buttons = false, $needsconfirmed = false){
global $CFG, $USER;
    $time = get_timestamp();
    $export = ""; $registration = "";
    $featureid = get_db_field("featureid","pages_features","pageid='$pageid' AND feature='events'");    
    if($event["start_reg"] > 0){ //Event is a registerable page...at one time
		$regcount = get_db_count("SELECT * FROM events_registrations WHERE eventid='" . $event['eventid'] . "'");
		$limit = $event['max_users'] == "0" ? "&#8734;" : $event['max_users'];
		
		//Currently can register for event (time check)
	    if($event["start_reg"] < $time && $event["stop_reg"] > $time){
	        $registerable = $event["max_users"] == 0 || ($event["max_users"] != 0 and $event["max_users"] > $regcount) ? true : false;
	        //Availability check
	        if($registerable){
	            $registration = "Registration ends in " . ago($event["event_begin_date"]) . "&nbsp;&nbsp;";
	            $left = $event['max_users'] == "0" ? "&#8734;" : '(' . ($limit - $regcount) . ' out of ' . $limit . ' openings left)';
	            if(user_has_ability_in_page($USER->userid, "signupforevents", $pageid, "events", $featureid)){ 
                    $registration .= make_modal_links(array("title"=>"Register $left","path"=>$CFG->wwwroot."/features/events/events.php?action=show_registration&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","validate"=>"true","width"=>"630","height"=>"800","image"=>$CFG->wwwroot."/images/register.png","confirmexit"=>"true"));
                }
	            if($event["paypal"] != ""){ 
	               $registration .= make_modal_links(array("title"=>"Event Payment","path"=>$CFG->wwwroot."/features/events/events.php?action=pay&amp;modal=1&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"image"=>$CFG->wwwroot."/images/pay.png","width"=>"95%","height"=>"95%"));
	            }
	        }
	    }
	    
		//GET EXPORT CSV BUTTON
		if(user_has_ability_in_page($USER->userid, "exportcsv", $event["pageid"],"events",$featureid)){ $export = '<a href="javascript: void(0)" onclick="ajaxapi(\'/features/events/events_ajax.php\',\'export_csv\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $event['eventid'] . '\',function() { run_this();});"><img src="' . $CFG->wwwroot . '/images/csv.png" title="Export ' . $regcount . '/' . $limit . ' Registrations" alt="Export ' . $regcount . ' Registrations" /></a>';}
    }

    if($registration == ""){
        $location = get_db_row("SELECT * FROM events_locations WHERE id='".$event['location']."'");
        $registration = "Where: " . stripslashes($location["location"]);
    }
    
    if($needsconfirmed){
        $returnme = '<div id="confirm_' . $event['eventid'] . '">
			<table class="eventstable">
			<tr>
			' . $daygraphic . '
			<table style="width:100%;border-spacing: 0px;">
			<tr>
			<td>
			<div style="font-size:.95em; color:gray;float:left;padding-right:10px;">Unconfirmed: 
                '.make_modal_links(array("title"=> stripslashes($event["name"]),"path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"700","height"=>"650")).'
            </div>
            <span style="font-size:.85em">&nbsp;
                ' . stripslashes(strip_tags($event["extrainfo"])) . '
			</span> ';
        $returnme .= '<div class="hprcp_n" style="margin-top:4px;"><div class="hprcp_e"><div class="hprcp_w"></div></div></div>
			<div class="hprcp_head">
			<div style="width:100%;vertical-align:middle;color:gray;position:relative;_right:2px;top:-8px;">
			<span style="font-size:.85em; float:left;line-height:28px;vertical-align:top">
                ' . $export . '
                ' . $registration . '
			</span>' . $buttons . '
			</div>
			</div>
			</td>
			</tr>
			</table>
			</td>
			</tr>
			</table></div>';
    }else{
        $returnme = '<div id="confirm_' . $event['eventid'] . '">
			<table class="eventstable">
			<tr>
			' . $daygraphic . '
			<table style="width:100%;border-spacing: 0px;">
			<tr>
			<td>
			<div style="font-size:.95em; color:blue;float:left;padding-right:10px;">
            '.make_modal_links(array("title"=> stripslashes($event["name"]),"path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"700","height"=>"650")).'
            </div>
			<span style="font-size:.85em">&nbsp;
			' . stripslashes(strip_tags($event["extrainfo"])) . '
			</span> ';
        $returnme .= '<div class="hprcp_n" style="margin-top:4px;"><div class="hprcp_e"><div class="hprcp_w"></div></div></div>
			<div class="hprcp_head">
			<div style="width:100%;vertical-align:middle;color:gray;position:relative;_right:2px;top:-8px;">
			<span style="font-size:.85em; float:left;line-height:28px;">
			' . $export . '
			' . $registration . '
			</span>' . $buttons . '
			</div>
			</div>
			</td>
			</tr>
			</table>
			</td>
			</tr>
			</table></div>';
    }
    return $returnme;
}

function get_event_button_layout($pageid, $event, $edit, $confirm){
global $CFG;
    $buttons = get_event_edit_buttons($pageid, $event, $edit, $confirm);
    
	$themeid = getpagetheme($pageid);
    $styles = get_styles($pageid,$themeid,"news");

    $contentbgcolor = isset($styles['contentbgcolor']) ? $styles['contentbgcolor'] : "";
	$bordercolor = isset($styles['bordercolor']) ? $styles['bordercolor'] : "";
	$titlebgcolor = isset($styles['titlebgcolor']) ? $styles['titlebgcolor'] : "";
	$titlefontcolor = isset($styles['titlefontcolor']) ? $styles['titlefontcolor'] : "";
        
	if(strlen($buttons) > 0){
	   	   return '
        <div id="slide_menu" class="slide_menu_invisible slide_menu" style="border-top:1px solid '.$bordercolor.';border-bottom:1px solid '.$bordercolor.';">
        <div id="event_' . $event["eventid"] . '_buttons" style="padding:0;">
		  ' . $buttons . '
		</div>
        </div>
        <div onclick="$(this).prev(\'#slide_menu\').animate({width: \'toggle\'},function(){$(this).toggleClass(\'slide_menu_visible\');});" class="slide_menu slide_menu_tab" style="background-color:'.$titlefontcolor.';color:'.$titlebgcolor.';border-left:1px solid '.$bordercolor.';border-top:1px solid '.$bordercolor.';border-bottom:1px solid '.$bordercolor.';"><strong>+</strong></div>
        <div style="clear:both"></div>';
	}

    return "";
}

function get_event_edit_buttons($pageid, $event, $canedit, $canconfirm){
global $CFG, $USER;
    $returnme = "";
    $is_section = true;
    if(is_logged_in()){
        $time = get_timestamp();
        $editable = ($time - 86400) < $event["event_end_date"] ? true : false;
        //Confirm Event Buttons
        if($canconfirm && $event["confirmed"] != 1 && $event["siteviewable"] == 1){
            $returnme .= ' <a class="slide_menu_button" href="javascript: void(0);" onclick="if(confirm(\'Are you sure you want to confirm this event?\')){ ajaxapi(\'/features/events/events_ajax.php\',\'confirm_events_relay\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $event['eventid'] . '&amp;confirm=1\',function() { simple_display(\'confirm_' . $event['eventid'] . '\'); update_login_contents(\'' . $pageid . '\');});}"> <img src="' . $CFG->wwwroot . '/images/add.png" title="Confirm Event\'s Global Visibility" alt="Confirm Event" /></a>';
            $returnme .= ' <a class="slide_menu_button" href="javascript: void(0);" onclick="if(confirm(\'Are you sure you want to deny this event?\')){ ajaxapi(\'/features/events/events_ajax.php\',\'confirm_events_relay\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $event['eventid'] . '&amp;confirm=0\',function() { simple_display(\'confirm_' . $event['eventid'] . '\'); update_login_contents(\'' . $pageid . '\');});}"> <img src="' . $CFG->wwwroot . '/images/deny.png" title="Deny Event\'s Global Visibility" alt="Deny Event" /></a>';
        }
        //Edit && Delete button
        if($canedit && $editable){
            $returnme .= make_modal_links(array("title"=> "Edit Event","path"=>$CFG->wwwroot."/features/events/events.php?action=add_event_form&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"refresh"=>"true","iframe"=>"true","width"=>"800","height"=>"95%","image"=>$CFG->wwwroot . "/images/edit.png","class"=>"slide_menu_button"));
            
            //Delete button
            $returnme .= ' <a class="slide_menu_button" title="Delete Event" href="javascript: if(confirm(\'Are you sure you want to delete this event?\')){ ajaxapi(\'/features/events/events_ajax.php\',\'delete_event_relay\',\'&amp;featureid=' . $event['eventid'] . '\',function() { update_login_contents(\'' . $pageid . '\'); });}"> <img src="' . $CFG->wwwroot . '/images/delete.png" title="Delete Event" alt="Delete Event" /></a>';
        }
    }else{  return "";}
    return $returnme;
}

//Gathers the events that can be edited
function get_confirm_events(){
global $CFG, $USER;
    $returnme = "";
    $pageid = $CFG->SITEID;
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $SQL = "SELECT * FROM events e WHERE ((e.pageid != '$pageid' AND siteviewable='1') OR (e.pageid = '$pageid')) AND $time < e.event_end_date AND confirmed='3' ORDER BY e.event_begin_date, e.event_begin_time";
    if($events = get_db_result($SQL)){
        while($event = fetch_row($events)){
            $returnme .= '
                <table style="width:100%;background-color:#edfafa;border-bottom:1px gray inset; margin:1px;">
                    <tr>
                        <td>
                            <span id="confirm_' . $event['eventid'] . '">
                                '.make_modal_links(array("title"=> stripslashes($event['name']),"path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"700","height"=>"650"));
                            
            $featureid = get_db_field("featureid","pages_features","pageid='$pageid' AND feature='events'");
            if(user_has_ability_in_page($USER->userid, "editevents", $pageid, "events", $featureid)){ 
                $returnme .= make_modal_links(array("title"=> "Edit Event","path"=>$CFG->wwwroot."/features/events/events.php?action=add_event_form&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"refresh"=>"true","iframe"=>"true","width"=>"800","height"=>"95%","image"=>$CFG->wwwroot."/images/edit.png"));
            }
            
            $returnme .= '      <a href="javascript: if(confirm(\'Are you sure you want to confirm this event?\')){ ajaxapi(\'/features/events/events_ajax.php\',\'confirm_events_relay\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $event['eventid'] . '&amp;confirm=1\',function() { simple_display(\'confirm_' . $event['eventid'] . '\'); update_login_contents(\'' . $pageid . '\');});}"> <img src="' . $CFG->wwwroot . '/images/add.png" title="Confirm Event" alt="Confirm Event" /></a>';
            $returnme .= '      <a href="javascript: if(confirm(\'Are you sure you want to deny this event?\')){ ajaxapi(\'/features/events/events_ajax.php\',\'confirm_events_relay\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $event['eventid'] . '&amp;confirm=0\',function() { simple_display(\'confirm_' . $event['eventid'] . '\'); update_login_contents(\'' . $pageid . '\');});}"> <img src="' . $CFG->wwwroot . '/images/deny.png" title="Deny Event" alt="Deny Event" /></a>';           
            $returnme .= '  </span>
                        </td>
                    </tr>
                </table>';
        }
    }
    $returnme = $returnme == "" ? false : '<b> Events Need Confirmed</b> <img src="' . $CFG->wwwroot . '/images/new.png" title="New" alt="New" /><br /><hr /><table style="width:100%;"><tr><td style="background-color:#edfafa; white-space:nowrap">' . $returnme . '</td></tr></table>';
    return $returnme;
}

//Gathers the events that can be edited
function get_editable_events($pageid){
global $CFG, $USER;
    $returnme = "";
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $siteviewable = $pageid == $CFG->SITEID ? " OR (siteviewable = '1' AND confirmed = '1')" : "";
    $SQL = "SELECT e.* FROM events e WHERE (e.pageid='$pageid' $siteviewable) AND ($time -86400) < e.event_end_date ORDER BY e.event_begin_date, e.event_begin_time";
    if($events = get_db_result($SQL)){
        while($event = fetch_row($events)){
            $returnme .= '<span id="edit_' . $event['eventid'] . '">
                            <table style="width:100%;background-color:#edfafa;border-bottom:1px gray inset; margin:1px;">
                                <tr>
                                    <td>
                                        '.make_modal_links(array("title"=> stripslashes($event['name']),"path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"700","height"=>"650"));
            $returnme .= " ".make_modal_links(array("title"=> "Edit Event","path"=>$CFG->wwwroot."/features/events/events.php?action=add_event_form&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"refresh"=>"true","iframe"=>"true","width"=>"750","height"=>"650","image"=>$CFG->wwwroot."/images/edit.png"));
            $returnme .= ' <a href="javascript: if(confirm(\'Are you sure you want to delete this event?\')){ ajaxapi(\'/features/events/events_ajax.php\',\'delete_event_relay\',\'&amp;featureid=' . $event['eventid'] . '\',function() { update_login_contents(\'' . $pageid . '\');});}"> <img src="' . $CFG->wwwroot . '/images/delete.png" title="Delete Event" alt="Delete Event" /></a>';
            $returnme .= '</td></tr></table></span>';
        }
    }
    $returnme = $returnme == "" ? false : '<strong>Edit Events</strong><br /><hr /><table style="width:100%;"><tr><td style="background-color:#edfafa; white-space:nowrap">' . $returnme . '</td></tr></table>';
    return $returnme;
}

//Gathers the events that are currently available for enrollment
function get_open_enrollment_events($pageid){
global $CFG, $USER;
    $returnme = "";
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $siteviewable = $pageid == $CFG->SITEID ? " OR siteviewable = '1' AND confirmed = '1'" : "";
    $SQL = "SELECT e.* FROM events e WHERE (e.pageid='$pageid' $siteviewable) AND e.start_reg < '$time' AND e.stop_reg > '$time' AND (e.max_users=0 OR (e.max_users != 0 AND e.max_users > (SELECT COUNT(*) FROM events_registrations er WHERE er.eventid=e.eventid AND verified='1'))) ORDER BY e.event_begin_date, e.event_begin_time";
    if($events = get_db_result($SQL)) {
        while($event = fetch_row($events)){
            $returnme .= '<table style="width:100%;background-color:#edfafa;border-bottom:1px gray inset; margin:1px;">
                            <tr>
                                <td style="white-space:normal">
                                    '.make_modal_links(array("title"=> stripslashes($event["name"]),"path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"700","height"=>"650")).'
                                </td>
                                <td style="text-align:right; padding:2px;white-space:nowrap;">';
            $regcount = get_db_count("SELECT * FROM events_registrations WHERE eventid='".$event['eventid']."' AND verified='1'");
            $limit = $event['max_users'] == "0" ? "&#8734;" : $event['max_users'];
            $left = $event['max_users'] == "0" ? "&#8734;" : '(' . ($limit - $regcount) . ' out of ' . $limit . ' openings left)';
            $featureid = get_db_field("featureid","pages_features","pageid='$pageid' AND feature='events'");
            
            //Export registrations
            if(user_has_ability_in_page($USER->userid, "exportcsv", $pageid,"events",$featureid)){
                $returnme .= '<a href="javascript:ajaxapi(\'/features/events/events_ajax.php\',\'export_csv\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $event['eventid'] . '\',function() { run_this();});"><img src="' . $CFG->wwwroot . '/images/csv.png" title="Export ' . $regcount . '/' . $limit . ' Registrations" alt="Export ' . $regcount . ' Registrations" /></a>';
            } 
            
            //Payment Area
            if($event["paypal"] != ""){
                $returnme .= ' <a title="Event Payment" href="./features/events/events.php?action=pay&amp;pageid=' . $pageid . '&amp;eventid=' . $event['eventid'] . '"> <img src="' . $CFG->wwwroot . '/images/pay.png" title="Make Payment" alt="Make Payment" /></a>';
            } 
            
            //Registration button
            if(user_has_ability_in_page($USER->userid, "signupforevents", $pageid, "events", $featureid)){
                $returnme .= make_modal_links(array("title"=> "Register $left","path"=>$CFG->wwwroot."/features/events/events.php?action=show_registration&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"800","height"=>"600","image"=>$CFG->wwwroot . "/images/register.png"));
            } 
            $returnme .= '</td></tr></table>';
        }
    }
    $returnme = $returnme == "" ? false : '<b>Open Registration</b><br /><hr /><table style="width:100%;"><tr><td style="white-space:nowrap">' . $returnme . '</td></tr></table>';
    return $returnme;
}

//Gathers the events that are happening in the next (SETTINGS: upcomingdays) days
function get_upcoming_events($pageid, $upcomingdays){
global $CFG;
    $returnme = "";
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $oneday = 86400;
    $totime = $time + ($upcomingdays * $oneday);
    $siteviewable = $pageid == $CFG->SITEID ? " OR (siteviewable = '1' AND confirmed = '1')" : "";
    $SQL = "SELECT e.* FROM events e WHERE (e.pageid=$pageid $siteviewable) AND e.event_begin_date < $totime AND e.event_begin_date > $time ORDER BY e.event_begin_date, e.event_begin_time";
    if($events = get_db_result($SQL)){
        while($event = fetch_row($events)){
            $length = get_event_length($event['event_begin_date'], $event['event_end_date'], $event['allday'], $event['event_begin_time'], $event['event_end_time']);
            $returnme .= '<table style="width:100%;background-color:#edfafa;border-bottom:1px gray inset; margin:1px;">
                            <tr>
                                <td>
                                    '.make_modal_links(array("title"=> stripslashes($event["name"]),"path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"700","height"=>"650")).'
                                    <span style="display:block;color:gray; font-size:.75em;">' . $length . '</span>
                                </td>
                            </tr>
                          </table>';
        }
    }
    $returnme = $returnme == "" ? false : '<b>Upcoming Events</b><br /><hr /><table style="width:100%;"><tr><td style="background-color:#edfafa; white-space:nowrap">' . $returnme . '</td></tr></table>';
    return $returnme;
}

//Gathers the events that are happening right now
function get_current_events($pageid){
global $CFG, $USER;
    $returnme = "";
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $oneday = 86400;
    $siteviewable = $pageid == $CFG->SITEID ? " OR (siteviewable = '1' AND confirmed = '1')" : "";
    $SQL = "SELECT * FROM events e WHERE (e.pageid='$pageid' $siteviewable) AND ((((e.event_begin_date + $oneday) - $time) < $oneday AND ((e.event_begin_date + $oneday) - $time) > 0) OR ($time > (e.event_begin_date) AND $time < (e.event_end_date))) ORDER BY e.event_begin_date, e.event_begin_time";
    if($events = get_db_result($SQL)){
        while($event = fetch_row($events)){
            $length = get_event_length($event['event_begin_date'], $event['event_end_date'], $event['allday'], $event['event_begin_time'], $event['event_end_time']);
            $returnme .= '<table style="width:100%;background-color:#edfafa;border-bottom:1px gray inset; margin:1px;">
                            <tr>
                                <td>
                                    '.make_modal_links(array("title"=> stripslashes($event["name"]),"path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"700","height"=>"650")).'
                                    <span style="display:block;color:gray; font-size:.75em;">' . $length . '</span>
                                </td>
                                <td style="text-align:right; padding:2px;white-space:nowrap;">';
            $regcount = get_db_count("SELECT * FROM events_registrations WHERE eventid='".$event['eventid']."' AND verified='1'");
            $limit = $event['max_users'] == "0" ? "&#8734;" : $event['max_users'];
            $featureid = get_db_field("featureid","pages_features","pageid='$pageid' AND feature='events'");
            if(user_has_ability_in_page($USER->userid, "exportcsv", $pageid, "events", $featureid)){ $returnme .= '<a href="javascript:ajaxapi(\'/features/events/events_ajax.php\',\'export_csv\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $event['eventid'] . '\',function() { run_this();});"><img src="' . $CFG->wwwroot . '/images/csv.png" title="Export ' . $regcount . '/' . $limit . ' Registrations" alt="Export ' . $regcount . ' Registrations" /></a>';}
            $returnme .= "</td></tr></table>";
        }
    }
    $returnme = $returnme == "" ? false : '<b>Currently Active Events</b><br /><hr /><table style="width:100%;"><tr><td style="background-color:#edfafa; white-space:nowrap">' . $returnme . '</td></tr></table>';
    return $returnme;
}

//Gathers the events that are currently available for enrollment
function get_recent_events($pageid, $recentdays, $archivedays){
global $CFG, $USER;
    $returnme = "";
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $oneday = 86400;
    $featureid = get_db_field("featureid","pages_features","pageid='$pageid' AND feature='events'");
    $dayspan = user_has_ability_in_page($USER->userid, "exportcsv", $pageid, "events", $featureid) ? $archivedays : $recentdays;
    $to_day = ($dayspan * $oneday);
    $siteviewable = $pageid == $CFG->SITEID ? " OR siteviewable = '1' AND confirmed = '1'" : "";
    $SQL = "SELECT e.* FROM events e WHERE (e.pageid='$pageid' $siteviewable) AND (e.event_end_date + $to_day) > $time AND e.event_end_date < $time ORDER BY e.event_begin_date DESC, e.event_begin_time DESC";
    if($events = get_db_result($SQL)){
        while($event = fetch_row($events)){
            $length = get_event_length($event['event_begin_date'], $event['event_end_date'], $event['allday'], $event['event_begin_time'], $event['event_end_time']);
            $returnme .= '<table style="width:100%;background-color:#edfafa;border-bottom:1px gray inset; margin:1px;">
                            <tr>
                                <td style="white-space:normal">
                                    '.make_modal_links(array("title"=> stripslashes($event["name"]),"path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event['eventid'],"iframe"=>"true","width"=>"700","height"=>"650")).'
                                    <span style="display:block;color:gray; font-size:.75em;">' . $length . '</span>
                                </td>
                                <td style="text-align:right; padding:2px;white-space:nowrap;">';
            if(!empty($event["start_reg"]) && user_has_ability_in_page($USER->userid, "exportcsv", $pageid, "events", $featureid)){ 
                $returnme .= '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/events/events_ajax.php\',\'export_csv\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $event['eventid'] . '\',function() { run_this();});"><img src="' . $CFG->wwwroot . '/images/csv.png" title="Export Registrations" alt="Export Registrations" /></a>';
            }
            $returnme .= '</td></tr></table>';
        }
    }
    $returnme = $returnme == "" ? false : '<p><br /><b>Recent Events</b><br /><hr /><table style="width:100%;"><tr><td style="white-space:nowrap">' . $returnme . '</td></tr></table>';
    return $returnme;
}

function make_fee_options($min, $full, $name, $options = "", $sale_end = "", $sale = false){
    if($sale_end != "" && $sale && get_timestamp() < $sale_end){ $full = $sale;}
    
    $returnme = '<select id="' . $name . '" name="' . $name . '" ' . $options . ' >';
    $select = "selected";
    
    if($min == $full){ return '<span style="float:left;margin:4px;">$</span><input id="' . $name . '" name="' . $name . '" type="text" READONLY value="' . $full . '.00"/>';}
    
    while($min < $full){
        $returnme .= '<option value="' . $min . '" ' . $select . '>$' . $min . '</option>';
        $min = ($full - $min) > 10 ? $min + 10 : $full;
        $select = "";
    }
    $returnme .= '<option value="' . $min . '">$' . $min . '</option></select>';
    return $returnme;
}

function make_paypal_button($items, $sellersemail){
global $CFG;
    $regids = "";
    $returnme = $CFG->paypal ? '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">' : '<form action="https://www.sandbox.paypal.com/cgi-bin/webscr" method="post" target="_blank">';
    $returnme .= '
        <input type="hidden" name="upload" value="1">
        <input type="hidden" name="cmd" value="_cart">
        <input type="hidden" name="return" value="'.$CFG->wwwroot.'/features/events/events.php?action=showcart">
        <input type="hidden" name="notify_url" value="'.$CFG->wwwroot.'/features/events/ipn.php">
        <input type="hidden" name="business" value="' . $sellersemail . '">';
    $i = 1;
    foreach($items as $item){
        $returnme .= '
            <input type="hidden" name="item_name_' . $i . '" value="' . $item->description . '">
            <input type="hidden" name="amount_' . $i . '" value="' . number_format($item->cost, 2, '.', '') . '">';
        $regids .= $regids == "" ? $item->regid : ":" . $item->regid;
        $i++;
    }
    $returnme .= '<input type="hidden" name="custom" value="' . $regids . '"><input type="hidden" name="no_shipping" value="1"><input type="hidden" name="currency_code" value="USD">
        <input type="hidden" name="tax" value="0.00"><input type="hidden" name="lc" value="US"><input type="hidden" name="bn" value="PP-BuyNowBF">
        <input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but6.gif" border="0" name="submit" alt="Make payments with PayPal - it\'s fast, free and secure!">
        <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" /></form>';
    return $returnme;
}

function get_registrant_name($regid){
global $CFG;
    $SQL = "SELECT * FROM events_templates WHERE template_id IN (SELECT template_id FROM events WHERE eventid IN (SELECT eventid FROM events_registrations WHERE regid='$regid'))";
    $template = get_db_row($SQL);
    $name = "";
    if($template["folder"] == "none"){
        if($name_fields = get_db_result("SELECT * FROM events_templates_forms WHERE template_id=" . $template["template_id"] . " AND nameforemail=1")){
            while($name_field = fetch_row($name_fields)){
                $value = stripslashes(get_db_field("value", "events_registrations_values", "regid='$regid' AND elementid='". $name_field["elementid"] ."'"));
                $name .= $name == "" ? $value : " " . $value;
            }
        }
    }else{
        $name_fields = explode(",", $template["registrant_name"]);
        $i = 0;
        while(isset($name_fields[$i])){
            $value = stripslashes(get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='" . $name_fields[$i] . "'"));
            $name .= $name == "" ? $value : " " . $value;
            $i++;
        }
    }
    
    return ucwords($name);
}

function enter_registration($eventid, $reg, $contactemail){
global $CFG, $why, $error;
    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
    $template = get_db_row("SELECT * FROM events_templates WHERE template_id='" . $event['template_id'] . "'");
    $nolimit = true;
    $why = "";
    $time = get_timestamp();
    $REGIDSQL = "INSERT INTO events_registrations (eventid,date,email,code,verified) VALUES(" . $eventid . "," . $time . ",'$contactemail','" . md5($time . $contactemail) . "','0')";
    $regid = execute_db_sql($REGIDSQL);
    if($template['folder'] != "none"){ //custom file style
        if($regid){
            $formlist = explode(";", get_db_field("formlist", "events_templates", "folder='" . $template['folder'] . "'"));
            $sql_values = "";
            foreach($formlist as $list){    
                $element = explode(":", $list);
                $sql_values .= $sql_values == "" ? "($regid,'" . dbescape($reg[$element[0]]) . "'," . $eventid . ",'" . $element[0] . "')" : ",($regid,'" . dbescape($reg[$element[0]]) . "'," . $eventid . ",'" . $element[0] . "')";
            }
            $SQL = "INSERT INTO events_registrations_values (regid,value,eventid,elementname) VALUES" . $sql_values;
            if($entries = execute_db_sql($SQL) && $nolimit = hard_limits($regid, $event, $template)) {
                if(!$nolimit = soft_limits($regid, $event, $template)){
                    $error = "Because this event has $why, you have been placed in the waiting line for this event.";
                    execute_db_sql("UPDATE events_registrations SET queue='1' WHERE regid='$regid'");
                }
                return $regid; //Success
            }else {
                if(!$nolimit){ $error = "We are sorry, because this event has $why, you are unable to register for this event.";}
                else{ $error = "We are sorry, there has been an error while trying to register for this event.  Please try again. ERROR CODE: 0001";}
                execute_db_sql("DELETE FROM events_registrations WHERE regid='$regid'");
                execute_db_sql("DELETE FROM events_registrations_values WHERE regid='$regid'");
                log_entry("event", $event["name"], "Failed Registration", $error . ": " . $SQL);
                return false;
            }
        }else{  
            $error = "We are sorry, there has been an error while trying to register for this event.  Please try again. ERROR CODE: 0002";
            log_entry("event", $event["name"], "Failed Registration", $error . ": " . $REGIDSQL);
            return false;
        }
    }else{ //db form style
        $sql_values = "";
        if($elements = get_db_result("SELECT * FROM events_templates_forms WHERE template_id='" . $event['template_id'] . "' ORDER BY sort")){
	        while($element = fetch_row($elements)){
	            if($event["fee_full"] != 0 && $element["type"] == "payment") {
	                $sql_values .= $sql_values == "" ? "('$eventid','$regid','" . $element['elementid'] . "','" . $event["fee_full"] . "','total_owed'),('$eventid','$regid','" . $element['elementid'] . "','0','paid'),('$eventid','$regid','" . $element['elementid'] . "','" . $reg["payment_method"] . "','payment_method')" : ",('$eventid','$regid','" . $element['elementid'] . "','" . $event["fee_full"] . "','total_owed'),('$eventid','$regid','" . $element['elementid'] . "','0','paid'),('$eventid','$regid','" . $element['elementid'] . "','" . $reg["payment_method"] . "','payment_method')";
	            }elseif(isset($reg[$element['elementid']])){
                    $sql_values .= $sql_values == "" ? "('$eventid','$regid','" . $element['elementid'] . "','" . $reg[$element['elementid']] . "','" . $element['display'] . "')" : ",('$eventid','$regid','" . $element['elementid'] . "','" . dbescape($reg[$element['elementid']]) . "','" . $element['display'] . "')";
                }
	        }
        }
        $SQL = "INSERT INTO events_registrations_values (eventid,regid,elementid,value,elementname) VALUES" . $sql_values;
        if($entries = execute_db_sql($SQL) && $nolimit = hard_limits($regid, $event, $template)){
            if(!$nolimit = soft_limits($regid, $event, $template)){
                $error = "Because this event has $why, you have been placed in the waiting line for this event.";
                execute_db_sql("UPDATE events_registrations SET queue=1 WHERE regid=" . $regid);
            }
            return $regid; //Success
        }else{
            if(!$nolimit){ $error = "We are sorry, because this event has $why, you are unable to register for this event.";
            }else{ $error = "We are sorry, there has been an error while trying to register for this event.  Please try again. ERROR CODE: 0003"; }
            execute_db_sql("DELETE FROM events_registrations WHERE regid='$regid'");
            execute_db_sql("DELETE FROM events_registrations_values WHERE regid='$regid'");
            log_entry("event", $event["name"], "Failed Registration", $error);
            return false;
        }
    }
}

function registration_email($regid, $touser){
global $CFG;
    $reg = get_db_row("SELECT * FROM events_registrations WHERE regid='$regid'");
    $event = get_db_row("SELECT * FROM events WHERE eventid='" . $reg["eventid"]."'");
    $template = get_db_row("SELECT * FROM events_templates WHERE template_id='" . $event["template_id"]."'");
    $returnme = '	<p><font size="3"><strong>Thank you </strong></font>for registering ' . $touser->fname ." ". $touser->lname . ' for <font size="2"><strong>' . $event["name"] . '.</strong>&nbsp; </font></p>  <strong>Please keep this email for your records.  It contains a registration ID that can allow you to make payments on your registration.</strong>';
    if($event["fee_full"] != 0){
        $total_owed = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='total_owed'");
        if(empty($total_owed)){
            $total_owed = $reg["date"] < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
        }
        $total_owed = empty($total_owed) ? $event["fee_full"] : $total_owed;

        $paid = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='paid'");
        $paid = empty($paid) ? 0 : $paid;
		$remaining = $total_owed - $paid;

        $returnme .= '	<p><strong>Total Paid:</strong> $'.number_format($paid,2).'<br /><strong>Remaining Balance:</strong> $'.number_format($remaining,2).'<br /><br /><em>Note:This event requires payment in full to complete the registration process.  The above balances may not reflect recent changes.</em></p>';
        
        $returnme .= '	<p><strong>Registration ID:</strong><font color="#993300"><strong> ' . $reg["code"] . '</strong></font></p>';

        //If event can be paid via paypal
        if($event["paypal"] != ""){
            $returnme .= '	<p><strong>Make payment online:</strong> <a href="'.$CFG->wwwroot.'/features/events/events.php?action=pay&amp;i=!&amp;regcode='.$reg["code"].'">Make Payment</a></p>';
        }
            
        $returnme .= '<p><strong>Make payment by check or money order: </strong><br />Payable to: ' . stripslashes($event['payableto']) . '<br />' . stripslashes($event['checksaddress']) . '<br />On the memo line be sure to write "' . $touser->fname ." ". $touser->lname . ' - ' . $event["name"] . '".</p>';
    }
    $returnme .= '<p><font size="2">If you have any questions about this event, contact ' . $event["contact"] . ' at <a href="mailto:' . $event["email"] . '">' . $event["email"] . '</a> </font>. 
                <br />We hope that you have enjoyed your time on the <strong>' . $CFG->sitename . ' </strong>website.</p>';
    return $returnme;
}

function registration_pending_email($regid, $touser){
global $CFG;
    $reg = get_db_row("SELECT * FROM events_registrations WHERE regid='$regid'");
    $event = get_db_row("SELECT * FROM events WHERE eventid='" . $reg["eventid"]."'");
    $template = get_db_row("SELECT * FROM events_templates WHERE template_id='" . $event["template_id"]."'");
    $returnme = '	<p><font size="3"><strong>Thank you </strong></font>for beginning the registration process of ' . $touser->fname ." ". $touser->lname . ' for <font size="2"><strong>' . $event["name"] . '.</strong>&nbsp; </font></p>  <strong>Please keep this email for your records.  It contains a registration ID that can allow you to make payments on your registration.</strong>';
    if($event["fee_full"] != 0){
        $total_owed = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='total_owed'");
        $paid = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='paid'");
		$remaining = $total_owed - $paid;

        $returnme .= '	<p><strong>To complete the registration process, an advance payment must be made.<br /><br /><strong>Remaining Balance:</strong> $'.number_format($remaining,2).'<br /><br /></p>';
        
        $returnme .= '	<p><strong>Registration ID:</strong><font color="#993300"><strong> ' . $reg["code"] . '</strong></font></p>';

        //If event can be paid via paypal
        if($event["paypal"] != ""){
            $returnme .= '	<p><strong>Make payment online:</strong> <a href="'.$CFG->wwwroot.'/features/events/events.php?action=pay&amp;i=!&amp;regcode='.$reg["code"].'">Make Payment</a></p>';
        }
            
        $returnme .= '<p><strong>Make payment by check or money order: </strong><br />Payable to: ' . stripslashes($event['payableto']) . '<br />' . stripslashes($event['checksaddress']) . '<br />On the memo line be sure to write "' . $touser->fname ." ". $touser->lname . ' - ' . $event["name"] . '".</p>';
    }
    $returnme .= '<p><font size="2">If you have any questions about this event, contact ' . $event["contact"] . ' at <a href="mailto:' . $event["email"] . '">' . $event["email"] . '</a> </font>. 
                <br />We hope that you have enjoyed your time on the <strong>' . $CFG->sitename . ' </strong>website.</p>';
    return $returnme;
}

function get_template_field_displayname($templateid,$fieldname){
    $template = get_db_row("SELECT * FROM events_templates WHERE template_id='$templateid'");
    if($template["folder"] == "none"){
        return get_db_field("display", "events_templates_forms", "elementid='$fieldname'");       
    }else{
        $fields = explode(";",$template["formlist"]);
        foreach($fields as $f){
            $field = explode(":",$f);
            if($field[0] == $fieldname){
                return $field[2]; 
            }
        }
    }
    return $fieldname;
}

function hard_limits($regid, $event, $template){
global $CFG, $why;
    //If there are no custom limits in place, just return a passing grade
    if ($event["hard_limits"] == ""){ return true; }
    $limits_array = explode("*", $event["hard_limits"]);
    $i = 0;
    while(isset($limits_array[$i])){
        $limit = explode(":", $limits_array[$i]);
        $elementtype = $template["folder"] == "none" ? "elementid" : "elementname";
        $SQL = "SELECT * FROM events_registrations_values WHERE eventid='" . $event["eventid"] . "' AND $elementtype='" . $limit[0] . "' AND value" . make_limit_statement($limit[1], $limit[2], true);
        if(get_db_row($SQL . "AND regid='$regid'")){ 
            $field_count = get_db_count($SQL);
            if($field_count > $limit[3]){ //if registration limit is reached
                $displayname = get_template_field_displayname($template["template_id"],$limit[0]);
                $why = "reached the limit of " . $limit[3] . " registrations where " . $displayname . make_limit_statement($limit[1], $limit[2], false);
                return false;
            }                
        }
        $i++;
    }
    return true;
}

function soft_limits($regid, $event, $template){
global $CFG, $why;
    //If there are no custom limits in place, just return a passing grade
    if($event["soft_limits"] == ""){ return true; }
    $limits_array = explode("*", $event["soft_limits"]);
    $i = 0;
    while(isset($limits_array[$i])){
        $limit = explode(":", $limits_array[$i]);
        $elementtype = $template["folder"] == "none" ? "elementid" : "elementname";
        $SQL = "SELECT * FROM events_registrations_values WHERE eventid='" . $event["eventid"] . "' AND $elementtype='" . $limit[0] . "' AND value" . make_limit_statement($limit[1], $limit[2], true);
        if(get_db_row($SQL . "AND regid='$regid'")){ 
            $field_count = get_db_count($SQL);
            if($field_count > $limit[3]){ //if registration limit is reached
                $displayname = get_template_field_displayname($template["template_id"],$limit[0]);
                $why = "reached the limit of " . $limit[3] . " registrations where " . $displayname . make_limit_statement($limit[1], $limit[2], false);
                return false;
            }                
        }
        $i++;
    }
    return true;
}

function make_limit_statement($operator, $value, $SQLmode = false){
    $quotes = is_numeric($value) ? "" : "'";
    if($SQLmode){
        switch ($operator) {
            case "eq":
                return " = $quotes$value$quotes ";
                break;
            case "neq":
                return " != $quotes$value$quotes ";
                break;
            case "gt":
                return " > $value ";
                break;
            case "lt":
                return " < $value ";
                break;
            case "lk":
                return " LIKE '%$value%' ";
                break;
            case "lk":
                return " NOT LIKE '%$value%' ";
                break;
            case "gteq":
                return " >= $value ";
                break;
            case "lteq":
                return " <= $value ";
                break;
        }
    }else{
        switch ($operator) {
            case "eq":
                return " is $value";
                break;
            case "neq":
                return " is not $value";
                break;
            case "gt":
                return " is greater than $value";
                break;
            case "lt":
                return " is less than $value";
                break;
            case "lk":
                return " is like $value";
                break;
            case "nlk":
                return " is not like $value";
                break;
            case "gteq":
                return " is greater than or equal to $value";
                break;
            case "lteq":
                return " is less than or equal to $value";
                break;
        }
    }
}

//Delete an event
function delete_event(){
global $MYVARS, $CFG, $USER;
    $eventid = dbescape($MYVARS->GET['featureid']);
    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
    delete_calendar_events($event);
    
    if($eventid){
        execute_db_sql("DELETE FROM events WHERE eventid='$eventid'");
        execute_db_sql("DELETE FROM calendar_events WHERE eventid='$eventid'");
        execute_db_sql("DELETE FROM events_registrations WHERE eventid='$eventid'");
        execute_db_sql("DELETE FROM events_registrations_values WHERE eventid='$eventid'");
    }
    
    //Log
    log_entry("event", $event["name"], "Deleted Event");
    echo "";
}

function refresh_calendar_events($eventid){
    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
    $siteviewable = $event["confirmed"];
    $startdate = $event["event_begin_date"];
    $event_end_date = $event["event_end_date"];
    
    delete_calendar_events($event); //Delete old calendar events
    $caleventid = "";
    while($startdate <= $event_end_date){
        $caleventid .= $caleventid == "" ? "" : ":";
        date_default_timezone_set("UTC");
        $day = date('d',$startdate);
        $month = date('m',$startdate);
        $year = date('Y',$startdate);
        $event_begin_time = $event["event_begin_time"] == "NULL" ? "" : $event["event_begin_time"];
        $event_end_time = $event["event_end_time"] == "NULL" ? "" : $event["event_end_time"];
        $SQL = "INSERT INTO calendar_events (eventid,date,title,event,location,cat,starttime,endtime,day,month,year,site_viewable,groupid,pageid) VALUES('$eventid',".$startdate.",'" .dbescape($event["name"]). "','".dbescape($event["extrainfo"]). "','" .$event["location"]. "','" .$event["category"]. "','" .$event_begin_time . "','" . $event_end_time . "',$day,$month,$year,$siteviewable,0," .$event["pageid"]. ")";
        $caleventid .= execute_db_sql($SQL);
        $startdate += 86400; //Advance 1 day
    }

    execute_db_sql("UPDATE events SET caleventid='$caleventid' WHERE eventid='$eventid'");               
}

//Confirms a site event
function confirm_event($pageid = false, $eventid = false, $confirm = false){
global $MYVARS, $CFG, $USER;
    date_default_timezone_set("UTC");
    $eventid = $eventid ? $eventid : dbescape($MYVARS->GET['featureid']);
    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
    $confirm = !empty($confirm) ? '1' : (empty($MYVARS->GET['confirm']) ? "0" : "1");

    //Make Calendar event
    if(!empty($confirm)){
        //Set events to confirm then refresh
        execute_db_sql("UPDATE events SET confirmed='$confirm' WHERE eventid='$eventid'");
        refresh_calendar_events($eventid);
    }else{ //NO to site viewability 
        //Set events to confirm
        if($event["pageid"] == $CFG->SITEID){
            delete_calendar_events($event);
        }
        execute_db_sql("UPDATE events SET confirmed='$confirm' WHERE eventid='$eventid'");
    }
    //Log
    log_entry("events", $event["name"], "Confirmed Event's Visibility");
    echo "";
}

//Make sure that the calendar is edited when the event is edited.
function delete_calendar_events($event){
    //if calendar events exist
    if(!empty($event['caleventid'])){
        $calevents = explode(":", $event['caleventid']);
        $i = 0;
        while(isset($calevents[$i])){
            execute_db_sql("DELETE FROM calendar_events WHERE id='$calevents[$i]'");
            $i++;
        }
    }
}

function get_event_length($startdate, $enddate, $allday, $starttime, $endtime){
    date_default_timezone_set(date_default_timezone_get());
    if ($startdate == $enddate){ //ONE DAY EVENT
        $length = date("n/j/Y", $startdate);
    }else{ //Multiple Days
        $length = date("n/j/Y", $startdate) . " - " . date("n/j/Y", $enddate);
    }
    if(!$allday){
        $length .= "<br />from ";
        $start = convert_time($starttime);
        $finish = convert_time($endtime);
        $length .= $start . " to " . $finish;
    }
    return $length;
}

function get_templates($selected = false, $eventid="", $activeonly=false){
global $CFG;
    $returnme = check_for_new_templates();
    $active = !empty($activeonly) ? ' activated=1' : '';
    if($templates = get_db_result("SELECT * FROM events_templates WHERE $active ORDER BY name")){
        while($template = fetch_row($templates)){
            $returnme .= $returnme == "" ? '<select id="template" onchange="clear_limits(); ajaxapi(\'/features/events/events_ajax.php\',\'show_template_settings\',\'&amp;eventid='.$eventid.'&amp;templateid=\'+document.getElementById(\'template\').value,function(){ simple_display(\'template_settings_div\');});"><option value="0">Select a template</option>' : '';
            $selectme = $selected && ($template['template_id'] == $selected) ? ' selected' : '';
            $returnme .= '<option value="' . $template['template_id'] . '"' . $selectme . '>' . stripslashes($template['name']) . '</option>';
        }
    }
    $returnme .= $returnme == "" ? "No templates exist" : '</select> <a href="javascript:void(0);" onclick="window.open(\'' . $CFG->wwwroot . '/features/events/preview.php?action=preview_template&amp;template_id=\'+document.getElementById(\'template\').value,\'Template\',\'menubar=yes,toolbar=yes,scrollbars=1,resizable=1,width=600,height=400\');">Preview</a>';
    return $returnme;
}

function get_template_settings($templateid,$eventid){
global $CFG;
    $returnme = "";
    if(!empty($templateid) && $template_settings = get_db_field("settings","events_templates","template_id='$templateid'")){ //template settings
        if(!empty($template_settings)){ //there are settings in this template
            $returnme = '<table style="margin:0px 0px 0px 50px;"><tr><td class="field_title" style="width:115px;">Template Settings</td><td class="field_input"></td></tr>';
            $settings = unserialize($template_settings);
            foreach($settings as $setting){ //save each setting with the default if no other is given
                $set = get_db_field("setting","settings","type='events_template' AND extra='$eventid' AND setting_name='".$setting['name']."'");
                $current_setting = !empty($set) ? $set : $setting['default'];
                $returnme .= make_setting_input($setting["name"],$setting["title"],$setting["type"],NULL,NULL,$current_setting,$setting["numeric"],$setting["extravalidation"], $setting["extra_alert"],false);
            }
        }
    }
    return $returnme;
}

function get_possible_times($formid, $selected_time = "false", $start_time = "false"){
    $times = array("00:00*12:00 am", "00:30*12:30 am", "01:00*01:00 am", "01:30*01:30 am", "02:00*02:00 am", "02:30*02:30 am", "03:00*03:00 am", "03:30*03:30 am", "04:00*04:00 am", "04:30*04:30 am", "05:00*05:00 am", "05:30*05:30 am", "06:00*06:00 am", "06:30*06:30 am", "07:00*07:00 am", "07:30*07:30 am", "08:00*08:00 am", "08:30*08:30 am", "09:00*09:00 am", "09:30*09:30 am", "10:00*10:00 am", "10:30*10:30 am", "11:00*11:00 am", "11:30*11:30 am", "12:00*12:00 pm", "12:30*12:30 pm", "13:00*01:00 pm", "13:30*01:30 pm", "14:00*02:00 pm", "14:30*02:30 pm", "15:00*03:00 pm", "15:30*03:30 pm", "16:00*04:00 pm", "16:30*04:30 pm", "17:00*05:00 pm", "17:30*05:30 pm", "18:00*06:00 pm", "18:30*06:30 pm", "19:00*07:00 pm", "19:30*07:30 pm", "20:00*08:00 pm", "20:30*08:30 pm", "21:00*09:00 pm", "21:30*09:30 pm", "22:00*10:00 pm", "22:30*10:30 pm", "23:00*11:00 pm", "23:30*11:30 pm");
    $onchange = $formid == 'begin_time' ? 'onchange="get_end_time(this.value);"' : '';
    $to = $formid == 'begin_time' ? '<div style="font-size:.75em; color:green;">From </div>' : '<div style="font-size:.75em; color:green;">&nbsp; To </div>';
    $returnme = $to . '<select id="' . $formid . '" ' . $onchange . '><option></option>';
    $i = 0;
    $from = false;
    while(isset($times[$i])){
        $time = explode("*", $times[$i]);
        if($start_time != "false" && $from){
            if(strstr($time[0], $selected_time)){ $returnme .= '<option value="' . $time[0] . '" selected>' . $time[1] . '</option>';}
            else{ $returnme .= '<option value="' . $time[0] . '">' . $time[1] . '</option>';}
        }
        if($start_time == "false"){
            if(strstr($time[0], $selected_time)){ $returnme .= '<option value="' . $time[0] . '" selected>' . $time[1] . '</option>'; }
            else{ $returnme .= '<option value="' . $time[0] . '">' . $time[1] . '</option>'; }
        }
        $from = strstr($time[0], $start_time) ? true : $from;
        $i++;
    }
    $returnme .= '</select>';
    return $returnme;
}

function get_my_locations($userid, $selected = false, $eventid=false){
    $returnme = "";
    $union_statement = $eventid ? " UNION SELECT * FROM events_locations WHERE id IN (SELECT location FROM events WHERE eventid=$eventid)" : "";
    $SQL = "SELECT * FROM events_locations WHERE userid LIKE '%,$userid,%' $union_statement GROUP BY id ORDER BY location";

	if($locations = get_db_result($SQL)){
        while($location = fetch_row($locations)){
            $returnme .= $returnme == "" ? '<select id="location">' : '';
            $selectme = $selected && ($location['id'] == $selected) ? ' selected' : '';
            $returnme .= '<option value="' . $location['id'] . '"' . $selectme . '>' . stripslashes($location['location']) . '</option>';
        }
    }
    $returnme .= $returnme == "" ? "You must add a location." : "</select>";
    return $returnme;
}

function get_my_hidden_limits($templateid, $hard_limits, $soft_limits){
    $returnme = "";
    if(empty($templateid)){ return $returnme; }
    $hidden_variable1 = $hidden_variable2 = "";
    
    if(!empty($hard_limits)){ // There are some hard limits
        $limits_array = explode("*", $hard_limits);
        $i = 0;
        $returnme .= "<br /><strong>Hard Limits</strong> <br />";
        $hidden_variable1 = "";
        while(!empty($limits_array[$i])){
            $limit = explode(":", $limits_array[$i]);
            if(!empty($limit)){
                $displayname = get_template_field_displayname($templateid,$limit[0]);
                $returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . '&nbsp;-&nbsp;<a href="javascript:void(0);" onclick="delete_limit(\'hard_limits\',\'' . $i . '\');">Delete</a><br />';
                $hidden_variable1 .= $hidden_variable1 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
            }
            $i++;
        }
    }
    
    if(!empty($soft_limits)){ // There are some soft limits
        $limits_array = explode("*", $soft_limits);
        $i = 0;
        $returnme .= "<br /><strong>Soft Limits</strong> <br />";
        $hidden_variable2 = "";
        while (isset($limits_array[$i])){
            $limit = explode(":", $limits_array[$i]);
            if(!empty($limit)){
                $displayname = get_template_field_displayname($templateid,$limit[0]);
                $returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . '&nbsp;-&nbsp;<a href="javascript:void(0);" onclick="delete_limit(\'soft_limits\',\'' . $i . '\');">Delete</a><br />';
                $hidden_variable2 .= $hidden_variable2 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
            }
            $i++;
        }
    }
    return $returnme . '<input type="hidden" id="hard_limits" value="' . $hidden_variable1 . '" />' . '<input type="hidden" id="soft_limits" value="' . $hidden_variable2 . '" />';
}

function get_my_category($selected = false){
    $returnme = "";
    if($categories = get_db_result("SELECT * FROM calendar_cat ORDER BY cat_id")){
        while($category = fetch_row($categories)){
            $returnme .= $returnme == "" ? '<select id="category">' : '';
            $selectme = $selected && ($category['cat_id'] == $selected) ? ' selected' : '';
            $returnme .= '<option value="' . $category['cat_id'] . '"' . $selectme . '>' . stripslashes($category['cat_name']) . '</option>';
        }
    }
    $returnme .= $returnme == "" ? "No categories exist." : "</select>";
    return $returnme;
}

function staff_application_form($row, $viewonly = false){
global $USER, $CFG, $MYVARS;
    $v["staffid"] = empty($row) ? false : $row["staffid"];
    $v["name"] = empty($row) ? $USER->fname . " " . $USER->lname : $row["name"];
    $v["phone"] = empty($row) ? "" : $row["phone"];
    $v["dateofbirth"] = empty($row) ? "" : (isset($row['dateofbirth']) ? date('m/d/Y',$row['dateofbirth']) : '');
    $v["address"] = empty($row) ? "" : $row["address"];
    $v["ar1selected"] = empty($row) ? "" : ($row["agerange"] == "0" ? "selected" : "");
    $v["ar2selected"] = empty($row) ? "" : ($row["agerange"] == "1" ? "selected" : "");
    $v["ar3selected"] = empty($row) ? "" : ($row["agerange"] == "2" ? "selected" : "");
    $v["cocmembernoselected"] = empty($row) ? "" : ($row["cocmember"] == "0" ? "selected" : "");
    $v["cocmemberyesselected"] = empty($row) ? "" : ($row["cocmember"] == "1" ? "selected" : "");
    $v["congregation"] = empty($row) ? "" : $row["congregation"];
    $v["priorworknoselected"] = empty($row) ? "" : ($row["priorwork"] == "0" ? "selected" : "");
    $v["priorworkyesselected"] = empty($row) ? "" : ($row["priorwork"] == "1" ? "selected" : "");
    $v["q1_1noselected"] = empty($row) ? "" : ($row["q1_1"] == "0" ? "selected" : "");
    $v["q1_1yesselected"] = empty($row) ? "" : ($row["q1_1"] == "1" ? "selected" : "");
    $v["q1_2noselected"] = empty($row) ? "" : ($row["q1_2"] == "0" ? "selected" : "");
    $v["q1_2yesselected"] = empty($row) ? "" : ($row["q1_2"] == "1" ? "selected" : "");
    $v["q1_3noselected"] = empty($row) ? "" : ($row["q1_3"] == "0" ? "selected" : "");
    $v["q1_3yesselected"] = empty($row) ? "" : ($row["q1_3"] == "1" ? "selected" : "");
    $v["q2_1noselected"] = empty($row) ? "" : ($row["q2_1"] == "0" ? "selected" : "");
    $v["q2_1yesselected"] = empty($row) ? "" : ($row["q2_1"] == "1" ? "selected" : "");
    $v["q2_2noselected"] = empty($row) ? "" : ($row["q2_2"] == "0" ? "selected" : "");
    $v["q2_2yesselected"] = empty($row) ? "" : ($row["q2_2"] == "1" ? "selected" : "");
    $v["q2_3"] = empty($row) ? "" : $row["q2_3"];
    
    $v["yestotal"] = empty($row) ? 0 : $row["q1_1"] + $row["q1_2"] + $row["q1_3"] + $row["q2_1"] + $row["q2_2"];
    
    $v["parentalconsent"] = empty($row) ? "" : $row["parentalconsent"];
    $v["parentalconsentsig"] = empty($row) ? "" : ($row["parentalconsentsig"] == "on" ? "checked" : "");
    
    $v["sub18dispaly"] = empty($v["ar1selected"]) ? "display:none" : "";
    $v["workerconsent"] = empty($row) ? "" : $row["workerconsent"];
    $v["workerconsentsig"] = empty($row) ? "" : ($row["workerconsentsig"] == "on" && $viewonly  ? "checked" : "");
    $v["workerconsentdate"] = empty($row) ? date('m/d/Y') : (!empty($row['workerconsentdate']) && $viewonly ? date('m/d/Y',$row['workerconsentdate']) : date('m/d/Y'));

    $v["ref1name"] = empty($row) ? "" : $row["ref1name"];
    $v["ref1relationship"] = empty($row) ? "" : $row["ref1relationship"];
    $v["ref1phone"] = empty($row) ? "" : $row["ref1phone"];

    $v["ref2name"] = empty($row) ? "" : $row["ref2name"];
    $v["ref2relationship"] = empty($row) ? "" : $row["ref2relationship"];
    $v["ref2phone"] = empty($row) ? "" : $row["ref2phone"];

    $v["ref3name"] = empty($row) ? "" : $row["ref3name"];
    $v["ref3relationship"] = empty($row) ? "" : $row["ref3relationship"];
    $v["ref3phone"] = empty($row) ? "" : $row["ref3phone"];
        
    return '<div class="formDiv" id="staffapplication_form_div">
         '.($viewonly ? '' : '  <style>
                                    .rowContainer { 
                                        padding-bottom: 25px;width:initial;
                                    }
                                    .fieldtitle {
                                        width: 100% !important;
                                    }
                                    input[type="submit"] {
                                        padding: 3px 5px;
                                    }
                                    .rowContainer input[type="text"],textarea {
                                        width: 350px !important;
                                    }

                                    /* ------------------------------
                                    This CSS should be used site wide
                                    -------------------------------- */
                                    .rowContainer input,select,textarea {
                                        display: block;
                                        margin-right: 20px;
                                        padding: 6px 12px;
                                        font-size: 14px;
                                        line-height: 1.42857143;
                                        color: #555;
                                        background-image: none;
                                        border: 1px solid #ccc;
                                        border-radius: 4px;
                                        -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
                                        box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
                                        -webkit-transition: border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s;
                                        -o-transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
                                        transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
                                    }
                                    hr {
                                        background-color: #ccc;
                                        border: 0;
                                        height: 1px;
                                        margin: 8px;
                                    }
                                    input[type="checkbox"] {
                                        appearance: none;
                                        background-color: #fafafa;
                                        border: 1px solid #d3d3d3;
                                        border-radius: 26px;
                                        cursor: pointer;
                                        height: 28px;
                                        position: relative;
                                        transition: border .25s .15s, box-shadow .25s .3s, padding .25s;
                                        width: 44px;
                                        vertical-align: top;
                                        -webkit-appearance: none;
                                    }
                                    input[type="checkbox"]:after {
                                        background-color: white;
                                        border: 1px solid #d3d3d3;
                                        border-radius: 24px;
                                        box-shadow: inset 0 -3px 3px rgba(0, 0, 0, 0.025), 0 1px 4px rgba(0, 0, 0, 0.15), 0 4px 4px rgba(0, 0, 0, 0.1);
                                        content:"";
                                        display: block;
                                        height: 24px;
                                        left: 0;
                                        position: absolute;
                                        right: 16px;
                                        top: 0;
                                        transition: border .25s .15s, left .25s .1s, right .15s .175s;
                                    }
                                    input[type="checkbox"]:checked {
                                        border-color: #53d76a;
                                        box-shadow: inset 0 0 0 13px #53d76a;
                                        padding-left: 18px;
                                        transition: border .25s, box-shadow .25s, padding .25s .15s;
                                    }
                                    input[type="checkbox"]:checked:after {
                                        border-color: #53d76a;
                                        left: 16px;
                                        right: 0;
                                        transition: border .25s, left .15s .25s, right .25s .175s;
                                    }
                                    /* ------------------------------
                                    ---------------------------------------------------------------------------------------------------------------------------
                                    -------------------------------- */
                                    .rowContainer label { '.($viewonly ? 'width: 250px;padding-right: 20px;' : 'width: initial;padding-right: 15px;').' }
                                </style>').'
         '.($viewonly ? '' : '<p align="center"><b><font size="+1">Staff Application</font></b></p><br /><br />').'
    		'.($viewonly ? '<div style="text-align:center"><h2>' . $v["name"] . ' Application</h2></div>' : '<br /><br />').'
    		<form name="staffapplication_form" id="staffapplication_form">
                '.(empty($v["staffid"]) ? '' : '<input type="hidden" id="staffid" name="staffid" value="'.$v["staffid"].'" />').'
    			<fieldset class="formContainer" '.($viewonly ? '' : 'style="width: 420px;margin-left: auto;margin-right: auto;"').'>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="name">Name</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="name" name="name" value="'.$v["name"].'" data-rule-required="true" data-msg-required="'.get_error_message('valid_staff_name:events').'" /><div class="tooltipContainer info">'.get_help("input_staff_name:events").'</div><br />
    				</div>
                    <div class="rowContainer">
        				<label class="fieldtitle" for="dateofbirth">Date of Birth</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="dateofbirth" name="dateofbirth" value="'.$v["dateofbirth"].'" data-rule-required="true" data-rule-date="true" /><div class="tooltipContainer info">'.get_help("input_staff_dob:events").'</div>
        			</div>
                    <div class="rowContainer">
				        <label class="fieldtitle" for="phone">Phone</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="phone" name="phone" value="'.$v["phone"].'" data-rule-required="true"  data-rule-phone="true" data-msg-required="'.get_error_message('valid_staff_phone:events').'" data-msg-phone="'.get_error_message('valid_staff_phone_invalid:events').'" /><div class="tooltipContainer info">'.get_help("input_staff_phone:events").'</div><br />
                    </div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="address">Address</label>'.($viewonly ? '' : '<br />').'<textarea '.($viewonly ? 'disabled="disabled"' : '').' rows="3" id="address" name="address" data-rule-required="true">'.$v["address"].'</textarea><div class="tooltipContainer info">'.get_help("input_staff_address:events").'</div><br />
    	  			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="agerange">Age Range</label>'.($viewonly ? '' : '<br />').'<select '.($viewonly ? 'disabled="disabled"' : '').' id="agerange" name="agerange" data-rule-required="true" onchange="if($(this).val() != 0){ $(\'#sub18\').hide(); $(\'#parentalconsent\').val(\'\'); $(\'#parentalconsent\').removeData(\'rule-required\').removeAttr(\'data-rule-required\'); $(\'#parentalconsentsig\').prop(\'checked\', false); $(\'#parentalconsentsig\').removeData(\'rule-required\').removeAttr(\'data-rule-required\'); } if($(this).val() == 0){ $(\'#parentalconsent\').val(\'\'); $(\'#parentalconsentsig\').prop(\'checked\', false); $(\'#parentalconsent\').removeData(\'rule-required\').attr(\'data-rule-required\',\'true\'); $(\'#sub18\').show(); }"><option>Please select</option><option value="0" '.$v["ar1selected"].'>18 or younger</option><option value="1" '.$v["ar2selected"].'>19-25</option><option value="2" '.$v["ar3selected"].'>26 or older</option></select><div class="tooltipContainer info">'.get_help("input_staff_agerange:events").'</div><br />
    	  			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="cocmember">Are you a member of the church of Christ?</label>'.($viewonly ? '' : '<br />').'<select '.($viewonly ? 'disabled="disabled"' : '').' style="width:80px" id="cocmember" name="cocmember" data-rule-required="true"><option value="0" '.$v["cocmembernoselected"].'>No</option><option value="1" '.$v["cocmemberyesselected"].'>Yes</option></select><div class="tooltipContainer info">'.get_help("input_staff_cocmember:events").'</div><br />
    	  			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="congregation">Congregation Name</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text"  id="congregation" name="congregation" value="'.$v["congregation"].'" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_congregation:events").'</div><br />
    	  			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="priorwork">Have you worked at Camp Wabashi as a staff member before?</label>'.($viewonly ? '' : '<br />').'<select '.($viewonly ? 'disabled="disabled"' : '').' style="width:80px" id="priorwork" name="priorwork" data-rule-required="true"><option value="0" '.$v["priorworknoselected"].'>No</option><option value="1" '.$v["priorworkyesselected"].'>Yes</option></select><div class="tooltipContainer info">'.get_help("input_staff_priorwork:events").'</div><br />
    	  			</div>
                    <br /><hr><br />
                    <h3>Have you at any time ever:</h3>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="q1_1">Been arrested for any reason?</label>'.($viewonly ? '' : '<br />').'<select '.($viewonly ? 'disabled="disabled"' : '').' onchange="if(($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0){ $(\'#q2_3\').attr(\'data-rule-required\', \'true\'); } else { $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\'); }" style="width:80px" id="q1_1" name="q1_1" data-rule-required="true"><option value="0" '.$v["q1_1noselected"].'>No</option><option value="1" '.$v["q1_1yesselected"].'>Yes</option></select><div class="tooltipContainer info">'.get_help("input_staff_q1_1:events").'</div><br />
    	  			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="q1_2">Been convicted of, or pleaded guilty or no contest to, any crime?</label>'.($viewonly ? '' : '<br />').'<select '.($viewonly ? 'disabled="disabled"' : '').' onchange="if(($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0){ $(\'#q2_3\').attr(\'data-rule-required\', \'true\'); } else { $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\'); }" style="width:80px" id="q1_2" name="q1_2" data-rule-required="true"><option value="0" '.$v["q1_2noselected"].'>No</option><option value="1" '.$v["q1_2yesselected"].'>Yes</option></select><div class="tooltipContainer info">'.get_help("input_staff_q1_2:events").'</div><br />
    	  			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="q1_3">Engaged in, or been accused of, any child molestation, exploitation, or abuse?</label>'.($viewonly ? '' : '<br />').'<select '.($viewonly ? 'disabled="disabled"' : '').' onchange="if(($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0){ $(\'#q2_3\').attr(\'data-rule-required\', \'true\'); } else { $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\'); }" style="width:80px" id="q1_3" name="q1_3" data-rule-required="true"><option value="0" '.$v["q1_3noselected"].'>No</option><option value="1" '.$v["q1_3yesselected"].'>Yes</option></select><div class="tooltipContainer info">'.get_help("input_staff_q1_3:events").'</div><br />
    	  			</div>
                    <br /><hr><br />
                    <h3>Are you aware of:</h3>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="q2_1">Having any traits or tendencies that could pose any threat to children, youth, or others?</label>'.($viewonly ? '' : '<br />').'<select '.($viewonly ? 'disabled="disabled"' : '').' onchange="if(($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0){ $(\'#q2_3\').attr(\'data-rule-required\', \'true\'); } else { $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\'); }" style="width:80px" id="q2_1" name="q2_1" data-rule-required="true"><option value="0" '.$v["q2_1noselected"].'>No</option><option value="1" '.$v["q2_1yesselected"].'>Yes</option></select><div class="tooltipContainer info">'.get_help("input_staff_q2_1:events").'</div><br />
    	  			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="q2_2">Any reason why you should not work with children, youth, or others?</label>'.($viewonly ? '' : '<br />').'<select '.($viewonly ? 'disabled="disabled"' : '').' onchange="if(($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0){ $(\'#q2_3\').attr(\'data-rule-required\', \'true\'); } else { $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\'); }" style="width:80px" id="q2_2" name="q2_2" data-rule-required="true"><option value="0" '.$v["q2_2noselected"].'>No</option><option value="1" '.$v["q2_2yesselected"].'>Yes</option></select><div class="tooltipContainer info">'.get_help("input_staff_q2_2:events").'</div><br />
    	  			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="q2_3">If the answer to any of these questions is "Yes", please explain in detail</label>'.($viewonly ? '' : '<br />').'<textarea '.($viewonly ? 'disabled="disabled"' : '').' rows="3" id="q2_3" name="q2_3" '.(empty($v["yestotal"]) ? '' : 'data-rule-required="true"').'>'.$v["q2_3"].'</textarea><div class="tooltipContainer info">'.get_help("input_staff_q1_3:events").'</div><br />
    	  			</div>
                    <br /><hr><br />
                    <h3>Worker Renewal Work Verification and Release</h3><br />
                    <em>I recognize that Wabash Valley Christian Youth Camp is relying on the accuracy of the information I provide on the Worker Renewal Application form.   Accordingly, I attest and affirm that the information I have provided is absolutely true and correct.
                    <br />
                    I voluntarily release the organization and any such person or entity listed on the Worker Renewal Application form from liability involving the communication of information relating to my background or qualifications.   I further authorize the organization to conduct a criminal background investigation if such a check is deemed necessary.
                    <br />
                    I agree to abide by all policies and procedures of the organization and to protect the health and safety of the children or youth assigned to my care or supervision at all times.
                    </em>
    		  		<br /><br />
                    <div class="rowContainer">
    					<label class="fieldtitle" for="workerconsent">Full Name</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="workerconsent" name="workerconsent" value="'.$v["workerconsent"].'" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_workerconsent:events").'</div><br />
    				</div>
                    <div class="rowContainer">
        				<label class="fieldtitle" for="workerconsentdate">Date</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="workerconsentdate" name="workerconsentdate" value="'.$v["workerconsentdate"].'" data-rule-required="true" data-rule-date="true" disabled="disabled" /><div class="tooltipContainer info">'.get_help("input_staff_workerconsentdate:events").'</div>
        			</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="workerconsentsig">Signature</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="checkbox" id="workerconsentsig" name="workerconsentsig" '.$v["workerconsentsig"].' data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_workerconsentsig:events").'</div><br />
    				</div>
                    <div id="sub18" style="'.$v["sub18dispaly"].'">
                        <br /><hr><br />
                            <div style="background:#FFED00;padding:5px;">
                            <strong>If you are under 18, please have a parent or guardian affirm to the following:</strong><br />
                            <em>I swear and affirm that I am not aware of any traits or tendencies of the applicant that could pose a threat to children, youth or others and that I am not aware of any reasons why the applicant should not work with children, youth, or others.</em>
            		  		<br /><br />
                            <div class="rowContainer">
            					<label class="fieldtitle" for="parentalconsent">Parent or Gurdian Full Name</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="parentalconsent" name="parentalconsent" value="'.$v["parentalconsent"].'" '.(empty($v["ar1selected"]) ? '' : 'data-rule-required="true"').' /><div class="tooltipContainer info">'.get_help("input_staff_parentalconsent:events").'</div><br />
            				</div>
                            <div class="rowContainer">
            					<label class="fieldtitle" for="parentalconsentsig">Parent or Guardian Signature</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="checkbox" id="parentalconsentsig" name="parentalconsentsig" '.$v["parentalconsentsig"].' '.(empty($v["ar1selected"]) ? '' : 'data-rule-required="true"').' /><div class="tooltipContainer info">'.get_help("input_staff_parentalconsentsig:events").'</div><br />
            				</div>
                        </div>
                    </div>
                    '.($viewonly ? '<div style="text-align:center"><h2>' . $v["name"] . ' References</h2></div>' : '<br /><hr><br />').'
                    <h3>References #1</h3><br />
    		  		<br />
                    <div class="rowContainer">
    					<label class="fieldtitle" for="ref1name">Name</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref1name" name="ref1name" value="'.$v["ref1name"].'" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_refname:events").'</div><br />
    				</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="ref1relationship">Relationship</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref1relationship" name="ref1relationship" value="'.$v["ref1relationship"].'" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_refrelationship:events").'</div><br />
    				</div>
                    <div class="rowContainer">
				        <label class="fieldtitle" for="ref1phone">Phone</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref1phone" name="ref1phone" value="'.$v["ref1phone"].'" data-rule-required="true"  data-rule-phone="true" data-msg-required="'.get_error_message('valid_staff_phone:events').'" data-msg-phone="'.get_error_message('valid_staff_phone_invalid:events').'" /><div class="tooltipContainer info">'.get_help("input_staff_phone:events").'</div><br />
                    </div>
                    <br /><hr><br />
                    <h3>References #2</h3><br />
                    <div class="rowContainer">
    					<label class="fieldtitle" for="ref2name">Name</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref2name" name="ref2name" value="'.$v["ref2name"].'" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_refname:events").'</div><br />
    				</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="ref2relationship">Relationship</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref2relationship" name="ref2relationship" value="'.$v["ref2relationship"].'" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_refrelationship:events").'</div><br />
    				</div>
                    <div class="rowContainer">
				        <label class="fieldtitle" for="ref2phone">Phone</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref2phone" name="ref2phone" value="'.$v["ref2phone"].'" data-rule-required="true"  data-rule-phone="true" data-msg-required="'.get_error_message('valid_staff_phone:events').'" data-msg-phone="'.get_error_message('valid_staff_phone_invalid:events').'" /><div class="tooltipContainer info">'.get_help("input_staff_phone:events").'</div><br />
                    </div>
                    <br /><hr><br />
                    <h3>References #3</h3><br />
                    <div class="rowContainer">
    					<label class="fieldtitle" for="ref3name">Name</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref3name" name="ref3name" value="'.$v["ref3name"].'" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_refname:events").'</div><br />
    				</div>
                    <div class="rowContainer">
    					<label class="fieldtitle" for="ref3relationship">Relationship</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref3relationship" name="ref3relationship" value="'.$v["ref3relationship"].'" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("input_staff_refrelationship:events").'</div><br />
    				</div>
                    <div class="rowContainer">
				        <label class="fieldtitle" for="ref3phone">Phone</label>'.($viewonly ? '' : '<br />').'<input '.($viewonly ? 'disabled="disabled"' : '').' type="text" id="ref3phone" name="ref3phone" value="'.$v["ref3phone"].'" data-rule-required="true"  data-rule-phone="true" data-msg-required="'.get_error_message('valid_staff_phone:events').'" data-msg-phone="'.get_error_message('valid_staff_phone_invalid:events').'" /><div class="tooltipContainer info">'.get_help("input_staff_phone:events").'</div><br />
                    </div>
                    '.($viewonly ? '' : '<input class="submit" name="submit" type="submit" onmouseover="this.focus();" value="Submit Application" />').'	
    			</fieldset>
    		</form>
    	</div>';
}

function new_location_form($eventid){
    echo '
	<table>
		<tr>
			<td class="field_title">
				Name:
			</td>
			<td class="field_input">
				<input type="text" id="location_name" />
				<span class="hint">' . get_help("input_location_name:events") . '<span class="hint-pointer">&nbsp;</span></span>
			</td>
		</tr><tr><td></td><td class="field_input"><span id="location_name_error" class="error_text"></span></td></tr>
		<tr>
			<td class="field_title">
				Address:
			</td>
			<td class="field_input">
				<input type="text" id="location_address_1" />
				<span class="hint">' . get_help("input_location_add1:events") . '<span class="hint-pointer">&nbsp;</span></span>
			</td>
		</tr><tr><td></td><td class="field_input"><span id="location_address_1_error" class="error_text"></span></td></tr>
		<tr>
			<td></td>
			<td class="field_input">
				<input type="text" id="location_address_2" />
				<span class="hint">' . get_help("input_location_add2:events") . '<span class="hint-pointer">&nbsp;</span></span>
			</td>
		</tr><tr><td></td><td class="field_input"><span id="location_address_2_error" class="error_text"></span></td></tr>
		<tr>
			<td class="field_title">
				Zipcode:
			</td>
			<td class="field_input">
				<input type="text" id="zip" size="7" maxlength="5" />
				<span class="hint">' . get_help("input_location_zip:events") . '<span class="hint-pointer">&nbsp;</span></span>
			</td>
		</tr><tr><td></td><td class="field_input"><span id="zip_error" class="error_text"></span></td></tr>
		<tr>
			<td class="field_title">
				Share Location:
			</td>
			<td class="field_input">
				<input type="checkbox" id="share" />
				<span class="hint">' . get_help("input_location_share:events") . '<span class="hint-pointer">&nbsp;</span></span>
			</td>
		</tr>
		<tr>
			<td class="field_title">
				<span style="font-size:1.2em; color:blue;">(optional)</span> Phone:
			</td>
			<td class="field_input">
				<input type="hidden" id="opt_location_phone" value="1" /><input type="text" id="location_phone_1" maxlength="3" size="1" onkeyup="movetonextbox(event);" />-<input type="text" id="location_phone_2" size="1" maxlength="3" onkeyup="movetonextbox(event);" />-<input type="text" id="location_phone_3" size="2" maxlength="4" />
			</td>
		</tr><tr><td></td><td class="field_input"><span id="location_phone_error" class="error_text"></span></td></tr>
		<tr>
			<td class="field_title"></td>
			<td class="field_input">
				<input type="button" value="Submit" onclick="add_new_location(\''.$eventid.'\');" />
			</td>
		</tr>
	</table>
';
}

function location_list_form($eventid){
global $USER, $CFG;
    $locations = get_db_result("SELECT * FROM events_locations WHERE shared=1 and userid NOT LIKE '%," . $USER->userid . ",%' ORDER BY location");
    $listyes = true;
    $returnme = '';
    if($locations){
        while($location = fetch_row($locations)){
            $listyes = false;
            $returnme .= $returnme == "" ? '<table><tr><td style="vertical-align:top; width: 250px;"><select width="200" style="width: 200px" id="add_location" onchange="get_location_details(this.value);"><option value="false"></option>' : "";
            $selectme = $selected && ($location['id'] == $selected) ? ' selected' : '';
            $returnme .= '<option value="' . $location['id'] . '"' . $selectme . '>' . $location['location'] . '</option>';
        }
        $returnme .= '</select><a href="javascript:void(0);" onclick="copy_location(document.getElementById(\'add_location\').value,\''.$eventid.'\');"> <img src="' . $CFG->wwwroot . '/images/add.png" title="Add Location" alt="Add Location" /></a></td><td style="vertical-align:top"><span id="location_details_div" style="vertical-align:top"></span></td></tr></table>';
    }
    
    if(!$listyes){ return $returnme; 
    }else{ return "No other addable locations."; }
}

function events_delete($pageid, $featureid, $sectionid){
    execute_db_sql("DELETE FROM pages_features WHERE feature='events' AND pageid='$pageid' AND featureid='$featureid'");
    execute_db_sql("DELETE FROM settings WHERE type='events' AND pageid='$pageid' AND featureid='$featureid'");
    resort_page_features($pageid);
}

function create_form_element($type, $id, $optional, $length, $list = false){
    switch ($type) {
        case "text":
            $maxlength = $length > 0 ? ' maxlength="' . $length . '"': "";
            $returnme = '<input type="hidden" id="opt_' . $id . '" value="' . $optional . '" /><input size="25" type="text" id="' . $id . '"' . $maxlength . ' />';
            break;
        case "email":
            $maxlength = $length > 0 ? ' maxlength="' . $length . '"': "";
            $returnme = '<input type="hidden" id="opt_' . $id . '" value="' . $optional . '" /><input size="25" type="text" id="' . $id . '"' . $maxlength . ' />';
            break;
        case "contact":
            $maxlength = $length > 0 ? ' maxlength="' . $length . '"': "";
            $returnme = '<input type="hidden" id="opt_' . $id . '" value="0" /><input size="25" type="text" id="' . $id . '"' . $maxlength . ' />';
            break;
        case "phone":
            $returnme = '<input type="hidden" id="opt_' . $id . '" value="' . $optional . '" /><input type="text" id="' . $id . '_1" maxlength="3" size="1" onkeyup="movetonextbox(event);" />-<input type="text" id="' . $id . '_2" size="1" maxlength="3" onkeyup="movetonextbox(event);" />-<input type="text" id="' . $id . '_3" size="2" maxlength="4" />';
            break;
        case "select":
            echo "i equals 2";
            break;
    }
    return $returnme;
}

function check_for_new_templates(){
global $CFG, $USER;
    $startdir = $CFG->dirroot . "/features/events/templates/";
    $ignoredDirectory[] = '.';
    $ignoredDirectory[] = '..';
    if(is_dir($startdir)){
        if($dh = opendir($startdir)){
            while(($folder = readdir($dh)) !== false){
                if(!(array_search($folder, $ignoredDirectory) > -1)){
                    if(filetype($startdir . $folder) == "dir"){
                        $directorylist[$startdir . $folder]['name'] = $folder;
                        $directorylist[$startdir . $folder]['path'] = $startdir;
                    }
                }
            }
            closedir($dh);
        }
    }
    if(isset($directorylist)){
    	foreach ($directorylist as $folder) {
	        $name = $folder['name'];
	        include ($CFG->dirroot."/features/events/templates/$name/install.php");
	    }
    }
}

function get_events_admin_contacts(){
    $contacts = get_db_result("SELECT DISTINCT CONCAT(contact,': ',email,': ',phone) as admin_contact FROM events WHERE confirmed=1 ORDER BY contact,eventid DESC");
    
    $script = '
    <script type="text/javascript">
        function fill_admin_contacts(values){
            values = values.split(": ");
            document.getElementById("contact").value = values[0];
            document.getElementById("email").value = values[1];
            var phone = values[2].split("-");
            document.getElementById("phone_1").value = phone[0];
            document.getElementById("phone_2").value = phone[1];
            document.getElementById("phone_3").value = phone[2]; 
        }
    </script>';
    
    return $script.'<br /><table style="width:100%">
    	<tr>
    		<td class="field_title" style="width:115px;">
    			Contacts List:
    		</td>
    		<td class="field_input">
    			'.make_select("admin_contacts",$contacts,"admin_contact","admin_contact",false,"onchange='fill_admin_contacts(this.value)'",true).'
    		</td>
    	</tr><tr><td></td><td class="field_input"><span id="contact_error" class="error_text"></span></td></tr>
    </table>';    
}

function get_events_admin_payable(){
    $contacts = get_db_result("SELECT DISTINCT CONCAT(payableto,': ',checksaddress,': ',paypal) as admin_contact FROM events WHERE payableto!='' AND confirmed=1");
    
    $script = '
    <script type="text/javascript">
        function fill_admin_payable(values){
            values = values.split(": ");
            document.getElementById("payableto").value = values[0];
            document.getElementById("checksaddress").value = values[1];
            document.getElementById("paypal").value = values[2];
        }
    </script>';
    
    return $script.'<br /><table style="width:100%">
    	<tr>
    		<td class="field_title" style="width:115px;">
    			Payable List:
    		</td>
    		<td class="field_input">
    			'.make_select("admin_contacts",$contacts,"admin_contact","admin_contact",false,"onchange='fill_admin_payable(this.value)'",true).'
    		</td>
    	</tr><tr><td></td><td class="field_input"><span id="contact_error" class="error_text"></span></td></tr>
    </table>';    
} 

function events_buttons($pageid, $featuretype, $featureid){
global $CFG, $USER;
    $returnme = "";
    if(user_has_ability_in_page($USER->userid, "addevents", $pageid, $featuretype, $featureid)){ $returnme .= make_modal_links(array("title"=> "Add Event","path"=>$CFG->wwwroot."/features/events/events.php?action=add_event_form&amp;pageid=$pageid","refresh"=>"true","iframe"=>"true","width"=>"800","height"=>"95%","image"=>$CFG->wwwroot."/images/add.png","class"=>"slide_menu_button")); }
    return $returnme;
}

function events_default_settings($feature,$pageid,$featureid){
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","Events",false,"Events","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","upcomingdays","30",false,"30","Show Upcoming Events (days)","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","recentdays","5",false,"5","Recent Events (days)","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","archivedays","30",false,"30","Admin Recent Events (days)","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","showpastevents","1",false,"1","Show Past Events","yes/no");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","allowrequests","0","SELECT id as selectvalue,location as selectname FROM events_locations WHERE shared=1","0","Allow Location Reservations","select",null,null,"Select a location to allow event requests on.");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","emaillistconfirm","","3","","Request Email List","textarea");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","requestapprovalvotes","1",false,"1","Approval Votes Required","text",true,"<=0","Must be greater than 0.  Should be equal or less than the amount of email addresses.");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","requestdenyvotes","1",false,"1","Denial Votes Required","text",true,"<=0","Must be greater than 0.  Should be equal or less than the amount of email addresses.");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","request_text","","3","","Request Form Text","textarea");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","bgcheck_url","","3","","Background Check URL","textarea");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","bgcheck_years","5",false,"5","Background Check Expires (years)","text",true,"<=0","Must be greater than 0.");
    $settings_array[] = array(false,"$feature","$pageid","$featureid","staffapp_expires","1/1",false,"1/1","Staff Application Expires (day/month)","text");

    return $settings_array;
}

function facebook_share_button($eventid,$name,$keys=false){
global $CFG;
    if(!empty($keys)){
        require_once ($CFG->dirroot . '/features/events/facebook/facebook.php'); //'<path to facebook library, you uploaded>/facebook.php';
        $config = array(
            'appId' => $keys->app_key,
            'secret' => $keys->app_secret,
        );
        $event = get_db_row("SELECT * FROM events WHERE eventid = '$eventid'");
        $facebook = new Facebook($config);
        $login_url = $facebook->getLoginUrl( array( 'scope' => 'publish_stream',
                           'redirect_uri' => $CFG->wwwroot . '/features/events/events_ajax.php?action=send_facebook_message&info='.base64_encode(serialize(array($eventid,$name,$keys))) ) );
        return '<a title="Tell your friends about '.$name.'\'s registration for '.$event["name"].'!" href="' . $login_url . '" target="_blank"><img src="'.$CFG->wwwroot.'/images/facebook_button.png" /></a>';     
    }
    
}

function events_adminpanel($pageid) {
global $CFG, $USER;
    $content = "";
    //Course Event Manager
    $content .= user_has_ability_in_page($USER->userid,"manageevents",$pageid) ? make_modal_links(array("title"  => "Event Registrations",
                                                                                                     "text"   => "Event Registrations",
                                                                                                     "path"   => $CFG->wwwroot . "/features/events/events.php?action=event_manager&amp;pageid=$pageid",
                                                                                                     "iframe" => "true",
                                                                                                     "width"  => "640",
                                                                                                     "height" => "600",
                                                                                                     "iframe" => "true",
                                                                                                     "image"  => $CFG->wwwroot . "/images/manage.png",
                                                                                                     "styles" => "padding:1px;display:block;"))
                                                                            : "";
    //Application Manager
    $content .= user_has_ability_in_page($USER->userid,"manageapplications",$pageid) ? make_modal_links(array("title"  => "Staff Applications",
                                                                                                     "text"   => "Staff Applications",
                                                                                                     "path"   => $CFG->wwwroot . "/features/events/events.php?action=application_manager&amp;pageid=$pageid",
                                                                                                     "iframe" => "true",
                                                                                                     "width"  => "640",
                                                                                                     "height" => "600",
                                                                                                     "iframe" => "true",
                                                                                                     "image"  => $CFG->wwwroot . "/images/manage.png",
                                                                                                     "styles" => "padding:1px;display:block;"))
                                                                            : "";
    //Event Template Manager
    $content .= user_has_ability_in_page($USER->userid,"manageeventtemplates",$pageid) ? make_modal_links(array("title"  => "Event Templates",
                                                                                                     "text"   => "Event Templates",
                                                                                                     "path"   => $CFG->wwwroot . "/features/events/events.php?action=template_manager&amp;pageid=$pageid",
                                                                                                     "iframe" => "true",
                                                                                                     "width"  => "640",
                                                                                                     "height" => "600",
                                                                                                     "iframe" => "true",
                                                                                                     "image"  => $CFG->wwwroot . "/images/template.png",
                                                                                                     "styles" => "padding:1px;display:block;"))
                                                                            : "";
    return $content;
}
?>