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

    $return = "";
    try {
        start_db_transaction();
        if (!defined('COMLIB')) { include_once($CFG->dirroot . '/lib/comlib.php'); }
        $eventid = clean_myvar_req("eventid", "int");
        $event = get_event($eventid);
        $templateid = $event['template_id'];
        $template = get_event_template($templateid);

        // Facebook keys
        $global_settings = fetch_settings("events_template_global", $templateid);

        //Get charges for pictures and shirts
        $Camper_Picture = clean_myvar_opt("Camper_Picture", "bool", false);
        $Camper_Shirt = clean_myvar_opt("Camper_Shirt", "bool", false);
        $picture_cost = get_db_field("setting", "settings", "type='events_template' AND extra = ||extra|| AND setting_name='template_setting_pictures_price'", ["extra" => $eventid]);
        $shirt_cost = get_db_field("setting", "settings", "type='events_template' AND extra = ||extra|| AND setting_name='template_setting_shirt_price'", ["extra" => $eventid]);
        $picture_cost = $Camper_Picture ? $picture_cost : 0;
        $shirt_cost = $Camper_Shirt ? $shirt_cost : 0;

        //Total up the registration bill
        $total_owed = clean_myvar_opt("total_owed", "int", 0);
        $owed = clean_myvar_opt("owed", "int", 0);
        $payment_method = clean_myvar_opt("payment_method", "string", "PayPal");
        $campership = clean_myvar_opt("campership", "string", false);
        $cart_total = $total_owed ? $total_owed + $owed : $owed;
        $total_owed = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] + $picture_cost + $shirt_cost : $event["fee_full"] + $picture_cost + $shirt_cost;

        // Check for complete campership data.
        $payment_method = $payment_method == "Campership" && empty($campership) ? "Pay Later" : $payment_method;
        $campership = $payment_method == "Campership" && empty($campership) ? "" : $campership;
        $pending = $payment_method == "Campership" ? false : true;

        // Prepare names
        $Camper_Name_Middle = clean_myvar_opt("Camper_Name_Middle", "string", "");
        $Camper_Name_Middle = empty($Camper_Name_Middle) ? '' : " " . nameize($Camper_Name_Middle) . ".";
        $Camper_Name_First = nameize(clean_myvar_opt("Camper_Name_First", "string", ""));
        $Camper_Name_Last = nameize(clean_myvar_opt("Camper_Name_Last", "string", ""));
        $Camper_Name = $Camper_Name_Last . ", " . $Camper_Name_First . $Camper_Name_Middle;

        $email = clean_myvar_req("email", "string");

        // Make registration email user objects.
        $touser = (object)["fname" => $Camper_Name_First, "lname" => $Camper_Name_Last, "email" => $email];
        $fromuser = (object)["fname" => $CFG->sitename, "lname" => "", "email" => $CFG->siteemail];

        // Format phone numbers
        $Parent_Phone1 = clean_myvar_opt("Parent_Phone1", "string", "");
        $Parent_Phone2 = clean_myvar_opt("Parent_Phone2", "string", "");
        $Parent_Phone3 = clean_myvar_opt("Parent_Phone3", "string", "");
        $Parent_Phone4 = clean_myvar_opt("Parent_Phone4", "string", "");

        $Parent_Phone1 = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $Parent_Phone1)), 2);
        $Parent_Phone2 = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $Parent_Phone2)), 2);
        $Parent_Phone3 = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $Parent_Phone3)), 2);
        $Parent_Phone4 = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", $Parent_Phone4)), 2);

        // Go through entire template form list
        $formlist = explode(";", $template['formlist']);
        foreach ($formlist as $formelements) {
            $element = explode(":", $formelements);
            //Get values
            $elname = $element[0];
            if (isset($$elname)) {
                $reg[$elname] = $$elname;
            } elseif (isset($MYVARS->GET[$elname])) {
                $reg[$elname] = $MYVARS->GET[$elname];
            } else {
                $reg[$elname] = "";
            }
        }

        // Save registration
        $error = "";
        if ($regid = enter_registration($eventid, $reg, $email, $pending)) {
            if (!empty($error)) {
                throw new Exception($error);
            }

            $emailsubject = "Camp Wabashi Registration";
            $campershipreq = "";

            // Successful registration
            if ($event['fee_full'] === 0) { // Support for a free event.
                execute_db_sql("UPDATE events_registrations SET verified = 1 WHERE regid = ||regid||", ["regid" => $regid]);
                $return .= '<h3>You have successfully registered ' . $Camper_Name_First . ' for ' . $event['name'] . '.</h3>';

                $message = registration_email($regid, $touser); // Get email message.
            } else { // This registration has a cost.
                $items = clean_myvar_opt("items", "string", "");
                $items = !empty($items) ? $items . "**" . $regid . "::" . $Camper_Name . " - " . $event["name"] . "::" . $owed : $regid . "::" . $Camper_Name . " - " . $event["name"] . "::" . $owed;
                $return .= '
                    <div id="backup">
                        <input type="hidden" name="total_owed" id="total_owed" value="' . $cart_total . '" />
                        <input type="hidden" name="items" id="items" value="' . $items . '" />
                    </div>';

                $items = explode("**", $items);
                $i = 0;
                foreach ($items as $item) {
                    $itm = explode("::", $item);
                    $cart_items = [];
                    $cart_items[$i] = (object)[
                        "regid" => $itm[0],
                        "description" => $itm[1],
                        "cost" => $itm[2],
                    ];
                    $i++;
                }

                if ($payment_method !== "Campership") {
                    $return .= '
                        <h3>You have successfully added: ' . $Camper_Name_First . ' for ' . $event['name'] . ' to your cart.</h3>
                        <br />
                        Your current cart total is:  <span style="color:blue;font-size:1.25em;">$' . number_format($cart_total, 2) . '</span>';
                } else { // Campership
                    $return .= '<h3>You have successfully registered: ' . $Camper_Name_First . ' for ' . $event['name'] . '.</h3>';
                }

                $more1 = common_weeks($event, false, "week2", $regid, 1);
                $more2 = common_weeks($event, true, "week1", $regid);

                if ($more1 || $more2) {
                    $return .= '
                        <br /><br />
                        <h3 style="color:green">Do you need to do more?</h3>
                        <br />
                        ' . (empty($more1) ? "" : '<strong>Register ' . $Camper_Name_First . ' for another week.<br />Select from the weeks available</strong><br />' . $more1 . '.<br />') . '
                        ' . (!empty($more1) && !empty($more2) ? '<br />' : "") . '
                        ' . (empty($more2) ? "" : '<strong>Register another child.<br />Select from the weeks available</strong><br />' . $more2 . '<br />');
                }

                $campershipreq = "";
                $waivefee = false;
                if ($payment_method == "PayPal") { // Paypal payment selected.
                    $regmessage = '
                        Your registration is pending. To finalize your registrations:
                        <br />
                        Click the Paypal button below to pay for your camper fees.
                        <br /><br />
                        <div style="text-align:center;">
                            ' . make_paypal_button($cart_items, $event['paypal']) . '
                        </div>';
                } else if ($payment_method == "Campership") { // Campership selected.
                    $waivefee = true; // Campership is a free registration.
                    $campershipreq = "CAMPERSHIP REQUEST (" . $campership . "): ";
                    $regmessage = '
                        <br />
                        You have requested to pay by Campership.
                        <br />
                        We will review your application. If we find that you are not elegible for a campership, we will notifiy you before your event date. Thank you! You are now registered for camp.';
                } else { // Pay Later.
                    $regmessage = '
                        Your registration is pending. To finalize your registrations:
                        <br />
                        You have chosen to pay at a later date.  There are multiple ways you may do this.
                        <br />
                        Payment can be made via the pay link that you will recieve in your registration email, or
                        <br />
                        you may pay in-person at check-in for the event (additional fees may apply).
                        <br /><br />
                        You may also send a check or money order in the amount of <span style="color:blue;font-size:1.25em;">$' . number_format($cart_total, 2) . '</span> payable to <strong>' . $event["payableto"] . '</strong> and send it to
                        <br />
                        <div style="text-align:center;">
                            <strong>' . $event['checksaddress'] . '</strong>
                        </div>';
                }

                $emailsubject = "Camp Wabashi Registration";
                if ($pending) { // Payment is required to finalize registration.
                    $return .= '<br /><h3 style="background-color: black; color: yellow">You\'re not finished yet...</h3>';
                    $emailsubject = "Pending Camp Wabashi Registration";
                }

                $return .= $regmessage; // Close registration div.
                $message = registration_email($regid, $touser, $pending, $waivefee);
            }

            if ($event['allowinpage'] !== 0) {
                if (is_logged_in() && $event['pageid'] != $CFG->SITEID) {
                    change_page_subscription($event['pageid'], $USER->userid);
                    $return .= '
                        <br />
                        You have been automatically allowed into this events\' web page.  This page contain specific information about this event.
                        <br />';
                }
            }

            try {
                $facebookbuttons = \facebook_share_button($event, $Camper_Name_First);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                $facebookbuttons = "";
            }

            $return = '
                <div style="margin:auto;width:90%;text-align:center;">
                    <h1>Congratulations!</h1>
                    <br /><br />
                    ' . $return . '
                </div>
                <br />
                <div style="margin:auto;width:90%;text-align:center;">
                ' . $facebookbuttons . '
                </div>';

            try {
                if (\send_email($touser, $fromuser, $emailsubject, $message)) {
                    send_email($fromuser, $fromuser, $campershipreq . $emailsubject, $message);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } else { // Failed registration.
            $cart_total = $cart_total - $owed;
            $return .= '
                <div style="width:60%;margin:auto;">
                    <span class="error_text">
                        Your registration for ' . $event['name'] . ' has failed.
                    </span>
                    <br />
                    ' . $error . '
                </div>';

            $items = clean_myvar_opt("items", "string", false);
            if ($items) { // Other registrations have already occured
                if ($event['fee_full'] != 0) {
                    if ($items) {
                        $return .= '
                            <div id="backup">
                                <input type="hidden" name="total_owed" id="total_owed" value="' . $cart_total . '" />
                                <input type="hidden" name="items" id="items" value="' . $items . '" />
                            </div>';

                        $items = explode("**", $items);
                        $i = 0;
                        foreach ($items as $item) {
                            $itm = explode("::", $item);
                            $cart_items = [];
                            $cart_items[$i] = (object)[
                                "regid" => $itm[0],
                                "description" => $itm[1],
                                "cost" => $itm[2],
                            ];
                            $i++;
                        }

                        if ($payment_method == "PayPal") {
                            $return .= '
                                <br />
                                To register a child: Select the week ' . common_weeks($event, true, "week1", "") . '.
                                <br /><br />
                                If you would like to pay the <span style="color:blue;font-size:1.25em;">$' . $cart_total . '</span> fee now, click the Paypal button below.
                                <br /><br />
                                <div style="text-align:center;">
                                    ' . make_paypal_button($cart_items, $event['paypal']) . '
                                </div>';
                        } elseif ($payment_method == "Campership") { // Campership
                            $return .= '
                                <br />
                                To register a child:  Select the week ' . common_weeks($event, true, "week1", $regid) . '.<br />';
                        } else { // Pay by check
                            $return .= '
                                <br />
                                To register a child:  Select the week ' . common_weeks($event, true, "week1", $regid) . '.
                                <br /><br />
                                If you are done with the registration process, you have multiple ways to pay <br />
                                You can make an online payment through the payment link in the email you will receive <br />
                                You can also pay at the time of the event (although additional fees could apply) <br />
                                or if you are planning to pay by check or money order, make it <br />
                                in the amount of <span style="color:blue;font-size:1.25em;">$' . $cart_total . '</span> payable to <strong>' . $event["payableto"] . '</strong> and send it to
                                <br /><br /><br />
                                <div style="text-align:center;">
                                    ' . $event['checksaddress'] . '.
                                </div>';
                        }

                        $return .= '
                            <br /><br />
                            <strong>Thank you for registering for this event.</strong>';
                    }
                }
            }
            $return = '<div style="text-align:center;">' . $return . '</div>';
        }
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error .= $e->getMessage();
    }

    ajax_return($return, $error);
}

