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
        if ($area == "middle") {
            $content .= '<span id="calendarmarker"></span><div id="calendar_div" style="width:100%;z-index:2;">' . get_large_calendar($pageid, $USER->userid) . '</div><div id="day_info" style="display:none"></div>';
        } else {
            $content .= '<span id="calendarmarker"></span><div id="calendar_div" style="width:100%;z-index:2;">' . get_small_calendar($pageid, $USER->userid) . '</div><div id="day_info" style="display:none"></div>';
        }

        $buttons = is_logged_in() ? get_button_layout("calendar", $featureid, $pageid) : "";
        return get_css_box($title, $content, $buttons, '0px', "calendar", $featureid);
    }
}

function clean_for_overlib($str) {
    return str_replace(chr(13), '<br />', str_replace(chr(10), '<br />', $str));
}

function get_small_calendar($pageid, $userid = 0, $month = false, $year = false, $extra_row = false) {
    global $CFG;
    $show_site_events = ($pageid == $CFG->SITEID) || get_db_field("setting", "settings", "type='calendar' AND pageid=$pageid AND setting_name='dont_show_site_events' AND setting='1'") ? true : false;
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
   $returnme = '<table class="calendar_table">
                             <tr>
                                <td class="calendar_month" colspan="7">
                                    <div class="calendar_month_flex">
                                    <a class="calendar_change_month" style="float:left;" href="javascript: void(0);"
                                            onclick="ajaxapi_old(\'/features/calendar/calendar_ajax.php\',
                                                                    \'print_calendar\',
                                                                    \'&displaymode=0&userid=' . $userid . '&pageid=' . $pageid . '&month=' . $prevmonth . '&year=' . $prevyear . '\',
                                                                    function() {
                                                                        simple_display(\'calendar_div\');
                                                                    });">&laquo;</a>';
    $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
    $yearName = date('Y', mktime(0, 0, 0, $month, 1, $year));
    $returnme .= $monthName . ' ' . $yearName;
    if ($month == '12') {
        $nextmonth = '01';
        $nextyear = $year + 1;
    } else {
        $nextmonth = $month + 1;
        $nextyear = $year;
    }

    $returnme .= 				'<a class="calendar_change_month" style="float:right;" href="javascript: void(0);"
                                         onclick="ajaxapi_old(\'/features/calendar/calendar_ajax.php\',
                                                                \'print_calendar\',
                                                                \'&displaymode=0&userid=' . $userid . '&pageid=' . $pageid . '&month=' . $nextmonth . '&year=' . $nextyear . '\',
                                                                function() {
                                                                    simple_display(\'calendar_div\');
                                                                });">&raquo;</a>
                                    </div>
                                </td>
                              </tr>
                              <tr class="calendar_day_names">
                                <td>S</td>
                                <td>M</td>
                                <td>T</td>
                                <td>W</td>
                                <td>T</td>
                                <td>F</td>
                                <td>S</td>
                            </tr>
                            <tr class="calendar_week">';

    for ($i = 0; $i < $theday; $i++) {
        $returnme .= '<td></td>';
    }

    $HowManyRows = 0;
    $whichevents = $show_site_events ? 'AND ((pageid=' . $pageid . ') OR (pageid=' . $CFG->SITEID . ') OR (site_viewable=1))' : 'AND pageid=' . $pageid;
    for ($list_day = 1; $list_day <= $daysinmonth; $list_day++) {
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
                    } else { $category_colors = 'background-color: #CCFF00; color: #333333;'; }
                }

                ajaxapi([
                    'id'     => $tm . $tn,
                    'url'    => '/features/calendar/calendar_ajax.php',
                    'data'   => [
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
                $returnme .= '<td id="' . $tm . $tn . '" style="text-align:center;' . $category_colors . ' cursor: pointer;">';
                db_free_result($result);
            }
        } elseif ($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year) {
            $returnme .= '<td class="calendar_today">';
        } else {
                $returnme .= '<td>';
        }
        $returnme .= $list_day;
        $returnme .= '</td>';
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
        $returnme .= str_repeat("<td></td>", 7-$theday);
        $returnme .= '</tr>';
    }
    $returnme .= '</table>';
    return $returnme;
}

