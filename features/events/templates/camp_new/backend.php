<?php
/***************************************************************************
 * backend.php - Backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 08/16/2013
 * $Revision: 0.1.4
 ***************************************************************************/
if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'config.php')) {
		$sub .= '../';
	}
	include($sub . 'config.php'); 
}
include($CFG->dirroot . '/pages/header.php');

if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php');}

callfunction();

update_user_cookie();

function register() {
global $CFG, $MYVARS, $USER, $error;
//error_reporting(E_ERROR | E_PARSE); // Keep warnings from showing
 
    if (!defined('COMLIB')) { include_once($CFG->dirroot . '/lib/comlib.php'); }
    $eventid = $MYVARS->GET["eventid"];
	$event = get_db_row("SELECT * FROM events WHERE eventid = '$eventid'");
    $templateid = $event['template_id'];
	$template = get_db_row("SELECT * FROM events_templates WHERE template_id='$templateid'");

    // Facebook keys
    $global_settings = fetch_settings("events_template_global", $templateid);

    //Get charges for pictures and shirts
    $picture_cost = get_db_field("setting", "settings", "type='events_template' AND extra='$eventid' AND setting_name='template_setting_pictures_price'");
    $shirt_cost = get_db_field("setting", "settings", "type='events_template' AND extra='$eventid' AND setting_name='template_setting_shirt_price'");
    $MYVARS->GET["Camper_Picture"] = $MYVARS->GET["Camper_Picture"] != "0" ? 1 : 0;
    $MYVARS->GET["Camper_Shirt"] = !empty($MYVARS->GET["Camper_Shirt_Size"]) ? 1 : 0;
    $picture_cost = $MYVARS->GET["Camper_Picture"] ? $picture_cost : 0; 
    $shirt_cost = $MYVARS->GET["Camper_Shirt"] ? $shirt_cost : 0;   
    
    //Total up the registration bill
	$MYVARS->GET["cart_total"] = $MYVARS->GET["total_owed"] != 0 ? $MYVARS->GET["total_owed"] + $MYVARS->GET["owed"] : $MYVARS->GET["owed"];
	$MYVARS->GET["total_owed"] = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] + $picture_cost + $shirt_cost : $event["fee_full"] +  + $picture_cost + $shirt_cost;
    $MYVARS->GET["campership"] = $MYVARS->GET["payment_method"] == "Campership" && empty($MYVARS->GET["campership"]) ? "David Grubb Campership Fund" : $MYVARS->GET["campership"];
	
    $pending = $MYVARS->GET["payment_method"] == "Campership" ? false : true;

    //Prepare names
    $middle_i = empty($MYVARS->GET["Camper_Name_Middle"]) ? '' : " " . nameize($MYVARS->GET["Camper_Name_Middle"]) . ".";
    $MYVARS->GET["Camper_Name_Middle"] = $middle_i;
    $MYVARS->GET["Camper_Name_First"] = nameize($MYVARS->GET["Camper_Name_First"]);
    $MYVARS->GET["Camper_Name_Last"] = nameize($MYVARS->GET["Camper_Name_Last"]);
	$MYVARS->GET["Camper_Name"] = $MYVARS->GET["Camper_Name_Last"] . ", " . $MYVARS->GET["Camper_Name_First"] . $middle_i; 
    
    //Format phone numbers 
    $MYVARS->GET["Parent_Phone1"] = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["Parent_Phone1"])), 2);
    $MYVARS->GET["Parent_Phone2"] = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["Parent_Phone2"])), 2);
    $MYVARS->GET["Parent_Phone3"] = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["Parent_Phone3"])), 2);
    $MYVARS->GET["Parent_Phone4"] = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $MYVARS->GET["Parent_Phone4"])), 2);
    
    //Go through entire template form list
	$formlist = explode(";", $template['formlist']);
    foreach ($formlist as $formelements) {
        $element = explode(":", $formelements);
        $reg[$element[0]] = $MYVARS->GET[$element[0]] ?? ""; 
    }
	$error = "";
	
	if ($regid = enter_registration($eventid, $reg, $MYVARS->GET["email"], $pending)) { // Successful registration
        
        if ($error != "") { echo $error . "<br />"; }
        
        // Open registration div.
        echo '<div style="margin:auto;width:90%;text-align:center;">
                <h1>Congratulations!</h1>
                <br /><br />';
		
		if ($event['fee_full'] != 0) {
			$items = !empty($MYVARS->GET["items"]) ? $MYVARS->GET["items"] . "**" . $regid . "::" . $MYVARS->GET["Camper_Name"] . " - " . $event["name"] . "::" . $MYVARS->GET["owed"] : $regid . "::" . $MYVARS->GET["Camper_Name"] . " - " . $event["name"] . "::" . $MYVARS->GET["owed"];
			echo '<div id="backup">
                    <input type="hidden" name="total_owed" id="total_owed" value="' . $MYVARS->GET["cart_total"] . '" />
					  <input type="hidden" name="items" id="items" value="' . $items . '" />
                  </div>';

			$items = explode("**", $items);
            $i = 0;
            foreach ($items as $item) {
                $itm = explode("::", $item);
				$cart_items[$i]->regid = $itm[0];
				$cart_items[$i]->description = $itm[1];
				$cart_items[$i]->cost = $itm[2];     
                $i++;           
            }

            if ($MYVARS->GET['payment_method'] !== "Campership") {
                echo '  <h3>You have successfully added: ' . $MYVARS->GET["Camper_Name_First"] . ' for ' . $event['name'] . ' to your cart.</h3>';
                echo '  <br />Your current cart total is:  <span style="color:blue;font-size:1.25em;">$' . number_format($MYVARS->GET["cart_total"], 2) . '</span>';
            } else { // Campership
                echo '  <h3>You have successfully registered: ' . $MYVARS->GET["Camper_Name_First"] . ' for ' . $event['name'] . '.</h3>';
            }

            $more1 = common_weeks($event, false, "week2", $regid, 1);
            $more2 = common_weeks($event, true, "week1", $regid);

            if ($more1 || $more2) {
                echo '<br /><br /><h3 style="color:green">Do you need to do more?</h3><br />';
                echo empty($more1) ? "" : '<strong>Register ' . $MYVARS->GET["Camper_Name_First"] . ' for another week.<br />Select from the weeks available</strong><br />' . $more1 . '.<br />';
				  echo !empty($more1) && !empty($more2) ? '<br />' : ""; 
                echo empty($more2) ? "" : '<strong>Register another child.<br />Select from the weeks available</strong><br />' . $more2 . '<br />';
            }

            $campershipreq = "";
            $waivefee = false;
            if ($MYVARS->GET['payment_method'] == "PayPal") { // Paypal payment selected.
                $regmessage = 'Your registration is pending. To finalize your registrations:
                                <br />Click the Paypal button below to pay for your camper fees.
                                <br /><br />
                                <div style="text-align:center;">
                                    ' . make_paypal_button($cart_items, $event['paypal']) . '
                                </div>';
            } else if ($MYVARS->GET['payment_method'] == "Campership") { // Campership selected.
                $waivefee = true; // Campership is a free registration.
                $campershipreq = "CAMPERSHIP REQUEST (" . $MYVARS->GET['campership'] . "): ";
                $regmessage = '<br />You have requested to pay by Campership.
                                <br />We will review your application.  If we find that you are not elegible for a campership, we will notifiy you before
                                your event date.  Thank you! You are now registered for camp.';
            } else { // Check or Money Order required.
                $regmessage = 'Your registration is pending. To finalize your registrations:
                                <br /> Please send a check or money order in the amount of 
                                <span style="color:blue;font-size:1.25em;">
                                    $' . number_format($MYVARS->GET["cart_total"], 2) . '
                                </span> payable to <strong>' . $event["payableto"] . '</strong> and send it to
                                <br />
                                <div style="text-align:center;">
                                    <strong>' . $event['checksaddress'] . '</strong> 
                                </div>';
            }

            $emailsubject = "Camp Wabashi Registration";
            if ($pending) { // Payment is required to finalize registration.
                echo '<br /><h3 style="background-color: black; color: yellow">You\'re not finished yet...</h3>';
                $emailsubject = "Pending Camp Wabashi Registration";
            }

            echo $regmessage; // Close registration div.

            // Send registration email.
            $touser = new stdClass();
            $touser->fname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name_First'");
  			$touser->lname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name_Last'");
  			$touser->email = get_db_field("email", "events_registrations", "regid='$regid'");
  			$fromuser = new stdClass();
            $fromuser->email = $CFG->siteemail;
  			$fromuser->fname = $CFG->sitename;
  			$fromuser->lname = "";    
            $message = registration_email($regid, $touser, $pending, $waivefee);
  			if (send_email($touser, $fromuser, $emailsubject, $message)) {
  				send_email($fromuser, $fromuser, $campershipreq.$emailsubject, $message);
  			}

		} else { // Support for a free event.
            echo '<h3>You have successfully registered ' . $MYVARS->GET["Camper_Name_First"] . ' for ' . $event['name'] . '.</h3>';

            execute_db_sql("UPDATE events_registrations SET verified='1' WHERE regid='$regid'");

            //Send registration email
            $touser = new stdClass();
            $touser->fname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name_First'");
  			$touser->lname = get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name_Last'");
  			$touser->email = get_db_field("email", "events_registrations", "regid='$regid'");
  			$fromuser = new stdClass();
            $fromuser->email = $CFG->siteemail;
  			$fromuser->fname = $CFG->sitename;
  			$fromuser->lname = "";
  			$message = registration_email($regid, $touser);
  			if (send_email($touser, $fromuser, "Camp Wabashi Registration", $message)) {
  				send_email($fromuser, $fromuser, "Camp Wabashi Registration", $message);
  			}
        }

        if ($event['allowinpage'] !== 0) {
			if (is_logged_in() && $event['pageid'] != $CFG->SITEID) { 
				subscribe_to_page($event['pageid'], $USER->userid);
				echo '<br />You have been automatically allowed into this events\' web page.  This page contain specific information about this event.<br />';
			}
		}

        echo '</div>
              <br />
              <div style="margin:auto;width:90%;text-align:center;">' . facebook_share_button($event, $MYVARS->GET["Camper_Name_First"]) . '</div>';
	} else { // Failed registration.
		$MYVARS->GET["cart_total"] = $MYVARS->GET["cart_total"] - $MYVARS->GET["owed"];
		echo '<div style="text-align:center;">
                <div style="width:60%;margin:auto;">
                    <span class="error_text">Your registration for ' . $event['name'] . ' has failed. </span>
                    <br />
                    ' . $error . '
                </div>';	
		if (isset($MYVARS->GET["items"])) { // Other registrations have already occured
			if ($event['fee_full'] != 0) {
                if (!empty($MYVARS->GET["items"])) {
                    $items = $MYVARS->GET["items"];
  					echo '<div id="backup">
                            <input type="hidden" name="total_owed" id="total_owed" value="' . $MYVARS->GET["cart_total"] . '" />
  							  <input type="hidden" name="items" id="items" value="' . $items . '" />
                          </div>';

  					$items = explode("**", $items);
                    $i = 0;
                    foreach ($items as $item) {
  						$itm = explode("::", $item);
  						$cart_items[$i]->regid = $itm[0];
  						$cart_items[$i]->description = $itm[1];
  						$cart_items[$i]->cost = $itm[2];                    
                        $i++;
                    }

  					if ($MYVARS->GET['payment_method'] == "PayPal") {
  						echo '<br />
  						To register a child: Select the week ' . common_weeks($event, true, "week1", "") . '.<br />
  						<br />
  						If you would like to pay the <span style="color:blue;font-size:1.25em;">$' . $MYVARS->GET["cart_total"] . '</span> fee now, click the Paypal button below.
  						<br /><br />
                        <div style="text-align:center;">
  						' . make_paypal_button($cart_items, $event['paypal']) . '
  						</div>';	
  					} else if ($MYVARS->GET['payment_method'] == "Campership") { // Campership
                        echo '<br />
  						To register a child:  Select the week ' . common_weeks($event, true, "week1", $regid) . '<br />';
                    } else { // Pay by check
  						echo '<br />
  						To register a child:  Select the week ' . common_weeks($event, true, "week1", $regid) . '<br />
  						<br />
  						If you are done with the registration process, please make out your <br />
  						check or money order in the amount of <span style="color:blue;font-size:1.25em;">$' . $MYVARS->GET["cart_total"] . '</span> payable to <b>' . $event["payableto"] . '</b> and send it to <br /><br />
  						<br />
                        <div style="text-align:center;">
  						' . $event['checksaddress'] . '.  
  						</div>';
  					}

                    echo "<br /><br /><strong>Thank you for registering for this event.</strong>";		                 
                }
			}
		}
        echo "</div>";
	}
}

