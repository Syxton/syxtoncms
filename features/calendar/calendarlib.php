<?php
/***************************************************************************
* calendarlib.php - Calendar function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.2.8
***************************************************************************/

if (!LIBHEADER) {
    $sub = './';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == './' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('CALENDARLIB', true);

function display_calendar($pageid, $area, $featureid) {
global $CFG, $USER, $ROLES;
    $content = '';

    if (!$settings = fetch_settings("calendar", $featureid, $pageid)) {
        save_batch_settings(default_settings("calendar", $pageid, $featureid));
        $settings = fetch_settings("calendar", $featureid, $pageid);
    }

    $title = $settings->calendar->$featureid->feature_title->setting;
    $title = '<span class="box_title_text">' . $title . '</span>';
    if (user_is_able($USER->userid, "viewcalendar", $pageid, "calendar", $featureid)) {
        ajaxapi([
            "id" => "changemonth",
            "url" => "/features/calendar/calendar_ajax.php",
            "data" => [
                "action" => "print_calendar",
                "userid" => "js||userid||js",
                "pageid" => "js||pageid||js",
                "month" => "js||month||js",
                "year" => "js||year||js",
            ],
            "paramlist" => "month, year, pageid, userid",
            "display" => "calendar_div",
            "event" => "none",
        ]);

        $display = get_calendar(["pageid" => $pageid, "userid" => $USER->userid, "area" => $area]);
        $content .= '
            <span id="calendarmarker"></span>
            <div id="calendar_div" style="width:100%;z-index:2;">
                ' . $display . '
            </div>
            <div id="day_info" style="display:none"></div>';

        $buttons = is_logged_in() ? get_button_layout("calendar", $featureid, $pageid) : "";
        return get_css_box($title, $content, $buttons, '0px', "calendar", $featureid);
    }
}

function clean_for_overlib($str) {
    return str_replace(chr(13), '<br />', str_replace(chr(10), '<br />', $str));
}

function get_calendar($params) {
global $CFG;
    $pageid = clean_param_opt($params, "pageid", "int", get_pageid());
    $userid = clean_param_opt($params, "userid", "int", "");
    $month = clean_param_opt($params, "month", "int", "");
    $year = clean_param_opt($params, "year", "int", "");
    $extra_row = clean_param_opt($params, "extra_row", "int", "");
    $area = clean_param_opt($params, "area", "string", "side");

    $no_site_events = get_db_field("setting", "settings", "type='calendar' AND pageid = ||pageid|| AND setting_name='dont_show_site_events' AND setting='1'", ["pageid" => $pageid]);
    $show_site_events = ($pageid == $CFG->SITEID) || $no_site_events ? true : false;
    date_default_timezone_set($CFG->timezone);
    if (!$month) {
        $month = date('m');
    }
    if (!$year) {
        $year = date('Y');
    }
    $theday = date('w', mktime(0, 0, 0, $month, 1, $year));
    $daysinmonth = date("t", mktime(0, 0, 0, $month, 1, $year));
    if ($month == '01') {
        $prevmonth = '12';
        $prevyear = $year - 1;
    } else {
        $prevmonth = $month - 1;
        $prevyear = $year;
    }

    $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
    $yearName = date('Y', mktime(0, 0, 0, $month, 1, $year));
    if ($month == '12') {
        $nextmonth = '01';
        $nextyear = $year + 1;
    } else {
        $nextmonth = $month + 1;
        $nextyear = $year;
    }

    $returnme = '
        <table class="calendar_table">
            <tr>
                <td class="calendar_month" colspan="7">
                    <div class="calendar_month_flex">
                        <button class="alike calendar_change_month" style="float:left;" onclick="changemonth(' . $prevmonth . ', ' . $prevyear . ', ' . $pageid . ', ' . $userid . ');">
                            ' . icon("angles-left") . '
                        </button>
                        ' . $monthName . ' ' . $yearName . '
                        <button class="alike calendar_change_month" style="float:right;" onclick="changemonth(' . $nextmonth . ', ' . $nextyear . ', ' . $pageid . ', ' . $userid . ');">
                            ' . icon("angles-right") . '
                        </button>
                    </div>
                </td>
            </tr>
            <tr class="calendar_day_names">
                <th>S</th>
                <th>M</th>
                <th>T</th>
                <th>W</th>
                <th>T</th>
                <th>F</th>
                <th>S</th>
            </tr>
            <tr class="calendar_week">';

    for ($i = 0; $i < $theday; $i++) {
        $returnme .= '<td></td>';
    }

    $HowManyRows = 0;
    $whichevents = $show_site_events ? 'AND ((pageid=' . $pageid . ') OR (pageid=' . $CFG->SITEID . ') OR (site_viewable=1))' : 'AND pageid=' . $pageid;
    for ($list_day = 1; $list_day <= $daysinmonth; $list_day++) {
        $eventlabel = $id = $style = $class = "";
        if ($theday == 0 && $list_day != 1) { $returnme .= '<tr class="calendar_week">'; }
        $tm = date("U", mktime(0, 0, 0, $month, $list_day, $year)) - 86400;
        $tn = date("U", mktime(0, 0, 0, $month, $list_day, $year));
        $tp = date("U", mktime(0, 0, 0, $month, $list_day, $year)) + 86400;
        $SQL = sprintf("SELECT * FROM `calendar_events` WHERE `date` > '%s' AND `date` < '%s' AND `day` = '%s' $whichevents ORDER BY day;", $tm, $tp, $list_day);

        $count = get_db_count($SQL);
        if ($count) { //Event exists
            if ($result = get_db_result($SQL)) {
                $eventlist = $today = "";
                while ($event = fetch_row($result)) {
                    if ($event["cat"] != 0) {
                        $category = get_db_row("SELECT * FROM calendar_cat WHERE cat_id=" . $event["cat"]);
                        $background_color = $category["cat_color"] != "" ? 'color:' . $category["cat_color"] . ';' : 'color: #333333;';
                        $font_color = $category["cat_bgcolor"] != '' ? 'background-color:' . $category["cat_bgcolor"] . ';' : 'background-color: #CCFF00;';
                        $category_colors = $background_color . $font_color;
                    } else {
                        $category_colors = 'background-color: #CCFF00; color: #333333;';
                    }
                }

                ajaxapi([
                    'id' => $tm . $tn,
                    'url' => '/features/calendar/calendar_ajax.php',
                    'data' => [
                        'action' => 'get_date_info',
                        'show_site_events' => $show_site_events,
                        'pageid' => $pageid,
                        'tm' => $tm,
                        'tn' => $tn,
                        'tp' => $tp,
                        'list_day' => $list_day,
                    ],
                    'display' => 'day_info',
                    'ondone' => '
                        show_section("day_info");
                        countdown("cal_countdown", 10, "hide_section(\'day_info\');");',
                ]);
                $id = $tm . $tn;
                $style = $category_colors . ' cursor: pointer;';
                db_free_result($result);

                if ($count) {
                    $label = empty($eventlist) ? 'Calendar Event' : $eventlist;
                    $eventlabel = '<span class="calendar_event_label">' . $label . '</span>';
                }
            }
        } elseif ($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year) {
            $class = 'calendar_today';
        }

        $todaylabel = '';
        if ($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year) {
            $todaylabel = '<span class="calendar_today_label">Today</span>';
        }

        $returnme .= '
            <td id="' . $id . '" class="' . $class . '" style="' . $style . '">
                <span class="calendar_day_label">
                    ' . $list_day . '
                </span>
                ' . $todaylabel . '
                ' . $eventlabel . '
            </td>';

        if ($theday == 6) {
            $returnme .= '</tr>';
            $theday = -1;
            $HowManyRows = $HowManyRows + 1;
        }
        $theday++;
    }

    if (($HowManyRows <= 4) and ($extra_row == 1)) {
        $returnme .= '</tr>';
        $returnme .= '<tr class="calendar_week">';
        $returnme .= '<td></td>';
    }

    if ($theday != 0) {
        $returnme .= str_repeat("<td></td>", 7 - $theday);
        $returnme .= '</tr>';
    }

    $returnme .= '</table>';
    return $returnme;
}

function calendar_delete($pageid, $featureid) {
    $params = [
        "pageid" => $pageid,
        "featureid" => $featureid,
        "feature" => "calendar",
    ];

    try {
        start_db_transaction();
        execute_db_sql(fetch_template("dbsql/features.sql", "delete_feature"), $params);
        execute_db_sql(fetch_template("dbsql/features.sql", "delete_feature_settings"), $params);
        resort_page_features($pageid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        return false;
    }
}

function calendar_buttons($pageid, $featuretype, $featureid) {
    global $CFG, $USER;
    $returnme = "";
    return $returnme;
}

function calendar_default_settings($type, $pageid, $featureid) {
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "Calendar",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "setting_name" => "dont_show_site_events",
            "defaultsetting" => "0",
            "display" => "Show Global Events",
            "inputtype" => "yes/no",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}
?>