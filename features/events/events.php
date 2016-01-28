<?php
/***************************************************************************
* events.php - Events page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 1/28/2016
* Revision: 1.4.7
***************************************************************************/
if(empty($_POST["aslib"])){
    if(!isset($CFG)){ include('../header.php'); } 
    if(!isset($EVENTSLIB)){ include_once($CFG->dirroot . '/features/events/eventslib.php'); }

    callfunction();
    
    echo '
        <input id="lasthint" type="hidden" />
        <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'features/events&amp;f=events.js"></script>
    ';
    
    echo '</body></html>';
}


function events_settings(){
global $CFG,$MYVARS,$USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "events";

	//Default Settings	
	$default_settings = default_settings($feature,$pageid,$featureid);
	$setting_names = get_setting_names($default_settings);
    
	//Check if any settings exist for this feature
	if($settings = fetch_settings($feature,$featureid,$pageid)){
        echo make_settings_page($setting_names,$settings,$default_settings,$feature,$featureid,$pageid);
	}else{ //No Settings found...setup default settings
		if(make_or_update_settings_array($default_settings)){ events_settings(); }
	}
}

function event_manager(){
global $CFG,$MYVARS,$USER;
	
    echo '<div class="dontprint"><form onsubmit="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';ajaxapi(\'/features/events/events_ajax.php\',\'eventsearch\',\'&amp;pageid='.$MYVARS->GET["pageid"].'&amp;searchwords=\'+escape(document.getElementById(\'searchbox\').value),function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); document.getElementById(\'loading_overlay\').style.visibility=\'hidden\'; }},true); return false;">
	Event Search <input type="text" id="searchbox" name="searchbox" />&nbsp;<input type="submit" value="Search" />&nbsp;&nbsp;Search for events by their name.
	<br /></form></div>
	<div id="loading_overlay" class="dontprint" style="text-align:center;position:absolute;width:98%;height:85%;background-color:white;opacity:.6;visibility:hidden;"><br /><br /><br /><img src="' . $CFG->wwwroot . '/images/loading_large.gif" /></div>
	<span id="searchcontainer" style="padding:5px; display:block; width:99%;"></span>';
}

function template_manager(){
global $CFG,$MYVARS,$USER;
	
    echo '<div class="dontprint"><form onsubmit="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';ajaxapi(\'/features/events/events_ajax.php\',\'templatesearch\',\'&amp;pageid='.$MYVARS->GET["pageid"].'&amp;searchwords=\'+escape(document.getElementById(\'searchbox\').value),function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); document.getElementById(\'loading_overlay\').style.visibility=\'hidden\'; }},true); return false;">
	Template Search <input type="text" id="searchbox" name="searchbox" />&nbsp;<input type="submit" value="Search" />&nbsp;&nbsp;Search for templates by their name.
	<br /></form></div>
	<div id="loading_overlay" class="dontprint" style="text-align:center;position:absolute;width:98%;height:85%;background-color:white;opacity:.6;visibility:hidden;"><br /><br /><br /><img src="' . $CFG->wwwroot . '/images/loading_large.gif" /></div>
	<span id="searchcontainer" style="padding:5px; display:block; width:99%;"></span>';
}

function application_manager(){
global $CFG,$MYVARS,$USER;
	
    echo '<div class="dontprint"><form onsubmit="document.getElementById(\'loading_overlay\').style.visibility=\'visible\';ajaxapi(\'/features/events/events_ajax.php\',\'appsearch\',\'&amp;pageid='.$MYVARS->GET["pageid"].'&amp;searchwords=\'+escape(document.getElementById(\'searchbox\').value),function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); document.getElementById(\'loading_overlay\').style.visibility=\'hidden\'; }},true); return false;">
	Applicant Search <input type="text" id="searchbox" name="searchbox" />&nbsp;<input type="submit" value="Search" />&nbsp;&nbsp;Search for applicants by their name.
	<br /></form></div>
	<div id="loading_overlay" class="dontprint" style="text-align:center;position:absolute;width:98%;height:85%;background-color:white;opacity:.6;visibility:hidden;"><br /><br /><br /><img src="' . $CFG->wwwroot . '/images/loading_large.gif" /></div>
	<span id="searchcontainer" style="padding:5px; display:block; width:99%;"></span>';
}

function pay(){
global $CFG,$MYVARS,$USER;	
    $regcode = isset($MYVARS->GET["regcode"]) ? $MYVARS->GET["regcode"] : "";
    
    if(empty($MYVARS->GET["modal"])){
        echo '<script type="text/javascript" src="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts&amp;f=jquery.min.js"></script>'.main_body(true).'<br /><br />';
    }
    
    echo '
        <div style="text-align:center;padding:15px;">
            <h3>'.$CFG->sitename.' Registration Lookup</h3><br />
            <form id="payarea_form" onsubmit="lookup_reg($(\'#code\').val()); return false;">
            Enter your Registration ID: <input type="text" id="code" size="35" value="'.$regcode.'" /> <input type="submit" value="Submit" />
            </form>
        </div>
        <div id="payarea" style="padding:15px;">
        </div>
        <script type="text/javascript">
        if($("#code").val() != ""){
            lookup_reg($("#code").val());
        }
        </script>
    ';
}

function event_request_form(){
global $CFG,$MYVARS,$USER;
    $featureid = $MYVARS->GET["featureid"];
    if(isset($featureid)){
        $pageid = get_db_field("pageid","pages_features","featureid=$featureid");
        if(!$settings = fetch_settings("events",$featureid,$pageid)){
    		make_or_update_settings_array(default_settings("events",$pageid,$featureid));
    		$settings = fetch_settings("events",$featureid,$pageid);
    	}
        $event_begin_date = $event_end_date = 'true';
        $locationid = $settings->events->$featureid->allowrequests->setting;
        $request_text = $settings->events->$featureid->request_text->setting;
        
        if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }
        	
    	echo create_validation_script("request_form" , "ajaxapi('/features/events/events_ajax.php','event_request',create_request_string('request_form'),function(){ simple_display('request_form_div'); });") . '
    	<div class="formDiv" id="request_form_div">
        <p align="center"><b><font size="+1">Event Request Form</font></b></p><br />'.$request_text.'<br />If you would like to have your event hosted at ' . get_db_field("location","events_locations","id=$locationid") . ' please fill out the below form and we will get back to you.<br />
    		<br /><br />
    		<form name="request_form" id="request_form">
                <input type="hidden" id="featureid" name="featureid" value="'.$featureid.'" />
    			<fieldset class="formContainer">
                    <div class="rowContainer">
    					<label for="name">Contact Name</label><input type="text" id="name" name="name" data-rule-required="true" data-msg-required="'.get_error_message('valid_request_name:events').'" /><div class="tooltipContainer info">'.get_help("input_request_name:events").'</div><br />
    				</div>
    				<div class="rowContainer">
    					<label for="email">Email Address</label><input type="text" id="email" name="email" data-rule-required="true" data-rule-email="true" data-msg-required="'.get_error_message('valid_request_email:events').'" data-msg-email="'.get_error_message('valid_request_email_invalid:events').'" /><div class="tooltipContainer info">'.get_help("input_request_email:events").'</div><br />
    				</div>
       	            <div class="rowContainer">
				        <label for="phone">Phone</label><input type="text" id="phone" name="phone" data-rule-required="true"  data-rule-phone="true" data-msg-required="'.get_error_message('valid_request_phone:events').'" data-msg-phone="'.get_error_message('valid_request_phone_invalid:events').'" /><div class="tooltipContainer info">'.get_help("input_request_phone:events").'</div><br />
                    </div>
                    <div class="rowContainer">
    					<label for="event_name">Event Name</label><input type="text" id="event_name" name="event_name" data-rule-required="true" data-msg-required="'.get_error_message('valid_request_event_name:events').'" /><div class="tooltipContainer info">'.get_help("input_request_event_name:events").'</div><br />
    				</div>
        			<div class="rowContainer">
        				<label for="startdate">Event Start Date</label><input type="text" id="startdate" name="startdate" data-rule-required="true" data-rule-date="true" data-rule-futuredate="true" date-rule-ajax1="features/events/events_ajax.php::request_date_open::&featureid='.$featureid.'&startdate=::true" data-msg-ajax1="'.get_error_message('valid_request_date_used:events').'" data-msg-futuredate="'.get_error_message('valid_request_date_future:events').'" /><div class="tooltipContainer info">'.get_help("input_request_startdate:events").'</div>
        			</div>
        			<div class="rowContainer">
        				<label>&nbsp;</label><span style="font-size:.8em;">through</span>
        			</div>
        			<div class="rowContainer">
        				<label for="enddate">Event End Date</label><input type="text" id="enddate" name="enddate" data-rule-date="true" data-rule-futuredate="#startdate" data-rule-ajax1="features/events/events_ajax.php::request_date_open::&featureid='.$featureid.'&startdate=#startdate&enddate=::true" data-msg-ajax1="'.get_error_message('valid_request_date_used:events').'" data-msg-futuredate="'.get_error_message('valid_request_date_later:events').'" /><div class="tooltipContainer info">'.get_help("input_request_enddate:events").'</div>
        			</div>
                    <div class="rowContainer">
				        <label for="participants"># of participants</label><input type="text" id="participants" name="participants" data-rule-required="true" data-rule-number="true" /><div class="tooltipContainer info">'.get_help("input_request_participants:events").'</div>
			        </div>
    				<div class="rowContainer">
    					<label for="description">Event Description</label><textarea rows="10" id="description" name="description" data-rule-required="true" data-msg-required="'.get_error_message('valid_request_description:events').'" /><div class="tooltipContainer info">'.get_help("input_request_description:events").'</div><br />
    	  			</div>
    		  		<input class="submit" name="submit" type="submit" onmouseover="this.focus();" value="Submit" />	
    			</fieldset>
    		</form>
    	</div>';
    }else{ echo "Sorry, This form is not available"; }
}

function staff_application(){
global $CFG, $USER, $MYVARS;
    if(isset($USER->userid)){
        $row = get_db_row("SELECT * FROM events_staff WHERE userid='$USER->userid'"); //Update existing event

        if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }
        	
    	echo create_validation_script("staffapplication_form" , "ajaxapi('/features/events/events_ajax.php','event_save_staffapp',create_request_string('staffapplication_form'),function(){ simple_display('staffapplication_form_div'); });");
        echo staff_application_form($row);
    }else{ echo "Sorry, This form is not available"; }
}

