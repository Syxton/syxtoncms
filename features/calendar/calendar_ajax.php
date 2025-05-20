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
    $pageid = clean_myvar_opt("pageid", "int", "");
    $userid = clean_myvar_opt("userid", "int", "");
    $month = clean_myvar_opt("month", "int", "");
    $year = clean_myvar_opt("year", "int", "");
    $extra_row = clean_myvar_opt("extra_row", "int", "");

    $featureid = get_feature_id("calendar", $pageid);
    $area = get_feature_area("calendar", $featureid);

	$display = get_calendar(["pageid" => $pageid, "userid" => $userid, "month" => $month, "year" => $year, "extra_row" => $extra_row, "area" => $area]);
	ajax_return($display);
}

function get_date_info() {
global $CFG, $MYVARS;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $show_site_events = clean_myvar_opt("show_site_events", "bool", false);
    $tm = clean_myvar_req("tm", "int");
    $tn = clean_myvar_req("tn", "int");
    $tp = clean_myvar_req("tp", "int");
    $list_day = clean_myvar_req("list_day", "int");

 	$SQL = fetch_template("dbsql/calendar.sql", "get_calendar_event_data", "calendar", [
        "show_site_events" => $show_site_events,
    ]);

    $params = [
        "pageid" => $pageid,
        "siteid" => $CFG->SITEID,
        "from" => $tm,
        "to" => $tp,
        "day" => $list_day,
    ];

    $eventlist = '';
    if ($result = get_db_result($SQL, $params)) {
        while ($event = fetch_row($result)) {
      		if ($eventlist != "") {
                $eventlist .= '<br />';
                $firstevent = '';
            } else {
                $firstevent = '<span style="text-align:center;float:right;font-size:.9em;color:gray;">hide <span id="cal_countdown"></span></span>';
            }

            $eventlist .= '
                <div class="popupEventTitle">' .
                    make_modal_links([
                    "title" => "Event Info",
                    "text" => $event["title"],
                    "path" => action_path("events") . "info&pageid=$pageid&eventid=" . $event["eventid"],
                    "iframe" => true,
                    "width" => "700",
                    "height" => "650",
                    "styles" => "float:left;padding:2px;",
                    "icon" => icon("circle-info"),
                    'styles' => 'vertical-align:top;',
                ]) . $firstevent .
                '</div>';

			if ($event['picture_1'] != "") {
                $eventlist .= '<img style="margin:3px;height:50px;margin-bottom:0px;" src="' . $CFG->wwwroot . '/scripts/calendar/event_images/' . $event['picture_1'] . '" />';
            }

            $eventlist .= '<div class="popupEventDescription">';
            if ($event["starttime"] != "" && $event["starttime"] != "NULL") {
                $eventlist .= 'Time: ' . twelvehourtime($event["starttime"]) . ' - ' .
                twelvehourtime($event["endtime"]) . "<br />";
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