<?php
/***************************************************************************
 * template.php - simple_contact_form Template page
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
* Date: 4/09/2013
 * $Revision: 2.1.2
 ***************************************************************************/
if(!isset($CFG)){ require('../../../../config.php'); }
if(!isset($EVENTSLIB)){ include_once($CFG->dirroot . '/features/events/eventslib.php'); }
if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }

 //Retrieve from Javascript
$postorget = isset($_GET["eventid"]) ? $_GET : $_POST;
$postorget = isset($postorget["eventid"]) ? $postorget : "";

$MYVARS->GET = $postorget;
$preview = isset($preview) ? 'disabled="disabled"': "";
$eventid = empty($MYVARS->GET['eventid']) ? false : $MYVARS->GET['eventid'];
$show_again = isset($MYVARS->GET['show_again']) ? true : false;
$regid = isset($MYVARS->GET['regid']) && $MYVARS->GET['regid'] != "false" ? $MYVARS->GET['regid'] : false;
$autofill = isset($MYVARS->GET['autofill']) && $MYVARS->GET['autofill'] == "1" ? true : false;
$email = "";

if($show_again){ //This is not the first time through
	if($autofill){ //Same person..so auto fill all items
		$last_reg = get_db_result("SELECT * FROM events_registrations_values WHERE regid='$regid'");
		while($reginfo = fetch_row($last_reg)){
			$$reginfo["elementname"] = $reginfo["value"];
		}
		$email = get_db_field("email","events_registrations","regid='$regid'");
	}else{ //Different person...but auto fill the payment method and hide it.
		$payment_method = get_db_field("value", "events_registrations_values", "elementname='payment_method' AND regid='$regid'");
	}
}

//output required javascript
echo '  <html>
        <head>
        <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'features/events/templates/simple_contact_form/ajax.js"></script>
        </head>
        <body>
';    


//output any passed on hidden info from previous registrations
$total_owed = isset($MYVARS->GET['total_owed']) ? $MYVARS->GET['total_owed'] : 0;
$items = isset($MYVARS->GET["items"]) ? $MYVARS->GET["items"] : "";

//Somebody please tell me why I MUST HAVE THE &nbsp; before the form to make it show up?
echo '<form name="form1" id="form1">
    <div id="camp">
        <fieldset class="formContainer">
            <input type="hidden" name="eventid" value="'.$eventid.'" />
            <input type="hidden" name="paid" value="0" />
            <input type="hidden" name="total_owed" id="total_owed" value="'.$total_owed.'" />
            <input type="hidden" name="items" id="items" value="'.$items.'" />';
                 
//Get full event info
$event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
echo '
    <div style="width:85%;font-size:15px;text-align:center;font-weight:bold">Online Registration</div>
    <div style="width:85%;font-size:13px;text-align:center;font-weight:bold">'.$event["name"].'</div>';
  
