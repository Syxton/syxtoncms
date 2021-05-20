<?php
/***************************************************************************
 * template.php - Camp Wabashi Template page
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
* Date: 4/09/2013
 * $Revision: 2.1.2
 ***************************************************************************/
if (!isset($CFG)) { require('../../../../config.php'); }
if (!isset($EVENTSLIB)) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }
if (!isset($VALIDATELIB)) { include_once($CFG->dirroot . '/lib/validatelib.php'); }

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

if ($show_again) { //This is not the first time through
	if ($autofill) { //Same person..so auto fill all items
		$last_reg = get_db_result("SELECT * FROM events_registrations_values WHERE regid='$regid'");
		while ($reginfo = fetch_row($last_reg)) {
			${$reginfo["elementname"]} = $reginfo["value"];
		}
		$email = get_db_field("email","events_registrations","regid='$regid'");
	}else{ //Different person...but auto fill the payment method and hide it.
		$payment_method = get_db_field("value", "events_registrations_values", "elementname='payment_method' AND regid='$regid'");
	}
}

//output required javascript
echo '  <html>
        <head>
        <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts/popupcalendar.js" ></script>
        <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'features/events/templates/camp_new/ajax.js"></script>
        </head>
        <body>
';    

//output any passed on hidden info from previous registrations
$total_owed = isset($MYVARS->GET['total_owed']) ? $MYVARS->GET['total_owed'] : 0;
$items = isset($MYVARS->GET["items"]) ? $MYVARS->GET["items"] : "";

//Somebody please tell me why I MUST HAVE THE &nbsp; before the form to make it show up?
echo '<form name="form1" id="form1">
        <fieldset class="formContainer">
            <input type="hidden" name="eventid" value="'.$eventid.'" />
            <input type="hidden" name="paid" value="0" />
            <input type="hidden" name="total_owed" id="total_owed" value="'.$total_owed.'" />
            <input type="hidden" name="items" id="items" value="'.$items.'" />';
                 
//Get full event info
$event = get_db_row("SELECT * FROM events WHERE eventid='$eventid'");
echo '
    <div style="font-size:15px;text-align:center;font-weight:bold">Camp Wabashi Online Pre-Registration</div>
    <div style="font-size:13px;text-align:center;font-weight:bold">'.$event["name"].'</div>
    <p><a target="policy" href="'.$CFG->wwwroot.'/features/events/templates/camp_new/regpolicy.html">Registration Policy</a></p>';

$min_age = get_db_field("setting","settings","type='events_template' AND extra='$eventid' AND setting_name='template_setting_min_age'");
$max_age = get_db_field("setting","settings","type='events_template' AND extra='$eventid' AND setting_name='template_setting_max_age'");

$min_age_error = empty($min_age) ? "" : ' data-msg-min="'.get_error_message('error_age_min:events:templates/camp_new').'"';
$max_age_error = empty($max_age) ? "" : ' data-msg-max="'.get_error_message('error_age_max:events:templates/camp_new').'"';
$min_age = empty($min_age) ? "" : " data-rule-min=\"$min_age\"";
$max_age = empty($max_age) ? "" : " data-rule-max=\"$max_age\"";
 
$pictures = get_db_field("setting","settings","type='events_template' AND extra='$eventid' AND setting_name='template_setting_pictures'");
$pictures = empty($pictures) ? false : true;
if ($pictures) {
    $pictures_price = get_db_field("setting","settings","type='events_template' AND extra='$eventid' AND setting_name='template_setting_pictures_price'");   
    $pictures_price = empty($pictures_price) ? "0" : $pictures_price; 
}

$shirt = get_db_field("setting","settings","type='events_template' AND extra='$eventid' AND setting_name='template_setting_shirt'");
$shirt = empty($shirt) ? false : true;
if ($shirt) {
    $shirt_price = get_db_field("setting","settings","type='events_template' AND extra='$eventid' AND setting_name='template_setting_shirt_price'");   
    $shirt_price = empty($shirt_price) ? "0" : $shirt_price; 
}
  
