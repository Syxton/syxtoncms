<?php
/***************************************************************************
* eventslib.php - Events function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.8.7
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('EVENTSLIB', true);
if (!defined('SEARCH_PERPAGE')) { define('SEARCH_PERPAGE', 8); }

function display_events($pageid, $area, $featureid) {
global $CFG, $USER, $ROLES;
    $content = "";

    if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        save_batch_settings(default_settings("events", $pageid, $featureid));
        $settings = fetch_settings("events", $featureid, $pageid);
    }

    $title = $settings->events->$featureid->feature_title->setting;
    $upcomingdays = $settings->events->$featureid->upcomingdays->setting;
    $recentdays = $settings->events->$featureid->recentdays->setting;
    $archivedays = $settings->events->$featureid->archivedays->setting;
    $showpastevents = $settings->events->$featureid->showpastevents->setting;
    $allowrequests = $settings->events->$featureid->allowrequests->setting;

    if ($area == "middle") {
        //Get calendar of events
        return get_calendar_of_events($title, $pageid, $featureid, false, $showpastevents, $content, $allowrequests);
    } else {
        if (is_logged_in()) { // Logged in user will see...
            if (get_db_row("SELECT eventid FROM events WHERE workers=1 AND event_begin_date > " . time())) {
                if (user_is_able($USER->userid, "staffapply", $pageid, "events", $featureid)) {
                    $content .= get_staff_application_link();
                }
            }

            if (user_is_able($USER->userid, "viewevents", $pageid, "events", $featureid)) {
                // Get events that must be confirmed
                if ($pageid == $CFG->SITEID) {
                    if (user_is_able($USER->userid, "confirmevents", $pageid, "events", $featureid) && $section = get_confirm_events()) {
                        $content .= $section;
                    }
                }

                // Get events that can be edited
                if (user_is_able($USER->userid, "editevents", $pageid, "events", $featureid) && $section = get_editable_events($pageid)) {
                    $content .= $section;
                }

                // Get current events
                if ($section = get_current_events($pageid)) {
                    $content .= $section;
                }

                // Get upcoming events
                if ($section = get_upcoming_events($pageid, $upcomingdays)) {
                    $content .= $section . "<br />";
                }

                // Get events that are registerable
                if ($section = get_open_enrollment_events($pageid)) {
                    $content .= $section;
                }

                // No events
                if (empty($content)) {
                    $content = "There are no current or upcoming events.";
                }

                // Get link for request form
                if ($allowrequests) {
                    $content = get_event_request_link($area, $featureid) . $content;
                }

                // Get recent events
                if ($section = get_recent_events($pageid, $recentdays, $archivedays)) {
                    $content .= $section;
                }

                // Get feature layout
                $buttons = get_button_layout("events", $featureid, $pageid);
                $title = '<span class="box_title_text">' . $title . '</span>';
                return get_css_box($title, $content, $buttons, NULL, "events", $featureid);
            }
        } elseif (role_is_able($ROLES->visitor, "viewevents", $pageid)) { //If unlogged in users can see...
            //Get current events
            if ($section = get_current_events($pageid)) { $content .= $section;}

            //Get upcoming events
            if ($section = get_upcoming_events($pageid, $upcomingdays)) { $content .= $section;}

            //Get registerable events
            if ($section = get_open_enrollment_events($pageid)) { $content .= $section . "";}

            //No events
            if ($content == "") { $content .= "There are no current or upcoming events.";}

            //Get link for request form
            if ($allowrequests) { $content = get_event_request_link($area, $featureid) . $content; }

            //Show past events
            if ($section = get_recent_events($pageid, $recentdays, $archivedays)) { $content .= $section;}

            //Get feature layout
            $title = '<span class="box_title_text">' . $title . '</span>';
            return get_css_box($title, $content, NULL, NULL, "events", $featureid);
        }
    }
}

function get_staff_application_link() {
global $CFG;
    $returnme = '';
    if (is_logged_in()) { // Staff Apply menu item visible only if logged in
        $p = [
            "title" => "Staff Apply",
            "path" => action_path("events") . "staff_application",
            "validate" => "true",
            "width" => "600",
            "height" => "650",
        ];
        $menuitem = fill_template("tmp/page.template", "get_ul_item", false, ["item" =>  make_modal_links($p)]);
        $menuitem = str_replace(array("\r", "\n"), '', $menuitem);
        $returnme .= js_code_wrap('var item = "' . addslashes($menuitem) . '"; $("#pagenav").append(item);', 'defer', true);
    }

    $p = [
        "title" => "Staff Application/Renewal Form",
        "text" => "Staff Apply",
        "path" => action_path("events") . "staff_application",
        "validate" => "true",
        "width" => "600",
        "height" => "650",
        "icon" => icon("clipboard-user"),
        "styles" => "font-size: 1.5em;font-weight: bold;",
        "confirmexit" => "true",
    ];
    $returnme .= '<div class="staff_application_link">' . make_modal_links($p) . '</div>';

    return $returnme;
}

function get_event_request_link($area, $featureid) {
global $CFG;
    $p = [
        "title" => "Begin Event Request Process",
        "text" => "Request an Event",
        "path" => action_path("events") . "event_request_form&featureid=$featureid",
        "validate" => "true",
        "width" => "600",
        "height" => "650",
        "styles" => "font-size: 1.5em;font-weight: bold;",
        "icon" => icon("bell-concierge", 1, "", "#c5c526"),
    ];
    return '<div class="request_event_link">' . make_modal_links($p) . '</div>';
}

function get_calendar_of_events($title, $pageid, $featureid, $year = false, $showpastevents = true, $content = "", $allowrequests=false) {
global $CFG, $USER, $ROLES;
    $time = get_timestamp();
    $year = $year ? $year : date("Y", $time);

    $begincurrentyear = mktime(0, 0, 0, 1, 1, $year); //Beginning of current year
    $endcurrentyear = mktime(0, 0, 0, 12, 32, $year); //End of current year

    // GET ABILITIES FOR EVENTS
    $canconfirm = false;
    $canedit = false;
    $canview = false;
    $site = $pageid == $CFG->SITEID ? "((e.pageid != $pageid AND siteviewable=1) OR (e.pageid = $pageid))" : "e.pageid = $pageid";

    if (is_logged_in()) {
        if (user_is_able($USER->userid, "staffapply", $pageid, "events", $featureid)) {
            $content .= get_staff_application_link($featureid, $pageid);
        }
        if (user_is_able($USER->userid, "viewevents", $pageid, "events", $featureid)) {
            $canview = true;
            $canconfirm = user_is_able($USER->userid, "confirmevents", $CFG->SITEID, "events", $featureid) ? true : false;
            $buttons = get_button_layout("events", $featureid, $pageid);
            if ($canconfirm) {
                $SQL = "SELECT * FROM events e WHERE $site AND $begincurrentyear < e.event_begin_date AND $endcurrentyear > e.event_begin_date ORDER BY e.event_begin_date, e.event_begin_time";
            } else {
                $SQL = "SELECT * FROM events e WHERE $site AND $begincurrentyear < e.event_begin_date AND $endcurrentyear > e.event_begin_date AND e.confirmed=1 ORDER BY e.event_begin_date, e.event_begin_time";
            }
        }
    } else {
        $canview = role_is_able($ROLES->visitor, "viewevents", $pageid) ? true : false;
        if ($canview && $pageid == $CFG->SITEID) {
            $SQL = "SELECT * FROM events e WHERE $site AND $begincurrentyear < e.event_begin_date AND $endcurrentyear > e.event_begin_date AND e.confirmed=1 ORDER BY e.event_begin_date, e.event_begin_time";
        } elseif ($canview) {
            $SQL = "SELECT * FROM events e WHERE $site AND $begincurrentyear < e.event_begin_date AND $endcurrentyear > e.event_begin_date AND e.confirmed=1 ORDER BY e.event_begin_date, e.event_begin_time";
        }
        $buttons = null;
    } // END ABILITIES CHECK

    if ($canview && $result = get_db_result($SQL)) {
        $lastday = false;
        while ($event = fetch_row($result)) {
            if ($showpastevents || ($event["event_end_date"] >= ($time - 86400))) {
                $newday = true;
                  $newday = $lastday == date("n/d/Y", $event["event_begin_date"]) ? false : true;
                  $lastday = date("n/d/Y", $event["event_begin_date"]);
                  $canedit = user_is_able($USER->userid, "editevents", $event["pageid"], "events", $featureid) ? true : false;
                  $event_buttons = get_event_button_layout($pageid, $event, $canedit, $canconfirm);
                  $needsconfirmed = $canconfirm && $event["confirmed"] != 1 && $pageid == $CFG->SITEID ? true : false;
                  $dategraphic = $needsconfirmed || ($event["event_end_date"] < ($time - 86400)) ? get_date_graphic($event["event_begin_date"], $newday, null, true, true) : get_date_graphic($event["event_begin_date"], $newday, null, true);
                  $content .= make_calendar_table($pageid, $dategraphic, $event, $event_buttons, $needsconfirmed);
            }
        }
    }

    if ($content == "") {
        $content .= "There are no events for this calendar year.";
    }

    // Get link for request form.
    if ($allowrequests) {
        $content = get_event_request_link("middle", $featureid) . $content;
    }

    $title = '<span class="box_title_text">' . $title . '</span>';
    return get_css_box($title, $content, $buttons, NULL, "events", $featureid);
}

function make_calendar_table($pageid, $daygraphic, $event, $buttons = false, $needsconfirmed = false) {
global $CFG, $USER;
    $time = get_timestamp();
    $registration_info = "";
    $alert = $export = $info = $eventbuttons = "";

    $featureid = get_db_field("featureid", "pages_features", "pageid='$pageid' AND feature='events'");
    if ($event["start_reg"] > 0) { // Event is a registerable page...at one time.
        $regcount = get_db_count("SELECT * FROM events_registrations WHERE eventid='" . $event['eventid'] . "'");
        $limit = $event['max_users'] == "0" ? "&#8734;" : $event['max_users'];

        // Currently can register for event (time check)
        if ($event["start_reg"] < $time && $event["stop_reg"] > ($time - 86400)) {
            $info  = "Registration ends in " . ago($event["event_begin_date"]);
            $maxreached = $event["max_users"] == 0 || ($event["max_users"] != 0 and $event["max_users"] > $regcount) ? false : true;

            // Availability check
            if (!$maxreached) {
                $left = $event['max_users'] == "0" ? "&#8734;" : '(' . ($limit - $regcount) . ' out of ' . $limit . ' openings left)';

                // Alert
                if ($event['max_users'] > 0 && (($limit - $regcount) < 10)) {
                    $alert = '<span class="events_limit_alert">Only ' . ($limit - $regcount) . ' spots remaining.</span>';
                } else {
                    $alert = "";
                }

                // Can you sign up for this event.
                if (user_is_able($USER->userid, "signupforevents", $pageid, "events", $featureid)) {
                    $eventbuttons .= get_event_register_link($event, $pageid);
                }

                // Can you pay for this event.
                if ($event["paypal"] != "") {
                    $eventbuttons .= get_event_pay_link($event, $pageid);
                }
            } else {
                $alert = '<span class="events_limit_alert">No spots available.</span>';
            }
        }

        if ($time > $event["event_begin_date"] && $time < $event["event_end_date"]) {
            $info = '<span class="events_alert">This event is currently underway!</span>';
        }

        // GET EXPORT CSV BUTTON
        if (user_is_able($USER->userid, "exportcsv", $event["pageid"], "events", $featureid)) {
            ajaxapi([
                "id" => "export_event_registrations" ,
                "paramlist" => "eventid, pageid",
                "url" => "/features/events/events_ajax.php",
                "data" => [
                    "action" => "export_registrations",
                    "pagieid" => "js||pageid||js",
                    "eventid" => "js||eventid||js",
                ],
                "display" => "downloadframe",
                "event" => "none",
            ]);
            $export = '
                <button onclick="export_event_registrations(' . $event['eventid'] . ', ' . $pageid . ');" title="Export ' . $regcount . '/' . $limit . ' Registrations" class="alike">
                    ' . icon([["icon" => "file-csv", "style" => "font-size: 1.3em"]]) . '
                </button>';
        }
    }

    if ($info == "") { // If the info is empty, let's put something there.
        $location = get_db_row("SELECT * FROM events_locations WHERE id='" . $event['location'] . "'");
        $info = "Event Location: " . stripslashes($location["location"]);
    }

    $registration_info = '<div role="export_button" class="events_reginfoblock">' . $export . '</div>' .
                         '<div role="event_info" class="events_reginfoblock">' . $info . '</div>' .
                         '<div role="event_buttons" class="events_reginfoblock event_buttons">' . $eventbuttons . '</div>' .
                         '<div role="alert" class="events_reginfoblock">' . $alert . '</div>';

    $confirmed = $needsconfirmed ? "Unconfirmed:" : "";
    $returnme = '
        <div id="confirm_' . $event['eventid'] . '">
            <table class="eventstable">
                <tr>
                    <td style="width: 6%;">
                    ' . $daygraphic . '
                    </td>
                    <td>
                        <table style="width:100%;border-spacing: 0px;">
                        <tr>
                            <td>
                                <div class="event_title" style="color:gray;">
                                    ' . $confirmed . '
                                    ' . get_event_info_link($event, $pageid) . '
                                </div>
                                <span style="font-size:.85em;padding-left:5px;">
                                    ' . stripslashes(strip_tags($event["byline"], '<a>')) . '
                                </span>
                                <div class="container_head">
                                    <div class="event_info_box">
                                        <div class="event_info">
                                            ' . $registration_info . '
                                        </div>
                                        ' . $buttons . '
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>';
    return $returnme;
}

function get_event_button_layout($pageid, $event, $edit, $confirm) {
global $CFG;
    $buttons = get_event_edit_buttons($pageid, $event, $edit, $confirm);
    $themeid = get_page_themeid($pageid);
    $styles = get_styles($pageid, $themeid, "news");

    $contentbgcolor = isset($styles['contentbgcolor']) ? $styles['contentbgcolor'] : "";
    $bordercolor = isset($styles['bordercolor']) ? $styles['bordercolor'] : "";
    $titlebgcolor = isset($styles['titlebgcolor']) ? $styles['titlebgcolor'] : "";
    $titlefontcolor = isset($styles['titlefontcolor']) ? $styles['titlefontcolor'] : "";

    if (strlen($buttons) > 0) {
        $params = [
            "bordercolor" => $bordercolor,
            "titlefontcolor" => $titlefontcolor,
            "titlebgcolor" => $titlebgcolor,
            "featuretype" => "event",
            "featureid" => $event["eventid"],
            "buttons" => $buttons,
            "icon" => icon("grip-vertical", 1, "", $titlebgcolor),
        ];
        return fill_template("tmp/pagelib.template", "get_button_layout_template", false, $params);
    }

    return "";
}

function get_event_edit_buttons($pageid, $event, $canedit, $canconfirm) {
global $CFG, $USER;
    $returnme = "";
    $is_section = true;
    if (is_logged_in()) {
        // Confirm Event Buttons
        if ($canconfirm && $event["confirmed"] != 1 && $event["siteviewable"] == 1) {
            ajaxapi([
                "id" => "confirm_event_" . $event['eventid'],
                "paramlist" => "confirm = 0",
                "if" => "(confirm == 1 && confirm('Are you sure you want to confirm this event?')) || (confirm == 0 && confirm('Are you sure you want to deny this event?'))",
                "url" => "/features/events/events_ajax.php",
                "data" => [
                    "action" => "confirm_event_relay",
                    "confirm" => "js||confirm||js",
                    "pagieid" => $pageid,
                    "eventid" => $event['eventid'],
                ],
                "display" => "confirm_" . $event['eventid'],
                "ondone" => "go_to_page('" . $pageid . "');",
                "event" => "none",
            ]);
            $returnme .= '
                <button title="Confirm Event\'s Global Visibility" onclick="confirm_event_' . $event['eventid'] . '(1);" class="slide_menu_button alike">
                    ' . icon("thumbs-up") . '
                </button>
                <button title="Deny Event\'s Global Visibility" onclick="confirm_event_' . $event['eventid'] . '(0);" class="slide_menu_button alike">
                    ' . icon("thumbs-down") . '
                </button>';
        }

        // Edit && Delete button
        if ($canedit) {
            $returnme .= get_event_edit_link($event, $pageid);

            ajaxapi([
                "id" => "delete_event_" . $event['eventid'],
                "paramlist" => "confirm = 0",
                "if" => "confirm('Are you sure you want to delete this event?')",
                "url" => "/features/events/events_ajax.php",
                "data" => [
                    "action" => "delete_events_relay",
                    "eventid" => $event['eventid'],
                ],
                "ondone" => "go_to_page('" . $pageid . "');",
            ]);

            // Delete button
            $returnme .= '
                <button id="delete_event_' . $event['eventid'] . '" title="Delete Event" class="slide_menu_button alike" >
                    ' . icon("trash") . '
                </button>';
        }
    } else {  return "";}
    return $returnme;
}

// Gathers the events that need to be confirmed
function get_confirm_events() {
global $CFG, $USER;
    $returnme = "";
    $pageid = $CFG->SITEID;
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $SQL = fill_template("dbsql/events.sql", "confirmable_events", "events", ["time" => $time], true);
    if ($events = get_db_result($SQL, ["pageid" => $pageid])) {
        ajaxapi([
            "id" => "confirm_event",
            "paramlist" => "eventid, confirmed = 0",
            "if" => "(confirmed == 1 && confirm('Are you sure you want to confirm this event?')) || (confirmed == 0 && confirm('Are you sure you want to deny this event?'))",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "confirm_event_relay",
                "confirm" => "js||confirmed||js",
                "pagieid" => $pageid,
                "eventid" => "js||eventid||js",
            ],
            "display" => "confirm_js||eventid||js",
            "ondone" => "go_to_page('" . $pageid . "');",
            "event" => "none",
        ]);

        $eventslist = "";
        while ($event = fetch_row($events)) {
            $buttons = [];

            $featureid = get_db_field("featureid", "pages_features", "pageid='$pageid' AND feature='events'");
            $buttons[] = user_is_able($USER->userid, "editevents", $pageid, "events", $featureid) ? get_event_edit_link($event, $pageid) : "";

            $buttons[] = '
                <button title="Confirm Event" onclick="confirm_event(' . $event['eventid'] . ', 1);" class="alike slide_menu_button">
                    ' . icon("thumbs-up") . '
                </button>
                <button title="Deny Event" onclick="confirm_event(' . $event['eventid'] . ', 0);"" class="alike slide_menu_button">
                    ' . icon("thumbs-down") . '
                </button>';

            $params = [
                "containername" => "confirm_" . $event['eventid'],
                "info" => get_event_info_link($event, $pageid),
                "buttons" => $buttons,
                "extrainfo" => get_event_length($event),
            ];
            $eventslist .= fill_template("tmp/events.template", "eventslist", "events", $params);
        }

        $params  = [
            "title" => icon("bell-concierge", 1, "", "#c5c526") . " <span>Events Need Confirmed</span>",
            "eventslist" => $eventslist];
        return fill_template("tmp/events.template", "eventtype", "events", $params);
    }
    return false;
}

// Gathers the events that can be edited
function get_editable_events($pageid) {
global $CFG, $USER;
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $siteviewable = $pageid == $CFG->SITEID ? " OR (siteviewable = 1 AND confirmed = 1)" : "";
    $SQL = fill_template("dbsql/events.sql", "editable_events", "events", ["time" => $time, "siteviewable" => $siteviewable], true);
    if ($events = get_db_result($SQL, ["pageid" => $pageid])) {
        ajaxapi([
            "id" => "delete_event",
            "paramlist" => "eventid",
            "if" => "confirm('Are you sure you want to delete this event?')",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "delete_events_relay",
                "eventid" => "js||eventid||js",
            ],
            "ondone" => "go_to_page('" . $pageid . "');",
            "event" => "none",
        ]);

        $eventslist = "";
        while ($event = fetch_row($events)) {
            $buttons = [];

            // EDIT LINK
            $buttons[] = get_event_edit_link($event, $pageid);

            // DELETE LINK
            $buttons[] = '
                <button title="Delete Event" onclick="delete_event(' . $event['eventid'] . ');" class="alike slide_menu_button">
                    ' . icon("trash") . '
                </button>';

            $params = [
                "containername" => "edit_" . $event['eventid'],
                "info" => get_event_info_link($event, $pageid),
                "buttons" => $buttons,
                "extrainfo" => get_event_length($event),
            ];
            $eventslist .= fill_template("tmp/events.template", "eventslist", "events", $params);
        }

        $params  = [
            "title" => icon("pencil") . " <span>Editable Events</span>",
            "eventslist" => $eventslist];
        return fill_template("tmp/events.template", "eventtype", "events", $params);
    }
    return false;
}

// Gathers the events that are currently available for enrollment
function get_open_enrollment_events($pageid) {
global $CFG, $USER;
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $siteviewable = $pageid == $CFG->SITEID ? " OR (siteviewable = 1 AND confirmed = 1)" : "";
    $SQL = fill_template("dbsql/events.sql", "open_enrollable_events", "events", ["siteviewable" => $siteviewable, "time" => $time], true);
    if ($events = get_db_result($SQL, ["pageid" => $pageid])) {
        ajaxapi([
            "id" => "delete_event",
            "paramlist" => "eventid",
            "if" => "confirm('Are you sure you want to delete this event?')",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "delete_events_relay",
                "eventid" => "js||eventid||js",
            ],
            "ondone" => "go_to_page('" . $pageid . "');",
            "event" => "none",
        ]);

        ajaxapi([
            "id" => "export_event_registrations" ,
            "paramlist" => "eventid, pageid",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "export_registrations",
                "pagieid" => "js||pageid||js",
                "eventid" => "js||eventid||js",
            ],
            "display" => "downloadframe",
            "event" => "none",
        ]);

        $featureid = get_db_field("featureid", "pages_features", "pageid='$pageid' AND feature='events'");
        $eventslist = "";
        while ($event = fetch_row($events)) {
            $buttons = [];
            $exportlink = "";

            // INFO LINK
            $infolink = get_event_info_link($event, $pageid);

            $SQL = fetch_template("dbsql/events.sql", "get_verified_event_registrations", "events");
            $regcurrent = get_db_count($SQL, ["eventid" => $event['eventid']]);

            // Registration button
            if (user_is_able($USER->userid, "signupforevents", $pageid, "events", $featureid)) {
                $titleinfo = "&#8734;"; $openings = true;
                if (!empty($event['max_users'])) {
                    $openings = $event['max_users'] - $regcurrent > 0 ? $event['max_users'] - $regcurrent : false;
                    $titleinfo = $left ? $left . ' out of ' . $event['max_users'] : '0';
                }

                $titleinfo = $event['max_users'] == "0" ? "&#8734;" : $titleinfo . ' openings left)';
                if (!$openings) {
                    $titleinfo = 'Registrations will be added to the waitlist for this event';
                }

                $buttons[] = get_event_register_link($event, $pageid, $titleinfo);
            }

            $SQL = fetch_template("dbsql/events.sql", "get_pending_event_registrations", "events");
            $regpending = get_db_count($SQL, ["eventid" => $event['eventid']]);

            // Export registrations
            if (user_is_able($USER->userid, "exportcsv", $pageid,"events", $featureid)) {
                $buttons[] = '
                    <button title="Export ' . $regcurrent . ' Verified Registrations and ' . $regpending . ' Pending Registrations" onclick="export_registrations(' . $event['eventid'] . ',' . $pageid . ');" class="alike slide_menu_button">
                        ' . icon([["icon" => "file-csv", "style" => "font-size: 1.3em"]]) . '
                    </button>';
            }

            // Payment Area
            if ($event["paypal"] != "") {
                $buttons[] = get_event_pay_link($event, $pageid);
            }

            $params = [
                "containername" => "edit_" . $event['eventid'],
                "info" => get_event_info_link($event, $pageid),
                "buttons" => $buttons,
                "extrainfo" => get_event_length($event),
            ];
            $eventslist .= fill_template("tmp/events.template", "eventslist", "events", $params);
        }

        $params  = [
            "title" => icon([["icon" => "clipboard-check", "color" => "green"],]) . " <span>Registerable Events</span>",
            "eventslist" => $eventslist];
        return fill_template("tmp/events.template", "eventtype", "events", $params);
    }
    return false;
}

// Gathers the events that are happening in the next (SETTINGS: upcomingdays) days
function get_upcoming_events($pageid, $upcomingdays) {
global $CFG;
    $returnme = "";
    $time = get_timestamp();
    date_default_timezone_set("UTC");

    $totime = $time + ($upcomingdays * 86400);
    $siteviewable = $pageid == $CFG->SITEID ? " OR (siteviewable = 1 AND confirmed = 1)" : "";
    $SQL = fill_template("dbsql/events.sql", "upcoming_events", "events", ["fromtime" => $time, "totime" => $totime, "siteviewable" => $siteviewable], true);
    if ($events = get_db_result($SQL, ["pageid" => $pageid])) {
        $eventslist = "";
        while ($event = fetch_row($events)) {
            $params = [
                "containername" => "upcoming_" . $event['eventid'],
                "info" => get_event_info_link($event, $pageid),
                "buttons" => [],
                "extrainfo" => get_event_length($event),
            ];
            $eventslist .= fill_template("tmp/events.template", "eventslist", "events", $params);
        }

        $params  = [
            "title" => icon([["icon" => "clock", "color" => "#0098b3"],]) . " <span>Upcoming Events</span>",
            "eventslist" => $eventslist];
        return fill_template("tmp/events.template", "eventtype", "events", $params);
    }

    return false;
}

// Gathers the events that are happening right now
function get_current_events($pageid) {
global $CFG, $USER;
    $time = get_timestamp();
    date_default_timezone_set("UTC");
    $siteviewable = $pageid == $CFG->SITEID ? " OR (siteviewable = 1 AND confirmed = 1)" : "";
    $SQL = fill_template("dbsql/events.sql", "current_events", "events", ["time" => $time, "siteviewable" => $siteviewable], true);
    if ($events = get_db_result($SQL, ["pageid" => $pageid])) {
        $featureid = get_db_field("featureid", "pages_features", "pageid = ||pageid|| AND feature='events'", ["pageid" => $pageid]);
        $eventslist = "";
        while ($event = fetch_row($events)) {
            $buttons = [];

            // Export registrations
            if (user_is_able($USER->userid, "exportcsv", $pageid, "events", $featureid)) {
                $SQL = fetch_template("dbsql/events.sql", "get_verified_event_registrations", "events");
                $regcurrent = get_db_count($SQL, ["eventid" => $event['eventid']]);
                $SQL = fetch_template("dbsql/events.sql", "get_pending_event_registrations", "events");
                $regpending = get_db_count($SQL, ["eventid" => $event['eventid']]);

                $buttons[] = '
                    <button title="Export ' . $regcurrent . ' Verified Registrations and ' . $regpending . ' Pending Registrations" onclick="export_registrations(' . $event['eventid'] . ',' . $pageid . ');" class="alike slide_menu_button">
                        ' . icon([["icon" => "file-csv", "style" => "font-size: 1.3em"]]) . '
                    </button>';
            }

            $params = [
                "containername" => "current_" . $event['eventid'],
                "info" => get_event_info_link($event, $pageid),
                "buttons" => $buttons,
                "extrainfo" => get_event_length($event),
            ];
            $eventslist .= fill_template("tmp/events.template", "eventslist", "events", $params);
        }

        $params  = [
            "title" => icon([["icon" => "fire", "color" => "red"],]) . " <span>Active Events</span>",
            "eventslist" => $eventslist];
        return fill_template("tmp/events.template", "eventtype", "events", $params);
    }

    return false;
}

// Gathers the events that are happening right now
function get_recent_events($pageid, $recentdays, $archivedays) {
global $CFG, $USER;
    $time = get_timestamp();
    date_default_timezone_set("UTC");

    $featureid = get_db_field("featureid", "pages_features", "pageid = ||pageid|| AND feature='events'", ["pageid" => $pageid]);
    $dayspan = user_is_able($USER->userid, "exportcsv", $pageid, "events", $featureid) ? $archivedays : $recentdays;
    $to_day = $dayspan * 86400;
    $siteviewable = $pageid == $CFG->SITEID ? " OR (siteviewable = 1 AND confirmed = 1)" : "";
    $SQL = fill_template("dbsql/events.sql", "recent_events", "events", ["time" => $time, "to_day" => $to_day, "siteviewable" => $siteviewable], true);
    if ($events = get_db_result($SQL, ["pageid" => $pageid])) {
        $eventslist = "";
        while ($event = fetch_row($events)) {
            $buttons = [];

            // Export registrations
            if (user_is_able($USER->userid, "exportcsv", $pageid,"events", $featureid)) {
                $SQL = fetch_template("dbsql/events.sql", "get_verified_event_registrations", "events");
                $regcurrent = get_db_count($SQL, ["eventid" => $event['eventid']]);
                $SQL = fetch_template("dbsql/events.sql", "get_pending_event_registrations", "events");
                $regpending = get_db_count($SQL, ["eventid" => $event['eventid']]);

                $buttons[] = '
                    <button title="Export ' . $regcurrent . ' Verified Registrations and ' . $regpending . ' Pending Registrations" onclick="export_registrations(' . $event['eventid'] . ',' . $pageid . ');" class="alike slide_menu_button">
                        ' . icon([["icon" => "file-csv", "style" => "font-size: 1.3em"]]) . '
                    </button>';
            }

            $params = [
                "containername" => "recent_" . $event['eventid'],
                "info" => get_event_info_link($event, $pageid),
                "buttons" => $buttons,
                "extrainfo" => get_event_length($event),
            ];
            $eventslist .= fill_template("tmp/events.template", "eventslist", "events", $params);
        }

        $params  = [
            "title" => icon([["icon" => "clock-rotate-left", "color" => "red"],]) . " <span>Recent Events</span>",
            "eventslist" => $eventslist];
        return fill_template("tmp/events.template", "eventtype", "events", $params);
    }

    return false;
}

function get_event_info_link($event, $pageid) {
    return make_modal_links([
        "title" => $event['name'],
        "path" => action_path("events") . "info&pageid=$pageid&eventid=" . $event['eventid'],
        "iframe" => true,
        "width" => "800",
    ]);
}

function get_event_edit_link($event, $pageid) {
    return make_modal_links([
        "title" => "Edit Event",
        "path" => action_path("events") . "add_event_form&pageid=$pageid&eventid=" . $event['eventid'],
        "refresh" => "true",
        "iframe" => true,
        "width" => "800",
        "icon" => icon("pencil"),
        "class" => "slide_menu_button",
    ]);
}

function get_event_register_link($event, $pageid, $titleinfo = "") {
    return make_modal_links([
        "title" => "Register $titleinfo",
        "path" => action_path("events") . "show_registration&pageid=$pageid&eventid=" . $event['eventid'],
        "iframe" => true,
        "validate" => "true",
        "width" => "630",
        "height" => "95%",
        "confirmexit" => "true",
        "icon" => icon([["icon" => "clipboard-check", "color" => "green"],]),
        "class" => "slide_menu_button",
    ]);
}

function get_event_pay_link($event, $pageid) {
    return make_modal_links([
        "title" => "Make Payment",
        "path" => action_path("events") . "pay&modal=1&pageid=$pageid&eventid=" . $event['eventid'],
        "width" => "95%",
        "height" => "95%",
        "icon" => icon("credit-card"),
        "class" => "slide_menu_button",
    ]);
}

function get_todays_fee($fullfee, $salefee, $sale_end) {
    if (!$sale_end || !$sale || get_timestamp() > $sale_end) {
        return $fullfee;
    }

    return $salefee;

}

function make_fee_options($min, $full, $name, $options = "", $sale_end = "", $sale = false) {
    // Get full price factoring in possible sale price.
    $full = get_todays_fee($full, $sale, $sale_end);

    $returnme = '<select id="' . $name . '" name="' . $name . '" ' . $options . ' >';
    $select = "selected";

    if ($min == $full) { return '<span style="float:left;margin:4px;">$</span><input id="' . $name . '" name="' . $name . '" type="text" READONLY value="' . $full . '.00"/>';}

    while ($min < $full) {
        $returnme .= '<option value="' . $min . '" ' . $select . '>$' . $min . '</option>';
        $min = ($full - $min) > 10 ? $min + 10 : $full;
        $select = "";
    }
    $returnme .= '<option value="' . $min . '">$' . $min . '</option></select>';
    return $returnme;
}

function make_paypal_button($items, $sellersemail) {
global $CFG;
    $regids = "";
    $paypal = $CFG->paypal ? 'www.paypal.com' : 'www.sandbox.paypal.com';
    $protocol = get_protocol();

    $returnme = '
        <form style="margin: auto; text-align: center;" action="https://' . $paypal . '/cgi-bin/webscr" method="post" target="_top">
            <div style="width: 100%; text-align: center;">
            <input name="upload" type="hidden" value="1" />
            <input name="cmd" type="hidden" value="_cart" />
            <input name="business" type="hidden" value="' . $sellersemail . '" />
            <input name="return" type="hidden" value="' . $protocol.$CFG->wwwroot . '/features/events/events.php?action=showcart" />
            <input name="notify_url" type="hidden" value="' . $protocol.$CFG->wwwroot . '/features/events/ipn.php" />
            <input name="lc" type="hidden" value="US" />
            <input name="currency_code" type="hidden" value="USD" />
            <input name="no_shipping" type="hidden" value="1" />
            <input name="tax" type="hidden" value="0.00" />
            <input name="rm" type="hidden" value="2" />
            <input name="bn" type="hidden" value="PP-BuyNowBF" />
    ';

    $i = 0;
    foreach ($items as $item) {
        $i++;
        $returnme .= '
            <input name="item_name_' . $i . '" type="hidden" value="' . $item->description . '" />
            <input name="amount_' . $i . '" type="hidden" value="' . number_format($item->cost, 2, '.', '') . '" />';
        $regids .= empty($regids) ? $item->regid : ":" . $item->regid;
    }

    $returnme .= '
            <input name="custom" type="hidden" value="' . $regids . '">
            <br />
            <input style="border: 0px;" alt="PayPal - The safer, easier way to pay online!" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/x-click-but6.gif" type="image">
            <img style="border: 0px; display: block; margin-left: auto; margin-right: auto; width: 80%;" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" alt="" width="1" height="1">
            </div>
        </form>';
    return $returnme;
}

function get_registrant_name($regid) {
global $CFG;
    $SQL = "SELECT * FROM events_templates WHERE template_id IN (SELECT template_id FROM events WHERE eventid IN (SELECT eventid FROM events_registrations WHERE regid='$regid'))";
    $template = get_db_row($SQL);
    $name = "";
    if ($template["folder"] == "none") {
        if ($name_fields = get_db_result("SELECT * FROM events_templates_forms WHERE template_id=" . $template["template_id"] . " AND nameforemail=1")) {
            while ($name_field = fetch_row($name_fields)) {
                $value = stripslashes(get_db_field("value", "events_registrations_values", "regid='$regid' AND elementid='" . $name_field["elementid"] . "'"));
                $name .= $name == "" ? $value : " " . $value;
            }
        }
    } else {
        $name_fields = explode(",", $template["registrant_name"]);
        $i = 0;
        while (isset($name_fields[$i])) {
            $value = stripslashes(get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='" . $name_fields[$i] . "'"));
            $name .= $name == "" ? $value : " " . $value;
            $i++;
        }
    }

    return ucwords($name);
}

function enter_registration($eventid, $reg, $contactemail, $pending = true) {
global $CFG, $why, $error;
    try {
        start_db_transaction();

        $event = get_event($eventid);
        $template = get_event_template($event['template_id']);
        $nolimit = true;
        $why = "";
        $time = get_timestamp();
        $pending = $pending ? 0 : 1;

        $SQL = fetch_template("dbsql/events.sql", "insert_registration", "events");
        $params = ["eventid" => $eventid, "date" => $time, "email" => $contactemail, "code" => md5($time . $contactemail), "verified" => $pending];
        $regid = execute_db_sql($SQL, $params);
        if ($template['folder'] != "none") { // custom file style
            if ($regid) {
                $formlist = explode(";", get_db_field("formlist", "events_templates", "folder = '" . $template['folder'] . "'"));
                $sql_values = "";
                $SQL = fetch_template("dbsql/events.sql", "insert_registration_values", "events");
                foreach ($formlist as $list) {
                    $element = explode(":", $list);
                    execute_db_sql($SQL, ["regid" => $regid, "value" => $reg[$element[0]], "eventid" => $eventid, "elementname" => $element[0]]);
                }

                if ($nolimit = hard_limits($regid, $event, $template)) {
                    if (!$nolimit = soft_limits($regid, $event, $template)) {
                        $error = "Because this event has $why, you have been placed in the waiting line for this event.";
                        execute_db_sql("UPDATE events_registrations SET queue = 1 WHERE regid = ||regid||", ["regid" => $regid]);
                    }
                    return $regid; // Success
                } else {
                    if (!$nolimit) {
                        $error = "We are sorry, because this event has $why, you are unable to register for this event.";
                    } else {
                        $error = "We are sorry, there has been an error while trying to register for this event.  Please try again. ERROR CODE: 0001";
                    }
                    $params = [
                        "file" => "dbsql/events.sql",
                        "feature" => "events",
                        "subsection" => ["delete_registration", "delete_registration_values"],
                    ];
                    execute_db_sqls(fetch_template_set($params), ["regid" => $regid]);
                    log_entry("event", $event["name"], "Failed Registration", $error);
                    return false;
                }
            } else {
                $error = "We are sorry, there has been an error while trying to register for this event.  Please try again. ERROR CODE: 0002";
                log_entry("event", $event["name"], "Failed Registration", $error);
                return false;
            }
        } else { //db form style
            $sql_values = "";
            if ($elements = get_db_result("SELECT * FROM events_templates_forms WHERE template_id='" . $event['template_id'] . "' ORDER BY sort")) {
                  while ($element = fetch_row($elements)) {
                      if ($event["fee_full"] != 0 && $element["type"] == "payment") {
                          $sql_values .= $sql_values == "" ? "('$eventid','$regid','" . $element['elementid'] . "','" . $event["fee_full"] . "','total_owed'),('$eventid','$regid','" . $element['elementid'] . "','0','paid'),('$eventid','$regid','" . $element['elementid'] . "','" . $reg["payment_method"] . "','payment_method')" : ",('$eventid','$regid','" . $element['elementid'] . "','" . $event["fee_full"] . "','total_owed'),('$eventid','$regid','" . $element['elementid'] . "','0','paid'),('$eventid','$regid','" . $element['elementid'] . "','" . $reg["payment_method"] . "','payment_method')";
                      } elseif (isset($reg[$element['elementid']])) {
                        $sql_values .= $sql_values == "" ? "('$eventid','$regid','" . $element['elementid'] . "','" . $reg[$element['elementid']] . "','" . $element['display'] . "')" : ",('$eventid','$regid','" . $element['elementid'] . "','" . dbescape($reg[$element['elementid']]) . "','" . $element['display'] . "')";
                    }
                  }
            }
            $SQL = "INSERT INTO events_registrations_values (eventid,regid,elementid,value,elementname) VALUES" . $sql_values;
            if ($entries = execute_db_sql($SQL) && $nolimit = hard_limits($regid, $event, $template)) {
                if (!$nolimit = soft_limits($regid, $event, $template)) {
                    $error = "Because this event has $why, you have been placed in the waiting line for this event.";
                    execute_db_sql("UPDATE events_registrations SET queue=1 WHERE regid=" . $regid);
                }
                return $regid; //Success
            } else {
                if (!$nolimit) {
                    $error = "We are sorry, because this event has $why, you are unable to register for this event.";
                } else {
                    $error = "We are sorry, there has been an error while trying to register for this event.  Please try again. ERROR CODE: 0003";
                }
                execute_db_sql("DELETE FROM events_registrations WHERE regid='$regid'");
                execute_db_sql("DELETE FROM events_registrations_values WHERE regid='$regid'");
                log_entry("event", $event["name"], "Failed Registration", $error);
                return false;
            }
        }
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        trigger_error($e->getMessage(), E_USER_WARNING);
    }

}

function registration_email($regid, $touser, $pending=false, $waivefee=false) {
global $CFG;
    $reg = get_db_row("SELECT * FROM events_registrations WHERE regid='$regid'");
    $event = get_event($reg["eventid"]);
    $template = get_event_template($event['template_id']);

    $protocol = get_protocol();

    if (!empty($CFG->logofile)) {
        $email = '<img src="' . $protocol.$CFG->userfilesurl . '/branding/logos/' . $CFG->logofile . '" style="max-width: 80%;" /><br />';
    } else {
        $email = '<h1>' . $CFG->sitename . '</h1>';
    }

    if ($pending) {
        if (!empty($event["fee_full"])) { // This event requires payment to attend
            $total_owed = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='total_owed'");

            if (empty($total_owed)) {
                $total_owed = $reg["date"] < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
            }

            $total_owed = empty($total_owed) ? $event["fee_full"] : $total_owed;
            $paid = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='paid'");
            $paid = empty($paid) ? 0 : $paid;
              $remaining = $total_owed - $paid;

            $email .= '
                <h2>We show a PENDING registration for ' . $touser->fname . " " . $touser->lname . ' to attend ' . $event["name"] . '.</h2>
                <h2 style="color:red">This registration is NOT COMPLETED until a payment is received.</h2>
                <strong>Please keep this email for your records.  It contains a registration ID that can allow you to make payments on your registration.</strong>
                <br /><br /><h3>Total Paid: $' . number_format($paid,2) . '</h3><h3>Remaining Balance: $' . number_format($remaining,2) . '</h3><br /><em>Note:This event requires payment in full to complete the registration process.  The above balances may not reflect recent changes.</em>
                <br /><br /><strong>Registration ID:</strong><span style="color:#993300;"><strong> ' . $reg["code"] . '</strong></span>
                ' . (!empty($event["paypal"]) ? '<br /><br /><strong>Make payment online:</strong> <a href="' . $protocol.$CFG->wwwroot . '/features/events/events.php?action=pay&i=!&regcode=' . $reg["code"] . '">Make Payment</a>' : '') . '
                <br /><br /><strong>Make payment by check or money order: </strong><br />Payable to: ' . stripslashes($event['payableto']) . '<br />' . stripslashes($event['checksaddress']) . '<br />On the memo line be sure to write "' . $touser->fname . " " . $touser->lname . ' - ' . $event["name"] . '".
                <br /><br />
                If you have any questions about this event, contact ' . $event["contact"] . ' at <a href="mailto:' . $event["email"] . '">' . $event["email"] . '</a>.
                <br />
                We hope that you have enjoyed your time on the <strong>' . $CFG->sitename . ' </strong>website.
            ';
        }
    } else {
        if (!empty($event["fee_full"]) && !$waivefee) { // This event requires payment to attend
            $total_owed = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='total_owed'");

            if (empty($total_owed)) {
                $total_owed = $reg["date"] < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];
            }

            $total_owed = empty($total_owed) ? $event["fee_full"] : $total_owed;
            $paid = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='paid'");
            $paid = empty($paid) ? 0 : $paid;
              $remaining = $total_owed - $paid;

            $email .= '
                <h2>Thank you for registering ' . $touser->fname . " " . $touser->lname . ' to attend ' . $event["name"] . '.</h2>
                <strong>Please keep this email for your records.  It contains a registration ID that can allow you to make payments on your registration.</strong>
                <br /><br /><h3>Total Paid: $' . number_format($paid,2) . '</h3><h3>Remaining Balance: $' . number_format($remaining,2) . '</h3><br /><em>Note:This event requires payment in full to complete the registration process.  The above balances may not reflect recent changes.</em>
                <br /><br /><strong>Registration ID:</strong><span style="color:#993300;"><strong> ' . $reg["code"] . '</strong></span>
                ' . (!empty($event["paypal"]) ? '<br /><br /><strong>Make payment online:</strong> <a href="' . $protocol.$CFG->wwwroot . '/features/events/events.php?action=pay&i=!&regcode=' . $reg["code"] . '">Make Payment</a>' : '') . '
                <br /><br /><strong>Make payment by check or money order: </strong><br />Payable to: ' . stripslashes($event['payableto']) . '<br />' . stripslashes($event['checksaddress']) . '<br />On the memo line be sure to write "' . $touser->fname . " " . $touser->lname . ' - ' . $event["name"] . '".
                <br /><br />
                If you have any questions about this event, contact ' . $event["contact"] . ' at <a href="mailto:' . $event["email"] . '">' . $event["email"] . '</a>.
                <br />
                We hope that you have enjoyed your time on the <strong>' . $CFG->sitename . ' </strong>website.
            ';
        } else { // This event does NOT require payment
            $email .= '
                <h2>Thank you for registering ' . $touser->fname . " " . $touser->lname . ' to attend ' . $event["name"] . '.</h2>
                <strong>Please keep this email for your records.  It contains a registration ID as proof of your registration.</strong>
                <br /><br />
                <strong>Registration ID:</strong><span style="color:#993300;"><strong> ' . $reg["code"] . '</strong></span>
                <br /><br />
                If you have any questions about this event, contact ' . $event["contact"] . ' at <a href="mailto:' . $event["email"] . '">' . $event["email"] . '</a>.
                <br />
                We hope that you have enjoyed your time on the <strong>' . $CFG->sitename . ' </strong>website.
            ';
        }
    }

    return $email;
}

function get_template_field_displayname($templateid, $fieldname) {
    $template = get_event_template($templateid);
    if ($template["folder"] == "none") {
        return get_db_field("display", "events_templates_forms", "elementid='$fieldname'");
    } else {
        $fields = explode(";", $template["formlist"]);
        foreach ($fields as $f) {
            $field = explode(":", $f);
            if ($field[0] == $fieldname) {
                return $field[2];
            }
        }
    }
    return $fieldname;
}

function hard_limits($regid, $event, $template) {
global $CFG, $why;
    //If there are no custom limits in place, just return a passing grade
    if ($event["hard_limits"] == "") { return true; }
    $limits_array = explode("*", $event["hard_limits"]);
    $i = 0;
    while (isset($limits_array[$i])) {
        $limit = explode(":", $limits_array[$i]);
        if (isset($limit[2])) {
            $elementtype = $template["folder"] == "none" ? "elementid" : "elementname";
            $SQL = "SELECT * FROM events_registrations_values WHERE eventid='" . $event["eventid"] . "' AND $elementtype='" . $limit[0] . "' AND value" . make_limit_statement($limit[1], $limit[2], true);
            if (get_db_row($SQL . "AND regid='$regid'")) {
                $field_count = get_db_count($SQL);
                if ($field_count > $limit[3]) { //if registration limit is reached
                    $displayname = get_template_field_displayname($template["template_id"], $limit[0]);
                    $why = "reached the limit of " . $limit[3] . " registrations where " . $displayname . make_limit_statement($limit[1], $limit[2], false);
                    return false;
                }
            }
        }
        $i++;
    }
    return true;
}

function soft_limits($regid, $event, $template) {
global $CFG, $why;
    //If there are no custom limits in place, just return a passing grade
    if ($event["soft_limits"] == "") { return true; }
    $limits_array = explode("*", $event["soft_limits"]);
    $i = 0;
    while (isset($limits_array[$i])) {
        $limit = explode(":", $limits_array[$i]);
        if (isset($limit[2])) {
            $elementtype = $template["folder"] == "none" ? "elementid" : "elementname";
            $SQL = "SELECT * FROM events_registrations_values WHERE eventid='" . $event["eventid"] . "' AND $elementtype='" . $limit[0] . "' AND value" . make_limit_statement($limit[1], $limit[2], true);
            if (get_db_row($SQL . "AND regid='$regid'")) {
                $field_count = get_db_count($SQL);
                if ($field_count > $limit[3]) { //if registration limit is reached
                    $displayname = get_template_field_displayname($template["template_id"], $limit[0]);
                    $why = "reached the limit of " . $limit[3] . " registrations where " . $displayname . make_limit_statement($limit[1], $limit[2], false);
                    return false;
                }
            }
        }
        $i++;
    }
    return true;
}

function make_limit_statement($operator, $value, $SQLmode = false) {
    $quotes = is_numeric($value) ? "" : "'";
    if ($SQLmode) {
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
    } else {
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
function delete_event($eventid) {
    try {
        start_db_transaction();
        $event = get_event($eventid);
        if ($event) {
            $templates = [];
            $templates[] = [
                "file" => "dbsql/events.sql",
                "feature" => "events",
                "subsection" => [
                    "delete_event",
                    "delete_event_registrations",
                    "delete_events_registrations_values",
                ],
            ];
            $templates[] = [
                "file" => "dbsql/calendar.sql",
                "feature" => "calendar",
                "subsection" => [
                    "delete_calendar_events",
                ],
            ];
            $params = ["eventid" => $eventid];
            execute_db_sqls(fetch_template_set($templates), $params);

            // Delete old calendar events.
            delete_calendar_events($event);
        }
        commit_db_transaction();
        log_entry("event", $event["name"], "Deleted Event");
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        return false;
    }
    return true;
}

function refresh_calendar_events($eventid) {
    $event = get_event($eventid);
    $siteviewable = $event["confirmed"];
    $startdate = $event["event_begin_date"];
    $event_end_date = $event["event_end_date"];

    delete_calendar_events($event); //Delete old calendar events
    $caleventid = "";
    while ($startdate <= $event_end_date) {
        $caleventid .= $caleventid == "" ? "" : ":";
        date_default_timezone_set("UTC");
        $day = date('d', $startdate);
        $month = date('m', $startdate);
        $year = date('Y', $startdate);
        $event_begin_time = $event["event_begin_time"] == "NULL" ? "" : $event["event_begin_time"];
        $event_end_time = $event["event_end_time"] == "NULL" ? "" : $event["event_end_time"];
        $SQL = "INSERT INTO calendar_events (eventid,date,title,event,location,cat,starttime,endtime,day,month,year,site_viewable,groupid,pageid) VALUES('$eventid'," . $startdate.",'" .dbescape($event["name"]). "','" . dbescape($event["byline"]). "','" .$event["location"]. "','" .$event["category"]. "','" .$event_begin_time . "','" . $event_end_time . "', $day, $month, $year, $siteviewable,0," .$event["pageid"]. ")";
        $caleventid .= execute_db_sql($SQL);
        $startdate += 86400; //Advance 1 day
    }

    execute_db_sql("UPDATE events SET caleventid='$caleventid' WHERE eventid='$eventid'");
}

// Confirms a site event
function confirm_or_deny_event($eventid = false, $confirm = false) {
global $CFG, $USER;
    date_default_timezone_set("UTC");
    $event = get_event($eventid);
    $confirm = $confirm ? 1 : 0;

    $status = execute_db_sql("UPDATE events SET confirmed = ||confirmed|| WHERE eventid = ||eventid||", ["confirmed" => $confirm, "eventid" => $eventid]);

    if (!$status) {
        return false;
    }

    // Make Calendar event
    if ($confirm) {
        //Set events to confirm then refresh
        refresh_calendar_events($eventid);
        log_entry("events", $event["name"], "Confirmed Event's Visibility");
    } else { // NO to site viewability
        // Set events to confirm
        if ($event["pageid"] == $CFG->SITEID) {
            delete_calendar_events($event);
        }
        log_entry("events", $event["name"], "Denied Event's Visibility");
    }

    return true;
}

//Make sure that the calendar is edited when the event is edited.
function delete_calendar_events($event) {
    // If calendar events exist
    if (!empty($event['caleventid'])) {
        $events = explode(":", $event['caleventid']);
        foreach ($events as $id) {
            if ($id = clean_var_opt($id, "int", false)) {
                $SQL = fetch_template("dbsql/calendar.sql", "delete_calendar_event", "calendar");
                execute_db_sql($SQL, ["id" => $id]);
            }
        }
    }
}

function get_back_to_registrations_link($eventid) {
    $event = get_event($eventid);
    $eventname = $event["name"];

    return '
    <button class="dontprint alike" onclick="show_registrations(' . $eventid . ');" title="Back to ' . $eventname . ' registrations.">
        Back to ' . $eventname . ' registrations.
    </button>
    <div class="dontprint" style="padding: 10px"></div>';
}
function get_event($id) {
    return get_db_row("SELECT * FROM events WHERE eventid = ||eventid||", ["eventid" => $id]);
}

function get_event_template($id) {
    return get_db_row("SELECT * FROM events_templates WHERE template_id = ||template_id||", ["template_id" => $id]);
}

function get_event_length($event) {
    if (!empty($event['allday'])) {
        return "All day";
    }

    date_default_timezone_set(date_default_timezone_get());
    if ($event['event_begin_date'] == $event['event_end_date']) { //ONE DAY EVENT
        $length = date("n/j/Y", $event['event_begin_date']);
    } else { //Multiple Days
        $length = date("n/j/Y", $event['event_begin_date']) . " - " . date("n/j/Y", $event['event_end_date']);
    }

    if (empty($event['allday'])) {
        $length .= "<br />from ";
        $start = twelvehourtime($event['event_begin_time']);
        $finish = twelvehourtime($event['event_end_time']);
        $length .= $start . " to " . $finish;
    }
    return $length;
}

function get_templates($selected = false, $eventid = false, $activeonly = false) {
global $CFG;
    check_for_new_templates();

    $activeonly = $activeonly == true ? ' WHERE activated = 1' : '';
    $fallback = "No templates found";

    ajaxapi([
        "id" => "template" ,
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "show_template_settings",
            "templateid" => "js||$('#template').val()||js",
            "eventid" => $eventid,
        ],
        "display" => "template_settings_div",
        "event" => "change",
        "before" => "clear_limits();",
    ]);

    $templateselect = [
        "properties" => [
            "name" => 'template',
            "id" => 'template',
        ],
        "values" => get_db_result("SELECT * FROM events_templates $activeonly ORDER BY name"),
        "valuename" => "template_id",
        "selected" => $selected,
        "firstoption" => "Select a template",
        "displayname" => "name",
        "fallback" => $fallback,
    ];

    $returnme = make_select($templateselect);
    if ($returnme !== $fallback) { // There are templates.
        $returnme .= '
            <button class="alike" onclick="if ($(\'#template\').val()) {
                    window.open(\'' . $CFG->wwwroot . '/features/events/preview.php?action=preview_template&template_id=\' + $(\'#template\').val(), \'Template\', \'menubar=yes,toolbar=yes,scrollbars=1,resizable=1,width=600,height=400\');
                }">
                Preview
            </button>';
    }

    return $returnme;
}

function get_template_settings_form($templateid, $eventid = false, $globalsettings = false) {
global $CFG;
    $returnme = "";
    $settings = get_template_settings($templateid, $globalsettings);

    if (!empty($settings)) { // There are settings in this template
        $settingform = "";
        foreach ($settings as $setting) { // Save each setting with the default if no other is given
            if ($globalsettings) {
                $value = get_setting_value('events_template_global', $setting['setting_name']);
            } else {
                $value = get_setting_value('events_template', $setting['setting_name'], $eventid);
            }
            $current_setting = !empty($value) ? $value : $setting['defaultsetting'];
            $settingform .= make_setting_input($setting, NULL, $current_setting, false);
        }

        $returnme = '
        <table style="margin:0px 0px 0px 50px;min-width: 485px;">
            <tr>
                <td class="field_title" style="width:115px;text-align: center;">
                    Template Settings
                </td>
            </tr>
            <tr>
                <td>
                ' . $settingform . '
                </td>
            </tr>
        </table>';
    }
    return $returnme;
}

function get_template_settings($templateid, $globalsettings = false) {
    $templatesettings = [];
    if ($template_settings = get_db_field("settings", "events_templates", "template_id='$templateid'")) { // Template settings
        if (!empty($template_settings)) { // There are settings in this template
            $settingform = '';
            $settings = unserialize($template_settings);
            foreach ($settings as $setting) { // Save each setting with the default if no other is given
                if ($globalsettings && isset($setting["global"])) {
                    $setting["type"] = "events_template_global";
                    $setting["featureid"] = $templateid;
                    $templatesettings[] = $setting;
                } elseif (!$globalsettings && !isset($setting["global"])) {
                    $setting["type"] = "events_template";
                    $setting["featureid"] = $templateid;
                    $templatesettings[] = $setting;
                }
            }
        }
    }
    return $templatesettings;
}

function save_template_settings($template_id, $savearray) {
    // Save any event template settings if necessary
    if ($template_id > 0) { // If a template is chosen continue
        //See if it should contain settings
        $settings = get_template_settings($template_id);
        if (!empty($settings)) { // There are settings in this template
            foreach ($settings as $setting) { // Save each setting with the default if no other is given
                if (isset($setting["global"])) {
                    $current_setting = isset($savearray[$setting['setting_name']]) ? $savearray[$setting['setting_name']] : $setting['defaultsetting'];
                    $info = [
                        "type" => "events_template_global",
                        "featureid" => $template_id,
                        "setting_name" => $setting['setting_name'],
                        "defaultsetting" => $setting['defaultsetting'],
                    ];
                    save_setting(false, $info, $current_setting, $savearray['eventid']);
                } else {
                    $current_setting = isset($savearray[$setting['setting_name']]) ? $savearray[$setting['setting_name']] : $setting['defaultsetting'];
                    $info = [
                        "insert" => true, // Always inserting because all settings will be deleted first.
                        "type" => "events_template",
                        "setting_name" => $setting['setting_name'],
                        "defaultsetting" => $setting['defaultsetting'],
                    ];
                    save_setting(false, $info, $current_setting, $savearray['eventid']);
                }
            }
        }
    }
}

function get_possible_times($formid, $selected_time = false, $start_time = false) {
    $selected_time ??= false;
    $start_time ??= false;

    $times = ["00:00*12:00 am", "00:30*12:30 am", "01:00*01:00 am", "01:30*01:30 am", "02:00*02:00 am", "02:30*02:30 am", "03:00*03:00 am", "03:30*03:30 am", "04:00*04:00 am", "04:30*04:30 am", "05:00*05:00 am", "05:30*05:30 am", "06:00*06:00 am", "06:30*06:30 am", "07:00*07:00 am", "07:30*07:30 am", "08:00*08:00 am", "08:30*08:30 am", "09:00*09:00 am", "09:30*09:30 am", "10:00*10:00 am", "10:30*10:30 am", "11:00*11:00 am", "11:30*11:30 am", "12:00*12:00 pm", "12:30*12:30 pm", "13:00*01:00 pm", "13:30*01:30 pm", "14:00*02:00 pm", "14:30*02:30 pm", "15:00*03:00 pm", "15:30*03:30 pm", "16:00*04:00 pm", "16:30*04:30 pm", "17:00*05:00 pm", "17:30*05:30 pm", "18:00*06:00 pm", "18:30*06:30 pm", "19:00*07:00 pm", "19:30*07:30 pm", "20:00*08:00 pm", "20:30*08:30 pm", "21:00*09:00 pm", "21:30*09:30 pm", "22:00*10:00 pm", "22:30*10:30 pm", "23:00*11:00 pm", "23:30*11:30 pm"];
    $onchange = $formid == 'begin_time' ? 'onchange="get_end_time();"' : '';
    $to = $formid == 'begin_time' ? '<div style="font-size:.75em; color:green;">From </div>' : '<div style="font-size:.75em; color:green;">&nbsp; To </div>';
    $returnme = $to . '<select id="' . $formid . '" name="' . $formid . '" ' . $onchange . '><option></option>';
    $i = 0;
    $from = false;
    while (isset($times[$i])) {
        $time = explode("*", $times[$i]);
        if ($start_time && $from) {
            if ($selected_time && strstr($time[0], $selected_time)) {
                $returnme .= '<option value="' . $time[0] . '" selected>' . $time[1] . '</option>';
            } else {
                $returnme .= '<option value="' . $time[0] . '">' . $time[1] . '</option>';
            }
        }
        if (!$start_time) {
            if ($selected_time && strstr($time[0], $selected_time)) {
                $returnme .= '<option value="' . $time[0] . '" selected>' . $time[1] . '</option>';
            } else {
                $returnme .= '<option value="' . $time[0] . '">' . $time[1] . '</option>';
            }
        }

        $from = strstr($time[0], $start_time) ? true : $from;
        $i++;
    }
    $returnme .= '</select>';
    return $returnme;
}

function get_my_locations($userid, $selected = false, $eventid = false) {
    $returnme = "";
    $union_statement = $eventid ? " UNION SELECT * FROM events_locations WHERE id IN (SELECT location FROM events WHERE eventid=$eventid)" : "";
    $SQL = "SELECT * FROM events_locations WHERE userid LIKE '%,$userid,%' $union_statement GROUP BY id ORDER BY location";

    if ($locations = get_db_result($SQL)) {
        while ($location = fetch_row($locations)) {
            $returnme .= $returnme == "" ? '<select id="location" name="location">' : '';
            $selectme = $selected && ($location['id'] == $selected) ? ' selected' : '';
            $returnme .= '<option value="' . $location['id'] . '"' . $selectme . '>' . stripslashes($location['location']) . '</option>';
        }
    }
    $returnme .= $returnme == "" ? "You must add a location." : "</select>";
    return $returnme;
}

function delete_limit_button($type, $num) {
    return '
        <button style="padding: 0 5px;" class="alike" onclick="delete_limit(\'' . $type . '\', ' . $num . ');">
            ' . icon("trash") . '
        </button>';
}

function get_my_hidden_limits($templateid, $hard_limits, $soft_limits) {
    $returnme = "";
    if (empty($templateid)) { return $returnme; }
    $hidden_variable1 = $hidden_variable2 = "";

    if (!empty($hard_limits)) { // There are some hard limits
        $limits_array = explode("*", $hard_limits);
        $i = 0;
        $returnme .= "<br /><strong>Hard Limits</strong> <br />";
        $hidden_variable1 = "";
        while (!empty($limits_array[$i])) {
            $limit = explode(":", $limits_array[$i]);
            if (!empty($limit)) {
                $displayname = get_template_field_displayname($templateid, $limit[0]);
                $returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . delete_limit_button("hard_limits", $i) . '<br />';
                $hidden_variable1 .= $hidden_variable1 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
            }
            $i++;
        }
    }

    if (!empty($soft_limits)) { // There are some soft limits
        $limits_array = explode("*", $soft_limits);
        $i = 0;
        $returnme .= "<br /><strong>Soft Limits</strong> <br />";
        $hidden_variable2 = "";
        while (isset($limits_array[$i])) {
            $limit = explode(":", $limits_array[$i]);
            if (!empty($limit)) {
                $displayname = get_template_field_displayname($templateid, $limit[0]);
                $returnme .= $limit[3] . " Record(s) where $displayname " . make_limit_statement($limit[1], $limit[2], false) . delete_limit_button("soft_limits", $i) . '<br />';
                $hidden_variable2 .= $hidden_variable2 == "" ? $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3] : "*" . $limit[0] . ":" . $limit[1] . ":" . $limit[2] . ":" . $limit[3];
            }
            $i++;
        }
    }
    return $returnme . '<input type="hidden" id="hard_limits" name="hard_limits" value="' . $hidden_variable1 . '" /><input type="hidden" id="soft_limits" name="soft_limits" value="' . $hidden_variable2 . '" />';
}

function get_my_category($selected = false) {
    $returnme = "";
    if ($categories = get_db_result("SELECT * FROM calendar_cat ORDER BY cat_id")) {
        while ($category = fetch_row($categories)) {
            $returnme .= $returnme == "" ? '<select id="category" name="category">' : '';
            $selectme = $selected && ($category['cat_id'] == $selected) ? ' selected' : '';
            $returnme .= '
                <option value="' . $category['cat_id'] . '"' . $selectme . '>' .
                    stripslashes($category['cat_name']) .
                '</option>';
        }
    }
    $returnme .= $returnme == "" ? "No categories exist." : "</select>";
    return $returnme;
}

function staff_status($staff, $userid = true) {
    $status = [];

    if ($userid) {
        $pageid = $_SESSION["pageid"];
        $featureid = "*";
        if (!$settings = fetch_settings("events", $featureid, $pageid)) {
            save_batch_settings(default_settings("events", $pageid, $featureid));
            $settings = fetch_settings("events", $featureid, $pageid);
        }

        if (!$staff) { // User exists but no staff history.
            return [
                [
                    "tag" => "Application",
                    "full" => "Application Incomplete",
                ],
                [
                    "tag" => "Background Check",
                    "full" => "Background Check Incomplete",
                ],
            ];
        }

        if ($staff["workerconsentdate"] < strtotime($settings->events->$featureid->staffapp_expires->setting . '/' . date('Y'))) {
            $status[] = [
                "tag" => "Application",
                "full" => "Application Out of Date",
            ];
        }

        $flag = $staff["q1_1"] + $staff["q1_2"] + $staff["q1_3"] + $staff["q2_1"] + $staff["q2_2"];
        if (!empty($flag)) {
            $status[] = [
                "tag" => "Flagged",
                "full" => "Flagged for review!",
            ];
        }

        $eighteen = 18 * 365 * 24 * 60 * 60; // 18 years in seconds
        $expireyear = $settings->events->$featureid->bgcheck_years->setting * 365 * 24 * 60 * 60;
        $time = get_timestamp();
        if (($time - $staff["dateofbirth"]) > $eighteen ) {
            if (empty($staff["bgcheckpass"])) {
                $status[] =  [
                    "tag" => "Background Check",
                    "full" => "Background Check Incomplete",
                ];
            } else if (($time - $staff["bgcheckpassdate"]) > $expireyear) {
                $status[] =  [
                    "tag" => "Background Check",
                    "full" => "Background Check Out of Date",
                ];
            }
        }
    } else {
        $status[] = ["tag" => "Account", "full" => "No account"];
        $status[] = ["tag" => "Application", "full" => "Application Incomplete"];
        $status[] = ["tag" => "Background Check", "full" => "Background Check Incomplete"];
    }

    return $status;
}

function print_status($status) {
global $CFG;
    $protocol = get_protocol();
    $print = '';
    if (!empty($status)) {
        foreach ($status as $s) {
            $print .= '
                <div class="staff_status_alert">
                    ' . icon("circle-exclamation") . ' ' . $s["full"] . '
                </div>';
        }
    } else {
        $print = '
            <div class="staff_status_approved">
                ' . icon("circle-check") . ' APPROVED
            </div>';
    }
    return $print;
}

function staff_application_form($row, $viewonly = false) {
global $USER, $CFG, $MYVARS;
    $v["staffid"] = empty($row) ? false : $row["staffid"];
    $v["name"] = empty($row) ? $USER->fname . " " . $USER->lname : $row["name"];
    $v["phone"] = empty($row) ? "" : $row["phone"];
    $v["dateofbirth"] = empty($row) ? "" : (isset($row['dateofbirth']) ? date('m/d/Y', $row['dateofbirth']) : '');
    $v["address"] = empty($row) ? "" : $row["address"];
    $v["ar1selected"] = "";
    $v["ar2selected"] = "";
    $v["ar3selected"] = "";
    if (!empty($row)) {
        if ($viewonly || empty($v["dateofbirth"])) {
            if ($row["agerange"] == "0") {
                $v["ar1selected"] = "selected";
            } elseif ($row["agerange"] == "1") {
                $v["ar2selected"] = "selected";
            } elseif ($row["agerange"] == "2") {
                $v["ar3selected"] = "selected";
            }
        } else {
            $time = get_timestamp();
            if (($time - $row['dateofbirth']) < (18 * 365 * 24 * 60 * 60)) { // Under 18
                $v["ar1selected"] = "selected";
            } elseif ((($time - $row['dateofbirth']) < (25 * 365 * 24 * 60 * 60))) { // Over 18 under 25
                $v["ar2selected"] = "selected";
            } else {
                $v["ar3selected"] = "selected";
            }
        }
    }
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

    $v["sub18display"] = empty($v["ar1selected"]) ? "display:none" : "";
    $v["workerconsent"] = empty($row) ? "" : $row["workerconsent"];
    $v["workerconsentsig"] = empty($row) ? "" : ($row["workerconsentsig"] == "on" && $viewonly  ? "checked" : "");
    $v["workerconsentdate"] = empty($row) ? date('m/d/Y') : (!empty($row['workerconsentdate']) && $viewonly ? date('m/d/Y', $row['workerconsentdate']) : date('m/d/Y'));

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
            <div style="text-align:center">
            <h2>' . (!$viewonly ? 'Staff Application' : $v["name"] . ' Application') . '</h2>
            <span style="font-weight:bold;font-size:.9em">If you are not ' . $v["name"] . ', please sign into your own account.</span>
            </div><br />
              <form name="staffapplication_form" id="staffapplication_form">
                ' . (empty($v["staffid"]) ? '' : '<input type="hidden" id="staffid" name="staffid" value="' . $v["staffid"] . '" />') . '
                  <fieldset class="formContainer" ' . ($viewonly ? '' : 'style="width: 420px;margin-left: auto;margin-right: auto;"') . '>
                    <div class="rowContainer">
                          <label class="rowTitle" for="name">Name</label>
                        <input disabled="disabled" type="text" id="name" name="name" value="' . $v["name"] . '"
                            data-rule-required="true"
                            data-msg-required="' . error_string('valid_staff_name:events') . '"
                        /><div class="tooltipContainer info">' . get_help("input_staff_name:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                    </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="dateofbirth">Date of Birth</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text"
                            id="dateofbirth"
                            name="dateofbirth"
                            value="' . $v["dateofbirth"] . '"
                            data-rule-required="true"
                            data-rule-custom="^[0-9]{2}\/[0-9]{2}\/[0-9]{4}$"
                            data-msg-custom="' . error_string('valid_staff_dateformat:events') . '"
                            data-rule-date="true"
                            onblur="
                                var d = new Date($(this).val()).getTime() / 1000;
                                if (' . time() . ' - d < 567648000) {
                                    $(\'#agerange\').val(0);
                                } else if (' . time() . ' - d < 788400000) {
                                    $(\'#agerange\').val(1);
                                } else {
                                    $(\'#agerange\').val(2);
                                }
                                $(\'#agerange\').change();
                            "
                        /><div class="tooltipContainer info">' . get_help("input_staff_dob:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="phone">Phone</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="phone" name="phone" value="' . $v["phone"] . '"
                            data-rule-required="true"
                            data-rule-phone="true"
                            data-msg-required="' . error_string('valid_staff_phone:events') . '"
                            data-msg-phone="' . error_string('valid_staff_phone_invalid:events') . '"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_phone:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                    </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="address">Address</label>
                        <textarea ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            rows="3" id="address" name="address"
                            data-rule-required="true"
                        >' . $v["address"] . '</textarea>
                        <div class="tooltipContainer info">' . get_help("input_staff_address:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="agerange">Age Range</label>
                        <select ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            id="agerange"
                            name="agerange"
                            data-rule-required="true"
                            onchange="
                                if ($(this).val() != 0) {
                                    $(\'#sub18\').hide();
                                    $(\'#parentalconsent\').val(\'\');
                                    $(\'#parentalconsent\').removeData(\'rule-required\').removeAttr(\'data-rule-required\');
                                    $(\'#parentalconsentsig\').prop(\'checked\', false);
                                    $(\'#parentalconsentsig\').removeData(\'rule-required\').removeAttr(\'data-rule-required\');
                                }
                                if ($(this).val() == 0) {
                                    $(\'#parentalconsent\').val(\'\');
                                    $(\'#parentalconsentsig\').prop(\'checked\', false);
                                    $(\'#parentalconsent\').removeData(\'rule-required\').attr(\'data-rule-required\',\'true\');
                                    $(\'#sub18\').show();
                                }"
                        >
                            <option>Please select</option>
                            <option value="0" ' . $v["ar1selected"] . '>younger than 18</option>
                            <option value="1" ' . $v["ar2selected"] . '>18-25</option>
                            <option value="2" ' . $v["ar3selected"] . '>26 or older</option>
                        </select><div class="tooltipContainer info">' . get_help("input_staff_agerange:events") . '</div><br />
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="cocmember">Are you a member of the church of Christ?</label>
                        <select ' . ($viewonly ? 'disabled="disabled"' : '') . ' style="width:80px" id="cocmember" name="cocmember"
                            data-rule-required="true"
                        >
                            <option value="0" ' . $v["cocmembernoselected"] . '>No</option>
                            <option value="1" ' . $v["cocmemberyesselected"] . '>Yes</option>
                        </select>
                        <div class="tooltipContainer info">' . get_help("input_staff_cocmember:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="congregation">Congregation Name</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . ' type="text" id="congregation" name="congregation" value="' . $v["congregation"] . '"
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_congregation:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="priorwork">Have you worked at Camp Wabashi as a staff member before?</label>
                        <select ' . ($viewonly ? 'disabled="disabled"' : '') . ' style="width:80px" id="priorwork" name="priorwork"
                            data-rule-required="true"
                        >
                            <option value="0" ' . $v["priorworknoselected"] . '>No</option>
                            <option value="1" ' . $v["priorworkyesselected"] . '>Yes</option>
                        </select>
                        <div class="tooltipContainer info">' . get_help("input_staff_priorwork:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <br /><hr><br />
                    <h3>Have you at any time ever:</h3>
                    <div class="rowContainer">
                          <label class="rowTitle" for="q1_1">Been arrested for any reason?</label>
                        <select ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            onchange="
                                if (($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0) {
                                    $(\'#q2_3\').attr(\'data-rule-required\', \'true\');
                                } else {
                                    $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\');
                                }
                            "
                            style="width:80px" id="q1_1" name="q1_1"
                            data-rule-required="true"
                        >
                            <option value="0" ' . $v["q1_1noselected"] . '>No</option>
                            <option value="1" ' . $v["q1_1yesselected"] . '>Yes</option>
                        </select>
                        <div class="tooltipContainer info">' . get_help("input_staff_q1_1:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="q1_2">Been convicted of, or pleaded guilty or no contest to, any crime?</label>
                        <select ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            onchange="
                                if (($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0) {
                                    $(\'#q2_3\').attr(\'data-rule-required\', \'true\');
                                } else {
                                    $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\');
                                }
                            "
                            style="width:80px" id="q1_2" name="q1_2"
                            data-rule-required="true"
                        >
                            <option value="0" ' . $v["q1_2noselected"] . '>No</option>
                            <option value="1" ' . $v["q1_2yesselected"] . '>Yes</option>
                        </select>
                        <div class="tooltipContainer info">' . get_help("input_staff_q1_2:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="q1_3">Engaged in, or been accused of, any child molestation, exploitation, or abuse?</label>
                        <select ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            onchange="
                                if (($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0) {
                                    $(\'#q2_3\').attr(\'data-rule-required\', \'true\');
                                } else {
                                    $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\');
                                }"
                            style="width:80px" id="q1_3" name="q1_3"
                            data-rule-required="true"
                        >
                            <option value="0" ' . $v["q1_3noselected"] . '>No</option>
                            <option value="1" ' . $v["q1_3yesselected"] . '>Yes</option>
                        </select>
                        <div class="tooltipContainer info">' . get_help("input_staff_q1_3:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <br /><hr><br />
                    <h3>Are you aware of:</h3>
                    <div class="rowContainer">
                          <label class="rowTitle" for="q2_1">Having any traits or tendencies that could pose any threat to children, youth, or others?</label>
                        <select ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            onchange="
                                if (($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0) {
                                    $(\'#q2_3\').attr(\'data-rule-required\', \'true\');
                                } else {
                                    $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\');
                                }
                            "
                            style="width:80px" id="q2_1" name="q2_1"
                            data-rule-required="true"
                        >
                            <option value="0" ' . $v["q2_1noselected"] . '>No</option>
                            <option value="1" ' . $v["q2_1yesselected"] . '>Yes</option>
                        </select>
                        <div class="tooltipContainer info">' . get_help("input_staff_q2_1:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="q2_2">Any reason why you should not work with children, youth, or others?</label>
                        <select ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            onchange="
                                if (($(\'#q1_1\').val() + $(\'#q1_2\').val() + $(\'#q1_3\').val() + $(\'#q2_1\').val() + $(\'#q2_2\').val()) > 0) {
                                    $(\'#q2_3\').attr(\'data-rule-required\', \'true\');
                                } else {
                                    $(\'#q2_3\').removeData(\'rule-required\').removeAttr(\'data-rule-required\');
                                }
                            "
                            style="width:80px" id="q2_2" name="q2_2"
                            data-rule-required="true"
                        >
                            <option value="0" ' . $v["q2_2noselected"] . '>No</option>
                            <option value="1" ' . $v["q2_2yesselected"] . '>Yes</option>
                        </select>
                        <div class="tooltipContainer info">' . get_help("input_staff_q2_2:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="q2_3">If the answer to any of these questions is "Yes", please explain in detail</label>
                            <textarea ' . ($viewonly ? 'disabled="disabled"' : '') . '
                                rows="3" id="q2_3" name="q2_3"
                                ' . (empty($v["yestotal"]) ? '' : 'data-rule-required="true"') . '
                            >' . $v["q2_3"] . '</textarea>
                            <div class="tooltipContainer info">' . get_help("input_staff_q1_3:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                          </div>
                    ' . ($viewonly ? '<div style="text-align:center"><h2>' . $v["name"] . ' References</h2></div>' : '<br /><hr><br />') . '
                    <h3>References #1</h3><br />
                          <br />
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref1name">Name</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref1name" name="ref1name" value="' . $v["ref1name"] . '"
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_refname:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref1relationship">Relationship</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref1relationship" name="ref1relationship" value="' . $v["ref1relationship"] . '"
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_refrelationship:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref1phone">Phone</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref1phone" name="ref1phone" value="' . $v["ref1phone"] . '"
                            data-rule-required="true"
                            data-rule-phone="true"
                            data-msg-required="' . error_string('valid_staff_phone:events') . '"
                            data-msg-phone="' . error_string('valid_staff_phone_invalid:events') . '"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_phone:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                    </div>
                    <br /><hr><br />
                    <h3>References #2</h3><br />
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref2name">Name</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref2name" name="ref2name" value="' . $v["ref2name"] . '"
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_refname:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref2relationship">Relationship</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref2relationship" name="ref2relationship" value="' . $v["ref2relationship"] . '"
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_refrelationship:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref2phone">Phone</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref2phone" name="ref2phone" value="' . $v["ref2phone"] . '"
                            data-rule-required="true"
                            data-rule-phone="true"
                            data-msg-required="' . error_string('valid_staff_phone:events') . '"
                            data-msg-phone="' . error_string('valid_staff_phone_invalid:events') . '"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_phone:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                    </div>
                    <br /><hr><br />
                    <h3>References #3</h3><br />
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref3name">Name</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref3name" name="ref3name" value="' . $v["ref3name"] . '"
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_refname:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref3relationship">Relationship</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref3relationship" name="ref3relationship" value="' . $v["ref3relationship"] . '"
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_refrelationship:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="ref3phone">Phone</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="ref3phone" name="ref3phone" value="' . $v["ref3phone"] . '"
                            data-rule-required="true"
                            data-rule-phone="true"
                            data-msg-required="' . error_string('valid_staff_phone:events') . '"
                            data-msg-phone="' . error_string('valid_staff_phone_invalid:events') . '"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_phone:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
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
                          <label class="rowTitle" for="workerconsent">Full Name</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="workerconsent" name="workerconsent" value="' . $v["workerconsent"] . '"
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_workerconsent:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div class="rowContainer">
                          <label class="rowTitle" for="workerconsentdate">Date</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="text" id="workerconsentdate" name="workerconsentdate" value="' . $v["workerconsentdate"] . '"
                            data-rule-required="true"
                            data-rule-custom="^[0-9]{2}\/[0-9]{2}\/[0-9]{4}$"
                            data-msg-custom="' . error_string('valid_staff_dateformat:events') . '"
                            data-rule-date="true"
                            disabled="disabled"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_workerconsentdate:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div>
                        <strong>You should understand that the name field and signature field have the same legal effect and can be enforced in the same way as a written signature.</strong>
                    </div>
                    <br />
                    <div class="rowContainer">
                          <label class="rowTitle" for="workerconsentsig">Signature</label>
                        <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                            type="checkbox" id="workerconsentsig" name="workerconsentsig" ' . $v["workerconsentsig"] . '
                            data-rule-required="true"
                        />
                        <div class="tooltipContainer info">' . get_help("input_staff_workerconsentsig:events") . '</div>
                            <div class="spacer" style="clear: both;"></div>
                      </div>
                    <div id="sub18" style="' . $v["sub18display"] . '">
                        <br /><hr><br />
                            <div style="background:#FFED00;padding:5px;">
                            <strong>If you are under 18, please have a parent or guardian affirm to the following:</strong><br />
                            <em>I swear and affirm that I am not aware of any traits or tendencies of the applicant that could pose a threat to children, youth or others and that I am not aware of any reasons why the applicant should not work with children, youth, or others.</em>
                                  <br /><br />
                            <div class="rowContainer">
                                  <label class="rowTitle" for="parentalconsent">Parent or Gurdian Full Name</label>
                                <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                                    type="text" id="parentalconsent" name="parentalconsent" value="' . $v["parentalconsent"] . '"
                                    ' . (empty($v["ar1selected"]) ? '' : 'data-rule-required="true"') . '
                                />
                                <div class="tooltipContainer info">' . get_help("input_staff_parentalconsent:events") . '</div>
                              </div>
                            <div>
                                <strong>You should understand that the name field and signature field have the same legal effect and can be enforced in the same way as a written signature.</strong>
                            </div>
                            <br />
                            <div class="rowContainer">
                                  <label class="rowTitle" for="parentalconsentsig">Parent or Guardian Signature</label>
                                <input ' . ($viewonly ? 'disabled="disabled"' : '') . '
                                    type="checkbox" id="parentalconsentsig" name="parentalconsentsig"
                                    ' . $v["parentalconsentsig"] . '
                                    ' . (empty($v["ar1selected"]) ? '' : 'data-rule-required="true"') . '
                                />
                                <div class="tooltipContainer info">' . get_help("input_staff_parentalconsentsig:events") . '</div>
                                    <div class="spacer" style="clear: both;"></div>
                              </div>
                        </div>
                    </div>
                    ' . ($viewonly ? '' : '<input class="submit" name="submit" type="submit" onmouseover="this.focus();" value="Submit Application" />') . '
                  </fieldset>
              </form>
            ' . keepalive() . '
          </div>';
}

function get_hint_box($hintstring) {
    return '
        <span class="hint">
            <span class="hint-pointer">&nbsp;</span>
            ' . get_help($hintstring) . '
        </span>';
}

function new_location_form($eventid) {
    return '
    <div id="new_event_location_form">
        <table>
            <tr>
                <td class="field_title">
                    Name:
                </td>
                <td class="field_input">
                    <input type="text" id="location_name" name="name" />
                    ' . get_hint_box("input_location_name:events") . '
                </td>
            </tr><tr><td></td><td class="field_input"><span id="location_name_error" class="error_text"></span></td></tr>
            <tr>
                <td class="field_title">
                    Address:
                </td>
                <td class="field_input">
                    <input type="text" id="location_address_1" name="add1" />
                    ' . get_hint_box("input_location_add1:events") . '
                </td>
            </tr><tr><td></td><td class="field_input"><span id="location_address_1_error" class="error_text"></span></td></tr>
            <tr>
                <td class="field_title">
                    City, State
                </td>
                <td class="field_input">
                    <input type="text" id="location_address_2" name="add2" />
                    ' . get_hint_box("input_location_add2:events") . '
                </td>
            </tr><tr><td></td><td class="field_input"><span id="location_address_2_error" class="error_text"></span></td></tr>
            <tr>
                <td class="field_title">
                    Zipcode:
                </td>
                <td class="field_input">
                    <input type="text" id="zip" name="zip" size="7" maxlength="5" />
                    ' . get_hint_box("input_location_zip:events") . '
                </td>
            </tr><tr><td></td><td class="field_input"><span id="zip_error" class="error_text"></span></td></tr>
            <tr>
                <td class="field_title">
                    Share Location:
                </td>
                <td class="field_input">
                    <input type="checkbox" id="shared" name="shared" />
                    ' . get_hint_box("input_location_share:events") . '
                </td>
            </tr>
            <tr>
                <td class="field_title">
                    <span style="font-size:1.2em; color:blue;">(optional)</span> Phone:
                </td>
                <td class="field_input">
                    ' . create_form_element("phone", "location_phone", "1") . '
                </td>
            </tr><tr><td></td><td class="field_input"><span id="location_phone_error" class="error_text"></span></td></tr>
            <tr>
                <td class="field_title"></td>
                <td class="field_input">
                    <button id="new_event_location_submit">
                        Submit
                    </button>
                </td>
            </tr>
        </table>
    </div>';
}

function location_list_form($eventid) {
global $USER;
    $locations = get_db_result("SELECT *
                                FROM events_locations
                                WHERE shared = 1
                                AND userid NOT LIKE '%," . $USER->userid . ",%' ORDER BY location");
    $locationsexist = false;

    if (!$locations) {
        return "No other addable locations.";
    }

    $options = '<option value="0">Select a shared location</option>';
    while ($location = fetch_row($locations)) {
        $options .= '<option value="' . $location['id'] . '">' . $location['location'] . '</option>';
    }
    $returnme = '
        <div style="display: inline-flex;align-items: center;">
            <select id="add_location" onchange="get_location_details()" style="margin: 10px;">
                ' . $options . '
            </select>
            <button class="alike" title="Add Location" onclick="copy_location($(\'#add_location\').val(), \'' . $eventid . '\');">
                ' . icon("plus", 2) . '
            </button>
        </div>
        <div id="location_details_div" style="vertical-align:top"></div>';

    return $returnme;
}

function events_delete($pageid, $featureid) {
    $params = [
        "pageid" => $pageid,
        "featureid" => $featureid,
        "feature" => "events",
    ];

    try {
        start_db_transaction();
        $sql = [];
        $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature"];
        $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature_settings"];

        // Delete feature
        execute_db_sqls(fetch_template_set($sql), $params);

        resort_page_features($pageid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        return false;
    }
}

function create_form_element($type, $id, $value, $length = 0) {
    switch ($type) {
        case "text":
            $maxlength = $length > 0 ? ' maxlength="' . $length . '"': "";
            $returnme = '<input type="hidden" id="opt_' . $id . '" name="opt_' . $id . '" value="' . $value . '" /><input size="25" type="text" id="' . $id . '" name="' . $id . '" ' . $maxlength . ' />';
            break;
        case "email":
            $maxlength = $length > 0 ? ' maxlength="' . $length . '"': "";
            $returnme = '<input type="hidden" id="opt_' . $id . '" name="opt_' . $id . '" value="' . $value . '" /><input size="25" type="text" id="' . $id . '" name="' . $id . '" ' . $maxlength . ' />';
            break;
        case "contact":
            $maxlength = $length > 0 ? ' maxlength="' . $length . '"': "";
            $returnme = '<input type="hidden" id="opt_' . $id . '" name="opt_' . $id . '" value="0" /><input size="25" type="text" id="' . $id . '" name="' . $id . '" ' . $maxlength . ' />';
            break;
        case "phone":
            $value = is_array($value) ? $value : explode("-", $value);
            if (count($value) < 3) {
                $value = [0, 0, 0];
            }

            $returnme = '
                <input class="phone1" type="text" id="' . $id . '_1" name="' . $id . '_1" value="' . $value[0] . '" maxlength="3" size="1" onkeyup="movetonextbox(event);" />
                -
                <input class="phone2" type="text" id="' . $id . '_2" name="' . $id . '_2" value="' . $value[1] . '" size="1" maxlength="3" onkeyup="movetonextbox(event);" />
                -
                <input class="phone3" type="text" id="' . $id . '_3" name="' . $id . '_3" value="' . $value[2] . '" size="2" maxlength="4" />';
            break;
        case "select":
            echo "i equals 2";
            break;
    }
    return $returnme;
}

function check_for_new_templates() {
global $CFG, $USER;
    $startdir = $CFG->dirroot . "/features/events/templates/";
    $ignoredDirectory[] = '.';
    $ignoredDirectory[] = '..';
    if (is_dir($startdir)) {
        if ($dh = opendir($startdir)) {
            while (($folder = readdir($dh)) !== false) {
                if (!(array_search($folder, $ignoredDirectory) > -1)) {
                    if (filetype($startdir . $folder) == "dir") {
                        $directorylist[$startdir . $folder]['name'] = $folder;
                        $directorylist[$startdir . $folder]['path'] = $startdir;
                    }
                }
            }
            closedir($dh);
        }
    }
    if (isset($directorylist)) {
          foreach ($directorylist as $folder) {
              $name = $folder['name'];
              include ($CFG->dirroot . "/features/events/templates/$name/install.php");
          }
    }
}

function get_events_admin_contacts() {
    $script = '
        function fill_admin_contacts(values) {
            values = values.split(": ");
            document.getElementById("contact").value = values[0];
            document.getElementById("email").value = values[1];
            var phone = values[2].split("-");
            document.getElementById("phone_1").value = phone[0];
            document.getElementById("phone_2").value = phone[1];
            document.getElementById("phone_3").value = phone[2];
        }';

    $p = [
        "properties" => [
            "name" => "admin_contacts",
            "id" => "admin_contacts",
            "onchange" => 'fill_admin_contacts(this.value);',
        ],
        "values" => get_db_result(fetch_template("dbsql/events.sql", "get_contacts_list", "events")),
        "valuename" => "admin_contact",
        "firstoption" => "",
    ];

    return js_code_wrap($script) . '<br /><table style="width:100%">
          <tr>
              <td class="field_title" style="width:115px;">
                  Contacts List:
              </td>
              <td class="field_input">
                  ' . make_select($p) . '
              </td>
          </tr><tr><td></td><td class="field_input"><span id="contact_error" class="error_text"></span></td></tr>
    </table>';
}

function get_events_admin_payable() {
    $script = '
        function fill_admin_payable(values) {
            values = values.split(": ");
            $("#payableto").val(values[0]);
            $("#checksaddress").val(values[1]);
            $("#paypal").val(values[2]);
        }';

    $p = [
        "properties" => [
            "name" => "admin_contacts",
            "id" => "admin_contacts",
            "onchange" => 'fill_admin_payable(this.value);',
        ],
        "values" => get_db_result(fetch_template("dbsql/events.sql", "get_payable_list", "events")),
        "valuename" => "admin_contact",
        "firstoption" => "",
    ];

    return js_code_wrap($script) . '<br /><table style="width:100%">
          <tr>
              <td class="field_title" style="width:115px;">
                  Payable List:
              </td>
              <td class="field_input">
                  ' . make_select($p) . '
              </td>
          </tr><tr><td></td><td class="field_input"><span id="contact_error" class="error_text"></span></td></tr>
    </table>';
}

function events_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
    $returnme = "";
    if (user_is_able($USER->userid, "addevents", $pageid, $featuretype, $featureid)) {
        $returnme .= make_modal_links([
                        "title" => "Add Event",
                        "path" => action_path("events") . "add_event_form&pageid=$pageid",
                        "refresh" => "true",
                        "iframe" => true,
                        "width" => "95%",
                        "height" => "95%",
                        "icon" => icon("plus"),
                        "class" => "slide_menu_button",
                    ]);
    }
    return $returnme;
}

function events_default_settings($type, $pageid, $featureid) {
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "Events",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "setting_name" => "upcomingdays",
            "defaultsetting" => "30",
            "display" => "Show Upcoming Events (days)",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
        [
            "setting_name" => "recentdays",
            "defaultsetting" => "5",
            "display" => "Recent Events (days)",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
        [
            "setting_name" => "archivedays",
            "defaultsetting" => "30",
            "display" => "Admin Recent Events (days)",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
        [
            "setting_name" => "showpastevents",
            "defaultsetting" => "1",
            "display" => "Show Past Events",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "allowrequests",
            "defaultsetting" => "0",
            "display" => "Allow Location Reservations",
            "inputtype" => "select",
            "extraforminfo" => "SELECT id as selectvalue, location as selectname
                                FROM events_locations
                                WHERE shared = 1",
            "numeric" => null,
            "validation" => null,
            "warning" => "Select a location to allow event requests on.",
        ],
        [
            "setting_name" => "emaillistconfirm",
            "defaultsetting" => "",
            "display" => "Request Email List",
            "inputtype" => "textarea",
            "extraforminfo" => "3",
        ],
        [
            "setting_name" => "requestapprovalvotes",
            "defaultsetting" => "1",
            "display" => "Approval Votes Required",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.  Should be less than or equal to the amount of email addresses.",
        ],
        [
            "setting_name" => "requestdenyvotes",
            "defaultsetting" => "1",
            "display" => "Denial Votes Required",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.  Should be less than or equal to the amount of email addresses.",
        ],
        [
            "setting_name" => "request_text",
            "defaultsetting" => "",
            "display" => "Request Form Text",
            "inputtype" => "textarea",
            "extraforminfo" => "3",
        ],
        [
            "setting_name" => "bgcheck_url",
            "defaultsetting" => "",
            "display" => "Background Check URL",
            "inputtype" => "textarea",
            "extraforminfo" => "3",
        ],
        [
            "setting_name" => "bgcheck_years",
            "defaultsetting" => "5",
            "display" => "Background Check Expires (years)",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
        [
            "setting_name" => "staffapp_expires",
            "defaultsetting" => "1/1",
            "display" => "Staff Application Expires (day/month)",
            "inputtype" => "text",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}

function facebook_share_button($event, $name) {
global $CFG;
    $keys = [];

    // Check for global settings in template.
    $templateid = $event["template_id"];
    $global_settings = fetch_settings("events_template_global", $templateid);
    $facebookappid = $global_settings->events_template_global->$templateid->facebookappid->setting;
    $facebooksecret = $global_settings->events_template_global->$templateid->facebooksecret->setting;

    if (!empty($facebookappid) && !empty($facebooksecret)) {
        // Load facebook library.
        require_once ($CFG->dirroot . '/features/events/facebook/facebook.php');
        $config = [
            "appId" =>  $global_settings->events_template_global->$templateid->facebookappid->setting,
            "secret" => $global_settings->events_template_global->$templateid->facebooksecret->setting,
        ];

        $facebook = new Facebook($config);
        $login_url = $facebook->getLoginUrl([
            'scope' => 'publish_stream',
            'redirect_uri' => $CFG->wwwroot . '/features/events/events_ajax.php?action=send_facebook_message&info=' . base64_encode(serialize([$event["eventid"], $name, $config])),
        ]);
        return '
            <a title="Tell your friends about ' . $name . '\'s registration for ' . $event["name"] . '!" href="' . $login_url . '" target="_blank">
                <img src="' . $CFG->wwwroot . '/features/events/images/facebook_button.png" />
            </a>';
    }
    return "";
}

function events_adminpanel($pageid) {
global $CFG, $USER;
    $content = "";
    //Event Template Manager
    $content .= user_is_able($USER->userid, "manageeventtemplates", $pageid) ? make_modal_links([
                                                                                            "title"  => "Event Templates",
                                                                                            "text"   => "Event Templates",
                                                                                            "path"   => action_path("events") . "template_manager&pageid=$pageid",
                                                                                            "iframe" => true,
                                                                                            "width"  => "640",
                                                                                            "icon"  => icon("table-list"),
                                                                                            "class" => "adminpanel_links",
                                                                                            ]) : "";
    //Course Event Manager
    $content .= user_is_able($USER->userid, "manageevents", $pageid) ? make_modal_links([
                                                                                    "title"  => "Event Registrations",
                                                                                    "text"   => "Event Registrations",
                                                                                    "path"   => action_path("events") . "event_manager&pageid=$pageid",
                                                                                    "iframe" => true,
                                                                                    "width"  => "640",
                                                                                    "icon"  => icon("list-check"),
                                                                                    "class" => "adminpanel_links",
                                                                                    ]) : "";
    //Application Manager
    $content .= user_is_able($USER->userid, "manageapplications", $pageid) ? make_modal_links([
                                                                                            "title"  => "Staff Applications",
                                                                                            "text"   => "Staff Applications",
                                                                                            "path"   => action_path("events") . "application_manager&pageid=$pageid",
                                                                                            "iframe" => true,
                                                                                            "width"  => "640",
                                                                                            "icon"  => icon("clipboard-user"),
                                                                                            "class" => "adminpanel_links",
                                                                                        ]) : "";
    //Staff Notifications
    $content .= user_is_able($USER->userid, "manageapplications", $pageid) ? make_modal_links([
                                                                                            "title"  => "Staff Process Email",
                                                                                            "text"   => "Staff Process Email",
                                                                                            "path"   => action_path("events") . "staff_emailer&pageid=$pageid",
                                                                                            "iframe" => true,
                                                                                            "width"  => "640",
                                                                                            "icon"  => icon("envelopes-bulk"),
                                                                                            "class" => "adminpanel_links",
                                                                                        ]) : "";
    return $content;
}

function add_blank_registration($eventid, $reserveamount = 1) {
    $event = get_event($eventid);
    $template_id = $event["template_id"];
    $eventname = $event["name"];
    $pageid = $event["pageid"];

    $reserved = 0;
    $return = [];
    while ($reserved < $reserveamount) {
        $SQL = "";$SQL2 = "";
        if ($regid = execute_db_sql("INSERT INTO events_registrations
                                    (eventid,date,code,manual)
                                    VALUES('$eventid','" . get_timestamp() . "','" . uniqid("", true) . "',1)")) {
            $template = get_event_template($template_id);
            if ($template["folder"] == "none") {
                if ($template_forms = get_db_result("SELECT * FROM events_templates_forms
                                                        WHERE template_id='$template_id'
                                                        ORDER BY sort")) {
                        while ($form_element = fetch_row($template_forms)) {
                            if ($form_element["type"] == "payment") {
                                $SQL2 .= $SQL2 == "" ? "" : ",";
                                $SQL2 .= "('$regid','" . $form_element["elementid"] . "', '','$eventid','total_owed'),('$regid'," . $form_element["elementid"] . ", '','$eventid','paid'),('$regid','" . $form_element["elementid"] . "', '','$eventid','payment_method')";
                        } else {
                                $SQL2 .= $SQL2 == "" ? "" : ",";
                                $value = $form_element["nameforemail"] == 1 ? "Reserved" : "";
                                $SQL2 .= "('$regid'," . $form_element["elementid"] . ",'$value','$eventid','" . $form_element["elementname"] . "')";
                            }
                    }
                  }
                  $SQL2 = "INSERT INTO events_registrations_values
                            (regid,elementid,value,eventid,elementname)
                            VALUES" . $SQL2;
              } else {
                  $template_forms = explode(";", trim($template["formlist"], ';'));
                foreach ($template_forms as $formset) {
                    $form = explode(":", $formset);
                        $value = strstr($template["registrant_name"], $form[0]) ? "Reserved" : "";
                    $SQL2 .= $SQL2 == "" ? "" : ",";
                    $SQL2 .= "('$regid','$value','$eventid','" . $form[0]."')";
                }

                $SQL2 = "INSERT INTO events_registrations_values
                            (regid,value,eventid,elementname)
                            VALUES" . $SQL2;
              }

              if (execute_db_sql($SQL2)) {
                $return[$reserved] = $regid;
              } else {
                execute_db_sql("DELETE FROM events_registrations
                                    WHERE regid='$regid'");
                $return[$reserved] = false;
            }
        } else { $return[$reserved] = false; }
        $reserved++;
    }
    return $return;
}

function request_questions_form($reqid, $voteid, $featureid, $pageid) {
    $return = "";

    // Check if any settings exist for this feature
    if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        save_batch_settings(default_settings("events", $pageid, $featureid));
        $settings = fetch_settings("events", $featureid, $pageid);
    }
    $locationid = $settings->events->$featureid->allowrequests->setting;

    $prev_questions = '<h3>Previous Questions</h3>';
    // Print out previous questions and answers
    if ($results = get_db_result("SELECT *
                                    FROM events_requests_questions
                                WHERE reqid = ||reqid||
                                ORDER BY question_time", ["reqid" => $reqid])) {
        while ($row = fetch_row($results)) {
            $question = strip_tags(trim($row['question'], " \n\r\t"),'<a><em><u><img><br>');
            $prev_questions .= '
                <div class="request_questions">
                    <strong>' . icon("question") . ' ' . $question . '</strong>
                </div>';

            if (empty($row["answer"])) { // Not answered
                $prev_questions .= '
                    <div class="request_answers none">
                        No response yet.
                    </div>';
            } else { //Print answer
                $answer = strip_tags(trim($row['answer'], " \n\r\t"),'<a><em><u><img><br>');
                $prev_questions .= '
                    <div class="request_answers">
                        ' . $answer . '
                    </div>';
            }
        }
    } else {
        $prev_questions .= 'No questions have been asked yet.';
    }

    $middlecontents = '
        <div id="question_form" style="padding: 20px;">
            <h2>Questions Regarding Event Request</h2>
            ' . get_request_info($reqid) . '
            <form id="request_question_form" method="post" action="./events_ajax.php">
                <br />
                <input type="hidden" name="action" value="request_question_send" />
                <input type="hidden" name="reqid" value="' . $reqid . '" />
                <input type="hidden" name="voteid" value="' . $voteid . '" />
                <div style="background-color:Aquamarine;padding:4px;">
                    <strong>Ask a Question</strong>
                </div>
                <br />
                ' . get_editor_box() . '
                <br />
                <div style="width:100%;text-align:center">
                    <input type="submit" value="Send Question" id="send_question" />
                </div>
            </form>
            ' . $prev_questions . '
        </div>';
    $params = [
        "mainmast" => page_masthead(true, true),
        "middlecontents" => $middlecontents,
    ];
    return fill_template("tmp/index.template", "simplelayout_template", false, $params);
}

function request_answers_form($reqid, $qid, $featureid, $pageid) {
global $CFG;
    if (!$settings = fetch_settings("events", $featureid, $pageid)) {
        save_batch_settings(default_settings("events", $pageid, $featureid));
        $settings = fetch_settings("events", $featureid, $pageid);
    }
    $locationid = $settings->events->$featureid->allowrequests->setting;

    // Print out previous questions and answers
    $params = [
        "reqid" => $reqid,
        "qid" => $qid,
    ];
    $mod = "AND id <> ||qid||";

    $prev_questions = "";
    $SQL = fill_template("dbsql/events.sql", "get_events_requests_questions", "events", ["mod" => $mod], true);
    if ($results = get_db_result($SQL, $params)) {
        while ($row = fetch_row($results)) {
            $question = strip_tags(trim($row['question'], " \n\r\t"),'<a><em><u><img><br>');
            $protocol = get_protocol();
            $prev_questions .= '
                <div style="background-color:Aquamarine;padding:4px;">
                    <strong>' . $question . '</strong>
                </div>';
            if ($row["answer"] == "") { // Not answered
                $prev_questions .= '
                    <div style="background-color:PaleTurquoise;padding:4px;overflow:hidden;">
                        <strong>
                            <a href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_answer&qid=' . $row['id'] . '&reqid=' . $reqid . '">
                                Answer Question
                            </a>
                        </strong>
                    </div>
                    <br /><br />';
            } else { // Print answer
                $prev_questions .= '
                    <div style="background-color:#feffaf;padding:8px;overflow:hidden;">
                        ' . $row['answer'] . '
                        <strong>
                            <a style="float:right" href="' . $protocol . $CFG->wwwroot . '/features/events/events_ajax.php?action=request_answer&qid=' . $row['id'] . '&reqid=' . $reqid . '">
                                Update Answer
                            </a>
                        </strong>
                    </div>
                    <br /><br />';
            }
        }
    } else {
        $prev_questions .= 'No other questions have been asked yet.';
    }

    $request_answer_form = "";
    if (!empty($qid)) {
        $answer = get_db_field("answer", "events_requests_questions", "id = ||id||", ["id" => $qid]);
        $question = get_db_field("question", "events_requests_questions", "id = ||id||", ["id" => $qid]);
        $question = strip_tags(trim($question, " \n\r\t"),'<a><em><u><img><br>');
        $request_answer_form = '
            <form id="request_answers_form" method="post" action="./events_ajax.php">
                <input type="hidden" name="reqid" value="' . $reqid . '" />
                <input type="hidden" name="qid" value="' . $qid . '" />
                <input type="hidden" name="action" value="request_answer_send" />
                <br />
                <div style="background-color:Aquamarine;padding:4px;">
                    <strong>Question: ' . $question . '</strong>
                </div>
                <br />
                ' . get_editor_box(["initialvalue" => $answer]) . '
                <br />
                <div style="width:100%;text-align:center">
                    <input type="submit" id="send_answer" value="Send Answer" />
                </div>
            </form>';
    }

    $middlecontents = '
        <div id="question_form" style="padding: 20px;">
            <h2>Questions Regarding Event Request</h2>
            ' . get_request_info($reqid) . '
            <br />
            ' . $request_answer_form . '
            <h3>Previous Questions</h3>
            ' . $prev_questions . '
        </div>';

    return fill_template("tmp/index.template", "simplelayout_template", false, ["mainmast" => page_masthead(true, true), "middlecontents" => $middlecontents]);
}

function request_has_already_voted($reqid, $voteid) {
    $SQL = 'SELECT *
            FROM events_requests
            WHERE reqid = ||reqid||
            AND voted LIKE ||voted||';
    return get_db_row($SQL, ["reqid" => $reqid, "voted" => "%:$voteid;%"]);
}

function get_events_alerts($userid, $countonly = true) {
global $CFG, $USER;
    $alerts = 0;
    $pageid = get_pageid();
    if ($pageid == $CFG->SITEID) {
        if (user_is_able($USER->userid, "confirmevents", $pageid, "events")) {
            // Confirmable events.
            $SQL = fill_template("dbsql/events.sql", "confirmable_events", "events", ["time" => get_timestamp()], true);
            if ($events = get_db_result($SQL, ["pageid" => $pageid])) {
                $alerts = count_db_result($events);
                $alerts_rows = "";
                while ($event = fetch_row($events)) {
                    $question = 'Allow ' . $event['name'] . ' on front page?';
                    $buttons = '
                        <button title="Confirm Event" onclick="confirm_event(' . $event['eventid'] . ', 1);" class="alike">
                            ' . icon("thumbs-up", 2) . '
                        </button>
                        <button title="Deny Event" onclick="confirm_event(' . $event['eventid'] . ', 0);"" class="alike">
                            ' . icon("thumbs-down", 2) . '
                        </button>
                    ';

                    $alerts_rows .= fill_template("tmp/pagelib.template", "user_alerts_row", false, ["question" => $question, "buttons" => $buttons]);
                }
            }
        }
    }

    // if you only want the count of alerts, we know that number now.
    if ($countonly) {
        return $alerts;
    }

    if ($alerts) {
        ajaxapi([
            "id" => "confirm_event",
            "paramlist" => "eventid, confirm = 0",
            "if" => "(confirm == 1 && confirm('Are you sure you want to confirm this event?')) || (confirm == 0 && confirm('Are you sure you want to deny this event?'))",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "confirm_event_relay",
                "confirm" => "js||confirm||js",
                "pagieid" => $pageid,
                "eventid" => "js||eventid||js",
            ],
            "event" => "none",
            "display" => "user_alerts_div",
            "ondone" => "getRoot()[0].update_alerts();",
        ]);

        $params = [
            "title" => "Add page events to the front page",
            "alerts_rows" => $alerts_rows,
        ];
        return fill_template("tmp/pagelib.template", "user_alerts_group", false, $params);
    }

    return false;
}
?>
