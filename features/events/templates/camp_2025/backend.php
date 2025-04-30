<?php
/***************************************************************************
 * backend.php - Backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 03/28/2025
 * $Revision: 0.0.1
 ***************************************************************************/
if (!isset($CFG)) {
    $sub = '';
    while (!file_exists($sub . 'config.php')) {
        $sub .= '../';
    }
    include($sub . 'config.php');
}
include($CFG->dirroot . '/pages/header.php');

if (!defined('EVENTSLIB')) { include_once($CFG->dirroot . '/features/events/eventslib.php'); }

// Include template specific functions.
include_once($CFG->dirroot . "/features/events/templates/camp_2025/lib.php");

callfunction();

update_user_cookie();

function add_registration_to_cart() {
    global $_SESSION, $MYVARS, $error;

    $return = "";

    if (!isset($_SESSION['registrations'])) {
        $_SESSION['registrations'] = [];
    }

    // There are two reasons to be here, 1. a registration was added. 2. the checkout button was pressed.
    $checkout = clean_myvar_opt("checkout", "bool", false);

    // Checkout button was not pressed, so save registration.
    if (!$checkout) {
        // Save everything from this registration.
        $_SESSION['registrations'][] = $MYVARS;

        // Now set checkout to true.
        $MYVARS->GET["checkout"] = true;
        $checkout = true;
    }

    $return .= '
    <h1 style="text-align: center;padding: 10px;">
        Review Order
    </h1>
    <div id="refreshableregcart">
        ' . print_registration_cart($checkout) . '
    </div>';

    ajaxapi([
        "id" => "submit_camp_2025_registration",
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "register",
        ],
        "reqstring" => "form1",
        "display" => "registration_div",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "remove_camp_2025_registration",
        "if" => "(confirm('Are you sure you want to delete this registration?'))",
        "paramlist" => "hash",
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "remove_registration",
            "checkout" => $checkout,
            "hash" => "js||hash||js",
        ],
        "reqstring" => "form1",
        "display" => "refreshableregcart",
        "event" => "none",
    ]);

    ajax_return($return, $error);
}

function remove_registration() {
    global $_SESSION, $MYVARS, $error;

    $checkout = clean_myvar_opt("checkout", "bool", false);
    $hash = clean_myvar_opt("hash", "string", false);

    if (isset($_SESSION['registrations'])) {
        foreach ($_SESSION['registrations'] as $i => $reg) {
            if ($reg->hash == $hash) {
                unset($_SESSION['registrations'][$i]);
            }
        }
    }
    $return = print_registration_cart($checkout);

    ajaxapi([
        "id" => "submit_camp_2025_registration",
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "register",
        ],
        "reqstring" => "form1",
        "display" => "registration_div",
        "event" => "none",
    ]);

    ajaxapi([
        "id" => "remove_camp_2025_registration",
        "if" => "(confirm('Are you sure you want to delete this registration?'))",
        "paramlist" => "hash",
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "remove_registration",
            "checkout" => $checkout,
            "hash" => "js||hash||js",
        ],
        "display" => "refreshableregcart",
        "event" => "none",
    ]);

    ajax_return($return, $error);
}

function get_eventid_from_hash($hash) {
    global $_SESSION;

    foreach ($_SESSION['registrations'] as $key => $reg) {
        if ($reg->hash == $hash) {
            return $reg->GET['eventid'];
        }
    }
    return false;
}

function applycampership() {
    global $_SESSION;
    $checkout = clean_myvar_opt("checkout", "bool", false);
    $hash = clean_myvar_opt("hash", "string", false);
    $code = clean_myvar_opt("code", "string", false);

    // Get eventid of cart item.
    $eventid = get_eventid_from_hash($hash);

    if ($eventid &&$promo = get_promo_code_match($eventid, $code)) {
        if (isset($_SESSION['registrations'])) {
            foreach ($_SESSION['registrations'] as $key => $reg) {
                if ($reg->hash == $hash) {
                    $_SESSION['registrations'][$key]->item["promocode"] = $code;
                    $_SESSION['registrations'][$key]->item["promoname"] = $promo['name'];
                }
            }
        }
    } else {
        // Make sure that the price is set back to the original prices
        if (isset($_SESSION['registrations'])) {
            foreach ($_SESSION['registrations'] as $key => $reg) {
                if ($reg->hash == $hash) {
                    unset($_SESSION['registrations'][$key]->item["promocode"]);
                    unset($_SESSION['registrations'][$key]->item["promoname"]);
                }
            }
        }
    }

    $return = print_registration_cart($checkout);

    $error = "";
    ajax_return($return, $error);
}

function register() {
    global $CFG, $USER, $error, $_SESSION;

    if (!defined('COMLIB')) { include_once($CFG->dirroot . '/lib/comlib.php'); }
    if (!defined('FORMLIB')) { include_once($CFG->dirroot . '/lib/formlib.php'); }

    $return = ""; $error = "";
    try {
        // Make sure we are starting completely clean.
        unset($_SESSION["completed_registrations"]);

        // payment_cart is the array used to send information to payment gateways like Paypal.
        $_SESSION["payment_cart"] = [];
        foreach ($_SESSION['registrations'] as $key => $reg) {
            $newreg = [];
            if (!isset($reg->GET)) {
                throw new Exception("Could not find registration");
            }

            $eventid = clean_param_req($reg->GET, "eventid", "int");
            $event = get_event($eventid);
            $templateid = $event['template_id'];
            $elements = get_template_formlist($templateid);

            // Get only elements that are to be saved in the form.
            $newreg = create_registration_array($elements, $reg->GET);

            // Attach payment info
            $newreg = attach_registration_payment_info($newreg, $reg->item);

            // Attach event info
            $newreg["eventid"] = $eventid;

            // Registration is Pending full payment.
            $pending = $newreg["total_owed"] > 0 ? true : false;

            // Save registration
            if ($regid = enter_registration($eventid, $newreg, $newreg["email"], $pending)) {
                // Success
                $_SESSION["completed_registrations"][$regid] = $newreg;

                // Save regid to payment cart.
                $_SESSION["payment_cart"][] = (object) [
                    "id" => $regid,
                    "description" => $newreg["camper_name"] . " - " . $event["name"] . " Registration",
                    "cost" => clean_myvar_opt("payment_amount_" . $reg->hash, "float", "0"),
                ];

                // Send registration emails.
                // Make registration email user objects.
                $touser = (object)["fname" => $newreg["camper_name_first"], "lname" => $newreg["camper_name_last"], "email" => $newreg["email"]];
                $fromuser = (object)["fname" => $CFG->sitename, "lname" => "", "email" => $CFG->siteemail];

                $fullypaid = $newreg["total_owed"] > 0 ? false : true;
                $message = registration_email($regid, $touser, $pending, $fullypaid);

                $emailsubject = "Camp Wabashi Registration";
                $usingcampership = empty($newreg["campership"]) ? "" : " (Campership: " . $newreg["campership"] . ")";
                try {
                    if (\send_email($touser, $fromuser, $emailsubject, $message)) {
                        \send_email($fromuser, $fromuser, $emailsubject . $usingcampership, $message);
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            } else {
                // Failed
                unset($_SESSION["payment_cart"]);
                unset($_SESSION["completed_registrations"]);
                throw new Exception("Could not save registration");
            }
        }

        commit_db_transaction();
        $return = show_post_registration_page();

        // Cart has been processed, so we can empty it.
        //unset($_SESSION["registrations"]);
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        unset($_SESSION["payment_cart"]);
        unset($_SESSION["completed_registrations"]);
        $error .= $e->getMessage();
    }

    ajax_return($return, $error);
}
?>