if ($autofill) {
 echo '     <strong>Camper: '.$Camper_Name.'</strong>
			<input type="hidden" name="Camper_Name" value="'.$Camper_Name.'" />
            <input type="hidden" name="Camper_Name_First" value="'.$Camper_Name_First.'" />
            <input type="hidden" name="Camper_Name_Last" value="'.$Camper_Name_Last.'" />
            <input type="hidden" name="Camper_Name_Middle" value="'.$Camper_Name_Middle.'" />
			<input type="hidden" name="email" value="'.$email.'" />
			<input type="hidden" name="Camper_Birth_Date" value="'.$Camper_Birth_Date.'" />
			<input type="hidden" name="Camper_Age" value="'.$Camper_Age.'" />
            <input type="hidden" name="Camper_Shirt" value="'.$Camper_Shirt.'" />
			<input type="hidden" name="Camper_Grade" value="'.$Camper_Grade.'" />
			<input type="hidden" name="Camper_Gender" value="'.$Camper_Gender.'" />
			<input type="hidden" name="Camper_Home_Congregation" value="'.$Camper_Home_Congregation.'" />
			<input type="hidden" name="Parent_Address_Line1" value="'.$Parent_Address_Line1.'" />
			<input type="hidden" name="Parent_Address_Line2" value="'.$Parent_Address_Line2.'" />
			<input type="hidden" name="Parent_Address_City" value="'.$Parent_Address_City.'" />
			<input type="hidden" name="Parent_Address_State" value="'.$Parent_Address_State.'" />
			<input type="hidden" name="Parent_Address_Zipcode" value="'.$Parent_Address_Zipcode.'" />
			<input type="hidden" name="Parent_Phone1" value="'.$Parent_Phone1.'" />
			<input type="hidden" name="Parent_Phone2" value="'.$Parent_Phone2.'" />
			<input type="hidden" name="Parent_Phone3" value="'.$Parent_Phone3.'" />
			<input type="hidden" name="Parent_Phone4" value="'.$Parent_Phone4.'" />
			<input type="hidden" name="HealthConsentFrom" value="'.$HealthConsentFrom.'" />
			<input type="hidden" name="HealthConsentTo" value="'.$HealthConsentTo.'" />
			<input type="hidden" name="HealthMemberName" value="'.$HealthMemberName.'" />
			<input type="hidden" name="HealthRelationship" value="'.$HealthRelationship.'" />
			<input type="hidden" name="HealthInsurance" value="'.$HealthInsurance.'" />
			<input type="hidden" name="HealthIdentification" value="'.$HealthIdentification.'" />
			<input type="hidden" name="HealthBenefitCode" value="'.$HealthBenefitCode.'" />
			<input type="hidden" name="HealthAccount" value="'.$HealthAccount.'" />
			<input type="hidden" name="HealthExpirationDate" value="'.$HealthExpirationDate.'" />
			<input type="hidden" name="HealthHistory" value="'.$HealthHistory.'" />
			<input type="hidden" name="HealthAllergies" value="'.$HealthAllergies.'" />
			<input type="hidden" name="HealthExisting" value="'.$HealthExisting.'" />
			<input type="hidden" name="HealthMedicines" value="'.$HealthMedicines.'" />
			<input type="hidden" name="HealthTetanusDate" value="'.$HealthTetanusDate.'" />';
}else{
 echo ' <input type="hidden" id="event_day" value="'.date("j",$event["event_begin_date"]).'" />
        <input type="hidden" id="event_month" value="'.date("n",$event["event_begin_date"]).'" />
        <input type="hidden" id="event_year" value="'.date("Y",$event["event_begin_date"]).'" />
        <input style="border:none;" type="hidden" name="HealthConsentFrom" id="HealthConsentFrom" value="'.date("m/d/Y",$event["event_begin_date"]).'" readonly />
        <input style="border:none;" type="hidden" name="HealthConsentTo" id="HealthConsentTo" value="'.date("m/d/Y",$event["event_end_date"]).'" readonly />

        <style> .calendarDateInput{margin-right:5px !important;}.info{ width: 92%; } .rowContainer textarea { width:80%;max-width: 480px; margin-right: 20px; } .rowContainer select { margin-right: 20px; }</style>
            <input type="hidden" name="Camper_Name" />
			<div class="rowContainer">
				<label class="rowTitle" for="email">Email Address *</label><input tabindex="1" type="text" id="email" name="email" data-rule-required="true" data-rule-email="true" data-msg-required="'.get_error_message('valid_req_email').'" data-msg-email="'.get_error_message('valid_email_invalid').'" /><div class="tooltipContainer info">'.get_help("help_email:events:templates/camp_new").'</div>
			    <div class="spacer" style="clear: both;"></div>
            </div>
			<div class="rowContainer">
				<label class="rowTitle" for="Camper_Name_First">Camper First Name *</label><input tabindex="2" type="text" id="Camper_Name_First" name="Camper_Name_First" data-rule-required="true" data-rule-nonumbers="true" data-msg-required="'.get_error_message('valid_req_fname').'" /><div class="tooltipContainer info">'.get_help("input_fname").'</div>
			    <div class="spacer" style="clear: both;"></div>
            </div>
			<div class="rowContainer">
				<label class="rowTitle" for="Camper_Name_Last">Camper Last Name *</label><input tabindex="3" type="text" id="Camper_Name_Last" name="Camper_Name_Last" data-rule-required="true" data-rule-nonumbers="true" data-msg-required="'.get_error_message('valid_req_lname').'" /><div class="tooltipContainer info">'.get_help("input_lname").'</div>
  			    <div class="spacer" style="clear: both;"></div>
            </div>
            <div class="rowContainer">
				<label class="rowTitle" for="Camper_Name_Middle">Camper Middle Initial</label><input tabindex="4" style="width:50px;" maxlength="1" type="text" id="Camper_Name_Middle" name="Camper_Name_Middle" data-rule-maxlength="1" data-rule-letters="true" /><div class="tooltipContainer info">'.get_help("help_middlei:events:templates/camp_new").'</div>
  			    <div class="spacer" style="clear: both;"></div>
            </div>
            <div class="rowContainer">
				<label class="rowTitle" for="Camper_Birth_Date">Camper Birthdate *</label><script>DateInput(\'Camper_Birth_Date\',true)</script><div class="tooltipContainer info">'.get_help("help_bday:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>
            <div class="rowContainer">
                <label class="rowTitle" for="Camper_Age">Age during week</label><input style="background-color: lightgray;border: 1px solid grey; width:50px;" type="text" id="Camper_Age" name="Camper_Age" data-rule-required="true" data-rule-number="true" '.$min_age.$max_age.$min_age_error.$max_age_error.' readonly /><div class="tooltipContainer info">'.get_help("help_age:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>
            <div class="rowContainer">
                <label class="rowTitle" for="Camper_Grade">Last Completed Grade *</label><input tabindex="5" style="width:50px;" type="text" size="2" maxlength="2" id="Camper_Grade" name="Camper_Grade" data-rule-required="true" data-rule-number="true" data-rule-max="12" data-rule-min="0" /><div class="tooltipContainer info">'.get_help("help_grade:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>            
            <div class="rowContainer">
                <label class="rowTitle" for="Camper_Gender">Gender *</label>
                <select tabindex="6" id="Camper_Gender" name="Camper_Gender" data-rule-required="true">
                    <option value="">Select One...</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <div class="tooltipContainer info">'.get_help("help_gender:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div> 
  			<div class="rowContainer">
				<label class="rowTitle" for="Camper_Home_Congregation">Home Congregation</label><input tabindex="7" type="text" id="Camper_Home_Congregation" name="Camper_Home_Congregation" data-rule-nonumbers="true" /><div class="tooltipContainer info">'.get_help("help_congregation:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>             
			<div class="rowContainer">
				<label class="rowTitle" for="Parent_Name">Parent/Guardian *</label><input tabindex="8" type="text" id="Parent_Name" name="Parent_Name" data-rule-required="true" data-rule-nonumbers="true" /><div class="tooltipContainer info">'.get_help("help_parent:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>
			<div class="rowContainer">
				<label class="rowTitle" for="Parent_Address_Line1">Mailing Address Line One *</label><input tabindex="9" type="text" id="Parent_Address_Line1" name="Parent_Address_Line1" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("help_address:events:templates/camp_new").'</div>
			    <div class="spacer" style="clear: both;"></div>
            </div>            
			<div class="rowContainer">
				<label class="rowTitle" for="Parent_Address_Line2">Mailing Address Line Two</label><input tabindex="10" type="text" id="Parent_Address_Line2" name="Parent_Address_Line2" /><div class="tooltipContainer info">'.get_help("help_address:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>         
			<div class="rowContainer">
				<label class="rowTitle" for="Parent_Address_City">City *</label><input tabindex="11" type="text" id="Parent_Address_City" name="Parent_Address_City" data-rule-required="true" data-rule-nonumbers="true" /><div class="tooltipContainer info">'.get_help("help_city:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>             
			<div class="rowContainer">
				<label class="rowTitle" for="Parent_Address_State">State *</label>
                <select tabindex="12" id="Parent_Address_State" name="Parent_Address_State" data-rule-required="true">
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
                <div class="tooltipContainer info">'.get_help("help_state:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>             
            <div class="rowContainer">
                <label class="rowTitle" for="Parent_Address_Zipcode">Zipcode *</label><input tabindex="13" type="text" size="5" maxlength="5" id="Parent_Address_Zipcode" name="Parent_Address_Zipcode" data-rule-required="true" data-rule-number="true" data-rule-minlength="5" /><div class="tooltipContainer info">'.get_help("help_zip:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>            
            <div class="rowContainer">
                <label class="rowTitle" for="Parent_Phone1">Parent/Guardian Phone 1 *</label><input tabindex="14" type="text" maxlength="22" id="Parent_Phone1" name="Parent_Phone1" data-rule-required="true" data-rule-phone="true" /><div class="tooltipContainer info">'.get_help("help_phone:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>
            <div class="rowContainer">
                <label class="rowTitle" for="Parent_Phone2">Parent/Guardian Phone 2 *</label><input tabindex="15" type="text" maxlength="22" id="Parent_Phone2" name="Parent_Phone2" data-rule-required="true" data-rule-phone="true" /><div class="tooltipContainer info">'.get_help("help_phone:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>
            <div class="rowContainer">
                <label class="rowTitle" for="Parent_Phone3">Parent/Guardian Phone 3</label><input tabindex="16" type="text" maxlength="22" id="Parent_Phone3" name="Parent_Phone3" data-rule-phone="true" /><div class="tooltipContainer info">'.get_help("help_phone:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>
            <div class="rowContainer">
                <label class="rowTitle" for="Parent_Phone4">Parent/Guardian Phone 4</label><input tabindex="17" type="text" maxlength="22" id="Parent_Phone4" name="Parent_Phone4" data-rule-phone="true" /><div class="tooltipContainer info">'.get_help("help_phone:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
            </div>
            <div style="text-align:left;width:85%;font-size:13px;font-weight:bold"> 
              CONSENT FOR MEDICAL TREATMENT OF A MINOR CHILD
            </div>
            <div style="font-size:12px;"> 
                <p style="text-align:left;width:85%"> 
                    I herewith authorize Camp Wabashi staff at the Wabash 
                    Valley Christian Youth Camp to request and consent in writing or 
                    otherwise as requested by Union Hospital, Inc. and/or any other 
                    medical facility to any and all examinations, medical treatment 
                    and/or procedures to or for the above named minor, either on or 
                    off the premises of medical facility, as deemed advisable or appropriate 
                    by any physician or surgeon licensed to practice medicine in the 
                    State of Indiana. I understand that verbal concent will be sought using
                    the phone numbers listed in this application.<br />
                    <br />
                    In consideration of the acceptance of the above named camper, I 
                    covenant and agree with Wabash Valley Christian Youth Camp, that 
                    I will at all times hereafter indemnify, and save harmless the said 
                    Wabash Valley Christian Youth Camp from all actions, proceedings, 
                    claims, demands, costs, damages and expenses which may be brought 
                    against, or claimed from Wabash Valley Christian Youth Camp, or 
                    which it may pay, sustain, or incur as a result of illness, accident, 
                    or misadventure to the above named camper during the period said 
                    camper is a participant in the Wabash Valley Christian Youth Camp.<br />
                    <br />
                    This authorization constitutes a Power of Attorney and waiver of 
                    liability appointing the above named adult or staff of Wabash Valley 
                    Christian Youth Camp as Attorney-In-Fact to sign said requests and 
                    as fully thought I, myself, did so. 
              </p>
            </div>
           	<div class="rowContainer">
				<label class="rowTitle" for="HealthMemberName">Member\'s Name *</label><input tabindex="18" type="text" id="HealthMemberName" name="HealthMemberName" data-rule-required="true" data-rule-nonumbers="true" /><div class="tooltipContainer info">'.get_help("help_membername:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
           	<div class="rowContainer">
				<label class="rowTitle" for="HealthRelationship">Relationship *</label><input tabindex="19" type="text" id="HealthRelationship" name="HealthRelationship" data-rule-required="true" data-rule-nonumbers="true" /><div class="tooltipContainer info">'.get_help("help_relationship:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
           	<div class="rowContainer">
				<label class="rowTitle" for="HealthInsurance">Medical Insurance Carrier *</label><input tabindex="20" type="text" id="HealthInsurance" name="HealthInsurance" data-rule-required="true" /><div class="tooltipContainer info">'.get_help("help_carrier:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
           	<div class="rowContainer">
				<label class="rowTitle" for="HealthIdentification">Medical Identification Number</label><input tabindex="21" type="text" id="HealthIdentification" name="HealthIdentification" /><div class="tooltipContainer info">'.get_help("help_memberid:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
           	<div class="rowContainer">
				<label class="rowTitle" for="HealthBenefitCode">Benefit Code</label><input tabindex="22" type="text" id="HealthBenefitCode" name="HealthBenefitCode" /><div class="tooltipContainer info">'.get_help("help_membercode:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
           	<div class="rowContainer">
				<label class="rowTitle" for="HealthAccount">Account Number</label><input tabindex="23" type="text" id="HealthAccount" name="HealthAccount" /><div class="tooltipContainer info">'.get_help("help_memberaccount:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
            <div class="rowContainer">
				<label class="rowTitle" for="HealthExpirationDate">Expiration Date</label><script>DateInput(\'HealthExpirationDate\',true)</script><div class="tooltipContainer info">'.get_help("help_healthto:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
  			</div>
           	<div class="rowContainer" style="height: auto;">
				<label class="rowTitle" for="HealthHistory">Medical History *</label>
                <textarea tabindex="24" id="HealthHistory" name="HealthHistory" rows="8" cols="60" data-rule-required="true">-none-</textarea>
                <div class="tooltipContainer info">'.get_help("help_history:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
           	<div class="rowContainer" style="height: auto;">
				<label class="rowTitle" for="HealthAllergies">Allergies *</label>
                <textarea tabindex="25" id="HealthAllergies" name="HealthAllergies" rows="8" cols="60" data-rule-required="true">-none-</textarea>
                <div class="tooltipContainer info">'.get_help("help_alergies:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
           	<div class="rowContainer" style="height: auto;">
				<label class="rowTitle" for="HealthExisting">Chronic/existing diseases/medical issues *</label>
                <textarea tabindex="26" id="HealthExisting" name="HealthExisting" rows="8" cols="60" data-rule-required="true">-none-</textarea>
                <div class="tooltipContainer info">'.get_help("help_existing:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>
           	<div class="rowContainer" style="height: auto;">
				<label class="rowTitle" for="HealthMedicines">Medicines *</label>
                <textarea tabindex="27" id="HealthMedicines" name="HealthMedicines" rows="8" cols="60" data-rule-required="true">-none-</textarea>
                <div class="tooltipContainer info">'.get_help("help_meds:events:templates/camp_new").'</div>
                <div class="spacer" style="clear: both;"></div>
			</div>           
            <div class="rowContainer">
				<label class="rowTitle" for="HealthTetanusDate">Date of last Tetanus injection/booster *</label><script>DateInput(\'HealthTetanusDate\',true)</script><div class="tooltipContainer info">'.get_help("help_tetanus:events:templates/camp_new").'</div>
  			    <div class="spacer" style="clear: both;"></div>
            </div>'; 
}

echo '
    <div class="rowContainer">
        <label class="rowTitle" for="Camp_Fee">Pay With Application</label>
        '.make_fee_options($event['fee_min'],$event['fee_full'],"payment_amount",'onchange="updateTotal();" onclick="updateTotal();"',$event['sale_end'],$event['sale_fee']).'
        <div class="tooltipContainer info">'.get_help("help_paywithapp:events:templates/camp_new").'</div>
        <div class="spacer" style="clear: both;"></div>
    </div>';
    
if ($pictures) {
    if ($pictures_price > 0) {
        echo '
            <div class="rowContainer">
                <label class="rowTitle" for="Camper_Picture">Camp Picture</label>
                <select tabindex="28" style="width:auto;" name="Camper_Picture" id="Camper_Picture" onchange="updateTotal();" onclick="updateTotal();">
                    <option value="0" selected>No</option>
                    <option value="'.$pictures_price.'">Yes</option>
                </select>
                <div class="tooltipContainer info">'.get_help("help_pictures:events:templates/camp_new").' ($'.$pictures_price.'.00 for 8x10 color picture of all campers and staff)</div>
                <div class="spacer" style="clear: both;"></div>
            </div>';        
    }else{
        echo '<input type="hidden" size="5" maxlength="5" id="Camper_Picture" name="Camper_Picture" value="1" readonly />';        
    }
}else{ echo '<input type="hidden" size="5" maxlength="5" id="Camper_Picture" name="Camper_Picture" value="0" readonly />'; }

if ($shirt) {
    $shirt_sizes = array("Youth XS","Youth S","Youth M","Youth L","Youth XL","Adult S","Adult M","Adult L","Adult XL","Adult XXL");
    if ($shirt_price > 0) {
        echo '
        <div class="rowContainer">
            <label class="rowTitle" for="Camper_Shirt">Shirt</label>
            <input type="hidden" id="Camper_Shirt" name="Camper_Shirt" value="1" readonly />
            <input type="hidden" id="Camper_Shirt_Price" name="Camper_Shirt_Price" value="'.$shirt_price.'" readonly />
            <select tabindex="29" style="width:auto;" name="Camper_Shirt_Size" id="Camper_Shirt_Size" onchange="updateTotal();" onclick="updateTotal();">
                <option value="0" selected>No</option>';
                foreach ($shirt_sizes as $ss) {
                    echo '<option value="'.$ss.'">'.$ss.'</option>';
                }
        echo '        
            </select>
            <div class="tooltipContainer info">'.get_help("help_shirt:events:templates/camp_new").' ($'.$shirt_price.'.00 for camp shirt)</div>
            <div class="spacer" style="clear: both;"></div>
        </div>
        ';        
    }else{
        echo '
        <div class="rowContainer">
            <label class="rowTitle" for="Camper_Shirt">Shirt</label>
            <input type="hidden" id="Camper_Shirt" name="Camper_Shirt" value="1" readonly />
            <input type="hidden" id="Camper_Shirt_Price" name="Camper_Shirt_Price" value="'.$shirt_price.'" readonly />
            <select tabindex="30" style="width:auto;" name="Camper_Shirt_Size" id="Camper_Shirt_Size" onchange="updateTotal();" onclick="updateTotal();">';
            foreach ($shirt_sizes as $ss) {
                echo '<option value="'.$ss.'">'.$ss.'</option>';
            }
            echo '
            </select>
            <div class="tooltipContainer info">'.get_help("help_shirt_size:events:templates/camp_new").' (no extra charge)</div>
            <div class="spacer" style="clear: both;"></div>
        </div>';        
    }
}else{ echo '<input type="hidden" id="Camper_Shirt" name="Camper_Shirt_Size" value="0" readonly /><input type="hidden" id="Camper_Shirt_Price" name="Camper_Shirt_Price" value="0" readonly />'; }

echo '
    <div class="rowContainer">
        <label class="rowTitle" for="owed">Total:</label>
        <span style="display:inline-block;width:12px;">$</span><input style="float:none;width:100px;border:none;" name="owed" id="owed" size="5" value="'.$event['fee_min'].'" type="text" readonly />
        <div class="spacer" style="clear: both;"></div>
    </div>';

if (!$show_again) {
    echo '
        <div class="rowContainer">
            <label class="rowTitle" for="payment_method">Method of Payment *:</label>
            <select tabindex="31" id="payment_method" name="payment_method" size="1" onchange="updateMessage();" onclick="updateMessage();" data-rule-required="true">
                <option value="">Choose One</option>
                <option value="PayPal">PayPal</option>
                <option value="Check/Money Order">Check or Money Order</option>
            </select>
            <div class="spacer" style="clear: both;"></div>
        </div>
        <div class="rowContainer" style="height: auto;">
            <label class="rowTitle" for="payment_note">Notes:</label>
            <textarea name="payment_note" id="payment_note" rows="8" cols="60">After you select a payment method, you can put a message here.'."\n\n".'Do you have a cabin preference or cabin-mates?'."\n".'Do you have a question for the director?
            </textarea>
            <div class="spacer" style="clear: both;"></div>
        </div>';
}else{
	echo '<table><tr><td></td><td><input type="hidden" name="payment_method" id="payment_method" value="'.$payment_method.'" /></td></tr><table>';
} 
    echo '<input tabindex="32" name="print" value="Print Application" onclick="window.print()" type="button" '.$preview.'/><br /><br />
          <input tabindex="33" class="submit" name="submit" type="submit" value="Send Application" '.$preview.'/><br /><br />
          <input tabindex="34" name="reset" type="reset" '.$preview.'/>
        </fieldset>
    </form>
    '.keepalive().'
';

//Finalize and activate validation code
echo create_validation_script("form1" , "submit_camp_new_registration()");
echo '  
<script type="text/javascript" language="javascript">
    $(document).ready(function() {
        $("select[id^=\'Camper_Birth_Date\']").change(function() { updateAge(); });
        $("select[id^=\'Camper_Birth_Date\']").attr("onKeyUp","updateAge()");
        $("input[id^=\'Camper_Birth_Date\']").change(function() { updateAge(); });
        $("#Camper_Birth_Date").change(function() { updateAge(); });
        $("#Camper_Birth_Date").attr("onChange","updateAge()");
        $("input,select,textarea").bind("focus",function() { $(this).closest(".rowContainer").css("background-color","whitesmoke"); });
        $("input,select,textarea").bind("blur",function() { $(this).closest(".rowContainer").css("background-color","white"); });
    });
</script>
';
?>