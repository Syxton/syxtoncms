<?php
/***************************************************************************
* events.php - Events page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.4.9
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
        $sub = '';
        while (!file_exists($sub . 'header.php')) {
            $sub = $sub == '' ? '../' : $sub . '../';
        }
        include($sub . 'header.php');
    }

    if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

    $head = get_js_tags(["features/events/events.js"]);

    echo fill_template("tmp/page.template", "start_of_page_template", false, ["head" => $head]);

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");

}

function events_settings() {
     $pageid = clean_myvar_opt("pageid", "int", get_pageid());
     $featureid = clean_myvar_req("featureid", "int");
    $feature = "events";

    //Default Settings
    $default_settings = default_settings($feature, $pageid, $featureid);

    //Check if any settings exist for this feature
    if ($settings = fetch_settings($feature, $featureid, $pageid)) {
          echo make_settings_page($settings, $default_settings);
    } else { //No Settings found...setup default settings
        if (save_batch_settings($default_settings)) { events_settings(); }
    }
}

function event_manager() {
global $CFG;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    ajaxapi([
        "id" => "perform_eventsearch",
        "paramlist" => "pagenum = 0, searchwords = false",
        "before" => "var searchwords = searchwords ? searchwords : $('#searchbox').val();",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "eventsearch",
            "pagenum" => "js||pagenum||js",
            "searchwords" => "js||encodeURIComponent(searchwords)||js"],
        "display" => "searchcontainer",
        "ondone" => "init_event_menu();",
        "loading" => "loading_overlay",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "show_registrations",
        "paramlist" => "eventid, sel = false",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "show_registrations",
            "eventid" => "js||eventid||js",
            "sel" => "js||sel||js",
        ],
        "display" => "searchcontainer",
        "ondone" => "init_event_menu();",
        "event" => "none",
    ]);

    echo fill_template("tmp/events.template", "eventsearchform", "events", ["searchcontainer" => get_searchcontainer()]);
}

function template_manager() {
global $CFG;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    check_for_new_templates();

    ajaxapi([
        "id" => "perform_templatesearch",
        "paramlist" => "pagenum = 0, searchwords = false",
        "before" => "var searchwords = searchwords ? searchwords : $('#searchbox').val();",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "templatesearch",
            "pagenum" => "js||pagenum||js",
            "searchwords" => "js||encodeURIComponent(searchwords)||js"],
        "display" => "searchcontainer",
        "ondone" => "init_event_menu();",
        "loading" => "loading_overlay",
        "event" => "none",
    ]);

    echo fill_template("tmp/events.template", "templatesearchform", "events", ["searchcontainer" => get_searchcontainer()]);
}

function application_manager() {
global $CFG;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $canexport = $exportselect = false;
    if ($archive = get_db_result(fetch_template("dbsql/events.sql", "get_all_staff_by_page", "events"), ["pageid" => $pageid])) {
        $values = [];
        while ($vals = fetch_row($archive)) {
            $values[] = ["year" => $vals["year"]];
        }

        $canexport = true;
        $params = [
            "properties" => [
                "name" => "appyears",
                "id" => "appyears",
            ],
            "values" => $values,
            "valuename" => "year",
            "displayname" => "year",
            "selected" => date("Y"),
        ];

        $exportselect = make_select($params);

        ajaxapi([
            "id" => "export_staffapp_$pageid",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "export_staffapp",
                "pageid" => $pageid,
                "year" => "js||$('#appyears').val()||js",
            ],
            "display" => "downloadframe",
        ]);
    }

    ajaxapi([
        "id" => "perform_appsearch",
        "paramlist" => "pagenum = 0, searchwords = false",
        "before" => "var searchwords = searchwords ? searchwords : $('#searchbox').val();",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "appsearch",
            "pagenum" => "js||pagenum||js",
            "searchwords" => "js||encodeURIComponent(searchwords)||js"],
        "display" => "searchcontainer",
        "ondone" => "init_event_menu();",
        "loading" => "loading_overlay",
        "event" => "none",
    ]);

    $params = [
        "pageid" => $pageid,
        "canexport" => $canexport,
        "exportselect" => $exportselect,
        "searchcontainer" => get_searchcontainer(),
    ];
    echo fill_template("tmp/events.template", "appsearchtemplate", "events", $params);
}

function staff_emailer() {
global $CFG;
    ajaxapi([
        "id" => "sendstaffemails",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "sendstaffemails",
            "stafflist" => "js||$('#stafflist').val()||js",
            "sendemails" => "js||$('#sendemails').prop('checked')||js",
        ],
        "display" => "searchcontainer",
        "loading" => "loading_overlay",
        "event" => "submit",
    ]);
    echo fill_template("tmp/events.template", "staff_emailer_template", "events", ["searchcontainer" => get_searchcontainer()]);
}

function pay() {
global $CFG;
    $regcode = clean_myvar_opt("regcode", "string", "");
    $modal = clean_myvar_opt("modal", "string", false);

    if (!$modal) {
        echo get_js_tags(["jquery"]);
        echo main_body(true) . '<br /><br />';
    }

    ajaxapi([
        "id" => "lookup_reg",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "lookup_reg",
            "code" => "js||$('#code').val()||js",
        ],
        "display" => "payarea",
        "event" => "none",
    ]);

    echo js_code_wrap('window.onload = function () { if ($("#code").val() !== "") { lookup_reg($("#code").val()); } }', "", true);
    echo '
        <div style="text-align:center;padding:15px;">
            <h3>' . $CFG->sitename . ' Registration Lookup</h3><br />
            <form id="payarea_form" onsubmit="lookup_reg($(\'#code\').val()); return false;">
                <div style="display: inline-flex;align-items: center;">
                    <span>
                        Enter your Registration ID:
                    </span>
                    <input type="text" id="code" size="35" style="margin: 0 10px;" value="' . $regcode . '" />
                    <button type="submit">
                        Submit
                    </button>
                </div>
            </form>
        </div>
        <div id="payarea" style="padding:15px;"></div>
    ';
}

function event_request_form() {
global $CFG;
    $featureid = clean_myvar_opt("featureid", "int", false);

    $return = $error = "";
    try {
        if (!$featureid) {
            throw new Exception("Missing featureid");
        }

        $pageid = get_db_field("pageid", "pages_features", "featureid=$featureid");
        if (!$settings = fetch_settings("events", $featureid, $pageid)) {
            save_batch_settings(default_settings("events", $pageid, $featureid));
            $settings = fetch_settings("events", $featureid, $pageid);
        }
        $event_begin_date = $event_end_date = 'true';
        $locationid = $settings->events->$featureid->allowrequests->setting;
        $request_text = $settings->events->$featureid->request_text->setting;

        if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

        ajaxapi([
            "id" => "request_event",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "event_request",
                "stafflist" => "js||$('#stafflist').val()||js",
                "sendemails" => "js||$('#sendemails').prop('checked')||js",
            ],
            "reqstring" => "request_form",
            "display" => "request_form_div",
            "event" => "none",
        ]);

        $params = [
            "validation" => create_validation_script("request_form" , "request_event();"),
            "request_text" => $request_text,
            "location" => get_db_field("location", "events_locations", "id = ||id||", ["id" => $locationid]),
            "featureid" => $featureid,
            "locationid" => $locationid,
        ];
        $return = fill_template("tmp/events.template", "event_request_form", "events", $params);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    echo $return . $error;
}

function staff_application() {
global $CFG, $USER;
    if (isset($USER->userid)) {
        $staff = get_db_row("SELECT * FROM events_staff WHERE userid='$USER->userid'"); //Update existing event

        if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

        ajaxapi([
            "id" => "event_save_staffapp",
            "url" => "/features/events/events_ajax.php",
            "data" => [
                "action" => "event_save_staffapp",
            ],
            "reqstring" => "staffapplication_form",
            "display" => "staffapplication_form_div",
            "event" => "none",
        ]);

        echo create_validation_script("staffapplication_form", "event_save_staffapp();");
        echo staff_application_form($staff);
    } else { echo "Sorry, This form is not available"; }
}

function info() {
global $CFG;
     $eventid = clean_myvar_opt("eventid", "int", false);

     if ($eventid && $event = get_event($eventid)) {
          $location = get_db_row("SELECT * FROM events_locations WHERE id='" . $event["location"] . "'");
          date_default_timezone_set("UTC");

          echo '<div style="text-align:center"><h1>' . $event["name"] . '</h1>' . $event["byline"] . '</div>';
          echo '<div>' . $event["description"]. '</div><br /><center>';

          if ($event['event_begin_date'] != $event['event_end_date']) { //Multi day event
                echo 'When: ' . date('F \t\h\e jS, Y', $event["event_begin_date"]) . ' to ' . date('F \t\h\e jS, Y', $event["event_end_date"]) . '<br />';
          } else {
                echo 'When: ' . date('F \t\h\e jS, Y', $event["event_begin_date"]) . '<br />';
          }

          echo '<br /><table style="font-size:1em"><tr><td>Where: </td><td>' . $location["location"] . '</td></tr>
          <tr><td></td><td>' . $location["address_1"] . '<br />' . $location["address_2"] . '&nbsp;' . $location["zip"] . '</td></tr></table>
          <span class="centered_span"><a title="Get Directions" href="' . $CFG->wwwroot . '/features/events/googlemaps.php?address_1=' . $location["address_1"] . '&address_2=' . $location["address_2"] . '">Get Directions</a></span><br />';

          if ($event['allday'] != 1) { //All day event
              echo 'Times: ' . convert_time($event['event_begin_time']) . ' to ' . convert_time($event['event_end_time']) . '. <br />';
          }

          echo '<br />For more information about this event<br /> contact ' . $event["contact"] . ' at ' . $event["email"] . '<br />or call ' . $event["phone"] . '.</center><br />';

          // Log
          log_entry("events", $eventid, "View Event Info");
     } else {
          // Log
          log_entry("events", "-", "Variable manipulation");
     }

}

function add_event_form() {
global $CFG, $USER;
    $pageid = clean_myvar_req("pageid", "int");
    $eventid = clean_myvar_opt("eventid", "int", false);

    date_default_timezone_set("UTC");
    $admin_contacts = $admin_payable = "";

     if (is_siteadmin($USER->userid)) { // Get special admin drop down lists for contacts and accounts payable
          $admin_contacts = get_events_admin_contacts();
          $admin_payable = get_events_admin_payable();
     }

    $today = date('Y-m-d');

    if ($eventid) { // Update existing event
        $heading = "Edit Event";
        if (!user_is_able($USER->userid, "editevents", $pageid)) {
            trigger_error(error_string("no_permission", ["editevents"]), E_USER_WARNING);
            return;
        }

        $event = get_event($eventid);
        $name = $event["name"];
        $contact = $event['contact'];
        $email = $event['email'];
        $fee_min = $event['fee_min'];
        $fee_full = $event['fee_full'];
        $sale_fee = $event['sale_fee'];
        $payableto = $event['payableto'];
        $checksaddress = $event['checksaddress'];
        $paypal = $event['paypal'];
        $phone = explode("-", $event['phone']);
        $global_display = $event['pageid'] == $CFG->SITEID ? 'none' : 'inline';
        $start_reg = isset($event['start_reg']) ? date('Y-m-d', $event['start_reg']) : $today;
        $stop_reg = isset($event['stop_reg']) ? date('Y-m-d', $event['stop_reg']) : $today;
        $sale_end = !empty($event['sale_end']) ? date('Y-m-d', $event['sale_end']) : $today;
        $template = isset($event['template_id']) ? $event['template_id'] : false;
        $template_settings_form = get_template_settings_form($template, $eventid);
        $event_begin_date = isset($event['event_begin_date']) ? date('Y-m-d', $event['event_begin_date']) : $today;
        $event_end_date = isset($event['event_end_date']) ? date('Y-m-d', $event['event_end_date']) : $today;
        $end_date_display = $event['event_begin_date'] != $event['event_end_date'] ? 'inline' : 'none';
        $times_display = $event['allday'] == "1" ? 'none' : 'inline';
        $fee_display = $event['fee_full'] == "0" ? 'none' : 'inline';
        $event_begin_time_form = isset($event['event_begin_time']) && $event['event_begin_time'] != "" ? get_possible_times('begin_time', $event['event_begin_time']) : get_possible_times('begin_time');
        $event_end_time_form = "";
        if (!empty($event['event_end_time'])) {
            $event_end_time_form = $event['event_begin_date'] != $event['event_end_date'] ? get_possible_times('end_time', $event['event_end_time']) : get_possible_times('end_time', $event['event_end_time'], $event['event_begin_time']);
        }
        $reg_display = $event['start_reg'] ? 'inline' : 'none';
        $max_users = $event['max_users'] != "0" ? $event['max_users'] : '0';
        $byline = $event['byline'];
        $description = $event['description'];
        $fee_yes = $event['fee_full'] != "0" ? "selected" : "";
        $fee_no = $fee_yes == "" ? "selected" : "";
        $allowinpage_yes = $event['allowinpage'] == "1" ? "selected" : "";
        $allowinpage_no = $allowinpage_yes == "" ? "selected" : "";
        $multiday_yes = $event['event_begin_date'] != $event['event_end_date'] ? "selected" : "";
        $multiday_no = $multiday_yes == "" ? "selected" : "";
        $workers_yes = !empty($event['workers']) ? "selected" : "";
        $workers_no = $workers_yes == "" ? "selected" : "";
        $allday_yes = $event['allday'] == "1" ? "selected" : "";
        $allday_no = $allday_yes == "" ? "selected" : "";
        $reg_yes = $event['start_reg'] ? "selected" : "";
        $reg_no = $reg_yes == "" ? "selected" : "";
        $limits_yes = !empty($event['max_users']) || !empty($event['hard_limits']) || !empty($event['soft_limits']) ? "selected" : "";
        $limits_no = empty($limits_yes) ? "selected" : "";
        $limits_display = !empty($limits_no) ? 'none' : 'inline';
        $siteviewable_yes = $event['siteviewable'] == "1" ? "selected" : "";
        $siteviewable_no = $siteviewable_yes == "" ? "selected" : "";
        $auto_allowinpage_display = $event['siteviewable'] == "1" ? "inline" : "none";
        $mycategories = get_my_category($event['category']);
        $mylocations = get_my_locations($USER->userid, $event['location'], $eventid);
        $hidden_limits = get_my_hidden_limits($template, $event['hard_limits'], $event['soft_limits']);
    } else { // New event form
        $heading = "Add Event";
        if (!user_is_able($USER->userid, "addevents", $pageid)) {
            trigger_error(error_string("no_permission", ["addevents"]), E_USER_WARNING);
            return;
        }
        $eventid = $template = false;
        $template_settings_form = "";
        $global_display = $pageid == $CFG->SITEID ? 'none' : 'inline';
        $hidden_limits = '<input type="hidden" id="hard_limits" name="hard_limits" value="" /><input type="hidden" id="soft_limits" name="soft_limits" value="" />';
        $phone = [0 => "", 1 => "", 2 => ""];
        $start_reg = $stop_reg = $sale_end = $event_begin_date = $event_end_date = $today;
        $end_date_display = $limits_display = $times_display = $reg_display = $fee_display = 'none';
        $max_users = $cost = '0';
        $checksaddress = $paypal = $payableto = $name = $contact = $email = $byline = $description = $siteviewable_yes = $workers_yes = $multiday_yes = $allday_no = $reg_yes = $fee_yes = $allowinpage_yes = $limits_yes = $event_end_time_form = $fee_min = $fee_full = $sale_fee = $template_settings = "";
        $event_begin_time_form = get_possible_times('begin_time');
        $mycategories = get_my_category();
        $mylocations = get_my_locations($USER->userid);
        $siteviewable_no = $workers_no = $multiday_no = $allday_yes = $reg_no = $fee_no = $allowinpage_no = $limits_no = "selected";
        $auto_allowinpage_display = "none";
    }

    ajaxapi([
        "id" => "add_location_form",
        "paramlist" => "formtype",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "add_location_form",
            "formtype" => "js||formtype||js",
            "eventid" => $eventid,
        ],
        "display" => "location_menu",
        "ondone" => "prepareInputsForHints();",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "get_location_details",
        "if" => "$('#add_location').val() > 0",
        "else" => "clear_display('location_details_div')",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "get_location_details",
            "location" => "js||$('#add_location').val()||js",
        ],
        "display" => "location_details_div",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "copy_location",
        "if" => "$('#add_location').val() > 0",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "copy_location",
            "location" => "js||$('#add_location').val()||js",
            "eventid" => $eventid,
        ],
        "display" => "select_location",
        "ondone" => "reset_location_menu();$('#location_status').html('Location Added'); setTimeout('clear_display(\'location_status\')', 5000);",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "get_end_time",
        "if" => "$('#begin_time').val() != ''",
        "before" => "var endtime = $('#end_time').length && $('#end_time').val() != '' ? $('#end_time').val() : ''; var limit = $('#multiday').val() == 1 ? 0 : 1;",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "get_end_time",
            "starttime" => "js||$('#begin_time').val()||js",
            "endtime" => "js||endtime||js",
            "limit" => "js||limit||js",
        ],
        "display" => "end_time_span",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "new_event_submit",
        "if" => "valid_new_event()",
        "reqstring" => "new_event_submit",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "submit_new_event",
        ],
        "display" => "add_event_div",
        "ondone" => "close_modal();",
        "event" => "submit",
    ]);

    ajaxapi([
        "id" => "custom_limit_form",
        "if" => "$('#template').val()",
        "else" => "alert('Please select a template first.')",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "get_limit_form",
            "template_id" => "js||$('#template').val()||js",
        ],
        "display" => "limit_form",
    ]);

    ajaxapi([
        "id" => "delete_limit",
        "paramlist" => "limit_type, limit_num",
        "url" => "/features/events/events_ajax.php",
        "data" => [
            "action" => "delete_limit",
            "limit_type" => "js||limit_type||js",
            "limit_num" => "js||limit_num||js",
            "hard_limits" => "js||$('#hard_limits').val()||js",
            "soft_limits" => "js||$('#soft_limits').val()||js",
            "template_id" => "js||$('#template').val()||js",
        ],
        "display" => "custom_limits",
        "event" => "none",
    ]);

    echo '
    <h3>' . $heading . '</h3>
        <div id="add_event_div">
            <form id="new_event_submit">
                <input type="hidden" id="pageid" name="pageid" value="' . $pageid . '" />
                <input type="hidden" id="eventid" name="eventid" value="' . $eventid . '" />
                <table style="width:100%">
                    <tr>
                        <td class="field_title" style="width:115px;">
                            Event Name:
                        </td>
                        <td class="field_input">
                            <input type="text" id="event_name" name="event_name" size="30" value="' . $name . '"/>
                                ' . get_hint_box("input_event_name:events") . '
                        </td>
                    </tr><tr><td></td><td class="field_input"><span id="event_name_error" class="error_text"></span></td></tr>
                </table>
                <table style="width:100%">
                    <tr>
                        <td class="field_title" style="width:115px;">
                            Category:
                        </td>
                        <td class="field_input">
                            <span id="select_category">' . $mycategories . '' . get_hint_box("input_event_category:events") . '</span>
                        </td>
                    </tr><tr><td></td><td class="field_input"><span id="category_error" class="error_text"></span></td></tr>
                </table>
                <table style="width:100%">
                    <tr>
                        <td class="field_title" style="width:115px; vertical-align:top">
                            Byline:
                        </td>
                        <td class="field_input">
                            <textarea id="byline" name="byline" cols="40" rows="5">' . $byline . '</textarea>
                            ' . get_hint_box("input_byline:events") . '
                        </td>
                    </tr><tr><td></td><td class="field_input"><span id="event_name_error" class="error_text"></span></td></tr>
                </table>
                <table style="width:100%">
                    <tr>
                        <td class="field_title" style="width:115px; vertical-align:top">
                            Description:
                        </td>
                        <td class="field_input">
                            ' . get_editor_box(["initialvalue" => $description, "height" => "300"]) . '
                            ' . get_hint_box("input_description:events") . '
                        </td>
                    </tr><tr><td></td><td class="field_input"><span id="event_name_error" class="error_text"></span></td></tr>
                </table>
                ' . $admin_contacts . '
                <table style="width:100%">
                    <tr>
                        <td class="field_title" style="width:115px;">
                            Contact Name:
                        </td>
                        <td class="field_input">
                            <input type="text" id="contact" name="contact" size="30" value="' . $contact . '"/>
                            ' . get_hint_box("input_contact:events") . '
                        </td>
                    </tr><tr><td></td><td class="field_input"><span id="contact_error" class="error_text"></span></td></tr>
                </table>
                <table style="width:100%">
                    <tr>
                        <td class="field_title" style="width:115px;">
                            Contact Email:
                        </td>
                        <td class="field_input">
                            <input type="text" id="email" name="email" size="30" value="' . $email . '"/>
                            ' . get_hint_box("input_event_email:events") . '
                        </td>
                    </tr><tr><td></td><td class="field_input"><span id="email_error" class="error_text"></span></td></tr>
                </table>
                <table style="width:100%">
                    <tr>
                        <td class="field_title" style="width:115px;">
                            Contact Phone:
                        </td>
                        <td class="field_input">
                            ' . create_form_element("phone", "phone", $phone) . '
                        </td>
                    </tr><tr><td></td><td class="field_input"><span id="phone_error" class="error_text"></span></td></tr>
                </table>
                <table style="width:100%; display:' . $global_display . '">
                    <tr>
                        <td class="field_title" style="width:115px;">
                            Request Site Event:
                        </td>
                        <td class="field_input">
                            <select id="siteviewable" name="siteviewable" onchange="if (this.value==0) { hide_section(\'auto_allowinpage\'); document.getElementById(\'allowinpage\').value=0; } else { show_section(\'auto_allowinpage\'); }" ><option value="0" ' . $siteviewable_no . '>No</option><option value="1" ' . $siteviewable_yes . '>Yes</option></select>
                            ' . get_hint_box("input_event_siteviewable:events") . '
                        </td>
                    </tr><tr><td></td><td class="field_input"><span id="event_name_error" class="error_text"></span></td></tr>
                </table>
                <br />
                <div class="dotted">
                    <div style="display: inline-flex;align-items: center;">
                        <span>
                            Location:
                        </span>
                        <span id="select_location" style="padding: 10px;">
                            ' . $mylocations . '
                            ' . get_hint_box("input_event_location:events") . '
                        </span>
                        <span>
                            <button id="addtolist" type="button" onclick="$(\'#addtolist\').addClass(\'hidden\');$(\'#add_location_div, #hide_menu\').removeClass(\'hidden\');">
                                Add to list
                            </button>
                            <button id="hide_menu" type="button" class="hidden" onclick="reset_location_menu()">
                                Hide Menu
                            </button>
                        </span>
                    </div>
                    <div id="location_error" class="error_text"></div>
                    <div id="locations_wrap">
                        <div id="add_location_div" class="hidden" style="width: 50vw;">
                            <div class="sub_field_title" style="display: flex;align-items: center;justify-content: space-between;padding: 10px;">
                                <button type="button" id="new_button" style="margin-right: 10px;margin-left: 0px;display:inline;float:left"
                                            onclick="$(\'#location_menu\').html(\'\'); $(\'#location_menu\').toggleClass(\'hidden\');$(\'#browse_button, #or\').toggleClass(\'invisible\'); if($(\'#browse_button\').hasClass(\'invisible\')) { add_location_form(\'new\'); }">
                                    <span>
                                        ' . icon("plus") . '
                                        <span> Toggle Add Form</span>
                                    </span>
                                </button>
                                <span id="or">&nbsp; or &nbsp;</span>
                                <button type="button" id="browse_button" style="margin-right: 0px;margin-left: 10px;display:inline"
                                        onclick="$(\'#location_menu\').html(\'\'); $(\'#location_menu\').toggleClass(\'hidden\');$(\'#new_button, #or\').toggleClass(\'invisible\'); if($(\'#new_button\').hasClass(\'invisible\')) { add_location_form(\'existing\'); }">
                                    <span>
                                        ' . icon("location-dot") . '
                                        <span> Toggle Existing List</span>
                                    </span>
                                </button>
                            </div>
                            <div id="location_menu" class="hidden" style="text-align:center"></div>
                            <span id="location_status"></span>
                        </div>
                    </div>
                </div>
                <br />
                <div class="dotted">
                    <table style="width:100%;">
                        <tr>
                            <td>
                                <table style="width:100%">
                                    <tr>
                                        <td class="field_title" style="width:115px;">
                                            Worker Application:
                                        </td>
                                        <td class="field_input">
                                            <select id="workers" name="workers"><option value="0" ' . $workers_no . '>No</option><option value="1" ' . $workers_yes . '>Yes</option></select>
                                            ' . get_hint_box("input_event_workers:events") . '
                                        </td>
                                    </tr><tr><td></td><td class="field_input"><span id="workers_error" class="error_text"></span></td></tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
                <br />
                <div class="dotted">
                    <table style="width:100%;">
                        <tr>
                            <td>
                                <table style="width:100%">
                                    <tr>
                                        <td class="field_title" style="width:115px;">
                                            Multi-day Event:
                                        </td>
                                        <td class="field_input">
                                            <select id="multiday" name="multiday" onchange="hide_show_buttons(\'event_end_date_div\'); get_end_time();" >
                                                <option value="0" ' . $multiday_no . '>No</option>
                                                <option value="1" ' . $multiday_yes . '>Yes</option>
                                            </select>
                                            ' . get_hint_box("input_event_multiday:events") . '
                                        </td>
                                    </tr><tr><td></td><td class="field_input"><span id="allowinpage_error" class="error_text"></span></td></tr>
                                </table>
                                <table style="width:100%">
                                    <tr>
                                        <td colspan="2">
                                            <table style="margin:0px 0px 0px 50px;">
                                                <tr>
                                                    <td class="sub_field_title">
                                                        Event Start Date:
                                                    </td>
                                                    <td class="field_input">
                                                        <input type="date" id="event_begin_date" name="event_begin_date" value="' . $event_begin_date . '">
                                                    </td>
                                                </tr><tr><td></td><td class="field_input"><span id="event_begin_date_error" class="error_text"></span></td></tr>
                                            </table>
                                            <span id="event_end_date_div" style="display:' . $end_date_display . '">
                                                <table style="margin:0px 0px 0px 50px;">
                                                    <tr>
                                                        <td class="sub_field_title">
                                                            Event Stop Date:
                                                        </td>
                                                        <td class="field_input">
                                                        <input type="date" id="event_end_date" name="event_end_date" value="' . $event_end_date . '">
                                                    </td>
                                                    </tr><tr><td></td><td class="field_input"><span id="event_end_date_error" class="error_text"></span></td></tr>
                                                </table>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
                <br />
                <div class="dotted">
                    <table style="width:100%;">
                        <tr>
                            <td>
                                <table style="width:100%">
                                    <tr>
                                        <td class="field_title" style="width:115px;">
                                            All Day Event:
                                        </td>
                                        <td class="field_input">
                                            <select id="allday" name="allday" onchange="hide_show_buttons(\'event_times_div\');" /><option value="1" ' . $allday_yes . '>Yes</option><option value="0" ' . $allday_no . '>No</option></select>
                                            ' . get_hint_box("input_event_allday:events") . '
                                        </td>
                                    </tr><tr><td></td><td class="field_input"><span id="allowinpage_error" class="error_text"></span></td></tr>
                                </table>
                                <table style="width:100%">
                                    <tr>
                                        <td colspan="2">
                                            <span id="event_times_div" style="display:' . $times_display . '">
                                                <table style="margin:0px 0px 0px 50px;">
                                                    <tr>
                                                        <td class="sub_field_title">
                                                            Times:
                                                        </td>
                                                        <td class="field_input">
                                                            ' . $event_begin_time_form . '
                                                        </td>
                                                        <td class="field_input">
                                                            <span id="end_time_span">
                                                                ' . $event_end_time_form . '
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr><td colspan="2"><td class="field_input"><span id="time_error" class="error_text"></span></td></tr>
                                                </table>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
                <br />
                <div class="dotted">
                    <table style="width:100%;">
                        <tr>
                            <td class="field_title" style="width:115px;">
                                Registration:
                            </td>
                            <td class="field_input">
                                <select id="reg" name="reg" onchange="hide_show_buttons(\'registration_panel\');$(\'#start_reg\').val(\'' . $today . '\');$(\'#stop_reg\').val(\'' . $today . '\');" >
                                    <option value="0" ' . $reg_no . '>No</option>
                                    <option value="1" ' . $reg_yes . '>Yes</option>
                                </select>
                                ' . get_hint_box("input_event_registration:events") . '
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div id="registration_panel" style="display:' . $reg_display . '">
                                    <div id="auto_allowinpage" style="display:' . $auto_allowinpage_display . '">
                                        <table style="margin:0px 0px 0px 50px;">
                                            <tr>
                                                <td class="field_title" style="width:115px;">
                                                    Auto Access:
                                                </td>
                                                <td class="field_input">
                                                    <select id="allowinpage" name="allowinpage">
                                                        <option value="0" ' . $allowinpage_no . '>No</option>
                                                        <option value="1" ' . $allowinpage_yes . '>Yes</option>
                                                    </select>
                                                    ' . get_hint_box("input_event_allowinpage:events") . '
                                                </td>
                                            </tr><tr><td></td><td class="field_input"><span id="allowinpage_error" class="error_text"></span></td></tr>
                                        </table>
                                    </div>
                                    <table style="margin:0px 0px 0px 50px;">
                                        <tr>
                                            <td class="field_title" style="width:115px;">
                                                Registration Form Template:
                                            </td>
                                            <td class="field_input">
                                                ' . get_templates($template, $eventid, true) . '
                                            </td>
                                        </tr><tr><td></td><td class="field_input"><span id="template_error" class="error_text"></span></td></tr>
                                    </table>
                                    <div name="template_settings_form">
                                        <div id="template_settings_div">
                                            ' . $template_settings_form . '
                                        </div>
                                    </div>
                                    <table style="margin:0px 0px 0px 50px;">
                                        <tr>
                                            <td class="sub_field_title">
                                                Open Registration Date:
                                            </td>
                                            <td class="field_input">
                                                <input type="date" id="start_reg" name="start_reg" value="' . $start_reg . '">
                                            </td>
                                        </tr><tr><td></td><td class="field_input"><span id="start_reg_error" class="error_text"></span></td></tr>
                                    </table>
                                    <table style="margin:0px 0px 0px 50px;">
                                        <tr>
                                            <td class="sub_field_title">
                                                Close Registration Date:
                                            </td>
                                            <td class="field_input">
                                                <input type="date" id="stop_reg" name="stop_reg" value="' . $stop_reg . '">
                                            </td>
                                        </tr><tr><td></td><td class="field_input"><span id="stop_reg_error" class="error_text"></span></td></tr>
                                    </table>
                                    <br />
                                    <div class="dotted" style="margin:5px;">
                                        <table style="width:100%">
                                            <tr>
                                                <td class="field_title" style="width:115px;">
                                                    Limits:
                                                </td>
                                                <td class="field_input">
                                                    <select id="limits" name="limits" onchange="hide_show_buttons(\'limits_div\');">
                                                        <option value="0" ' . $limits_no . '>No</option>
                                                        <option value="1" ' . $limits_yes . '>Yes</option>
                                                    </select>
                                                    ' . get_hint_box("input_event_limits:events") . '
                                                </td>
                                            </tr>
                                        </table>
                                        <span id="limits_div" style="display:' . $limits_display . '">
                                            <table style="width:100%">
                                                <tr>
                                                    <td colspan="2">
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Total Max:
                                                                </td>
                                                                <td class="field_input">
                                                                    <input type="text" id="max" name="max" size="4" maxlength="4" value="' . $max_users . '"/>
                                                                    ' . get_hint_box("input_event_max_users:events") . '
                                                                </td>
                                                            </tr><tr><td></td><td class="field_input"><span id="max_error" class="error_text"></span></td></tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                            <table style="width:100%">
                                                <tr>
                                                    <td colspan="2">
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Custom Limits:
                                                                </td>
                                                                <td class="field_input">
                                                                    <button id="custom_limit_form">
                                                                        Add Custom Limit
                                                                    </button>
                                                                    <br />
                                                                    <div id="limit_form"></div>
                                                                    <div id="custom_limits">
                                                                        ' . $hidden_limits . '
                                                                    </div>
                                                                </td>
                                                            </tr><tr><td></td><td class="field_input"><span id="max_error" class="error_text"></span></td></tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </span>
                                    </div>
                                    <br />
                                    <div class="dotted" style="margin:5px;">
                                        <table style="width:100%">
                                            <tr>
                                                <td class="field_title" style="width:115px;">
                                                    Fee:
                                                </td>
                                                <td class="field_input">
                                                    <select id="fee" name="fee" onchange="hide_show_buttons(\'fee_div\');$(\'#sale_end\').val(\'' . $today . '\');" >
                                                        <option value="0" ' . $fee_no . '>No</option>
                                                        <option value="1" ' . $fee_yes . '>Yes</option>
                                                    </select>
                                                    ' . get_hint_box("input_event_cost:events") . '
                                                </td>
                                            </tr>
                                        </table>
                                        <span id="fee_div" style="display:' . $fee_display . '">
                                            <table style="width:100%">
                                                <tr>
                                                    <td colspan="2">
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Minimum Payment:
                                                                </td>
                                                                <td class="field_input">
                                                                    <input type="text" id="min_fee" name="min_fee" size="4" value="' . $fee_min . '"/>
                                                                    ' . get_hint_box("input_event_min_cost:events") . '
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td></td>
                                                                <td class="field_input"><span id="event_min_fee_error" class="error_text"></span></td>
                                                            </tr>
                                                        </table>
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Full Price:
                                                                </td>
                                                                <td class="field_input">
                                                                    <input type="text" id="full_fee" name="full_fee" size="4" value="' . $fee_full . '"/>
                                                                    ' . get_hint_box("input_event_full_cost:events") . '
                                                                </td>
                                                            </tr><tr><td></td><td class="field_input"><span id="event_full_fee_error" class="error_text"></span></td></tr>
                                                        </table>
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Sale Price:
                                                                </td>
                                                                <td class="field_input">
                                                                    <input type="text" id="sale_fee" name="sale_fee" size="4" value="' . $sale_fee . '"/>
                                                                    ' . get_hint_box("input_event_sale_fee:events") . '
                                                                </td>
                                                            </tr><tr><td></td><td class="field_input"><span id="event_sale_fee_error" class="error_text"></span></td></tr>
                                                        </table>
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Sale Price End:
                                                                </td>
                                                                <td class="field_input">
                                                                    <input type="date" id="sale_end" name="sale_end" value="' . $sale_end . '">
                                                                    ' . get_hint_box("input_event_sale_end:events") . '
                                                                </td>
                                                            </tr><tr><td></td><td class="field_input"><span id="sale_end_error" class="error_text"></span></td></tr>
                                                        </table>
                                                        ' . $admin_payable . '
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Payable To:
                                                                </td>
                                                                <td class="field_input">
                                                                    <input type="text" id="payableto" name="payableto" size="28" value="' . $payableto . '"/>
                                                                    ' . get_hint_box("input_event_payableto:events") . '
                                                                </td>
                                                            </tr><tr><td></td><td class="field_input"><span id="event_payableto_error" class="error_text"></span></td></tr>
                                                        </table>
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Send To:
                                                                </td>
                                                                <td class="field_input">
                                                                    <textarea id="checksaddress" name="checksaddress" cols="21" rows="3">' . $checksaddress . '</textarea>
                                                                    ' . get_hint_box("input_event_checksaddress:events") . '
                                                                </td>
                                                            </tr><tr><td></td><td class="field_input"><span id="event_checksaddress_error" class="error_text"></span></td></tr>
                                                        </table>
                                                        <table style="margin:0px 0px 0px 50px;">
                                                            <tr>
                                                                <td class="sub_field_title">
                                                                    Paypal Account:
                                                                </td>
                                                                <td class="field_input">
                                                                    <input type="text" id="paypal" name="paypal" size="28" value="' . $paypal . '"/>
                                                                    ' . get_hint_box("input_event_paypal:events") . '
                                                                </td>
                                                            </tr><tr><td></td><td class="field_input"><span id="event_paypal_error" class="error_text"></span></td></tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                <br />
                <table>
                     <tr>
                         <td></td>
                         <td style="text-align:left;">
                             <button type="submit">
                                Save
                            </button>
                         </td>
                     </tr>
                </table>
            ' . js_code_wrap('prepareInputsForHints();') . '
          </form>
     </div>';
}

//Show registration form
function show_registration() {
global $CFG, $USER;
    $eventid = clean_myvar_req("eventid", "int");
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());

    if (!user_is_able($USER->userid, "signupforevents", $pageid)) { trigger_error(error_string("no_permission", ["signupforevents"]), E_USER_WARNING); return; }

    $event = get_event($eventid);
    $template = get_event_template($event['template_id']);

    if ($template['folder'] != "none") { //registration template refers to a file
        ob_start();
        include($CFG->dirroot . '/features/events/templates/' . $template['folder'] . '/template.php');
        $form = ob_get_clean();
    } else { //registration template refers to a database style template
        $formlist = $formrows = "";
        $templateform = get_db_result("SELECT * FROM events_templates_forms WHERE template_id='" . $template['template_id'] . "' ORDER BY sort");
        while ($element = fetch_row($templateform)) {
            $opt = $element['optional'] ? '<font size="1.2em" color="blue">(optional)</font> ' : '';
            $formlist .= $formlist == "" ? $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'] : "*" . $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'];

            $display1 = $display2 = $form_element1 = $form_element2 = $error_tag = "";
            switch ($element['type']) {
                case "select":
                    goto skipped;
                case "phone":
                    $display1 = $opt . $element['display'];
                    $form_element1 = create_form_element($element['type'], $element['elementid'], $element['optional'], $element['length']);
                    $error_tag = $element['elementid'];
                    break;
                case "contact":
                    $display1 = $opt . $element['display'];
                    $form_element1 = create_form_element($element['type'], $element['elementid'], $element['optional'], $element['length']) . get_hint_box("input_event_email:events");
                    $error_tag = $element['elementid'];
                    break;
                case "payment":
                    if ($event["fee_full"] == "0") {
                        goto skipped;
                    }
                    $display1 = "Payment Amount: ";
                    $form_element1 = make_fee_options($event['fee_min'], $event['fee_full'], 'payment_amount', '', $event['sale_end'], $event['sale_fee']);
                    $display2 = "Method of Payment: ";
                    $form_element2 = '
                        <select id="payment_method" name="payment_method" size="1" >
                            <option value="">Choose One</option>
                            <option value="PayPal">Pay online using Credit Card/PayPal</option>
                            <option value="Check/Money Order">Check or Money Order</option>
                        </select>';
                    $error_tag = "payment_method";
                    break;
                default:
                    $display1 = $opt . $element['display'];
                    $form_element1 = create_form_element($element['type'], $element['elementid'], $element['optional'], $element['length']) . '<span class="hint">' . $element['hint'] . '</span>';
                    $error_tag = $element['elementid'];
            }

            $optrow = "";
            if ($display2 !== "" || $form_element2 !== "") {
                $optrow = '
                    <tr>
                        <td class="field_title">
                            ' . $display2 . '
                        </td>
                        <td class="field_input">
                            ' . $form_element2 . '
                        </td>
                    </tr>';
            }

            $formrows .= '
                <tr>
                    <td class="field_title">
                        ' . $display1 . '
                    </td>
                    <td class="field_input">
                        ' . $form_element1 . '
                    </td>
                </tr>
                ' . $optrow . '
                <tr>
                    <td></td>
                    <td class="field_input">
                        <span id="' . $error_tag . '_error" class="error_text"></span>
                    </td>
                </tr>';
            skipped:
        }

        $form = create_validation_javascript($formlist, $eventid) . '
            <table style="width:100%">
                ' . $formrows . '
                <tr>
                    <td></td>
                    <td>
                        <input type="button" value="Submit" onclick="submit_registration(\'' . $eventid . '\',\'' . $formlist . '\');" />
                    </td>
                </tr>
            </table>' .
            js_code_wrap('prepareInputsForHints();');
    }

    $code = `
        $(document).keydown(function(e) {
            var nodeName = e.target.nodeName.toLowerCase();
            if (e.which === 8) {
                if ((nodeName === "input" && e.target.type === "text") || nodeName === "textarea") {
                    // do nothing
                } else {
                    e.preventDefault();
                }
            }
        });`;

    echo '
        <div id="registration_div">
            <table class="registration">
                <tr>
                    <td>
                        ' . $template['intro'] . '
                    </td>
                </tr>
            </table>
            ' . $form . '
        </div>' . js_code_wrap($code, "defer", true);;
}

function create_validation_javascript($formlist, $eventid) {
global $CFG;
    $validation_script = 'function validate_fields() {	var valid = true;';
    date_default_timezone_set(date_default_timezone_get());
    $element = explode("*", $formlist);
    $i = 0;
    while (isset($element[$i])) {
        $attribute = explode(":", $element[$i]);

        $ajaxcall = `
            var d = new Date();
            $.ajax({
                async: false,
                type: \'post\',
                url: ' . $CFG->wwwroot . '/features/events/events_ajax.php",
                dataType: \'json\',
                data: "action=unique&elementid=' . $attribute[1] . '&value=" + $("#' . $attribute[1] . '").val() + "&eventid=' . $eventid . '&currTime=" + d.toUTCString(),
            }).done(function (data) {
                $("#' . $attribute[1] . '_error").html("");
                if (!istrue(data)) {
                    $("#' . $attribute[1] . '_error").html(exists_error);
                    valid = false;
                }
            });
        `;
        switch ($attribute[0]) {
            case "text":
                $validation_script .= '
                    if ($("#opt_' . $attribute[1] . '").val() == 0 || ($("#opt_' . $attribute[1] . '").val() != 0 && $("#' . $attribute[1] . '").val().length > 0)) {
                        if (!$("#' . $attribute[1] . '").val().length > 0) {
                            $("#' . $attribute[1] . '_error").html("This is a required field.");
                            valid = false;
                        } else {
                            $("#' . $attribute[1] . '_error").html("");
                        }
                        if (' . $attribute[3] . ' == 0) {
                            var exists_error = "This value already exists in our database.";
                            ' . $ajaxcall . '
                        }
                    }';
                break;
            case "email":
                $validation_script .= '
                    if ($("#opt_' . $attribute[1] . '").val() == 0 || ($("#opt_' . $attribute[1] . '").val() != 0 && $("#' . $attribute[1] . '").val().length > 0)) {
                        //Email address validity test
                        if ($("#' . $attribute[1] . '").val().length > 0) {
                            if (isValidEmail($("#' . $attribute[1] . '").val())) {
                                if (' . $attribute[3] . ' == 0) {
                                    var exists_error = "This email address has already been registered with.";
                                    ' . $ajaxcall . '
                                }
                            } else {
                                $("#' . $attribute[1] . '_error").html("Email address is not valid.");
                                valid = false;
                            }
                        } else {
                            $("#' . $attribute[1] . '_error").html("Email address is required.");
                            valid = false;
                        }
                    }';
                break;
            case "contact":
                $validation_script .= '
                    if ($("#' . $attribute[1] . '").val().length > 0) {
                        if (isValidEmail($("#' . $attribute[1] . '").val())) {
                            $("#' . $attribute[1] . '_error").html("");
                        } else {
                            $("#' . $attribute[1] . '_error").html("Email address is not valid.");
                            valid = false;
                        }
                    } else {
                        $("#' . $attribute[1] . '_error").html("Email address is required.");
                        valid = false;
                    }';
                break;
            case "phone":
                $validation_script .= '
                    if ($("#opt_' . $attribute[1] . '").val() == 0 || ($("#opt_' . $attribute[1] . '").val() != 0 && ($("#' . $attribute[1] . '_1").val().length > 0 || $("#' . $attribute[1] . '_2").val().length > 0 || $("#' . $attribute[1] . '_3").val().length > 0))) {
                        //Phone # validity test
                        if ($("#' . $attribute[1] . '_1").val().length == 3 && $("#' . $attribute[1] . '_2").val().length == 3 && $("#' . $attribute[1] . '_3").val().length == 4) {
                            if (!(IsNumeric($("#' . $attribute[1] . '_1").val()) && IsNumeric($("#' . $attribute[1] . '_2").val()) && IsNumeric($("#' . $attribute[1] . '_3").val()))) {
                                $("#' . $attribute[1] . '_error").html("Not a valid phone #");
                                valid = false;
                            } else {
                                $("#' . $attribute[1] . '_error").html("");
                            }
                        } else {
                            $("#' . $attribute[1] . '_error").html("Phone # is not complete.");
                            valid = false;
                        }
                    }';
                break;
            case "select":
                break;
            case "payment":
                $validation_script .= '
                    if ($("#payment_method").length) {
                        if ($("#payment_method").val() == "") {
                            $("#payment_method_error").html("This is a required field.");
                            valid = false;
                        } else {
                            $("#payment_method_error").html("");
                        }
                    }';
                break;
            case "password":
                //Password validity test
                $validation_script .= '
                    if (!$("#' . $attribute[1] . '").val().length > 4) {
                        if ($("#' . $attribute[1] . '").val().length > 0) {
                            $("#' . $attribute[1] . '_error").html("Password must be between 5-20 characters long.");
                            valid = false;
                        } else if (!$("#' . $attribute[1] . '").val().length > 0) {
                            $("#' . $attribute[1] . '_error").html("Password is required.");
                            valid = false;
                        }
                    } else {
                        if (!checkPassword($("#' . $attribute[1] . '")[0], $("#verify_' . $attribute[1] . '")[0], $("#' . $attribute[1] . '")[0], true)) {
                            $("#' . $attribute[1] . '_error").html("Password and Verify fields must match.");
                            valid = false;
                        } else {
                            $("#' . $attribute[1] . '_error").html("");
                        }

                        if (' . $attribute[3] . ' == 0) {
                            var exists_error = "This value already exists in our database.";
                            ' . $ajaxcall . '
                        }
                    }';
                break;
        }
        $i++;
     }
    $validation_script .= 'return valid; }';
    return js_code_wrap($validation_script, "defer", true);
}

function showcart() {
global $CFG;
    if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

    $redirect = js_code_wrap('window.location = "' . $CFG->wwwroot . '";');
    echo main_body(true);

    $auth_token = $CFG->paypal_auth;

    $pp_hostname = $CFG->paypal ? 'ipnpb.paypal.com' : 'ipnpb.sandbox.paypal.com';

    // read the post from PayPal system and add 'cmd'
    $req = 'cmd=_notify-synch';

    $tx_token = $_GET['tx'];
    $req .= "&tx=$tx_token&at=$auth_token";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$pp_hostname/cgi-bin/webscr");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
    //set cacert.pem verisign certificate path in curl using 'CURLOPT_CAINFO' field here,
    //if your server does not bundled with default verisign certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: $pp_hostname"]);
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) {
        //HTTP ERROR
        echo $redirect;
    } else {
        // parse the data
        $lines = explode("\n", trim($res));
        $keyarray = [];
        if (strcmp ($lines[0], "SUCCESS") == 0) {
            for ($i = 1; $i < count($lines); $i++) {
                $temp = explode("=", $lines[$i],2);
                $keyarray[urldecode($temp[0])] = urldecode($temp[1]);
            }
            // check the payment_status is Completed
            // check that txn_id has not been previously processed
            // check that receiver_email is your Primary PayPal email
            // check that payment_amount/payment_currency are correct
            // process payment
            echo '
                <div style="width: 640px;text-align:center;margin:auto">
                    <h1>Thank You!</h1>
                    Your transaction has been completed, and a receipt for your purchase has been emailed to you.
                    <br />You may log into your account at <a href="https://www.paypal.com">www.paypal.com</a> to view details of this transaction.
                </div>
                <br />';
            echo print_cart($keyarray);
        }
        else if (strcmp ($lines[0], "FAIL") == 0) {
            // log for manual investigation
            echo $redirect;
        } else {
            echo $redirect;
        }
    }
}

function print_cart($items) {
global $CFG;
    $rows = '';
    $i = 0;
    while ($i < $items["num_cart_items"]) {
        $rows .= '
            <tr style="background-color:#FFF1FF;">
                <td style="text-align:left; font-size:.8em;">
                    ' . $items["item_name" . ($i + 1)] . '
                </td>
                <td style="text-align:left; padding:10px; font-size:.8em;">
                    $' . $items["mc_gross_" . ($i + 1)] . '
                </td>
            </tr>
            <tr>
                <td colspan="2"></td>
            </tr>';
        $i++;
    }

    $returnme .= '
        <a href="' . $CFG->wwwroot . '">
            Go back to ' . $CFG->sitename . '
        </a>
        <br /><br />
        <table style="border-collapse:collapse;width:60%; margin-right:auto; margin-left:auto;">
            <tr>
                <td colspan="2">
                    <strong>What you have paid for:</strong>
                </td>
            </tr>
            ' . $rows . '
            <tr>
                <td style="text-align:right;">
                    <strong>Total</strong>
                </td>
                <td style="border-top: 1px solid gray;text-align:left;padding:10px; font-size:.8em;">
                    $' . $items["mc_gross"] . '
                </td>
            </tr>
            <tr>
                <td style="text-align:right;">
                    <strong>Paid</strong>
                </td>
                <td style="text-align:left;padding:10px; font-size:.8em;">
                    $' . $items["payment_gross"] . '
                </td>
            </tr>
        </table>';
    return $returnme;
}
?>
