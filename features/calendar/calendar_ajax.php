<?php
/***************************************************************************
* calendar_ajax.php - Calendar backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.0.7
***************************************************************************/

if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

if (!defined('CALENDARLIB')) { include_once($CFG->dirroot . '/features/calendar/calendarlib.php'); }

update_user_cookie();

callfunction();

function print_calendar() {
global $CFG, $MYVARS;
	$pageid = isset($MYVARS->GET["pageid"]) ? $MYVARS->GET["pageid"] : "";
	$userid = isset($MYVARS->GET["userid"]) ? $MYVARS->GET["userid"] : "";
	$month = isset($MYVARS->GET["month"]) ? $MYVARS->GET["month"] : "";
	$year = isset($MYVARS->GET["year"]) ? $MYVARS->GET["year"] : "";
	$extra_row = isset($MYVARS->GET["extra_row"]) ? $MYVARS->GET["extra_row"] : "";

	if ($MYVARS->GET["displaymode"] == 1) {
		echo get_large_calendar($pageid, $userid, $month, $year, $extra_row);
	} else {
		echo get_small_calendar($pageid, $userid, $month, $year, $extra_row);
	}
}

function get_date_info() {
global $CFG, $MYVARS;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $show_site_events = clean_myvar_opt("show_site_events", "bool", false);
    $tm = clean_myvar_req("tm", "int");
    $tn = clean_myvar_req("tn", "int");
    $tp = clean_myvar_req("tp", "int");
    $list_day = clean_myvar_req("list_day", "int");

 	$whichevents = $show_site_events ? 'AND ((pageid=' . $pageid . ') OR (pageid=' . $CFG->SITEID . ') OR (site_viewable=1))' : 'AND pageid=' . $pageid;
	$SQL = sprintf("SELECT * FROM `calendar_events` WHERE `date` > '%s' AND `date` < '%s' AND `day` = '%s' $whichevents ORDER BY day;", $tm, $tp, $list_day);
 	if ($result = get_db_result($SQL)) {
        $eventlist = '';
        while ($event = fetch_row($result)) {
      		if ($eventlist != "") {
                $eventlist .= '<br />'; $firstevent = '';
            } else {
                $firstevent = '<span style="text-align:center;float:right;font-size:.9em;color:gray;">hide <span id="cal_countdown"></span></span>';
            }
            $p = [
                "title" => "Event Info",
                "text" => $event["title"],
                "path" => action_path("events") . "info&pageid=$pageid&eventid=" . $event["eventid"],
                "iframe" => true,
                "width" => "700",
                "height" => "650",
                "styles" => "float:left;padding:2px;",
                "image" => $CFG->wwwroot . '/images/info.gif',
                'styles' => 'vertical-align:top;',
            ];
            $eventlist .= '<div class="popupEventTitle">' .
                                make_modal_links($p) . $firstevent .
                          '</div>';

			if ($event['picture_1'] != "") {
                $eventlist .= '<img style="margin:3px;height:50px;margin-bottom:0px;" src="' . $CFG->wwwroot . '/scripts/calendar/event_images/' . $event['picture_1'] . '" />';
            }

            $eventlist .= '<div class="popupEventDescription">';
            if ($event["starttime"] != "" && $event["starttime"] != "NULL") {
                $eventlist .= 'Time: ' . convert_time($event["starttime"]) . ' - ' .
                convert_time($event["endtime"]) . "<br />";
            }
            $location = get_db_field("location", "events_locations", "id='" . $event["location"] . "'");
            $eventlist .= '<strong>Location:</strong> ' . $location;
            $eventlist .= $event["event"] !== '' ? '<br /><strong>Description:</strong> ' . truncate(strip_tags($event["event"]), 200) : '';
            $eventlist .= '</div>';
        }
    }
    ajax_return($eventlist);
}
?>