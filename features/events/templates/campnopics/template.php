<?php
/***************************************************************************
 * template.php - Camp Wabashi Template page
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * Date: 5/14/2022
 * $Revision: .8
 ***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}

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

$picturecost = false; //if no picture is needed, set to false

if ($show_again) {
    if ($autofill) {
        $lastRegistration = get_db_result("SELECT * FROM events_registrations_values WHERE regid=$regId");
        while ($registrationInfo = fetch_row($lastRegistration)) {
            ${$registrationInfo["elementname"]} = $registrationInfo["value"];
        }
        $email = get_db_field("email", "events_registrations", "regid=$regId");
    } else {
        $paymentMethod = get_db_field("value", "events_registrations_values", "elementname='payment_method' AND regid=$regId");
    }
}

if (!$show_again) {
  echo js_script_wrap($CFG->wwwroot . '/features/events/templates/campnopics/ajax.js');
  echo '	<form class="event_template_form" name="form1">
				<div id="camp">
					<input type="hidden" name="total_owed" id="total_owed" value="' . $total_owed . '" />';
}

echo '
	<input type="hidden" name="eventid" value="' . $eventid . '" />
	<input type="hidden" name="paid" value="0" />
	<table>
		<tr>
			<td colspan="2" align="center" valign="top">
				<h1 align="center">Camp Wabashi Online Pre-Registration</h1>
				<h3 align="center">' . $event["name"] . '</h3>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<p>
					<a target="policy" href="' . $CFG->wwwroot . '/userfiles/1/file/regpolicy.html">Registration Policy</a>
				</p>
			</td>
		</tr>';
  
if ($autofill) {
 echo '  <tr> 
            <td>
          		Camper: ' . $Camper_Name . '
					<input type="hidden" name="Camper_Name" value="' . $Camper_Name . '" />
					<input type="hidden" name="email" value="' . $email . '" />
					<input type="hidden" name="Camper_Birth_Date" value="' . $Camper_Birth_Date . '" />
					<input type="hidden" name="Camper_Age" value="' . $Camper_Age . '" />
					<input type="hidden" name="Camper_Grade" value="' . $Camper_Grade . '" />
					<input type="hidden" name="Camper_Gender" value="' . $Camper_Gender . '" />
					<input type="hidden" name="Camper_Home_Congregation" value="' . $Camper_Home_Congregation . '" />
					<input type="hidden" name="Parent_Address_Line1" value="' . $Parent_Address_Line1 . '" />
					<input type="hidden" name="Parent_Address_Line2" value="' . $Parent_Address_Line2 . '" />
					<input type="hidden" name="Parent_Address_City" value="' . $Parent_Address_City . '" />
					<input type="hidden" name="Parent_Address_State" value="' . $Parent_Address_State . '" />
					<input type="hidden" name="Parent_Address_Zipcode" value="' . $Parent_Address_Zipcode . '" />
					<input type="hidden" name="Parent_Phone1" value="' . $Parent_Phone1 . '" />
					<input type="hidden" name="Parent_Phone2" value="' . $Parent_Phone2 . '" />
					<input type="hidden" name="Parent_Phone3" value="' . $Parent_Phone3 . '" />
					<input type="hidden" name="Parent_Phone4" value="' . $Parent_Phone4 . '" />
					<input type="hidden" name="HealthConsentFrom" value="' . $HealthConsentFrom . '" />
					<input type="hidden" name="HealthConsentTo" value="' . $HealthConsentTo . '" />
					<input type="hidden" name="HealthMemberName" value="' . $HealthMemberName . '" />
					<input type="hidden" name="HealthRelationship" value="' . $HealthRelationship . '" />
					<input type="hidden" name="HealthInsurance" value="' . $HealthInsurance . '" />
					<input type="hidden" name="HealthIdentification" value="' . $HealthIdentification . '" />
					<input type="hidden" name="HealthBenefitCode" value="' . $HealthBenefitCode . '" />
					<input type="hidden" name="HealthAccount" value="' . $HealthAccount . '" />
					<input type="hidden" name="HealthExpirationDate" value="' . $HealthExpirationDate . '" />
					<input type="hidden" name="HealthHistory" value="' . $HealthHistory . '" />
					<input type="hidden" name="HealthAllergies" value="' . $HealthAllergies . '" />
					<input type="hidden" name="HealthExisting" value="' . $HealthExisting . '" />
					<input type="hidden" name="HealthMedicines" value="' . $HealthMedicines . '" />
					<input type="hidden" name="HealthTetanusDate" value="' . $HealthTetanusDate . '" />
				</td>
			</tr>';
}
else
{
 echo '  
		<tr>
			<td class="field_title" align="right" style="width:115px;">
			<strong><font size="2">Contact Email&nbsp;*&nbsp;</font></strong>
			</td>
			<td class="field_input" align="left">
			<input type="text" id="email" name="email" size="40" value="' . $email . '"/>
			</td>
		</tr><tr><td></td><td class="field_input"><span id="email_error" class="error_text"></span></td></tr>
 		<tr> 
            <td align="right"><strong><font size="2">Camper&nbsp;Name&nbsp;<strong>(Last,&nbsp;First&nbsp;Middle)</strong>&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Camper_Name" size="40" type="text" /></td>
          </tr>
          <tr> 
            <td align="right"><strong><font size="2">Date&nbsp;of&nbsp;Birth&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Camper_Birth_Date" size="10" maxlength="10" value="mm/dd/yy" type="text" /></td>
          </tr>
          <tr> 
            <td align="right"><strong><font size="2">Age&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Camper_Age" size="2" maxlength="2" type="text" /></td>
          </tr>
          <tr> 
            <td align="right"><strong><font size="2">Last&nbsp;Grade&nbsp;Completed&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Camper_Grade" size="2" maxlength="2" type="text" /></td>
          </tr>
          <tr> 
            <td align="right"><strong><font size="2">Gender&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><select name="Camper_Gender">
                <option value="">Select One...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select></td>
          </tr>
          <tr> 
            <td align="right"><font size="2">Home&nbsp;Congregation&nbsp;</font></td>
            <td align="left"><input name="Camper_Home_Congregation" size="40" type="text"></td>
          </tr>
          <tr><td colspan="2"><hr></td></tr>
          <tr>
            <td align="right"><font size="2">Parent/Guardian&nbsp;E-Mail</font></td>
            <td align="left"><input name="mailfrom" size="40" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Parent/Guardian&nbsp;Name&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="namefrom" size="40" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Mailing&nbsp;Address&nbsp;Line&nbsp;One&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Parent_Address_Line1" size="40" type="text"></td>
          </tr>
          <tr>
            <td align="right"><font size="2">Address&nbsp;Line&nbsp;Two&nbsp;</font></td>
            <td align="left"><input name="Parent_Address_Line2" size="40" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">City&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Parent_Address_City" size="40" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">State&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Parent_Address_State" size="2" maxlength="2" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Zip Code&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Parent_Address_Zipcode" size="13" maxlength="10" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Parent/Guardian&nbsp;Phone&nbsp;One&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Parent_Phone1" size="13" maxlength="13" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Parent/Guardian&nbsp;Phone&nbsp;Two&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="Parent_Phone2" size="13" maxlength="13" type="text"></td>
          </tr>
          <tr>
            <td align="right"><font size="2">Phone&nbsp;Three&nbsp;</font></td>
            <td align="left"><input name="Parent_Phone3" size="13" maxlength="13" type="text"></td>
          </tr>
          <tr>
            <td align="right"><font size="2">Phone&nbsp;Four&nbsp;</font></td>
            <td align="left"><input name="Parent_Phone4" size="13" maxlength="13" type="text"></td>
          </tr>
          <tr><td colspan="2"><hr></td></tr>
        <tr> 
          <td colspan="2" align="center">
            <font size="5"> 
              <b>CONSENT FOR MEDICAL TREATMENT OF A MINOR CHILD</b><br>
              </font>
            <font size="2"> 
            <p align="left"> I herewith authorize Camp Wabashi staff at the Wabash 
              Valley Christian Youth Camp to request and consent in writing or 
              otherwise as requested by Union Hospital, Inc. and/or any other 
              medical facility to any and all examinations, medical treatment 
              and/or procedures to or for the above named minor, either on or 
              off the premises of medical facility, as deemed advisable or appropriate 
              by any physician or surgeon licensed to practice medicine in the 
              State of Indiana. I understand that verbal concent will be sought using
              the phone numbers listed in this application.<br>
              <br>
              In consideration of the acceptance of the above named camper, I 
              covenant and agree with Wabash Valley Christian Youth Camp, that 
              I will at all times hereafter indemnify, and save harmless the said 
              Wabash Valley Christian Youth Camp from all actions, proceedings, 
              claims, demands, costs, damages and expenses which may be brought 
              against, or claimed from Wabash Valley Christian Youth Camp, or 
              which it may pay, sustain, or incur as a result of illness, accident, 
              or misadventure to the above named camper during the period said 
              camper is a participant in the Wabash Valley Christian Youth Camp.<br>
              <br>
              This authorization constitutes a Power of Attorney and waiver of 
              liability appointing the above named adult or staff of Wabash Valley 
              Christian Youth Camp as Attorney-In-Fact to sign said requests and 
              as fully thought I, myself, did so. </p>
            </font></td></tr>
          <tr>
            <td align="right"><strong><font size="2">This consent effective&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><font size="2">
            From: <input name="HealthConsentFrom" size="13" maxlength="13" value="' . date("m/d/Y", $event["event_begin_date"]) . '" type="text">
            To: <input name="HealthConsentTo" size="13" maxlength="13" value="' . date("m/d/Y", $event["event_end_date"]) . '" type="text"></font></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Member\'s Name&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="HealthMemberName" size="40" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Relationship&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="HealthRelationship" size="20" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Medical Insurance Carrier&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="HealthInsurance" size="40" type="text"></td>
          </tr>
          <tr>
            <td align="right"><font size="2">Medical Identification Number&nbsp;</font></td>
            <td align="left"><input name="HealthIdentification" size="20" type="text"></td>
          </tr>
          <tr>
            <td align="right"><font size="2">Benefit Code&nbsp;</font></td>
            <td align="left"><input name="HealthBenefitCode" size="20" type="text"></td>
          </tr>
          <tr>
            <td align="right"><font size="2">Account Number&nbsp;</font></td>
            <td align="left"><input name="HealthAccount" size="20" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Expiration Date&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="HealthExpirationDate" size="20" value="mm/dd/yy" type="text"></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Medical History&nbsp;*&nbsp;</font></strong></td>
            <td><textarea name="HealthHistory" rows="8" cols="64">-none-</textarea></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Allergies&nbsp;*&nbsp;</font></strong></td>
            <td><textarea name="HealthAllergies" rows="8" cols="64">-none-</textarea></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Chronic/existing diseases/medical problems&nbsp;*&nbsp;</font></strong></td>
            <td><textarea name="HealthExisting" rows="8" cols="64">-none-</textarea></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Medicines&nbsp;*&nbsp;</font></strong></td>
            <td><textarea name="HealthMedicines" rows="8" cols="64">-none-</textarea></td>
          </tr>
          <tr>
            <td align="right"><strong><font size="2">Date of last Tetanus injection/booster&nbsp;*&nbsp;</font></strong></td>
            <td align="left"><input name="HealthTetanusDate" size="20" value="mm/dd/yy" type="text"></td>
          </tr>';
}

echo '<tr><td colspan="2"><hr></td></tr>
    <tr>
      <td align="right"><strong><font size="2">Pay With Application</font></strong></td>
      <td>' . make_fee_options($event['fee_min'], $event['fee_full'], "payment_amount",'onchange="updateTotal();" onclick="updateTotal();"', $event['sale_end'], $event['sale_fee']) . '</td>
    </tr>';
    
if ($picturecost) {
echo '<tr>
      <td align="right"><strong><font size="2">Camp&nbsp;Picture:&nbsp;</font></strong></td>
       <td><input name="Camper_Picture" value="' . $picturecost . '" onchange="updateTotal();" onclick="updateTotal();" type="radio">Yes
        <input name="Camper_Picture" value="0" onchange="updateTotal();" onclick="updateTotal();" type="radio">No
        &nbsp;&nbsp;($5.00 for 8x10 color picture of all campers and staff)
      </td>
    </tr>';
}
echo
    '<tr>
      <td align="right"><strong><font size="2">Total:&nbsp;</font></strong></td>
        <td>$<input name="paypal_amount" size="5" value="' . $event['fee_min'] . '" type="text" READONLY></td>
    </tr>';

if (!$show_again) {
  echo '<tr>
      <td align="right"><strong><font size="2">Method&nbsp;of&nbsp;Payment:&nbsp;</font></strong></td>
        <td><select name="payment_method" size="1" onchange="updateMessage();" onclick="updateMessage();">
        <option value="">Choose One</option>
        <option value="PayPal">Pay online using Credit Card/PayPal</option>
        <option value="Check/Money Order">Check or Money Order</option>
      </select></td>
    </tr>
    <tr>
      <td align="right"><font size="2">Notes&nbsp;</font></td>
      <td><textarea name="payment_note" rows="8" cols="64">After you select a payment method, you can put a message here.
Do you have a cabin preference or cabin-mates?
Do you have a question for the director?
      </textarea></td>
    </tr>';
}
else
{
	echo '<tr><td></td><td><input type="hidden" name="payment_method" value="' . $payment_method . '" /></td></tr>';
} 
   
echo '<tr> 
          <td colspan="2" align="center">
            <input name="print" value="Print Application" onclick="window.print()" type="button" ' . $disable . '/>
            &nbsp;<input onclick="campnopics_submit_registration();" value="Send Application" type="button" ' . $disable . ' /> 
            &nbsp;<input name="reset" type="reset" ' . $disable . '/> </td>
        </tr>
      </table>';

if (!$show_again) {
	echo '</div></form>';
}

echo js_code_wrap('prepareInputsForHints();');

?>