function info(){
global $CFG,$MYVARS,$USER;
    $event = get_db_row("SELECT * FROM events WHERE eventid=".$MYVARS->GET["eventid"]);
    $location = get_db_row("SELECT * FROM events_locations WHERE id=".$event["location"]);
    date_default_timezone_set("UTC");
    
    echo '<center><h1>'.stripslashes($event["name"]).'</h1>'.stripslashes($event["extrainfo"]).'<br /><br />';
    
    if($event['event_begin_date'] != $event['event_end_date']){ //Multi day event
    	echo 'When: '.date('F \t\h\e jS, Y',$event["event_begin_date"]).' to '.date('F \t\h\e jS, Y',$event["event_end_date"]).'<br />';
    }else{
    	echo 'When: '.date('F \t\h\e jS, Y',$event["event_begin_date"]).'<br />';
    }
    
	echo '<br /><table style="font-size:1em"><tr><td>Where: </td><td>'.stripslashes($location["location"]).'</td></tr>
    <tr><td></td><td>'.stripslashes($location["address_1"]).'<br />'.$location["address_2"].'&nbsp;'.$location["zip"].'</td></tr></table>
	<span class="centered_span"><a title="Get Directions" href="'.$CFG->wwwroot.'/features/events/googlemaps.php?address_1='.stripslashes($location["address_1"]).'&address_2='.stripslashes($location["address_2"]).'">Get Directions</a></span><br />';

	if($event['allday'] != 1){ //All day event
		echo 'Times: '.convert_time($event['event_begin_time']).' to '.convert_time($event['event_end_time']).'. <br />';
	}	
    
    echo '<br />For more information about this event<br /> contact '.stripslashes($event["contact"]).' at '.$event["email"].'<br />or call '.$event["phone"].'.</center><br />';
	
    //Log
	log_entry("events", $MYVARS->GET["eventid"], "View Event Info");	
}

