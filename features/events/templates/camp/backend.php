<?php
/***************************************************************************
 * backend.php - Backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 1/30/2012
 * $Revision: .7
 ***************************************************************************/
if (!isset($CFG)) { include('../../../../config.php'); } 
include($CFG->dirroot . '/pages/header.php');

if (!isset($EVENTSLIB)) include_once($CFG->dirroot . '/features/events/eventslib.php');

//Retrieve from Javascript
$postorget = isset($_GET["action"]) ? $_GET : $_POST;
$postorget = isset($postorget["action"]) ? $postorget : "";

$MYVARS->GET = $postorget;
if ($postorget != "") {
	$action = $postorget["action"];
	$action(); //Go to the function that was called.
}

update_user_cookie();

function register() {
global $CFG, $MYVARS, $USER, $error;
    if (!isset($COMLIB)) { include_once($CFG->dirroot.'/lib/comlib.php'); }

	$event = get_db_row("SELECT * FROM events WHERE eventid = ".$MYVARS->GET["eventid"]);
	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='".$event['template_id']."'");
	
	$MYVARS->GET["cart_total"] = $MYVARS->GET["total_owed"] != 0 ? $MYVARS->GET["total_owed"] + $MYVARS->GET["paypal_amount"] : $MYVARS->GET["paypal_amount"];
	$MYVARS->GET["total_owed"] = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] + $MYVARS->GET["Camper_Picture"] : $event["fee_full"] + $MYVARS->GET["Camper_Picture"];
	$MYVARS->GET["Camper_Picture"] = $MYVARS->GET["Camper_Picture"] != "0" ? 1 : 0;
	
	$formlist = explode(";", $template['formlist']);

    foreach ($formlist as $formelements) {
        $element = explode(":", $formelements);
		$reg[$element[0]] = $MYVARS->GET[$element[0]];    
    }
	$error = "";
	
	if ($regid = enter_registration($MYVARS->GET["eventid"], $reg, $MYVARS->GET["email"])) { //successful registration
		echo '<center><div style="width:90%">You have successfully registered for '.$event['name'] . '.<br />';
		
		if ($error != "") { echo $error . "<br />"; }
		
		if ($event['allowinpage'] !=0) {
			if (is_logged_in() && $event['pageid'] != $CFG->SITEID) { 
				subscribe_to_page($event['pageid'], $USER->userid);
				echo 'You have been automatically allowed into this events\' web page.  This page contain specific information about this event.';
			}
		}
		
		if ($event['fee_full'] != 0) {
			$items = isset($MYVARS->GET["items"]) ? $MYVARS->GET["items"] . "**" . $regid . "::" . $MYVARS->GET["Camper_Name"] . " - " . $event["name"] . "::" . $MYVARS->GET["paypal_amount"] : $regid . "::" . $MYVARS->GET["Camper_Name"] . " - " . $event["name"] . "::" . $MYVARS->GET["paypal_amount"];
			echo '<div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="'.$MYVARS->GET["cart_total"].'" />
				 <input type="hidden" name="items" id="items" value="'.$items.'" /></div>';
			
			$items = explode("**", $items);
            $i=0;
            foreach ($items as $item) {
                $itm = explode("::", $item);
				$cart_items[$i]->regid = $itm[0];
				$cart_items[$i]->description = $itm[1];
				$cart_items[$i]->cost = $itm[2];     
                $i++;           
            }
			
			if ($MYVARS->GET['payment_method'] == "PayPal") {
    			echo '<br />
				To register a <b>different</b> child:  Select the week '.common_weeks($event, true, "week1", $regid).'.<br />
				To register the <b>same child</b> for a <b>different week</b>:  Select the week '.common_weeks($event, false, "week2", $regid, 1).'.<br />
				<br />
				If you would like to pay the <span style="color:blue;font-size:1.25em;">$'.$MYVARS->GET["cart_total"].'</span> fee now, click the Paypal button below.
				<center>
				'.make_paypal_button($cart_items, $event['paypal']).'
				</center>
				<br /><br />
				Thank you for registering for this event. ';	
			} else {
				echo '<br />
				To register a <b>different</b> child:  Select the week '.common_weeks($event, true, "week1", $regid).'.<br />
				To register the <b>same child</b> for a <b>different week</b>:  Select the week '.common_weeks($event, false, "week2", $regid, 1).'.<br />
				<br />
				If you are done with the registration process, please make out your <br />
				check or money order in the amount of <span style="color:blue;font-size:1.25em;">$'.$MYVARS->GET["cart_total"].'</span> payable to <b>'.$event["payableto"].'</b> and send it to <br /><br />
				<center>
				'.$event['checksaddress'].'.  
				</center>
				<br /><br />
				Thank you for registering for this event. 
				';
			}
		}
		
		$touser->fname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name'");
		$touser->lname = "";
		$touser->email = get_db_field("email","events_registrations","regid='$regid'");
		$fromuser->email = $CFG->siteemail;
		$fromuser->fname = $CFG->sitename;
		$fromuser->lname = "";
		$message = registration_email($regid, $touser);
		if (send_email($touser, $fromuser, "Camp Wabashi Registration", $message)) {
			send_email($fromuser, $fromuser, "Camp Wabashi Registration", $message);
		} else { echo "<br /><br />Registration Email NOT Sent."; }
		
	} else { //failed registration
		$MYVARS->GET["cart_total"] = $MYVARS->GET["cart_total"] - $MYVARS->GET["paypal_amount"];
		echo '<center><div style="width:60%"><span class="error_text">Your registration for '.$event['name'].' has failed. </span><br /> '.$error . '</div>';	
		if (isset($MYVARS->GET["items"])) { //other registrations have already occured
			if ($event['fee_full'] != 0) {
				$items = $MYVARS->GET["items"];
				echo '<div id="backup"><input type="hidden" name="total_owed" id="total_owed" value="'.$MYVARS->GET["cart_total"].'" />
					 <input type="hidden" name="items" id="items" value="'.$items.'" /></div>';
				
				$items = explode("**", $items);
                $i=0;
                foreach ($items as $item) {
					$itm = explode("::", $item);
					$cart_items[$i]->regid = $itm[0];
					$cart_items[$i]->description = $itm[1];
					$cart_items[$i]->cost = $itm[2];                    
                    $i++;
                }
				
				if ($MYVARS->GET['payment_method'] == "PayPal") {
					echo '<br />
					To register a child: Select the week '.common_weeks($event, true, "week1", "").'.<br />
					<br />
					If you would like to pay the <span style="color:blue;font-size:1.25em;">$'.$MYVARS->GET["cart_total"].'</span> fee now, click the Paypal button below.
					<center>
					'.make_paypal_button($cart_items, $event['paypal']).'
					</center>
					<br /><br />
					Thank you for registering for this event. ';	
				} else { //Pay by check
					echo '<br />
					To register a child:  Select the week '.common_weeks($event, true, "week1", $regid).'.<br />
					<br />
					If you are done with the registration process, please make out your <br />
					check or money order in the amount of <span style="color:blue;font-size:1.25em;">$'.$MYVARS->GET["cart_total"].'</span> payable to <b>'.$event["payableto"].'</b> and send it to <br /><br />
					<center>
					'.$event['checksaddress'].'.  
					</center>
					<br /><br />
					Thank you for registering for this event. 
					';
				}	
			}
		}
	}
}

