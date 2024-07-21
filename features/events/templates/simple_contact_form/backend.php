<?php
/***************************************************************************
 * backend.php - Backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 08/16/2013
 * $Revision: 0.1.4
 ***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}

if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

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

        // Total up the registration bill
        $total_owed = clean_myvar_opt("total_owed", "int", 0);
        $owed = clean_myvar_opt("owed", "int", 0);
        $cart_total = $total_owed ? $total_owed + $owed : $owed;
        $total_owed = get_timestamp() < $event["sale_end"] ? $event["sale_fee"] : $event["fee_full"];

        //Prepare names
        $Name_First = nameize(clean_myvar_opt("Name_First", "string", ""));
        $Name_Last = nameize(clean_myvar_opt("Name_Last", "string", ""));
        $Name = "$Name_Last, $Name_First";

        $email = clean_myvar_req("email", "string");

        // Make registration email user objects.
        $touser = (object)["fname" => $Name_First, "lname" => $Name_Last, "email" => $email];
        $fromuser = (object)["fname" => $CFG->sitename, "lname" => "", "email" => $CFG->siteemail];

        //Format phone numbers
        $Phone = preg_replace('/\d{3}/', '$0-', trim(preg_replace("/\D/", "", clean_myvar_opt("Phone", "string", ""))), 2);

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
        $error = "";

        if ($regid = enter_registration($eventid, $reg, $email)) { // Successful registration.
            if (!empty($error)) {
                throw new Exception($error);
            }

            $emailsubject = $event['name'] . " Registration";
            execute_db_sql("UPDATE events_registrations SET verified = 1 WHERE regid = ||regid||", ["regid" => $regid]);

            if ($event['fee_full'] === 0) { // Free event.

                $return .= '<h3>You have successfully registered ' . $Name . ' for ' . $event['name'] . '.</h3>';

                $message = registration_email($regid, $touser); // Get email message.
            } else { // This registration has a cost.
                $items = !empty($items) ? $items . "**" . $regid . "::" . $Name . " - " . $event["name"] . "::" . $owed : $regid . "::" . $Name . " - " . $event["name"] . "::" . $owed;
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

                $return .= '
                    <div style="margin:auto;width:90%;text-align:center;">
                        <h3>You have successfully registered ' . $Name_First . ' ' . $Name_Last . '<br />for<br />' . $event['name'] . '.</h3>';

                $message = registration_email($regid, $touser);

                if ($cart_total > 0) { // Event paid by Paypal.
                    $return .= '
                        <br />
                        The full payment amount of <span style="color:blue;font-size:1.25em;">$' . number_format($event['fee_full'],2) . '</span> will be expected when you show up to the event.';
                    if ($cart_total < $total_owed) {
                        $return .= '
                            <br />
                            You have indicated that you would like to pay: <span style="color:blue;font-size:1.25em;">$' . number_format($cart_total, 2) . '</span> right now.';
                    } else {
                        $return .= '
                            <br />
                            You have indicated that you would like to pay the full event cost of: <span style="color:blue;font-size:1.25em;">$' . number_format($cart_total, 2) . '</span> right now.';
                    }

                    $return .= '
                        <br /><br />
                        Click the Paypal button below to make that payment.
                        <br />
                        <div style="text-align:center;">
                          ' . make_paypal_button($cart_items, $event['paypal']) . '
                        </div>';
                } else {
                    $return .= '<br />Please bring cash, check or money order in the amount of <span style="color:blue;font-size:1.25em;">$' . number_format($event['fee_full'],2) . '</span><br />payable to <strong>' . $event["payableto"] . '</strong> on the day of the event.';
                }
            }

            if ($event['allowinpage'] !== 0) {
                if (is_logged_in() && $event['pageid'] != $CFG->SITEID) {
                    subscribe_to_page($event['pageid'], $USER->userid);
                    $return .= '
                        <br />
                        You have been automatically allowed into this events\' web page.  This page contain specific information about this event.
                        <br />';
                }
            }

            try {
                $facebookbuttons = \facebook_share_button($event, $Name_First);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                $facebookbuttons = "";
            }

            $return = '
                <div style="margin:auto;width:90%;text-align:center;">
                    <h1>Thank you for registering!</h1>
                    <br /><br />
                    ' . $return . '
                </div>
                <br />
                <div style="margin:auto;width:90%;text-align:center;">
                ' . $facebookbuttons . '
                </div>';

            try {
                if (\send_email($touser, $fromuser, $emailsubject, $message)) {
                    send_email($fromuser, $fromuser, $emailsubject, $message);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } else { // Failed registration
            $return .= '
                <br /><br />
                <strong>We were unable to register you for this event.  Please try again at a later date.</strong>';
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error .= $e->getMessage();
    }

    ajax_return($return, $error);
}
?>