function add_event_form(){
global $CFG,$MYVARS,$USER;
	$pageid = $MYVARS->GET['pageid'];
	date_default_timezone_set("UTC");
    $admin_contacts = $admin_payable = "";
    
    if(is_siteadmin($USER->userid)){ //Get special admin drop down lists for contacts and accounts payable
        $admin_contacts = get_events_admin_contacts();
        $admin_payable = get_events_admin_payable();   
    }
    
	if(isset($MYVARS->GET["eventid"])){ //Update existing event
		$eventid = $MYVARS->GET["eventid"];
        if(!user_has_ability_in_page($USER->userid,"editevents",$pageid)){ echo get_page_error_message("no_permission",array("editevents")); return; }
		$row = get_db_row("SELECT * FROM events WHERE eventid='".$MYVARS->GET["eventid"]."'");
		$name = $row["name"]; $contact = $row['contact'];
		$email = $row['email']; $fee_min = $row['fee_min'];
		$fee_full = $row['fee_full']; $sale_fee = $row['sale_fee'];
		$payableto = $row['payableto']; $checksaddress = $row['checksaddress'];
		$paypal = $row['paypal'];
		echo '<input type="hidden" id="eventid" value="'.$MYVARS->GET["eventid"].'" />';
		$phone = explode("-",$row['phone']);
		$global_display = $row['pageid'] == $CFG->SITEID ? 'none' : 'inline';
		$start_reg = isset($row['start_reg']) ? "false,'YYYY/MM/DD','" . date('Y/m/d',$row['start_reg']) . "'" : 'true';
		$stop_reg = isset($row['stop_reg']) ? "false,'YYYY/MM/DD','" . date('Y/m/d',$row['stop_reg']) . "'" : 'true';
		$sale_end = $row['sale_end'] != "" ? "false,'YYYY/MM/DD','" . date('Y/m/d',$row['sale_end']) . "'" : 'true';
		$template = isset($row['template_id']) ? $row['template_id'] : false;
        $template_settings = get_template_settings($template,$MYVARS->GET["eventid"]);
		$event_begin_date = isset($row['event_begin_date']) ? "false,'YYYY/MM/DD','" . date('Y/m/d',$row['event_begin_date']) . "'" : 'true';
		$event_end_date = isset($row['event_end_date']) ? "false,'YYYY/MM/DD','" . date('Y/m/d',$row['event_end_date']) . "'" : 'true';
		$end_date_display = $row['event_begin_date'] != $row['event_end_date'] ? 'inline' : 'none';
		$times_display = $row['allday'] == "1" ? 'none' : 'inline';
		$fee_display = $row['fee_full'] == "0" ? 'none' : 'inline';
		$event_begin_time_form = isset($row['event_begin_time']) && $row['event_begin_time'] != "" ? get_possible_times('begin_time',$row['event_begin_time']) : get_possible_times('begin_time');
		if(!empty($row['event_end_time'])){
			$event_end_time_form = $row['event_begin_date'] != $row['event_end_date'] ? get_possible_times('end_time',$row['event_end_time']) : get_possible_times('end_time',$row['event_end_time'],$row['event_begin_time']);
		}else{ $event_end_time_form = ""; }
		$reg_display = $row['start_reg'] ? 'inline' : 'none';
		$limits_display = $row['max_users'] == 0 && $row['hard_limits'] == "" && $row['soft_limits'] == "" ? 'none' : 'inline';
		$max_users = $row['max_users'] != "0" ? $row['max_users'] : '0';
		$extrainfo = $row['extrainfo'];
		$fee_yes = $row['fee_full'] != "0" ? "selected" : "";
		$fee_no = $fee_yes == "" ? "selected" : "";
		$allowinpage_yes = $row['allowinpage'] == "1" ? "selected" : "";
		$allowinpage_no = $allowinpage_yes == "" ? "selected" : "";
		$multiday_yes = $row['event_begin_date'] != $row['event_end_date'] ? "selected" : "";
		$multiday_no = $multiday_yes == "" ? "selected" : "";
		$workers_yes = !empty($row['workers']) ? "selected" : "";
		$workers_no = $workers_yes == "" ? "selected" : "";
		$allday_yes = $row['allday'] == "1" ? "selected" : "";
		$allday_no = $allday_yes == "" ? "selected" : "";
		$reg_yes = $row['start_reg'] ? "selected" : "";
		$reg_no = $reg_yes == "" ? "selected" : "";
		$limits_yes = $row['max_users'] != "0" || $row['hard_limits'] != "" || $row['soft_limits'] != "" ? "selected" : "";
		$limits_no = $limits_yes == "" ? "selected" : "";
		$siteviewable_yes = $row['siteviewable'] == "1" ? "selected" : "";
		$siteviewable_no = $siteviewable_yes == "" ? "selected" : "";
        $auto_allowinpage_display = $row['siteviewable'] == "1" ? "inline" : "none";
		$mycategories = get_my_category($row['category']);		
		$mylocations = get_my_locations($USER->userid, $row['location'],$MYVARS->GET["eventid"]);
		$hidden_limits = get_my_hidden_limits($template, $row['hard_limits'],$row['soft_limits']);
	}else{ //New event form
        if(!user_has_ability_in_page($USER->userid,"addevents",$pageid)){ echo get_page_error_message("no_permission",array("addevents")); return; }
		$eventid = $template = false;
		$global_display = $pageid == $CFG->SITEID ? 'none' : 'inline';
		$hidden_limits = '<input type="hidden" id="hard_limits" value="" /><input type="hidden" id="soft_limits" value="" />';
		$phone = array(0 => "", 1 => "", 2 => "");
		$start_reg = $stop_reg = $sale_end = $event_begin_date = $event_end_date = 'true';
		$end_date_display = $limits_display = $times_display = $reg_display = $fee_display = 'none';
		$max_users = $cost = '0';
		$checksaddress = $paypal = $payableto = $name = $contact = $email = $extrainfo = $siteviewable_yes = $workers_yes = $multiday_yes = $allday_no = $reg_yes = $fee_yes = $allowinpage_yes = $limits_yes = $event_end_time_form = $fee_min = $fee_full = $sale_fee = $template_settings = "";
		$event_begin_time_form = get_possible_times('begin_time');
		$mycategories = get_my_category();	
		$mylocations = get_my_locations($USER->userid);
		$siteviewable_no = $workers_no = $multiday_no = $allday_yes = $reg_no = $fee_no = $allowinpage_no = $limits_no = "selected";
        $auto_allowinpage_display = "none";
	}
	echo '
	<script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts/popupcalendar.js"></script>
    <div id="add_event_div">
        <form>
            <table style="width:100%">
            	<tr>
            		<td class="field_title" style="width:115px;">
            			Event Name:
            		</td>
            		<td class="field_input">
            			<input type="text" id="event_name" size="30" value="'. stripslashes($name) .'"/>
            			<span class="hint">'.get_help("input_event_name:events").'<span class="hint-pointer">&nbsp;</span></span>
            		</td>
            	</tr><tr><td></td><td class="field_input"><span id="event_name_error" class="error_text"></span></td></tr>
            </table>
            <table style="width:100%">
            	<tr>
            		<td class="field_title" style="width:115px;">
            			Category:
            		</td>
            		<td class="field_input">
            			<span id="select_category">'.$mycategories.'<span class="hint">'.get_help("input_event_category:events").'<span class="hint-pointer">&nbsp;</span></span></span>
            		</td>
            	</tr><tr><td></td><td class="field_input"><span id="category_error" class="error_text"></span></td></tr>
            </table>
            <table style="width:100%">
            	<tr>
            		<td class="field_title" style="width:115px; vertical-align:top">
            			Description:
            		</td>
            		<td class="field_input">
            			<textarea id="extrainfo" cols="40" rows="5">'. stripslashes($extrainfo) .'</textarea>
            			<span class="hint">'.get_help("input_extrainfo:events").'<span class="hint-pointer">&nbsp;</span></span>
            		</td>
            	</tr><tr><td></td><td class="field_input"><span id="event_name_error" class="error_text"></span></td></tr>
            </table>
            '.$admin_contacts.'
            <table style="width:100%">
            	<tr>
            		<td class="field_title" style="width:115px;">
            			Contact Name:
            		</td>
            		<td class="field_input">
            			<input type="text" id="contact" size="30" value="'. stripslashes($contact) .'"/>
            			<span class="hint">'.get_help("input_contact:events").'<span class="hint-pointer">&nbsp;</span></span>
            		</td>
            	</tr><tr><td></td><td class="field_input"><span id="contact_error" class="error_text"></span></td></tr>
            </table>
            <table style="width:100%">
            	<tr>
            		<td class="field_title" style="width:115px;">
            			Contact Email:
            		</td>
            		<td class="field_input">
            			<input type="text" id="email" size="30" value="'. stripslashes($email) .'"/>
            			<span class="hint">'.get_help("input_event_email:events").'<span class="hint-pointer">&nbsp;</span></span>
            		</td>
            	</tr><tr><td></td><td class="field_input"><span id="email_error" class="error_text"></span></td></tr>
            </table>
            <table style="width:100%">
            	<tr>
            		<td class="field_title" style="width:115px;">
            			Contact Phone:
            		</td>
            		<td class="field_input">
            			<input id="phone_1" type="text" onkeyup="movetonextbox(event);" size="1" maxlength="3" value="'.$phone[0].'" />-
                        <input id="phone_2" type="text" onkeyup="movetonextbox(event);" maxlength="3" size="1" value="'.$phone[1].'" />-
                        <input id="phone_3" type="text" maxlength="4" size="2" value="'.$phone[2].'" />
            		</td>
            	</tr><tr><td></td><td class="field_input"><span id="phone_error" class="error_text"></span></td></tr>
            </table>
            <table style="width:100%; display:'.$global_display.'">
            	<tr>
            		<td class="field_title" style="width:115px;">
            			Request Site Event:
            		</td>
            		<td class="field_input">
            			<select id="siteviewable" onchange="if(this.value==0){ hide_section(\'auto_allowinpage\'); document.getElementById(\'allowinpage\').value=0; }else{ show_section(\'auto_allowinpage\'); }" ><option value="0" '.$siteviewable_no.'>No</option><option value="1" '.$siteviewable_yes.'>Yes</option></select>
            			<span class="hint">'.get_help("input_event_siteviewable:events").'<span class="hint-pointer">&nbsp;</span></span>
            		</td>
            	</tr><tr><td></td><td class="field_input"><span id="event_name_error" class="error_text"></span></td></tr>
            </table>
            <br />
            <div style="border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
                <table style="width:100%;">
                	<tr>
                		<td class="field_title" style="width:115px;">
                			Location:
                		</td>
                		<td class="field_input">
                			<span id="select_location">'.$mylocations.'<span class="hint">'.get_help("input_event_location:events").'<span class="hint-pointer">&nbsp;</span></span></span>
                			<span id="addtolist" style="display:inline"><a href="javascript:void(0);" onclick="hide_show_buttons(\'addtolist\');hide_show_buttons(\'hide_menu\');hide_show_buttons(\'add_location_div\');"> Add to list</a></span>
                			<span id="hide_menu" style="display:none"><a href="javascript:void(0);" onclick="hide_show_buttons(\'hide_menu\');hide_show_buttons(\'addtolist\');hide_show_buttons(\'add_location_div\');"> Hide Menu</a></span>
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
                        				<td class="field_input" style="width:400px; background-color:buttonface; text-align:center">
                        					<span id="new_button" style="display:inline"><a href="javascript:void(0);" onclick="hide_show_buttons(\'browse_button\');hide_show_buttons(\'or\');hide_show_buttons(\'location_menu\');add_location_form(\'new\',\''.$eventid.'\');"><img src="'.$CFG->wwwroot.'/images/add.png" title="Add Location" alt="Add Location"> New Location</a></span>
                        					<span id="or" style="display:inline">&nbsp; or &nbsp;</span> 
                        					<span id="browse_button" style="display:inline"><a href="javascript:void(0);" onclick="hide_show_buttons(\'new_button\');hide_show_buttons(\'or\');hide_show_buttons(\'location_menu\');add_location_form(\'existing\',\''.$eventid.'\');"><img src="'.$CFG->wwwroot.'/images/folder.png" title="Add Location" alt="Add Location"> Browse Locations</a></span>
                        				</td>
                        			</tr>
                        			<tr>
                        				<td></td>
                        				<td>
                        					<span id="location_menu" style="display:none"></span>
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
            <div style="border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
                <table style="width:100%;">
                    <tr>
                        <td>
                        	<table style="width:100%">
                        		<tr>
                        			<td class="field_title" style="width:115px;">
                        				Worker Application:
                        			</td>
                        			<td class="field_input">
                        				<select id="workers"><option value="0" '.$workers_no.'>No</option><option value="1" '.$workers_yes.'>Yes</option></select>
                        				<span class="hint">'.get_help("input_event_workers:events").'<span class="hint-pointer">&nbsp;</span></span>
                        			</td>
                        		</tr><tr><td></td><td class="field_input"><span id="workers_error" class="error_text"></span></td></tr>
                        	</table>
                        </td>
                    </tr>
                </table>
            </div>
            <br />
            <div style="border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
                <table style="width:100%;">
                    <tr>
                        <td>
                        	<table style="width:100%">
                        		<tr>
                        			<td class="field_title" style="width:115px;">
                        				Multi-day Event:
                        			</td>
                        			<td class="field_input">
                        				<select id="multiday" onchange="hide_show_buttons(\'event_end_date_div\'); if(document.getElementById(\'begin_time\').value != \'\'){ get_end_time(document.getElementById(\'begin_time\').value) }" ><option value="0" '.$multiday_no.'>No</option><option value="1" '.$multiday_yes.'>Yes</option></select>
                        				<span class="hint">'.get_help("input_event_multiday:events").'<span class="hint-pointer">&nbsp;</span></span>
                        			</td>
                        		</tr><tr><td></td><td class="field_input"><span id="allowinpage_error" class="error_text"></span></td></tr>
                        	</table>
                        	<table style="width:100%">
                        		<tr>
                                    <td colspan="2">
                        				<table style="margin:0px 0px 0px 50px;">
                            				<tr>
                            					<td class="field_title"  style="width:115px; background-color:buttonface;">
                            						Event Start Date:
                            					</td>
                            					<td class="field_input">
                            						<script>DateInput(\'event_begin_date\', '.$event_begin_date.')</script>
                            					</td>
                            				</tr><tr><td></td><td class="field_input"><span id="event_begin_date_error" class="error_text"></span></td></tr>
                        				</table>
                        				<span id="event_end_date_div" style="display:'.$end_date_display.'">
                        					<table style="margin:0px 0px 0px 50px;">
                            					<tr>
                            						<td class="field_title" style="width:115px; background-color:buttonface;">
                            							Event Stop Date:
                            						</td>
                            						<td class="field_input">
                            							<script>DateInput(\'event_end_date\', '.$event_end_date.')</script>
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
            <div style="border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
                <table style="width:100%;">
                    <tr>
                        <td>
                        	<table style="width:100%">
                        		<tr>
                        			<td class="field_title" style="width:115px;">
                        				All Day Event:
                        			</td>
                        			<td class="field_input">
                        				<select id="allday" onchange="hide_show_buttons(\'event_times_div\');" /><option value="1" '.$allday_yes.'>Yes</option><option value="0" '.$allday_no.'>No</option></select>
                        				<span class="hint">'.get_help("input_event_allday:events").'<span class="hint-pointer">&nbsp;</span></span>
                        			</td>
                        		</tr><tr><td></td><td class="field_input"><span id="allowinpage_error" class="error_text"></span></td></tr>
                        	</table>
                        	<table style="width:100%">
                                <tr>
                                    <td colspan="2">
                            			<span id="event_times_div" style="display:'.$times_display.'">
                            				<table style="margin:0px 0px 0px 50px;">
                                				<tr>
                                					<td class="field_title" style="width:115px; background-color:buttonface;">
                                						Times:
                                					</td>
                                					<td class="field_input">
                                						'.$event_begin_time_form.'
                                					</td>
                                					<td class="field_input">
                                						<span id="end_time_span">
                                						'.$event_end_time_form.'
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
            <div style="border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
            	<table style="width:100%;">
                	<tr>
                		<td class="field_title" style="width:115px;">
                			Registration:
                		</td>
                		<td class="field_input">
                			<select id="reg" onchange="hide_show_buttons(\'registration_panel\');" ><option value="0" '.$reg_no.'>No</option><option value="1" '.$reg_yes.'>Yes</option></select>
                			<span class="hint">'.get_help("input_event_registration:events").'<span class="hint-pointer">&nbsp;</span></span>
                		</td>
                	</tr>
                    <tr>
                        <td colspan="2">
                            <div id="registration_panel" style="display:'.$reg_display.'">
                                <div id="auto_allowinpage" style="display:'.$auto_allowinpage_display.'">
                                    <table style="margin:0px 0px 0px 50px;">
                            			<tr>
                            				<td class="field_title" style="width:115px;">
                            					Auto Access:
                            				</td>
                            				<td class="field_input">
                            					<select id="allowinpage" /><option value="0" '.$allowinpage_no.'>No</option><option value="1" '.$allowinpage_yes.'>Yes</option></select>
                            					<span class="hint">'.get_help("input_event_allowinpage:events").'<span class="hint-pointer">&nbsp;</span></span>
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
                    						'.get_templates($template,$eventid,true).'
                    					</td>
                    				</tr><tr><td></td><td class="field_input"><span id="template_error" class="error_text"></span></td></tr>
                    			</table>
                                <div name="template_settings_form">
                                    <div id="template_settings_div">'.$template_settings.'</div>
                                </div>
                    			<table style="margin:0px 0px 0px 50px;">
                                    <tr>
                        				<td class="field_title" style="width:115px; background-color:buttonface;">
                        					Open Registration Date:
                        				</td>
                        				<td class="field_input">
                        					<script>DateInput(\'start_reg\', '.$start_reg.')</script>
                        				</td>
                                    </tr><tr><td></td><td class="field_input"><span id="start_reg_error" class="error_text"></span></td></tr>
                    			</table>
                                <table style="margin:0px 0px 0px 50px;">
                                	<tr>
                                		<td class="field_title" style="width:115px; background-color:buttonface;">
                                			Close Registration Date:
                                		</td>
                                		<td class="field_input">
                                			<script>DateInput(\'stop_reg\', '.$stop_reg.')</script>
                                		</td>
                                	</tr><tr><td></td><td class="field_input"><span id="stop_reg_error" class="error_text"></span></td></tr>
                                </table>
                                <br />
                                <table style="width:100%; border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;margin:5px;">
                                    <tr>
                                        <td>
                                        	<table style="width:100%">
                                        		<tr>
                                        			<td class="field_title" style="width:115px;">
                                        				Limits:
                                        			</td>
                                        			<td class="field_input">
                                        				<select id="limits" onchange="hide_show_buttons(\'limits_div\');" ><option value="0" '.$limits_no.'>No</option><option value="1" '.$limits_yes.'>Yes</option></select>
                                        				<span class="hint">'.get_help("input_event_limits:events").'<span class="hint-pointer">&nbsp;</span></span>
                                        			</td>
                                        		</tr>
                                        	</table>
                                        	<span id="limits_div" style="display:'.$limits_display.'">
                                            	<table style="width:100%">
                                            		<tr>
                                                        <td colspan="2">
                                            				<table style="margin:0px 0px 0px 50px;">
                                            					<tr>
                                            						<td class="field_title" style="width:115px; background-color:buttonface;">
                                            							Total Max:
                                            						</td>
                                            						<td class="field_input">
                                            							<input type="text" id="max" size="4" maxlength="4" value="'. $max_users .'"/>
                                            							<span class="hint">'.get_help("input_event_max_users:events").'<span class="hint-pointer">&nbsp;</span></span>
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
                                            						<td class="field_title" style="width:115px; background-color:buttonface; vertical-align:top;">
                                            							Custom Limits:
                                            						</td>
                                            						<td class="field_input">
                                            							<input type="button" value="Custom Limit Form" onclick="get_limit_form(document.getElementById(\'template\').value);" /><span></span> <br />
                                            							<div id="limit_form">
                                            							</div>
                                            							<div id="custom_limits" style="font-size:.7em;">
                                            							'.$hidden_limits.'
                                            							</div>
                                            						</td>
                                            					</tr><tr><td></td><td class="field_input"><span id="max_error" class="error_text"></span></td></tr>
                                            				</table>
                                                        </td>
                                                    </tr>
                                            	</table>
                                        	</span>
                                        </td>
                                    </tr>
                                </table>
                                <br />
                                <table style="width:100%; border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;margin:5px;">
                                    <tr>
                                        <td>
                                        	<table style="width:100%">
                                        		<tr>
                                        			<td class="field_title" style="width:115px;">
                                        				Fee:
                                        			</td>
                                        			<td class="field_input">
                                        				<select id="fee" onchange="hide_show_buttons(\'fee_div\');" ><option value="0" '.$fee_no.'>No</option><option value="1" '.$fee_yes.'>Yes</option></select>
                                        				<span class="hint">'.get_help("input_event_cost:events").'<span class="hint-pointer">&nbsp;</span></span>
                                        			</td>
                                        		</tr>
                                        	</table>
                                            <span id="fee_div" style="display:'.$fee_display.'">
                                            	<table style="width:100%">
                                            		<tr>
                                                        <td colspan="2">
                                            				<table style="margin:0px 0px 0px 50px;">
                                                				<tr>
                                                					<td class="field_title"  style="width:115px; background-color:buttonface;">
                                                						Minimum Payment:
                                                					</td>
                                                					<td class="field_input">
                                                						<input type="text" id="min_fee" size="4" value="'. $fee_min .'"/>
                                                						<span class="hint">'.get_help("input_event_min_cost:events").'<span class="hint-pointer">&nbsp;</span></span>
                                                					</td>
                                                				</tr>
                                            				    <tr>
                                                                    <td></td>
                                                                    <td class="field_input"><span id="event_min_fee_error" class="error_text"></span></td>
                                                                </tr>
                                            				</table>
                                            				<table style="margin:0px 0px 0px 50px;">
                                                				<tr>
                                                					<td class="field_title"  style="width:115px; background-color:buttonface;">
                                                						Full Price:
                                                					</td>
                                                					<td class="field_input">
                                                						<input type="text" id="full_fee" size="4" value="'. $fee_full .'"/>
                                                						<span class="hint">'.get_help("input_event_full_cost:events").'<span class="hint-pointer">&nbsp;</span></span>
                                                					</td>
                                                				</tr><tr><td></td><td class="field_input"><span id="event_full_fee_error" class="error_text"></span></td></tr>
                                            				</table>
                                            				<table style="margin:0px 0px 0px 50px;">
                                                				<tr>
                                                					<td class="field_title"  style="width:115px; background-color:buttonface;">
                                                						Sale Price:
                                                					</td>
                                                					<td class="field_input">
                                                						<input type="text" id="sale_fee" size="4" value="'. $sale_fee .'"/>
                                                						<span class="hint">'.get_help("input_event_sale_fee:events").'<span class="hint-pointer">&nbsp;</span></span>
                                                					</td>
                                                				</tr><tr><td></td><td class="field_input"><span id="event_sale_fee_error" class="error_text"></span></td></tr>
                                            				</table>
                                            				<table style="margin:0px 0px 0px 50px;">
                                                				<tr>
                                                					<td class="field_title" style="width:115px; background-color:buttonface;">
                                                						Sale Price End:
                                                					</td>
                                                					<td class="field_input">
                                                						<script>DateInput(\'sale_end\', '.$sale_end.')</script>
                                                						<span class="hint">'.get_help("input_event_sale_end:events").'<span class="hint-pointer">&nbsp;</span></span>
                                                					</td>
                                                				</tr><tr><td></td><td class="field_input"><span id="sale_end_error" class="error_text"></span></td></tr>
                                            				</table>
                                                            '.$admin_payable.'
                                            				<table style="margin:0px 0px 0px 50px;">
                                                				<tr>
                                                					<td class="field_title"  style="width:115px; background-color:buttonface;">
                                                						Payable To:
                                                					</td>
                                                					<td class="field_input">
                                                						<input type="text" id="payableto" size="28" value="'. $payableto .'"/>
                                                						<span class="hint">'.get_help("input_event_payableto:events").'<span class="hint-pointer">&nbsp;</span></span>
                                                					</td>
                                                				</tr><tr><td></td><td class="field_input"><span id="event_payableto_error" class="error_text"></span></td></tr>
                                            				</table>
                                            				<table style="margin:0px 0px 0px 50px;">
                                                				<tr>
                                                					<td class="field_title" style="width:115px; background-color:buttonface;vertical-align:top;">
                                                						Send To:
                                                					</td>
                                                					<td class="field_input">
                                                						<textarea id="checksaddress" cols="21" rows="3">'.$checksaddress.'</textarea>
                                                						<span class="hint">'.get_help("input_event_checksaddress:events").'<span class="hint-pointer">&nbsp;</span></span>
                                                					</td>
                                                				</tr><tr><td></td><td class="field_input"><span id="event_checksaddress_error" class="error_text"></span></td></tr>
                                            				</table>
                                            				<table style="margin:0px 0px 0px 50px;">
                                                				<tr>
                                                					<td class="field_title"  style="width:115px; background-color:buttonface;">
                                                						Paypal Account:
                                                					</td>
                                                					<td class="field_input">
                                                						<input type="text" id="paypal" size="28" value="'. $paypal .'"/>
                                                						<span class="hint">'.get_help("input_event_paypal:events").'<span class="hint-pointer">&nbsp;</span></span>
                                                					</td>
                                                				</tr><tr><td></td><td class="field_input"><span id="event_paypal_error" class="error_text"></span></td></tr>
                                            				</table>
                                   	                    </td>
                                                    </tr>
                                                </table>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
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
            			<input type="button" value="Save" onclick="new_event_submit(\''. $pageid .'\');" />
            		</td>
            	</tr>
        	</table>
       		<script>prepareInputsForHints();</script>
        </form>
    </div>';
}
      
//Show registration form
function show_registration(){
global $CFG,$MYVARS,$USER;
	$eventid = $MYVARS->GET['eventid'];
	$pageid = empty($MYVARS->GET['pageid']) ? $CFG->SITEID : $MYVARS->GET['pageid'];
	if(!user_has_ability_in_page($USER->userid,"signupforevents",$pageid)){ echo get_page_error_message("no_permission",array("signupforevents")); return; }
	
    $event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='".$event['template_id']."'");
	$formlist = ""; $form = "";
	
    $returnme = '<div id="registration_div">
                    <table class="registration"><tr><td>'.$template['intro'].' </td></tr></table>';

	if($template['folder'] != "none"){ //registration template refers to a file
		include($CFG->dirroot . '/features/events/templates/' . $template['folder'] . '/template.php');
	}else{ //registration template refers to a database style template
		$form = '<table style="width:100%">';
		$templateform = get_db_result("SELECT * FROM events_templates_forms WHERE template_id='".$template['template_id']."' ORDER BY sort");
		while($element = fetch_row($templateform)){
			$opt = $element['optional'] ? '<font size="1.2em" color="blue">(optional)</font> ' : '';
			$formlist .= $formlist == "" ? $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'] : "*" . $element['type'] . ":" . $element['elementid'] . ":" . $element['optional'] . ":" . $element['allowduplicates'] . ":" . $element['list'];
			if($element['type'] == 'select'){
			}elseif($element['type'] == 'phone'){
				$form .= '<tr><td class="field_title">' . $opt . $element['display'] . ': </td><td class="field_input" style="width:70%">' . create_form_element($element['type'],$element['elementid'],$element['optional'],$element['length'],false) . '</td></tr>';
				$form .= '<tr><td></td><td class="field_input"><span id="'.$element['elementid'].'_error" class="error_text"></span></td></tr>';
			}elseif($element['type'] == 'payment'){
				if($event["fee_full"] != "0"){
				$form .= '
    				<tr>
    					<td class="field_title">Payment Amount:</td>
    					<td class="field_input">'.make_fee_options($event['fee_min'],$event['fee_full'],'payment_amount','',$event['sale_end'],$event['sale_fee']).'</td>
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
			}elseif($element['type'] == 'contact'){
				$form .= '<tr><td class="field_title">' . $opt . $element['display'] . ': </td><td class="field_input" style="width:70%">' . create_form_element($element['type'],$element['elementid'],$element['optional'],$element['length'],false) . '<span class="hint">'.get_help("input_event_email:events").'<span class="hint-pointer">&nbsp;</span></span></td></tr>';
				$form .= '<tr><td></td><td class="field_input"><span id="'.$element['elementid'].'_error" class="error_text"></span></td></tr>';
			}else{
				$form .= '<tr><td class="field_title">' . $opt . $element['display'] . ': </td><td class="field_input" style="width:70%">' . create_form_element($element['type'],$element['elementid'],$element['optional'],$element['length'],false) . '<span class="hint">'. $element['hint'] . '<span class="hint-pointer">&nbsp;</span></span></td></tr>';
				$form .= '<tr><td></td><td class="field_input"><span id="'.$element['elementid'].'_error" class="error_text"></span></td></tr>';
			}
		}
		$form .= '<tr><td></td><td><input type="button" value="Submit" onclick="submit_registration(\''.$eventid.'\',\''.$formlist.'\');" /></td></tr></table>';
        $returnme .= create_validation_javascript($formlist,$eventid) . $form . '</div><script>prepareInputsForHints();</script>';
	}
    
    $returnme .= '</div>'; //end registration div
    
    $returnme .= '<script type="text/javascript">
        $(document).keydown(function(e) {
        var nodeName = e.target.nodeName.toLowerCase();
        
        if (e.which === 8) {
            if ((nodeName === "input" && e.target.type === "text") || nodeName === "textarea") {
                // do nothing
            } else {
                e.preventDefault();
            }
        }
        });</script>
    ';
echo $returnme;
}

function create_validation_javascript($formlist,$eventid){
global $CFG;
    $validation_script = '<script> function validate_fields(){	var valid = true;';
    date_default_timezone_set(date_default_timezone_get());	
    $element = explode("*",$formlist);
    $i = 0;
    while(isset($element[$i])){
    	$attribute = explode(":",$element[$i]);
    	switch ($attribute[0]){
    	case "text":
    		$validation_script .= '
    		if(document.getElementById(\'opt_'.$attribute[1].'\').value == 0 || (document.getElementById("opt_'.$attribute[1].'").value != 0 && document.getElementById("'.$attribute[1].'").value.length > 0)){
    			if(!document.getElementById("'.$attribute[1].'").value.length > 0){
    		  		document.getElementById("'.$attribute[1].'_error").innerHTML = "This is a required field.";
    		  		valid = false;
    		  	}else{ document.getElementById("'.$attribute[1].'_error").innerHTML = ""; }
      			if('.$attribute[3].' == 0){
        			// Build the URL to connect to
        		  	var url = "'.$CFG->wwwroot.'/features/events/events_ajax.php?action=unique&elementid='.$attribute[1].'&value="+document.getElementById("'.$attribute[1].'").value + "&eventid=" + '.$eventid.';
        			// Open a connection to the server\
        		     var d = new Date();
        		  	xmlHttp.open("GET", url + "&currTime=" + d.toUTCString(), false);
        		  	// Send the request
        			xmlHttp.send(null);
    				if(!istrue()){
    					document.getElementById("'.$attribute[1].'_error").innerHTML = "This value already exists in our database.";
    					valid = false;
    				}else{ document.getElementById("'.$attribute[1].'_error").innerHTML = ""; }
    			}
    		}';
    	    break;
    	case "email":
    		$validation_script .= '
    		if(document.getElementById("opt_'.$attribute[1].'").value == 0 || (document.getElementById("opt_'.$attribute[1].'").value != 0 && document.getElementById("'.$attribute[1].'").value.length > 0)){
    			//Email address validity test
    			if(document.getElementById("'.$attribute[1].'").value.length > 0){
    				if(echeck(document.getElementById("'.$attribute[1].'").value)){
    					if('.$attribute[3].' == 0){
        					// Build the URL to connect to
        				  	var url = "'.$CFG->wwwroot.'/features/events/events_ajax.php?action=unique&elementid='.$attribute[1].'&value="+document.getElementById("'.$attribute[1].'").value + "&eventid=" + '.$eventid.';
        					// Open a connection to the server\
        				     var d = new Date();
        				  	xmlHttp.open("GET", url + "&currTime=" + d.toUTCString(), false);
        				  	// Send the request
        					xmlHttp.send(null);
    						if(!istrue()){
    							document.getElementById("'.$attribute[1].'_error").innerHTML = "This email address has already been registered with.";
    							valid = false;
    						}else{	document.getElementById("'.$attribute[1].'_error").innerHTML = ""; }
    					}
    			  	}else{
    					document.getElementById("'.$attribute[1].'_error").innerHTML = "Email address is not valid.";
    					valid = false;
    				}
    			}else{
    		  		document.getElementById("'.$attribute[1].'_error").innerHTML = "Email address is required.";
    		  		valid = false;
    		  	}
    		}';
    		break;
    	case "contact":
    		$validation_script .= '
    			if(document.getElementById("'.$attribute[1].'").value.length > 0){
    				if(echeck(document.getElementById("'.$attribute[1].'").value)){
    					document.getElementById("'.$attribute[1].'_error").innerHTML = "";
    			  	}else{
    					document.getElementById("'.$attribute[1].'_error").innerHTML = "Email address is not valid.";
    					valid = false;
    				}
    			}else{
    		  		document.getElementById("'.$attribute[1].'_error").innerHTML = "Email address is required.";
    		  		valid = false;
    		  	}
    			  ';
    		break;
    	case "phone":
    		$validation_script .= '
    		if(document.getElementById("opt_'.$attribute[1].'").value == 0 || (document.getElementById("opt_'.$attribute[1].'").value != 0 && (document.getElementById("'.$attribute[1].'_1").value.length > 0 || document.getElementById("'.$attribute[1].'_2").value.length > 0 || document.getElementById("'.$attribute[1].'_3").value.length > 0))){
    			//Phone # validity test
    			if(document.getElementById("'.$attribute[1].'_1").value.length == 3 && document.getElementById("'.$attribute[1].'_2").value.length == 3 && document.getElementById("'.$attribute[1].'_3").value.length == 4){
    				if(!(IsNumeric(document.getElementById("'.$attribute[1].'_1").value) && IsNumeric(document.getElementById("'.$attribute[1].'_2").value) && IsNumeric(document.getElementById("'.$attribute[1].'_3").value))){
    					document.getElementById("'.$attribute[1].'_error").innerHTML = "Not a valid phone #";
    		  			valid = false;
    				}else{ document.getElementById("'.$attribute[1].'_error").innerHTML = ""; }
    			}else{
    		  		document.getElementById("'.$attribute[1].'_error").innerHTML = "Phone # is not complete.";
    		  		valid = false;
    		  	}
    		}
    		';
    		break;
    	case "select":
    	    break;
    	case "payment":
    		$validation_script .= '
    		if(document.getElementById(\'payment_method\')){
    			if(document.getElementById(\'payment_method\').value == ""){
    		  		document.getElementById("payment_method_error").innerHTML = "This is a required field.";
    			  	valid = false;
    			}else{ document.getElementById("payment_method_error").innerHTML = ""; }
    		}
    		';
    	break;
    	case "password":
    	//Password validity test
    	$validation_script .= '
      	if(!document.getElementById("'.$attribute[1].'").value.length > 4){
    	  	if(document.getElementById("'.$attribute[1].'").value.length > 0){ 
    			document.getElementById("'.$attribute[1].'_error").innerHTML = "Password must be between 5-20 characters long.";
    	  		valid = false;
    	  	}else if(!document.getElementById("'.$attribute[1].'").value.length > 0){
    	  		document.getElementById("'.$attribute[1].'_error").innerHTML = "Password is required.";
    	  		valid = false;
    	  	}
    	}else{
      		if(!checkPassword(document.getElementById("'.$attribute[1].'"),document.getElementById("verify_'.$attribute[1].'"),document.getElementById("'.$attribute[1].'"),true)){
      			document.getElementById("'.$attribute[1].'_error").innerHTML = "Password and Verify fields must match."
      			valid = false;
      		}else{ document.getElementById("'.$attribute[1].'_error").innerHTML = ""; }
    		
            if('.$attribute[3].' == 0){
        		// Build the URL to connect to
        	  	var url = "'.$CFG->wwwroot.'/features/events/events_ajax.php?action=unique&elementid='.$attribute[1].'&value="+document.getElementById("'.$attribute[1].'").value + "&eventid=" + '.$eventid.';
        		// Open a connection to the server\
        	     var d = new Date();
        	  	xmlHttp.open("GET", url + "&currTime=" + d.toUTCString(), false);
        	  	// Send the request
        		xmlHttp.send(null);
    			if(!istrue()){
    				document.getElementById("'.$attribute[1].'_error").innerHTML = "This value already exists in our database.";
    				valid = false;
    			}else{ document.getElementById("'.$attribute[1].'_error").innerHTML = ""; }
    		}
      	}';
    	    break;
    	}
    	$i++;
    }
    $validation_script .= 'return valid; } </script>';
    return $validation_script;
}

function showcart(){
global $CFG;
    if(!isset($EVENTSLIB)){ include_once($CFG->dirroot . '/features/events/eventslib.php'); }

    $redirect = '<script type="text/javascript">
                    window.location = "'.$CFG->wwwroot.'";
                </script>';
                   
    echo main_body(true);
    
    $auth_token = $CFG->paypal_auth; 
    
    $pp_hostname = $CFG->paypal ? 'www.paypal.com' : 'www.sandbox.paypal.com';
     
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Host: $pp_hostname"));
    $res = curl_exec($ch);
    curl_close($ch);
            
    if(!$res){
        //HTTP ERROR
        echo $redirect;
    }else{
         // parse the data
        $lines = explode("\n", $res);
        $keyarray = array();
        if (strcmp ($lines[0], "SUCCESS") == 0) {
            for ($i=1; $i<count($lines);$i++){
                if(!empty($lines[$i])){
                    list($key,$val) = explode("=", $lines[$i]);
                    $keyarray[urldecode($key)] = urldecode($val);    
                }
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
        }else{
            echo $redirect;
        }
    }
    
    
 //   
//    
//    // read the post from PayPal system and add 'cmd'
//    $req = 'cmd=_notify-synch';
//    $tx_token = $_GET['tx'];
//    $auth_token = $CFG->paypal_auth;
//    $req .= "&tx=$tx_token&at=$auth_token";
//    // post back to PayPal system to validate
//    $header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
//    $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
//    $header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
//    if($CFG->paypal){ $fp = fsockopen ('www.paypal.com', 80, $errno, $errstr, 30);
//    }else{ $fp = fsockopen ('www.sandbox.paypal.com', 80, $errno, $errstr, 30);}
//    
//    // If possible, securely post back to paypal using HTTPS
//    // Your PHP server will need to be SSL enabled
//    // $fp = fsockopen ('ssl://www.sandbox.paypal.com', 443, $errno, $errstr, 30);
//    if(!$fp){
//    // HTTP ERROR
//    }else{
//    	fputs ($fp, $header . $req);
//    	// read the body data
//    	$res = '';
//    	$headerdone = false;
//    	while(!feof($fp)) {
//        	$line = fgets ($fp, 1024);
//        	if(strcmp($line, "\r\n") == 0){
//            	// read the header
//            	$headerdone = true;
//        	}elseif($headerdone){
//            	// header has been read. now read the contents
//            	$res .= $line;
//        	}
//    	}
//
//    	// parse the data
//    	$lines = explode("\n", $res);
//    	$keyarray = array();
//    	if(strcmp ($lines[0], "SUCCESS") == 0) {
//        	for ($i=1; $i<count($lines);$i++){
//            	list($key,$val) = explode("=", $lines[$i]);
//            	$keyarray[urldecode($key)] = urldecode($val);
//        	}
//        	// check the payment_status is Completed
//        	// check that txn_id has not been previously processed
//        	// check that receiver_email is your Primary PayPal email
//        	// check that payment_amount/payment_currency are correct
//        	// process payment
////    		if(!get_db_row("SELECT * FROM logfile WHERE feature='events' AND description='Paypal' AND info='".$keyarray['txn_id']."'")){
////    			$regids = $keyarray['custom'];
////    			$regids = explode(":",$regids);
////    			$i=0;
////    			while(isset($regids[$i])){
////    				$paid = get_db_field("value", "events_registrations_values", "elementname='paid' AND regid=".$regids[$i]);
////    				$SQL = "UPDATE events_registrations_values SET value=".($paid + $keyarray["mc_gross_".($i+1)])." WHERE elementname='paid' AND regid=".$regids[$i];
////    				execute_db_sql($SQL);
////    				$i++;
////    			}
//			
//			//Log
//			//log_entry('events', $keyarray['txn_id'], "Paypal");
//    	   echo "Your transaction has been completed, and a receipt for your purchase has been emailed to you.<br>You may log into your account at <a href='https://www.paypal.com'>www.paypal.com</a> to view details of this transaction.<br>";
//    	   echo print_cart($keyarray);
//    	}elseif (strcmp ($lines[0], "FAIL") == 0){
//        	//Log
//        	log_entry('events', $lines[0], "Paypal (failed)");
//    	}
//     }
//    fclose ($fp);
}

function print_cart($items){
global $MYVARS, $CFG;
	$i=0; $returnme = '<a href="'.$CFG->wwwroot.'">Go back to '.$CFG->sitename.'</a><br /><br /><table style="border-collapse:collapse;width:60%; margin-right:auto; margin-left:auto;"><tr><td colspan=2><b>What you have paid for:</b></td></tr>';
	while($i < $items["num_cart_items"]){
		$returnme .= '<tr style="background-color:#FFF1FF;"><td style="text-align:left; font-size:.8em;">'.$items["item_name".($i+1)] . '</td><td style="text-align:left; padding:10px; font-size:.8em;">' . '$' . $items["mc_gross_".($i+1)] . '</td></tr><td colspan="2"></td></tr>';
		$i++;
	}
	$returnme .= '<tr><td style="text-align:right;"><b>Total</b></td><td style="border-top: 1px solid gray;text-align:left;padding:10px; font-size:.8em;">$' . $items["mc_gross"] . '</td></tr><td style="text-align:right;"><b>Paid</b></td><td style="text-align:left;padding:10px; font-size:.8em;">$' . $items["payment_gross"] . '</td></tr></table>';
	return $returnme;
}
?>