if($autofill){
 echo '     <strong>Registrant: '.$Name.'</strong>
			<input type="hidden" name="Name" value="'.$Name.'" />
            <input type="hidden" name="Name_First" value="'.$Name_First.'" />
            <input type="hidden" name="Name_Last" value="'.$Name_Last.'" />
			<input type="hidden" name="email" value="'.$email.'" />
			<input type="hidden" name="Address_Line1" value="'.$Address_Line1.'" />
			<input type="hidden" name="Address_Line2" value="'.$Address_Line2.'" />
			<input type="hidden" name="Address_City" value="'.$Address_City.'" />
			<input type="hidden" name="Address_State" value="'.$Address_State.'" />
			<input type="hidden" name="Address_Zipcode" value="'.$Address_Zipcode.'" />
			<input type="hidden" name="Phone1" value="'.$Phone1.'" />';
}else{
 echo ' <input type="hidden" id="event_day" value="'.date("j",$event["event_begin_date"]).'" />
        <input type="hidden" id="event_month" value="'.date("n",$event["event_begin_date"]).'" />
        <input type="hidden" id="event_year" value="'.date("Y",$event["event_begin_date"]).'" />
        <input style="border:none;" type="hidden" name="HealthConsentFrom" id="HealthConsentFrom" value="'.date("m/d/Y",$event["event_begin_date"]).'" readonly />
        <input style="border:none;" type="hidden" name="HealthConsentTo" id="HealthConsentTo" value="'.date("m/d/Y",$event["event_end_date"]).'" readonly />

        <style> .calendarDateInput{margin-right:5px !important;}.info{ width: 92%; } div.rowContainer { height: 75px; } .rowContainer label :not(.valid){ width:initial;} .rowContainer textarea { width:80%;max-width: 480px; margin-right: 20px; } .rowTitle { width:100% !important; } .rowContainer select { margin-right: 20px; }</style>
            <input type="hidden" name="Name" />
			<div class="rowContainer">
				<label class="rowTitle" for="email">Email Address *</label><br /><input tabindex="1" type="text" id="email" name="email" data-rule-required="true" data-rule-email="true" data-msg-required="'.get_error_message('valid_req_email').'" data-msg-email="'.get_error_message('valid_email_invalid').'" /><div class="tooltipContainer info">'.get_help("help_email:events:templates/simple_contact_form").'</div><br />
			</div>
			<div class="rowContainer">
				<label class="rowTitle" for="Name_First">First Name *</label><br /><input tabindex="2" type="text" id="Name_First" name="Name_First" data-rule-required="true" data-rule-nonumbers="true" data-msg-required="'.get_error_message('valid_req_fname').'" /><div class="tooltipContainer info">'.get_help("input_fname").'</div><br />
			</div>
			<div class="rowContainer">
				<label class="rowTitle" for="Name_Last">Last Name *</label><br /><input tabindex="3" type="text" id="Name_Last" name="Name_Last" data-rule-required="true" data-rule-nonumbers="true" data-msg-required="'.get_error_message('valid_req_lname').'" /><div class="tooltipContainer info">'.get_help("input_lname").'</div><br />
  			</div>
			<div class="rowContainer">
				<label class="rowTitle" for="Address_Line1">Mailing Address Line One *</label><br /><input tabindex="9" type="text" id="Address_Line1" name="Address_Line1" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("help_address:events:templates/simple_contact_form").'</div><br />
			</div>            
			<div class="rowContainer">
				<label class="rowTitle" for="Address_Line2">Mailing Address Line Two</label><br /><input tabindex="10" type="text" id="Address_Line2" name="Address_Line2" /><div class="tooltipContainer info">'.get_help("help_address:events:templates/simple_contact_form").'</div><br />
			</div>         
			<div class="rowContainer">
				<label class="rowTitle" for="Address_City">City *</label><br /><input tabindex="11" type="text" id="Address_City" name="Address_City" data-rule-required="true" data-rule-nonumbers="true" /><div class="tooltipContainer info">'.get_help("help_city:events:templates/simple_contact_form").'</div><br />
			</div>             
			<div class="rowContainer">
				<label class="rowTitle" for="Address_State">State *</label><br />
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
                <div class="tooltipContainer info">'.get_help("help_state:events:templates/simple_contact_form").'</div><br />
			</div>             
            <div class="rowContainer">
                <label class="rowTitle" for="Address_Zipcode">Zipcode *</label><br /><input tabindex="13" type="text" size="5" maxlength="5" id="Address_Zipcode" name="Address_Zipcode" data-rule-required="true" data-rule-number="true" data-rule-minlength="5" /><div class="tooltipContainer info">'.get_help("help_zip:events:templates/simple_contact_form").'</div><br />
            </div>            
            <div class="rowContainer">
                <label class="rowTitle" for="Phone">Phone</label><br /><input tabindex="14" type="text" maxlength="22" id="Phone" name="Phone" data-rule-phone="true" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("help_phone:events:templates/simple_contact_form").'</div><br />
            </div>
            <div class="rowContainer">
                <label class="rowTitle" for="Overnight">Overnight Stay</label><br />
                <select tabindex="6" id="Overnight" name="Overnight" data-rule-required="true">
                    <option value="">Select One...</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
                <div class="tooltipContainer info">'.get_help("help_overnight:events:templates/simple_contact_form").'</div><br />
            </div> '; 
}

echo '
    <div class="rowContainer">
        <label class="rowTitle" for="Camp_Fee">Pay With Application</label><br />
        '.make_fee_options($event['fee_min'],$event['fee_full'],"payment_amount",'onchange="updateTotal();" onclick="updateTotal();"',$event['sale_end'],$event['sale_fee']).'
        <div class="tooltipContainer info">'.get_help("help_paywithapp:events:templates/simple_contact_form").'</div><br />
    </div>';

echo '
    <div class="rowContainer">
        <label class="rowTitle" for="owed">Total:</label><br />
        <span style="display:inline-block;width:12px;">$</span><input style="float:none;width:100px;border:none;" name="owed" id="owed" size="5" value="'.$event['fee_min'].'" type="text" readonly />
    </div>';

    echo '<input tabindex="33" class="submit" name="submit" type="submit" value="Submit Application" '.$preview.'/><br /><br />
          <input tabindex="34" name="reset" type="reset" '.$preview.'/>
        </fieldset>
    </div>
</form>
';

//Finalize and activate validation code
echo create_validation_script("form1" , "submit_simple_contact_form_registration()");
echo '  
<script type="text/javascript" language="javascript">
    $(document).ready(function(){
        $("input,select,textarea").bind("focus",function(){ $(this).closest(".rowContainer").css("background-color","whitesmoke"); });
        $("input,select,textarea").bind("blur",function(){ $(this).closest(".rowContainer").css("background-color","white"); });
    });
</script>
';
?>