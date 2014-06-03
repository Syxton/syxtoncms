<?php
/***************************************************************************
* calendar_ajax.php - Calendar backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/09/2013
* Revision: 1.0.7
***************************************************************************/

if(!isset($CFG)){ include('../header.php'); } 
if(!isset($CALENDARLIB)){ include_once($CFG->dirroot . '/features/calendar/calendarlib.php'); }
update_user_cookie();

callfunction();

function print_calendar(){
global $CFG, $MYVARS;
	$pageid = isset($MYVARS->GET["pageid"]) ? $MYVARS->GET["pageid"] : "";
	$userid = isset($MYVARS->GET["userid"]) ? $MYVARS->GET["userid"] : "";
	$month = isset($MYVARS->GET["month"]) ? $MYVARS->GET["month"] : "";
	$year = isset($MYVARS->GET["year"]) ? $MYVARS->GET["year"] : "";
	$extra_row = isset($MYVARS->GET["extra_row"]) ? $MYVARS->GET["extra_row"] : "";
	
	if($MYVARS->GET["displaymode"] == 1){
		echo get_large_calendar($pageid, $userid, $month, $year, $extra_row);
	}else{
		echo get_small_calendar($pageid, $userid, $month, $year, $extra_row);
	}
}

function get_date_info(){
global $CFG,$MYVARS;
	$pageid = $MYVARS->GET["pageid"];
	$show_site_events = $MYVARS->GET["show_site_events"];
	$tm = $MYVARS->GET["tm"];
	$tn = $MYVARS->GET["tn"];
	$tp = $MYVARS->GET["tp"];
	$list_day = $MYVARS->GET["list_day"];
 	$whichevents = $show_site_events ? 'AND ((pageid=' . $pageid . ') OR (pageid='.$CFG->SITEID . ') OR (site_viewable=1))' : 'AND pageid=' . $pageid;
	$SQL = sprintf("SELECT * FROM `calendar_events` WHERE `date` > '%s' AND `date` < '%s' AND `day` = '%s' $whichevents ORDER BY day;",$tm, $tp, $list_day);
 	if($result = get_db_result($SQL)){
        $eventlist = '';
        while ($event = fetch_row($result)){
        	if($eventlist != ""){ 
                $eventlist .= '<br />'; $firstevent = ''; 
            }else{ 
                $firstevent = '<span style="width:35px;text-align:center;float:right;font-size:.7em;color:gray;">hide<br /><span id="cal_countdown"></span></span>';
            }
            $eventlist .= '<div class="popupEventTitle">'.make_modal_links(array("title"=>"Event Info","text"=> stripslashes($event["title"]), "path"=>$CFG->wwwroot."/features/events/events.php?action=info&amp;pageid=$pageid&amp;eventid=".$event["eventid"],"iframe"=>"true","width"=>"700","height"=>"650","styles"=>"float:left;padding:2px;","image"=>$CFG->wwwroot.'/images/info.gif','styles'=>'vertical-align:top;')).$firstevent.'</div>';
            
			if($event['picture_1'] != ""){
                $eventlist .= '<img style="margin:3px;height:50px;margin-bottom:0px;" src="' . $CFG->wwwroot . '/scripts/calendar/event_images/' . $event['picture_1'] . '" />';
            }
            
            $eventlist .= '<div class="popupEventDescription">';
            if($event["starttime"] != "" && $event["starttime"] != "NULL"){
                $eventlist .= 'Time: ' . convert_time($event["starttime"]) . ' - ' .
                convert_time($event["endtime"]) . "<br />";
            }
            $location = get_db_field("location","events_locations","id=".$event["location"]);
            $eventlist .= '<strong>Location:</strong> ' . stripslashes($location);
            $dots = strlen(stripslashes($event["event"]))>200 ? '...' : '';
            $eventlist .= $event["event"] !== '' ? '<br /><strong>Description:</strong> ' . substr(stripslashes(strip_tags($event["event"])),0,200) . $dots : '';
            $eventlist .= '</div>';
        }
    }
    echo $eventlist;
}
?>