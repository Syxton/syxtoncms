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

    callfunction();

    echo '<input id="lasthint" type="hidden" />';
	echo get_js_tags(["features/events/events.js"]);

    //echo '</body></html>';
}

function global_template_settings() { 
    $templateid = clean_myvar_req("templateid", "int");
    $feature = "events_template_global";

    //Default Settings
    $default_settings = get_template_settings($templateid, true);

	$template_name = get_db_field("name", "events_templates", "template_id = '$templateid'");

	//Check if any settings exist for this feature
    if ($settings = fetch_settings($feature, $templateid)) {
        echo make_settings_page($settings, $default_settings, "Template ($template_name) Settings");
    } else { // No Settings found...setup default settings
        if (save_batch_settings($default_settings)) {
			global_template_settings();
		}
    }
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
    echo '<div class="dontprint" style="text-align:center;">
            <h3>Search for events by their name.</h3>
            <form onsubmit="$(\'#loading_overlay\').show();
                            ajaxapi(\'/features/events/events_ajax.php\',
                                    \'eventsearch\',
                                    \'&amp;pageid=' . $pageid . '&amp;searchwords=\' + escape($(\'#searchbox\').val()),
                                    function() {
                                        if (xmlHttp.readyState == 4) {
                                            simple_display(\'searchcontainer\');
                                            $(\'#loading_overlay\').hide();
                                        }
                                    }, true); return false;">
		          Event Name <input type="text" id="searchbox" name="searchbox" />&nbsp;<input type="submit" value="Search" />
		      </form>
            <br />
        </div>
        <div id="loading_overlay" class="dontprint" style="text-align:center;position:absolute;width:98%;height:85%;background-color:white;opacity:.6;display:none;">
            <br /><br /><br />
            <img src="' . $CFG->wwwroot . '/images/loading_large.gif" />
        </div>
        <span id="searchcontainer"></span>';
}

function template_manager() {
global $CFG;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    check_for_new_templates();
    echo '<div class="dontprint" style="text-align:center;">
            <h3>Search for templates by their name.</h3>
            <form onsubmit="$(\'#loading_overlay\').show();
                            ajaxapi(\'/features/events/events_ajax.php\',
                                    \'templatesearch\',
                                    \'&amp;pageid=' . $pageid . '&amp;searchwords=\' + escape($(\'#searchbox\').val()),
                                    function() {
                                        if (xmlHttp.readyState == 4) {
                                            simple_display(\'searchcontainer\');
                                            $(\'#loading_overlay\').hide();
                                        }
                                    }, true); return false;">
		          Template Name <input type="text" id="searchbox" name="searchbox" />&nbsp;<input type="submit" value="Search" />
            </form>
            <br />
        </div>
		  <div id="loading_overlay" class="dontprint" style="text-align:center;position:absolute;width:98%;height:85%;background-color:white;opacity:.6;display:none;">
            <br /><br /><br />
            <img src="' . $CFG->wwwroot . '/images/loading_large.gif" />
        </div>
		  <span id="searchcontainer"></span>';
}

function application_manager() {
global $CFG;
    $pageid = clean_myvar_opt("pageid", "int", get_pageid());
    $export = "";
    if ($archive = get_db_result("SELECT * FROM events_staff_archive WHERE pageid='$pageid' GROUP BY year ORDER BY year")) {
        $i = 0;
        $values = new \stdClass;
		while ($vals = fetch_row($archive)) {
            $values->$i = new \stdClass;
			$values->$i->year = $vals["year"];
			$i++;
		}

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
        $export = '<div style="float:right;">
                    ' . make_select($params) . '
                    <a href="javascript: void(0);"
						onclick="ajaxapi(\'/features/events/events_ajax.php\',
										\'export_staffapp\',
										\'&amp;pageid=' . $pageid . '&amp;year=\'+$(\'#appyears\').val(),
										function() {
											if (xmlHttp.readyState == 4) {
												run_this();
											}
										},
										true
								);">
                        <img src="' . $CFG->wwwroot . '/images/csv.png" title="Export" />
                    </a><div></div>
                    </div>';
    }

    echo '<div class="dontprint"><form onsubmit="$(\'#loading_overlay\').show(); ajaxapi(\'/features/events/events_ajax.php\',\'appsearch\',\'&amp;pageid=' . $pageid . '&amp;searchwords=\'+escape($(\'#searchbox\').val()),function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true); return false;">
	Applicant Search <input type="text" id="searchbox" name="searchbox" />&nbsp;<input type="submit" value="Search" /><div style="float:right;width: 150px;">Search for applicants by their name.</div>
	' . $export . '
    </form></div>
	<div id="loading_overlay" class="dontprint" style="text-align:center;position:absolute;width:98%;height:85%;background-color:white;opacity:.6;display:none;"><br /><br /><br /><img src="' . $CFG->wwwroot . '/images/loading_large.gif" /></div>
	<span id="searchcontainer"></span>';
}

function staff_emailer() {
global $CFG;
    echo '<div class="dontprint"><form onsubmit="$(\'#loading_overlay\').show(); ajaxapi(\'/features/events/events_ajax.php\',\'sendstaffemails\',\'&amp;sendemails=\'+$(\'#sendemails\').prop(\'checked\')+\'&amp;stafflist=\'+encodeURIComponent($(\'#stafflist\').val()),function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true); return false;">
	<div style="text-align:center;margin:5px;font-weight: bolder;">Staff Status Checker</div>
    <div style="float:right;line-height:35px;">
		<label class="rowTitle" for="workerconsentsig">Send Emails</label>
        <input id="sendemails" name="sendemails" type="checkbox" />
	</div>
    <div style="text-align:center;width: 50%;">List of email addresses of staff, one email address per line.</div>
    <textarea rows="15" id="stafflist" name="stafflist" style="width:100%;max-width:500px"></textarea>
    <div style="text-align:center;margin:5px;"><input type="submit" value="Process" /></div>
    </form></div>
	<div id="loading_overlay" class="dontprint" style="text-align:center;position:absolute;width:98%;background-color:white;opacity:.6;display:none;"><br /><br /><br /><img src="' . $CFG->wwwroot . '/images/loading_large.gif" /></div>
	<span id="searchcontainer"></span>';
}

function pay() {
global $CFG;
    $regcode = clean_myvar_opt("regcode", "string", "");
    $modal = clean_myvar_opt("modal", "string", false);

    if (!$modal) {
		echo get_js_tags(["jquery"]);
        echo main_body(true) . '<br /><br />';
    }

	echo js_code_wrap('window.onload = function () { if ($("#code").val() != "") { lookup_reg($("#code").val()); } }', "", true);
    echo '
        <div style="text-align:center;padding:15px;">
            <h3>' . $CFG->sitename . ' Registration Lookup</h3><br />
            <form id="payarea_form" onsubmit="lookup_reg($(\'#code\').val()); return false;">
            Enter your Registration ID: <input type="text" id="code" size="35" value="' . $regcode . '" /> <input type="submit" value="Submit" />
            </form>
        </div>
        <div id="payarea" style="padding:15px;"></div>
    ';
}

function event_request_form() {
global $CFG;
    $featureid = clean_myvar_opt("featureid", "int", false);
    if ($featureid) {
        $pageid = get_db_field("pageid", "pages_features", "featureid=$featureid");
        if (!$settings = fetch_settings("events", $featureid, $pageid)) {
  			save_batch_settings(default_settings("events", $pageid, $featureid));
  			$settings = fetch_settings("events", $featureid, $pageid);
  		}
        $event_begin_date = $event_end_date = 'true';
        $locationid = $settings->events->$featureid->allowrequests->setting;
        $request_text = $settings->events->$featureid->request_text->setting;

        if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

  		echo create_validation_script("request_form" , "ajaxapi('/features/events/events_ajax.php','event_request',create_request_string('request_form'),function() { simple_display('request_form_div'); });") . '
  		<div class="formDiv" id="request_form_div">
        <p align="center"><b><font size="+1">Event Request Form</font></b></p><br />' . $request_text . '<br />If you would like to have your event hosted at ' . get_db_field("location", "events_locations", "id=$locationid") . ' please fill out the below form and we will get back to you.<br />
  			<br /><br />
  			<form name="request_form" id="request_form">
                <input type="hidden" id="featureid" name="featureid" value="' . $featureid . '" />
  				<fieldset class="formContainer">
                    <div class="rowContainer">
  						<label for="name">Contact Name</label><input type="text" id="name" name="name" data-rule-required="true" data-msg-required="' . error_string('valid_request_name:events') . '" /><div class="tooltipContainer info">' . get_help("input_request_name:events") . '</div><br />
  					</div>
  					<div class="rowContainer">
  						<label for="email">Email Address</label><input type="text" id="email" name="email" data-rule-required="true" data-rule-email="true" data-msg-required="' . error_string('valid_request_email:events') . '" data-msg-email="' . error_string('valid_request_email_invalid:events') . '" /><div class="tooltipContainer info">' . get_help("input_request_email:events") . '</div><br />
  					</div>
     			          <div class="rowContainer">
					      <label for="phone">Phone</label><input type="text" id="phone" name="phone" data-rule-required="true"  data-rule-phone="true" data-msg-required="' . error_string('valid_request_phone:events') . '" data-msg-phone="' . error_string('valid_request_phone_invalid:events') . '" /><div class="tooltipContainer info">' . get_help("input_request_phone:events") . '</div><br />
                    </div>
                    <div class="rowContainer">
  						<label for="event_name">Event Name</label><input type="text" id="event_name" name="event_name" data-rule-required="true" data-msg-required="' . error_string('valid_request_event_name:events') . '" /><div class="tooltipContainer info">' . get_help("input_request_event_name:events") . '</div><br />
  					</div>
      				<div class="rowContainer">
      					<label for="startdate">Event Start Date</label><input type="text" id="startdate" name="startdate" data-rule-required="true" data-rule-date="true" data-rule-futuredate="true" date-rule-ajax1="features/events/events_ajax.php::request_date_open::&featureid=' . $featureid . '&startdate=::true" data-msg-ajax1="' . error_string('valid_request_date_used:events') . '" data-msg-futuredate="' . error_string('valid_request_date_future:events') . '" /><div class="tooltipContainer info">' . get_help("input_request_startdate:events") . '</div>
      				</div>
      				<div class="rowContainer">
      					<label>&nbsp;</label><span style="font-size:.8em;">through</span>
      				</div>
      				<div class="rowContainer">
      					<label for="enddate">Event End Date</label><input type="text" id="enddate" name="enddate" data-rule-date="true" data-rule-futuredate="#startdate" data-rule-ajax1="features/events/events_ajax.php::request_date_open::&featureid=' . $featureid . '&startdate=#startdate&enddate=::true" data-msg-ajax1="' . error_string('valid_request_date_used:events') . '" data-msg-futuredate="' . error_string('valid_request_date_later:events') . '" /><div class="tooltipContainer info">' . get_help("input_request_enddate:events") . '</div>
      				</div>
                    <div class="rowContainer">
					      <label for="participants"># of participants</label><input type="text" id="participants" name="participants" data-rule-required="true" data-rule-number="true" /><div class="tooltipContainer info">' . get_help("input_request_participants:events") . '</div>
				      </div>
  					<div class="rowContainer">
  						<label for="description">Event Description</label><textarea rows="10" id="description" name="description" data-rule-required="true" data-msg-required="' . error_string('valid_request_description:events') . '" /><div class="tooltipContainer info">' . get_help("input_request_description:events") . '</div><br />
  						</div>
  						<input class="submit" name="submit" type="submit" onmouseover="this.focus();" value="Submit" />
  				</fieldset>
  			</form>
  		</div>';
    } else { echo "Sorry, This form is not available"; }
}

function staff_application() {
global $CFG, $USER;
    if (isset($USER->userid)) {
        $staff = get_db_row("SELECT * FROM events_staff WHERE userid='$USER->userid'"); //Update existing event

        if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

  		echo create_validation_script("staffapplication_form" , "ajaxapi('/features/events/events_ajax.php','event_save_staffapp',create_request_string('staffapplication_form'),function() { simple_display('staffapplication_form_div'); });");
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

	if ($eventid) { // Update existing event
		$heading = "Edit Event";
		echo '<input type="hidden" id="eventid" value="' . $eventid . '" />';

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
		$start_reg = isset($event['start_reg']) ? "false,'YYYY/MM/DD','" . date('Y/m/d', $event['start_reg']) . "'" : 'true';
		$stop_reg = isset($event['stop_reg']) ? "false,'YYYY/MM/DD','" . date('Y/m/d', $event['stop_reg']) . "'" : 'true';
		$sale_end = $event['sale_end'] != "" ? "false,'YYYY/MM/DD','" . date('Y/m/d', $event['sale_end']) . "'" : 'true';
		$template = isset($event['template_id']) ? $event['template_id'] : false;
        $template_settings_form = get_template_settings_form($template, $eventid);
		$event_begin_date = isset($event['event_begin_date']) ? "false,'YYYY/MM/DD','" . date('Y/m/d', $event['event_begin_date']) . "'" : 'true';
		$event_end_date = isset($event['event_end_date']) ? "false,'YYYY/MM/DD','" . date('Y/m/d', $event['event_end_date']) . "'" : 'true';
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
		$hidden_limits = '<input type="hidden" id="hard_limits" value="" /><input type="hidden" id="soft_limits" value="" />';
		$phone = [0 => "", 1 => "", 2 => ""];
		$start_reg = $stop_reg = $sale_end = $event_begin_date = $event_end_date = 'true';
		$end_date_display = $limits_display = $times_display = $reg_display = $fee_display = 'none';
		$max_users = $cost = '0';
		$checksaddress = $paypal = $payableto = $name = $contact = $email = $byline = $description = $siteviewable_yes = $workers_yes = $multiday_yes = $allday_no = $reg_yes = $fee_yes = $allowinpage_yes = $limits_yes = $event_end_time_form = $fee_min = $fee_full = $sale_fee = $template_settings = "";
		$event_begin_time_form = get_possible_times('begin_time');
		$mycategories = get_my_category();
		$mylocations = get_my_locations($USER->userid);
		$siteviewable_no = $workers_no = $multiday_no = $allday_yes = $reg_no = $fee_no = $allowinpage_no = $limits_no = "selected";
        $auto_allowinpage_display = "none";
	}

	echo get_js_tags(["popupcal"]);
	echo '
	<h3>' . $heading . '</h3>
    <div id="add_event_div">
        <form action="javascript: void(0);" onsubmit="new_event_submit(\'' . $pageid . '\');">
            <table style="width:100%">
          		<tr>
          			<td class="field_title" style="width:115px;">
          				Event Name:
          			</td>
          			<td class="field_input">
          				<input type="text" id="event_name" size="30" value="' . stripslashes($name) . '"/>
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
          				<textarea id="byline" cols="40" rows="5">' . stripslashes($byline) . '</textarea>
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
                        ' . get_editor_box(["initialvalue" => stripslashes($description), "height" => "300"]) . '
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
          				<input type="text" id="contact" size="30" value="' . stripslashes($contact) . '"/>
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
          				<input type="text" id="email" size="30" value="' . stripslashes($email) . '"/>
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
          				<input class="phone1" id="phone_1" type="text" onkeyup="movetonextbox(event);" size="1" maxlength="3" value="' . $phone[0] . '" />
						-
                        <input class="phone2" id="phone_2" type="text" onkeyup="movetonextbox(event);" size="1" maxlength="3" value="' . $phone[1] . '" />
						-
                        <input class="phone3" id="phone_3" type="text" size="2" maxlength="4" value="' . $phone[2] . '" />
          			</td>
          		</tr><tr><td></td><td class="field_input"><span id="phone_error" class="error_text"></span></td></tr>
            </table>
            <table style="width:100%; display:' . $global_display . '">
          		<tr>
          			<td class="field_title" style="width:115px;">
          				Request Site Event:
          			</td>
          			<td class="field_input">
          				<select id="siteviewable" onchange="if (this.value==0) { hide_section(\'auto_allowinpage\'); document.getElementById(\'allowinpage\').value=0; } else { show_section(\'auto_allowinpage\'); }" ><option value="0" ' . $siteviewable_no . '>No</option><option value="1" ' . $siteviewable_yes . '>Yes</option></select>
          				' . get_hint_box("input_event_siteviewable:events") . '
          			</td>
          		</tr><tr><td></td><td class="field_input"><span id="event_name_error" class="error_text"></span></td></tr>
            </table>
            <br />
            <div class="dotted">
                <table style="width:100%;">
              		<tr>
              			<td class="field_title" style="width:115px;">
              				Location:
              			</td>
              			<td class="field_input">
              				<span id="select_location">
								' . $mylocations . '
								' . get_hint_box("input_event_location:events") . '
							</span>
              				<button id="addtolist" type="button" style="display:inline;float:right;" 
									onclick="hide_show_buttons(\'addtolist\');
											 hide_show_buttons(\'hide_menu\');
											 hide_show_buttons(\'add_location_div\', true);">
								Add to list
							</button>
              				<button id="hide_menu" type="button" style="display:none;float:right;"
									onclick="hide_show_buttons(\'hide_menu\');
											 hide_show_buttons(\'addtolist\');
											 hide_show_buttons(\'add_location_div\', true);">
								Hide Menu
							</button>
              			</td>
              		</tr><tr><td></td><td class="field_input"><span id="location_error" class="error_text"></span></td></tr>
                </table>
                <table>
              		<tr>
                        <td colspan="2">
                      		<span id="add_location_div" style="display:none">
                      			<table>
                      				<tr>
                      					<td style="width:115px;"></td>
                      					<td class="sub_field_title" style="width:400px;padding: 10px;">
                      						<button type="button" id="new_button" style="margin-right: 10px;margin-left: 0px;display:inline;float:left" 
													onclick="hide_show_buttons(\'browse_button\');
															 hide_show_buttons(\'or\');
															 hide_show_buttons(\'location_menu\', true);
															 add_location_form(\'new\', \'' . $eventid . '\');">
												<span>
													<img src="' . $CFG->wwwroot . '/images/add.png" title="Add Location" alt="Add Location">
													<span> Toggle Add Form</span>
												</span>
															 
											</button>
                      						<span id="or" style="display:inline">&nbsp; or &nbsp;</span>
                      						<button type="button" id="browse_button" style="margin-right: 0px;margin-left: 10px;display:inline"
													onclick="hide_show_buttons(\'new_button\');
															 hide_show_buttons(\'or\');
															 hide_show_buttons(\'location_menu\', true);
															 add_location_form(\'existing\', \'' . $eventid . '\');">
												<span>
													<img src="' . $CFG->wwwroot . '/images/folder.png" title="Add Location" alt="Add Location">
													<span> Toggle Existing List</span>
												</span>
											</button>
                      					</td>
                      				</tr>
                      				<tr>
                      					<td></td>
                      					<td>
                      						<div id="location_menu" style="display:none;text-align:center"></div>
                      					</td>
                      				</tr>
                      			</table>
                      		</span>
              			 </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <span id="location_status" style="display:inline"></span>
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
                      					Worker Application:
                      				</td>
                      				<td class="field_input">
                      					<select id="workers"><option value="0" ' . $workers_no . '>No</option><option value="1" ' . $workers_yes . '>Yes</option></select>
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
                      					<select id="multiday" onchange="hide_show_buttons(\'event_end_date_div\'); if (document.getElementById(\'begin_time\').value != \'\') { get_end_time(document.getElementById(\'begin_time\').value) }" ><option value="0" ' . $multiday_no . '>No</option><option value="1" ' . $multiday_yes . '>Yes</option></select>
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
													' . js_code_wrap('DateInput(\'event_begin_date\', ' . $event_begin_date . ');') . '
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
														' . js_code_wrap('DateInput(\'event_end_date\', ' . $event_end_date . ');') . '
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
                      					<select id="allday" onchange="hide_show_buttons(\'event_times_div\');" /><option value="1" ' . $allday_yes . '>Yes</option><option value="0" ' . $allday_no . '>No</option></select>
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
              				<select id="reg" onchange="hide_show_buttons(\'registration_panel\');" ><option value="0" ' . $reg_no . '>No</option><option value="1" ' . $reg_yes . '>Yes</option></select>
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
                          						<select id="allowinpage" /><option value="0" ' . $allowinpage_no . '>No</option><option value="1" ' . $allowinpage_yes . '>Yes</option></select>
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
                                    <div id="template_settings_div">' . $template_settings_form . '</div>
                                </div>
                  				<table style="margin:0px 0px 0px 50px;">
                                    <tr>
                      					<td class="sub_field_title">
                      						Open Registration Date:
                      					</td>
                      					<td class="field_input">
											' . js_code_wrap('DateInput(\'start_reg\', ' . $start_reg . ');') . '
                      					</td>
                                    </tr><tr><td></td><td class="field_input"><span id="start_reg_error" class="error_text"></span></td></tr>
                  				</table>
                                <table style="margin:0px 0px 0px 50px;">
                              		<tr>
                              			<td class="sub_field_title">
                              				Close Registration Date:
                              			</td>
                              			<td class="field_input">
											' . js_code_wrap('DateInput(\'stop_reg\', ' . $stop_reg . ');') . '
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
												<select id="limits" onchange="hide_show_buttons(\'limits_div\');" ><option value="0" ' . $limits_no . '>No</option><option value="1" ' . $limits_yes . '>Yes</option></select>
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
																<input type="text" id="max" size="4" maxlength="4" value="' . $max_users . '"/>
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
																<input type="button" value="Custom Limit Form"
																	onclick="if (document.getElementById(\'template\').value) { get_limit_form(document.getElementById(\'template\').value); }else{ alert(\'Please select a template first.\'); } " />
																<br />
																<div id="limit_form">
																</div>
																<div id="custom_limits" style="font-size:.7em;">
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
												<select id="fee" onchange="hide_show_buttons(\'fee_div\');" ><option value="0" ' . $fee_no . '>No</option><option value="1" ' . $fee_yes . '>Yes</option></select>
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
																<input type="text" id="min_fee" size="4" value="' . $fee_min . '"/>
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
																<input type="text" id="full_fee" size="4" value="' . $fee_full . '"/>
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
																<input type="text" id="sale_fee" size="4" value="' . $sale_fee . '"/>
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
																' . js_code_wrap('DateInput(\'sale_end\', ' . $sale_end . ');') . '
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
																<input type="text" id="payableto" size="28" value="' . $payableto . '"/>
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
																<textarea id="checksaddress" cols="21" rows="3">' . $checksaddress . '</textarea>
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
																<input type="text" id="paypal" size="28" value="' . $paypal . '"/>
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
	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='" . $event['template_id'] . "'");
	$formlist = ""; $form = "";

    $returnme = '<div id="registration_div">
                    <table class="registration"><tr><td>' . $template['intro'] . ' </td></tr></table>';

	if ($template['folder'] != "none") { //registration template refers to a file
        ob_start();
        include($CFG->dirroot . '/features/events/templates/' . $template['folder'] . '/template.php');
        $returnme .= ob_get_clean();
	} else { //registration template refers to a database style template
		$form = '<table style="width:100%">';
		$templateform = get_db_result("SELECT * FROM events_templates_forms WHERE template_id='" . $template['template_id'] . "' ORDER BY sort");
		while ($element = fetch_row($templateform)) {
			$opt = $element['optional'] ? '<font size="1.2em" color="blue">(optional)</font> ' : '';
			$formlist .= $formlist == "" ? $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'] : "*" . $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'];
			if ($element['type'] == 'select') {
			} elseif ($element['type'] == 'phone') {
				$form .= '<tr><td class="field_title">' . $opt . $element['display'] . ': </td><td class="field_input" style="width:70%">' . create_form_element($element['type'], $element['elementid'], $element['optional'], $element['length'], false) . '</td></tr>';
				$form .= '<tr><td></td><td class="field_input"><span id="' . $element['elementid'] . '_error" class="error_text"></span></td></tr>';
			} elseif ($element['type'] == 'payment') {
				if ($event["fee_full"] != "0") {
				$form .= '
  					<tr>
  						<td class="field_title">Payment Amount:</td>
  						<td class="field_input">' . make_fee_options($event['fee_min'], $event['fee_full'], 'payment_amount', '', $event['sale_end'], $event['sale_fee']) . '</td>
  					</tr>
  					<tr>
  						<td class="field_title">Method of Payment:</td>
  						<td class="field_input">
  							<select id="payment_method" name="payment_method" size="1" >
  								<option value="">Choose One</option>
  								<option value="PayPal">Pay online using Credit Card/PayPal</option>
  								<option value="Check/Money Order">Check or Money Order</option>
  							</select>
  						</td>
  					</tr>
  					<tr><td></td><td class="field_input"><span id="payment_method_error" class="error_text"></span></td></tr>';
				}
			} elseif ($element['type'] == 'contact') {
				$form .= '<tr><td class="field_title">' . $opt . $element['display'] . ': </td><td class="field_input" style="width:70%">' . create_form_element($element['type'], $element['elementid'], $element['optional'], $element['length'], false) . '' . get_hint_box("input_event_email:events") . '</td></tr>';
				$form .= '<tr><td></td><td class="field_input"><span id="' . $element['elementid'] . '_error" class="error_text"></span></td></tr>';
			} else {
				$form .= '<tr><td class="field_title">' . $opt . $element['display'] . ': </td><td class="field_input" style="width:70%">' . create_form_element($element['type'], $element['elementid'], $element['optional'], $element['length'], false) . '<span class="hint">' . $element['hint'] . '</td></tr>';
				$form .= '<tr><td></td><td class="field_input"><span id="' . $element['elementid'] . '_error" class="error_text"></span></td></tr>';
			}
		}
		$form .= '<tr><td></td><td><input type="button" value="Submit" onclick="submit_registration(\'' . $eventid . '\',\'' . $formlist . '\');" /></td></tr></table>';
        $returnme .= create_validation_javascript($formlist, $eventid) . $form . '</div>' . js_code_wrap('prepareInputsForHints();');
	}

    $returnme .= '</div>'; //end registration div

    $code = '$(document).keydown(function(e) {
				var nodeName = e.target.nodeName.toLowerCase();

				if (e.which === 8) {
					if ((nodeName === "input" && e.target.type === "text") || nodeName === "textarea") {
						// do nothing
					} else {
						e.preventDefault();
					}
				}
				});';
	$returnme .= js_code_wrap($code, "defer", true);
	echo $returnme;
}

function create_validation_javascript($formlist, $eventid) {
global $CFG;
    $validation_script = 'function validate_fields() {	var valid = true;';
    date_default_timezone_set(date_default_timezone_get());
    $element = explode("*", $formlist);
    $i = 0;
    while (isset($element[$i])) {
  		$attribute = explode(":", $element[$i]);
  		switch ($attribute[0]) {
  		case "text":
  			$validation_script .= '
  			if (document.getElementById(\'opt_' . $attribute[1] . '\').value == 0 || (document.getElementById("opt_' . $attribute[1] . '").value != 0 && document.getElementById("' . $attribute[1] . '").value.length > 0)) {
  				if (!document.getElementById("' . $attribute[1] . '").value.length > 0) {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "This is a required field.";
  						valid = false;
  					} else { document.getElementById("' . $attribute[1] . '_error").innerHTML = ""; }
    				if (' . $attribute[3] . ' == 0) {
      				// Build the URL to connect to
      					var url = "' . $CFG->wwwroot . '/features/events/events_ajax.php?action=unique&elementid=' . $attribute[1] . '&value="+document.getElementById("' . $attribute[1] . '").value + "&eventid=" + ' . $eventid . ';
      				// Open a connection to the server\
      				   var d = new Date();
      					xmlHttp.open("GET", url + "&currTime=" + d.toUTCString(), false);
      					// Send the request
      				xmlHttp.send(null);
  					if (!istrue()) {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "This value already exists in our database.";
  						valid = false;
  					} else { document.getElementById("' . $attribute[1] . '_error").innerHTML = ""; }
  				}
  			}';
  			  break;
  		case "email":
  			$validation_script .= '
  			if (document.getElementById("opt_' . $attribute[1] . '").value == 0 || (document.getElementById("opt_' . $attribute[1] . '").value != 0 && document.getElementById("' . $attribute[1] . '").value.length > 0)) {
  				//Email address validity test
  				if (document.getElementById("' . $attribute[1] . '").value.length > 0) {
  					if (echeck(document.getElementById("' . $attribute[1] . '").value)) {
  						if (' . $attribute[3] . ' == 0) {
      						// Build the URL to connect to
      							var url = "' . $CFG->wwwroot . '/features/events/events_ajax.php?action=unique&elementid=' . $attribute[1] . '&value="+document.getElementById("' . $attribute[1] . '").value + "&eventid=" + ' . $eventid . ';
      						// Open a connection to the server\
      						   var d = new Date();
      							xmlHttp.open("GET", url + "&currTime=" + d.toUTCString(), false);
      							// Send the request
      						xmlHttp.send(null);
  							if (!istrue()) {
  								document.getElementById("' . $attribute[1] . '_error").innerHTML = "This email address has already been registered with.";
  								valid = false;
  							} else {	document.getElementById("' . $attribute[1] . '_error").innerHTML = ""; }
  						}
  						} else {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "Email address is not valid.";
  						valid = false;
  					}
  				} else {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "Email address is required.";
  						valid = false;
  					}
  			}';
  			break;
  		case "contact":
  			$validation_script .= '
  				if (document.getElementById("' . $attribute[1] . '").value.length > 0) {
  					if (echeck(document.getElementById("' . $attribute[1] . '").value)) {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "";
  						} else {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "Email address is not valid.";
  						valid = false;
  					}
  				} else {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "Email address is required.";
  						valid = false;
  					}
  					';
  			break;
  		case "phone":
  			$validation_script .= '
  			if (document.getElementById("opt_' . $attribute[1] . '").value == 0 || (document.getElementById("opt_' . $attribute[1] . '").value != 0 && (document.getElementById("' . $attribute[1] . '_1").value.length > 0 || document.getElementById("' . $attribute[1] . '_2").value.length > 0 || document.getElementById("' . $attribute[1] . '_3").value.length > 0))) {
  				//Phone # validity test
  				if (document.getElementById("' . $attribute[1] . '_1").value.length == 3 && document.getElementById("' . $attribute[1] . '_2").value.length == 3 && document.getElementById("' . $attribute[1] . '_3").value.length == 4) {
  					if (!(IsNumeric(document.getElementById("' . $attribute[1] . '_1").value) && IsNumeric(document.getElementById("' . $attribute[1] . '_2").value) && IsNumeric(document.getElementById("' . $attribute[1] . '_3").value))) {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "Not a valid phone #";
  							valid = false;
  					} else { document.getElementById("' . $attribute[1] . '_error").innerHTML = ""; }
  				} else {
  						document.getElementById("' . $attribute[1] . '_error").innerHTML = "Phone # is not complete.";
  						valid = false;
  					}
  			}
  			';
  			break;
  		case "select":
  			  break;
  		case "payment":
  			$validation_script .= '
  			if (document.getElementById(\'payment_method\')) {
  				if (document.getElementById(\'payment_method\').value == "") {
  						document.getElementById("payment_method_error").innerHTML = "This is a required field.";
  						valid = false;
  				} else { document.getElementById("payment_method_error").innerHTML = ""; }
  			}
  			';
  		break;
  		case "password":
  		//Password validity test
  		$validation_script .= '
    		if (!document.getElementById("' . $attribute[1] . '").value.length > 4) {
  				if (document.getElementById("' . $attribute[1] . '").value.length > 0) {
  				document.getElementById("' . $attribute[1] . '_error").innerHTML = "Password must be between 5-20 characters long.";
  					valid = false;
  				}else if (!document.getElementById("' . $attribute[1] . '").value.length > 0) {
  					document.getElementById("' . $attribute[1] . '_error").innerHTML = "Password is required.";
  					valid = false;
  				}
  		} else {
    			if (!checkPassword(document.getElementById("' . $attribute[1] . '"),document.getElementById("verify_' . $attribute[1] . '"),document.getElementById("' . $attribute[1] . '"), true)) {
    				document.getElementById("' . $attribute[1] . '_error").innerHTML = "Password and Verify fields must match."
    				valid = false;
    			} else { document.getElementById("' . $attribute[1] . '_error").innerHTML = ""; }

            if (' . $attribute[3] . ' == 0) {
      			// Build the URL to connect to
      				var url = "' . $CFG->wwwroot . '/features/events/events_ajax.php?action=unique&elementid=' . $attribute[1] . '&value="+document.getElementById("' . $attribute[1] . '").value + "&eventid=" + ' . $eventid . ';
      			// Open a connection to the server\
      			   var d = new Date();
      				xmlHttp.open("GET", url + "&currTime=" + d.toUTCString(), false);
      				// Send the request
      			xmlHttp.send(null);
  				if (!istrue()) {
  					document.getElementById("' . $attribute[1] . '_error").innerHTML = "This value already exists in our database.";
  					valid = false;
  				} else { document.getElementById("' . $attribute[1] . '_error").innerHTML = ""; }
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
    $returnme = '<a href="' . $CFG->wwwroot . '">Go back to ' . $CFG->sitename . '</a><br /><br /><table style="border-collapse:collapse;width:60%; margin-right:auto; margin-left:auto;"><tr><td colspan=2><b>What you have paid for:</b></td></tr>';
	$i = 0;
	while ($i < $items["num_cart_items"]) {
		$returnme .= '<tr style="background-color:#FFF1FF;"><td style="text-align:left; font-size:.8em;">' . $items["item_name" . ($i + 1)] . '</td><td style="text-align:left; padding:10px; font-size:.8em;">$' . $items["mc_gross_" . ($i + 1)] . '</td></tr><td colspan="2"></td></tr>';
		$i++;
	}
	$returnme .= '<tr><td style="text-align:right;"><b>Total</b></td><td style="border-top: 1px solid gray;text-align:left;padding:10px; font-size:.8em;">$' . $items["mc_gross"] . '</td></tr><td style="text-align:right;"><b>Paid</b></td><td style="text-align:left;padding:10px; font-size:.8em;">$' . $items["payment_gross"] . '</td></tr></table>';
	return $returnme;
}
?>