function common_weeks($event, $included = true, $id = "", $regid = "", $autofill = 0) {
global $CFG, $USER, $PAGE;
    $returnme = "";
    $time = get_timestamp();
    $camper_age = $autofill == 0 ? false : get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname='Camper_Age'", ["regid" => $regid]);
    $camper_name = $autofill == 0 ? false : get_db_field("value", "events_registrations_values", "regid = ||regid|| AND elementname='Camper_Name'", ["regid" => $regid]);
    $siteviewable = $event["pageid"] == $CFG->SITEID || ($event["siteviewable"] == 1 && $event["confirmed"] == 1) ? " OR (siteviewable = '1' AND confirmed = '1')" : "";
    $includelastevent = $included ? "" : "e.eventid != " . $event["eventid"]. " AND ";
    $SQL = "SELECT e.* FROM events e WHERE $includelastevent (e.template_id=" . $event["template_id"] . " AND (e.pageid='" . $event["pageid"] . "' $siteviewable)) AND (e.start_reg < $time AND e.stop_reg > ($time - 86400)) AND (e.max_users=0 OR (e.max_users != 0 AND e.max_users > (SELECT COUNT(*) FROM events_registrations er WHERE er.eventid=e.eventid AND verified='1')))";
    if ($events = get_db_result($SQL)) {
        $common = [];
        while ($evnt = fetch_row($events)) {
            $selected = $event["eventid"] == $evnt["eventid"] ? " SELECTED " : "";
                $min_age = get_db_field("setting", "settings", "type='events_template' AND extra = '" . $evnt["eventid"] . "' AND setting_name='template_setting_min_age'");
                $max_age = get_db_field("setting", "settings", "type='events_template' AND extra = '" . $evnt["eventid"] . "' AND setting_name='template_setting_max_age'");
                $already_registered = get_db_count("SELECT * FROM events_registrations WHERE regid IN (SELECT regid FROM events_registrations_values WHERE elementname='Camper_Name' AND value='$camper_name') AND regid IN (SELECT regid FROM events_registrations_values WHERE elementname='Camper_Age' AND value='$camper_age') AND eventid='" . $evnt["eventid"] . "'");
                // Meets minimum age and maximum age requirements if set
                if (!$camper_age || ((!$min_age && !$max_age) ||
                    ($min_age && $camper_age >= $min_age && $max_age && $camper_age <= $max_age) ||
                    (!$max_age && ($min_age && $camper_age >= $min_age)) ||
                    (!$min_age && ($max_age && $camper_age >= $max_age)))
                ) {
                    if (!$already_registered) {
                        $common[] = ['eventid' => $evnt['eventid'], 'selected' => $selected,'name' => $evnt['name']];
                    }
                }
        }
        $returnme = count($common) ? '<select id="' . $id . '">' : '&nbsp;<span style="color:red;">There are no other weeks available. </span>';
        foreach ($common as $c) {
            $returnme .= '<option value="' . $c['eventid'] . '" ' . $c['selected'] . '>' . $c['name'] . '</option>';
        }
        $returnme .= count($common) ? '</select> <input type="button" onclick="show_form_again($(\'#' . $id . '\').val(),\'' . $regid . '\', ' . $autofill . ');" value="Register" />' : '';
    } else {
        return false;
    }
    return $returnme;
}
?>