function get_large_calendar($pageid, $userid = 0, $month = false, $year = false, $extra_row = false) {
global $CFG;
    $show_site_events = ($pageid == $CFG->SITEID) || get_db_field("setting", "settings", "type='calendar' AND pageid=$pageid AND setting_name='dont_show_site_events' AND setting='1'") ? true : false;
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
    $returnme = '<table class="calendar_table_Large">
                        <tr>
                            <td style="text-align:center;" colspan="7" class="calendar_monthLarge">
                                <div class="calendar_month_flex">
                                    <a class="calendar_change_monthLarge" style="float:left;" href="javascript: void(0);"
                                        onclick="ajaxapi_old(\'/features/calendar/calendar_ajax.php\',
                                                                \'print_calendar\',
                                                                \'&displaymode=1&userid=' . $userid . '&pageid=' . $pageid . '&month=' . $prevmonth . '&year=' . $prevyear . '\',
                                                                function() {
                                                                    simple_display(\'calendar_div\');
                                                                });">&laquo;</a>';
    $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
    $yearName = date('Y', mktime(0, 0, 0, $month, 1, $year));
    $returnme .= $monthName . ' ' . $yearName;
    if ($month == '12') {
        $nextmonth = '01';
        $nextyear = $year + 1;
    } else {
        $nextmonth = $month + 1;
        $nextyear = $year;
    }
    $returnme .= 				'<a class="calendar_change_monthLarge" style="float:right;" href="javascript: void(0);"
                                            onclick="ajaxapi_old(\'/features/calendar/calendar_ajax.php\',
                                                                    \'print_calendar\',
                                                                    \'&displaymode=1&userid=' . $userid . '&pageid=' . $pageid . '&month=' . $nextmonth . '&year=' . $nextyear . '\',
                                                                    function() {
                                                                        simple_display(\'calendar_div\');
                                                                    });">&raquo;</a>
                                    </div>
                                </td>
                            </tr>
                            <tr class="calendar_day_namesLarge">
                                <td style="text-align:center">S</td>
                                <td style="text-align:center">M</td>
                                <td style="text-align:center">T</td>
                                <td style="text-align:center">W</td>
                                <td style="text-align:center">T</td>
                                <td style="text-align:center">F</td>
                                <td style="text-align:center">S</td>
                            </tr>
                            <tr class="calendar_week_Large">';
    for ($i = 0; $i < $theday; $i++) {
        $returnme .= '<td></td>';
    }
    $HowManyRows = 0;

    $whichevents = $show_site_events ? 'AND ((pageid=' . $pageid . ') OR (pageid=' . $CFG->SITEID . ') OR (site_viewable=1))' : 'AND pageid=' . $pageid;

    for ($list_day = 1; $list_day <= $daysinmonth; $list_day++) {
        if ($theday == 0 && $list_day != 1) { $returnme .= '<tr class="calendar_week_Large">'; }
        $tm = date("U", mktime(0, 0, 0, $month, $list_day, $year)) - 86400; // Bir g�n �nce
        $tn = date("U", mktime(0, 0, 0, $month, $list_day, $year)); // O g�n ...
        $tp = date("U", mktime(0, 0, 0, $month, $list_day, $year)) + 86400; // Bir g�n sonra
        $SQL = sprintf("SELECT * FROM `calendar_events` WHERE `date` > '%s' AND `date` < '%s' AND `day` = '%s' $whichevents ORDER BY date;", $tm, $tp, $list_day);

        if ($count = get_db_count($SQL)) { //Event exists
            if ($result = get_db_result($SQL)) {
                $eventlist = $today = "";
                while ($event = fetch_row($result)) {
                    if ($event["cat"] != 0) {
                        if (!empty($event["eventid"])) {
                            if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }
                            $e = get_event($event["eventid"]);
                            $eventlist .= '<div>' . $e["name"] . '</div>';
                        }
                        $category = get_db_row("SELECT * FROM calendar_cat WHERE cat_id=" . $event["cat"]);
                        $background_color = $category["cat_color"] != "" ? 'color:' . $category["cat_color"] . ';' : 'color: #333333;';
                        $font_color = $category["cat_bgcolor"] != '' ? 'background-color:' . $category["cat_bgcolor"] . ';' : 'background-color: #CCFF00;';
                        $category_colors = $background_color . $font_color;
                    } else {
                        $category_colors = 'background-color: #CCFF00; color: #333333;';
                    }
                }
                ajaxapi([
                    'id'     => $tm . $tn,
                    'url'    => '/features/calendar/calendar_ajax.php',
                    'data'   => [
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
                $returnme .= '<td id="' . $tm . $tn . '" style="' . $category_colors . '">';
                db_free_result($result);
            }
        } elseif ($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year) {
            $returnme .= '<td class="calendar_today">';
        } else {
            $returnme .= '<td>';
        }

        $returnme .= '<span class="calendar_corner_day">' . $list_day . '</span>';

        if ($tn > $tm && $tn < $tp && date('j') == $list_day && date('m') == $month && date('Y') == $year) {
            $returnme .= '<span class="calendar_today_label">Today</span>';
        }

        if ($count) {
            $label = empty($eventlist) ? 'Calendar Event' : $eventlist;
            $returnme .= '<span class="calendar_event_label">' . $label . '</span>';
        }

        $returnme .= '</td>';
        if ($theday == 6) {
            $returnme .= '</tr>';
            $theday = -1;
            $HowManyRows = $HowManyRows + 1;
        }
        $theday++;
    }
    if ($theday != 0) { $returnme .= '</tr>'; }
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