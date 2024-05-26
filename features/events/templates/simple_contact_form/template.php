<?php
/***************************************************************************
 * template.php - simple_contact_form Template page
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
* Date: 5/14/2024
 * $Revision: 2.1.2
 ***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }
if (!defined('VALIDATELIB')) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

//Retrieve from Javascript
global $MYVARS;
collect_vars();

$eventid = clean_myvar_opt("eventid", "int", false);
$regid = clean_myvar_opt("regid", "int", false);
$show_again = clean_myvar_opt("show_again", "bool", false);
$autofill = clean_myvar_opt("autofill", "bool", false);

$email = $payment_method = $disable = "";

// Preview of template.
if (isset($preview)) {
	$disable = 'disabled="disabled"';
	$event = [
		"name" => "Preview Event",
		"event_begin_date" => date("j"),
		"event_end_date" => date("j"),
		"fee_full" => 0,
		"fee_min" => 0,
		"sale_fee" => 0,
		"sale_end" => 0,
	];
}

// Get full event info
if ($eventid) {
	$event = get_event($eventid);
}

//output any passed on hidden info from previous registrations
$total_owed = clean_myvar_opt("total_owed", "float", 0);
$items = clean_myvar_opt("items", "string", "");

if ($show_again) { // This is not the first time through.
	if ($autofill) { //Same person..so auto fill all items
		$last_reg = get_db_result("SELECT * FROM events_registrations_values WHERE regid='$regid'");
		while ($reginfo = fetch_row($last_reg)) {
			${$reginfo["elementname"]} = $reginfo["value"];
		}
		$email = get_db_field("email", "events_registrations", "regid='$regid'");
	} else { // Different person...but auto fill the payment method and hide it.
		$payment_method = get_db_field("value", "events_registrations_values", "elementname='payment_method' AND regid='$regid'");
	}
}

//output required javascript
echo '<html>
        <head>
        ' . get_js_tags(["jquery", "validate"]) . '
        ' . get_js_tags(["features/events/templates/simple_contact_form/ajax.js"]) . '
        </head>
        <body>
';

// Somebody please tell me why I MUST HAVE THE &nbsp; before the form to make it show up?
echo '<form class="event_template_form" name="form1" id="form1">
			<div id="camp">
				<fieldset class="formContainer">
						<input type="hidden" name="eventid" value="' . $eventid . '" />
						<input type="hidden" name="paid" value="0" />
						<input type="hidden" name="total_owed" id="total_owed" value="' . $total_owed . '" />
						<input type="hidden" name="items" id="items" value="' . $items . '" />';

echo '<div style="font-size:15px;text-align:center;font-weight:bold">Online Registration</div>
      <div style="font-size:13px;text-align:center;font-weight:bold">' . $event["name"] . '</div><br />';

if ($autofill) {
 echo '     <strong>Registrant: ' . $Name . '</strong>
			<input type="hidden" name="Name" value="' . $Name . '" />
            <input type="hidden" name="Name_First" value="' . $Name_First . '" />
            <input type="hidden" name="Name_Last" value="' . $Name_Last . '" />
			<input type="hidden" name="email" value="' . $email . '" />
			<input type="hidden" name="Address_Line1" value="' . $Address_Line1 . '" />
			<input type="hidden" name="Address_Line2" value="' . $Address_Line2 . '" />
			<input type="hidden" name="Address_City" value="' . $Address_City . '" />
			<input type="hidden" name="Address_State" value="' . $Address_State . '" />
			<input type="hidden" name="Address_Zipcode" value="' . $Address_Zipcode . '" />
			<input type="hidden" name="Phone1" value="' . $Phone1 . '" />';
} else {
 echo ' <input type="hidden" id="event_begin_date" value="' . date("Y-m-d", $event["event_begin_date"]) . '" />
        <input style="border:none;" type="hidden" name="HealthConsentFrom" id="HealthConsentFrom" value="' . date("m/d/Y", $event["event_begin_date"]) . '" readonly />
        <input style="border:none;" type="hidden" name="HealthConsentTo" id="HealthConsentTo" value="' . date("m/d/Y", $event["event_end_date"]) . '" readonly />

            <input type="hidden" name="Name" />
			<div class="rowContainer">
				<label class="rowTitle" for="email">Email Address *</label><input tabindex="1" type="text" id="email" name="email" data-rule-required="true" data-rule-email="true" data-msg-required="' . error_string('valid_req_email') . '" data-msg-email="' . error_string('valid_email_invalid') . '" /><div class="tooltipContainer info">' . get_help("help_email:events:templates/simple_contact_form") . '</div>
				  <div class="spacer" style="clear: both;"></div>
            </div>
			<div class="rowContainer">
				<label class="rowTitle" for="Name_First">First Name *</label><input tabindex="2" type="text" id="Name_First" name="Name_First" data-rule-required="true" data-rule-nonumbers="true" data-msg-required="' . error_string('valid_req_fname') . '" /><div class="tooltipContainer info">' . get_help("input_fname") . '</div>
				  <div class="spacer" style="clear: both;"></div>
            </div>
			<div class="rowContainer">
				<label class="rowTitle" for="Name_Last">Last Name *</label><input tabindex="3" type="text" id="Name_Last" name="Name_Last" data-rule-required="true" data-rule-nonumbers="true" data-msg-required="' . error_string('valid_req_lname') . '" /><div class="tooltipContainer info">' . get_help("input_lname") . '</div>
					  <div class="spacer" style="clear: both;"></div>
            </div>
			<div class="rowContainer">
				<label class="rowTitle" for="Address_Line1">Mailing Address Line One *</label><input tabindex="9" type="text" id="Address_Line1" name="Address_Line1" data-rule-required="true" /><div class="tooltipContainer info">' . get_help("help_address:events:templates/simple_contact_form") . '</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
			<div class="rowContainer">
				<label class="rowTitle" for="Address_Line2">Mailing Address Line Two</label><input tabindex="10" type="text" id="Address_Line2" name="Address_Line2" /><div class="tooltipContainer info">' . get_help("help_address:events:templates/simple_contact_form") . '</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
			<div class="rowContainer">
				<label class="rowTitle" for="Address_City">City *</label><input tabindex="11" type="text" id="Address_City" name="Address_City" data-rule-required="true" data-rule-nonumbers="true" /><div class="tooltipContainer info">' . get_help("help_city:events:templates/simple_contact_form") . '</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
			<div class="rowContainer">
				<label class="rowTitle" for="Address_State">State *</label>
                <select tabindex="12" id="Address_State" name="Address_State" data-rule-required="true">
                    <option value="AL">Alabama
                    <option value="AK">Alaska
                    <option value="AZ">Arizona
                    <option value="AR">Arkansas
                    <option value="CA">California
                    <option value="CO">Colorado
                    <option value="CT">Connecticut
                    <option value="DE">Delaware
                    <option value="FL">Florida
                    <option value="GA">Georgia
                    <option value="HI">Hawaii
                    <option value="ID">Idaho
                    <option value="IL">Illinois
                    <option value="IN" selected>Indiana
                    <option value="IA">Iowa
                    <option value="KS">Kansas
                    <option value="KY">Kentucky
                    <option value="LA">Louisiana
                    <option value="ME">Maine
                    <option value="MD">Maryland
                    <option value="MA">Massachusetts
                    <option value="MI">Michigan
                    <option value="MN">Minnesota
                    <option value="MS">Mississippi
                    <option value="MO">Missouri
                    <option value="MT">Montana
                    <option value="NE">Nebraska
                    <option value="NV">Nevada
                    <option value="NH">New Hampshire
                    <option value="NJ">New Jersey
                    <option value="NM">New Mexico
                    <option value="NY">New York
                    <option value="NC">North Carolina
                    <option value="ND">North Dakota
                    <option value="OH">Ohio
                    <option value="OK">Oklahoma
                    <option value="OR">Oregon
                    <option value="PA">Pennsylvania
                    <option value="RI">Rhode Island
                    <option value="SC">South Carolina
                    <option value="SD">South Dakota
                    <option value="TN">Tennessee
                    <option value="TX">Texas
                    <option value="UT">Utah
                    <option value="VT">Vermont
                    <option value="VA">Virginia
                    <option value="WA">Washington
                    <option value="DC">Washington D.C.
                    <option value="WV">West Virginia
                    <option value="WI">Wisconsin
                    <option value="WY">Wyoming
                </select>
                <div class="tooltipContainer info">' . get_help("help_state:events:templates/simple_contact_form") . '</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
            <div class="rowContainer">
                <label class="rowTitle" for="Address_Zipcode">Zipcode *</label><input tabindex="13" type="text" size="5" maxlength="5" id="Address_Zipcode" name="Address_Zipcode" data-rule-required="true" data-rule-number="true" data-rule-minlength="5" /><div class="tooltipContainer info">' . get_help("help_zip:events:templates/simple_contact_form") . '</div>
                <div class="spacer" style="clear: both;"></div>
            </div>
            <div class="rowContainer">
                <label class="rowTitle" for="Phone">Phone *</label><input tabindex="14" type="text" maxlength="22" id="Phone" name="Phone" data-rule-phone="true" data-rule-required="true" /><div class="tooltipContainer info">' . get_help("help_phone:events:templates/simple_contact_form") . '</div>
                <div class="spacer" style="clear: both;"></div>
            </div>';

            $overnight = get_db_field("setting", "settings", "type='events_template' AND extra='$eventid' AND setting_name='template_setting_overnight'");
            $overnight = empty($overnight) ? false : true;
            if (!$overnight) {
                echo '<input type="hidden" id="Overnight" name="Overnight" value="No" />';
                echo '<input type="hidden" id="Gender" name="Gender" value="No" />';
            } else {
                echo '
                <div class="rowContainer">
                    <label class="rowTitle" for="Overnight">Overnight Stay *</label>
                    <select tabindex="6" id="Overnight" name="Overnight" data-rule-required="true">
                        <option value="">Select One...</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                    <div class="tooltipContainer info">' . get_help("help_overnight:events:templates/simple_contact_form") . '</div>
                    <div class="spacer" style="clear: both;"></div>
                </div>
                <div class="rowContainer">
                    <label class="rowTitle" for="Gender">Gender *</label>
                    <select tabindex="6" id="Gender" name="Gender" data-rule-required="true">
                        <option value="">Select One...</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                    <div class="tooltipContainer info">' . get_help("help_gender:events:templates/simple_contact_form") . '</div>
                    <div class="spacer" style="clear: both;"></div>
                </div>';
            }
}

if ($event['fee_full']) {
    echo '
        <div class="rowContainer">
            <label class="rowTitle" for="Camp_Fee">Pay With Application</label>
            ' . make_fee_options($event['fee_min'], $event['fee_full'], "payment_amount",'onchange="updateTotal();" onclick="updateTotal();"', $event['sale_end'], $event['sale_fee']) . '
            <div class="tooltipContainer info">' . get_help("help_paywithapp:events:templates/simple_contact_form") . '</div>
            <div class="spacer" style="clear: both;"></div>
        </div>

        <div class="rowContainer">
            <label class="rowTitle" for="owed">Total:</label>
            <span style="display:inline-block;width:12px;">$</span><input style="float:none;width:100px;border:none;" name="owed" id="owed" size="5" value="' . $event['fee_min'] . '" type="text" readonly />
        </div>';
} else {
    echo '
        <input type="hidden" id="payment_amount" name="payment_amount" value="0" />
        <input type="hidden" name="owed" id="owed" value="0" />
    ';
}

    echo '<input tabindex="33" class="submit" name="submit" type="submit" value="Submit Application" ' . $disable . '/>
        </fieldset>
    </div>
    ' . keepalive() . '
</form>
';

// Finalize and activate validation code.
echo create_validation_script("form1" , "submit_simple_contact_form_registration()");
$script = '$("input,select,textarea").bind("focus",function() { $(this).closest(".rowContainer").css("background-color", "whitesmoke"); });
           $("input,select,textarea").bind("blur",function() { $(this).closest(".rowContainer").css("background-color", "white"); });';
echo js_code_wrap($script, "defer", true);
?>