function common_weeks($event, $included = true, $id, $regid = "", $autofill = 0) {
global $CFG, $USER, $PAGE;
	$returnme = "";
	$time = get_timestamp();
	$siteviewable = $event["pageid"] == $CFG->SITEID || ($event["siteviewable"] == 1 && $event["confirmed"] == 1) ? " OR (siteviewable = '1' AND confirmed = '1')" : "";
	$includelastevent = $included ? "" : "e.eventid != ".$event["eventid"]. " AND ";
	$SQL = "SELECT e.* FROM events e WHERE $includelastevent (e.template_id=".$event["template_id"]." AND (e.pageid='".$event["pageid"]."' $siteviewable)) AND (e.start_reg < $time AND e.stop_reg > ($time - 86400)) AND (e.max_users=0 OR (e.max_users != 0 AND e.max_users > (SELECT COUNT(*) FROM events_registrations er WHERE er.eventid=e.eventid)))";

	if ($events = get_db_result($SQL)) {
		$returnme .= '<select id="'.$id.'">';
		while ($evnt = fetch_row($events)) {
			$selected = $event["eventid"] == $evnt["eventid"] ? " SELECTED " : "";
			$returnme .= '<option value="'.$evnt['eventid'].'" '.$selected.'>'.$evnt['name'].'</option>';
		}
		$returnme .= '</select>' . ' and click <a href="javascript:show_form_again(document.getElementById(\''.$id.'\').value,\''.$regid.'\', '.$autofill.');"><b>Continue</b></a>';
	} else { $returnme = '&nbsp;<span style="color:red;">There are no other weeks available. </span>'; }
	return $returnme;
}

function show_form_again() {
	include("template.php");
}
?>