function common_weeks($event, $included = true, $id = "", $regid = "", $autofill = 0) {
global $CFG, $USER, $PAGE;
	$returnme = "";
	$time = get_timestamp();
    $camper_age = $autofill == 0 ? false : get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Age'");
    $camper_name = $autofill == 0 ? false : get_db_field("value", "events_registrations_values", "regid='$regid' AND elementname='Camper_Name'");
	$siteviewable = $event["pageid"] == $CFG->SITEID || ($event["siteviewable"] == 1 && $event["confirmed"] == 1) ? " OR (siteviewable = '1' AND confirmed = '1')" : "";
	$includelastevent = $included ? "" : "e.eventid != " . $event["eventid"]. " AND ";
	$SQL = "SELECT e.* FROM events e WHERE $includelastevent (e.template_id=" . $event["template_id"] . " AND (e.pageid='" . $event["pageid"] . "' $siteviewable)) AND (e.start_reg < $time AND e.stop_reg > ($time - 86400)) AND (e.max_users=0 OR (e.max_users != 0 AND e.max_users > (SELECT COUNT(*) FROM events_registrations er WHERE er.eventid=e.eventid AND verified='1')))";
	if ($events = get_db_result($SQL)) {
        $common = [];
		while ($evnt = fetch_row($events)) {
			$selected = $event["eventid"] == $evnt["eventid"] ? " SELECTED " : "";
            $min_age = get_db_field("setting", "settings", "type='events_template' AND extra='" . $evnt["eventid"] . "' AND setting_name='template_setting_min_age'");
            $max_age = get_db_field("setting", "settings", "type='events_template' AND extra='" . $evnt["eventid"] . "' AND setting_name='template_setting_max_age'");
            $already_registered = get_db_count("SELECT * FROM events_registrations WHERE regid IN (SELECT regid FROM events_registrations_values WHERE elementname='Camper_Name' AND value='$camper_name') AND regid IN (SELECT regid FROM events_registrations_values WHERE elementname='Camper_Age' AND value='$camper_age') AND eventid='" . $evnt["eventid"] . "'");
            // Meets minimum age and maximum age requirements if set
            if (!$camper_age || ((!$min_age && !$max_age) || ($min_age && $camper_age >= $min_age && $max_age && $camper_age <= $max_age) || 
               (!$max_age && ($min_age && $camper_age >= $min_age)) || 
               (!$min_age && ($max_age && $camper_age >= $max_age)))
            ) { 
                if (!$already_registered) { $common[] = ['eventid' => $evnt['eventid'], 'selected' => $selected,'name' => $evnt['name']]; }       
            }
		}
        $returnme = count($common) ? '<select id="' . $id . '">' : '&nbsp;<span style="color:red;">There are no other weeks available. </span>';
        foreach ($common as $c) {
            $returnme .= '<option value="' . $c['eventid'] . '" ' . $c['selected'] . '>' . $c['name'] . '</option>';
        }
        $returnme .= count($common) ? '</select> <input type="button" onclick="show_form_again($(\'#' . $id . '\').val(),\'' . $regid . '\', ' . $autofill . ');" value="Register" />' : '';
	} else { return false; }
	return $returnme;
}
?>