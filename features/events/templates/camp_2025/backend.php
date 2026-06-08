<?php
/***************************************************************************
 * backend.php - Backend ajax script
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * Date: 5/02/2025
 * $Revision: 0.2.1
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

/**
 * Locate the event ID associated with a registration cart item.
 *
 * Each registration stored in the session may be an object that includes
 * a 'hash' property (unique identifier) and a GET payload (array or
 * object) containing the original request parameters used when adding
 * the registration to the cart. This function searches the session
 * registrations for the matching hash and returns the eventid from
 * the registration's GET data.
 *
 * @param string $hash The registration hash to look for.
 * @return mixed The eventid (string/int) if found, or false if not found.
 */
function get_eventid_from_hash($hash) {
    global $_SESSION;

    // Quick checks to ensure we have registrations stored in session.
    if (!isset($_SESSION['registrations']) || !is_array($_SESSION['registrations'])) {
        // No registrations available.
        return false;
    }

    // Iterate through each registration entry and locate the one matching
    // the provided hash. Registrations are expected to be objects but the
    // code is defensive in case a non-object sneaks in.
    foreach ($_SESSION['registrations'] as $key => $reg) {
        // Skip entries that are not objects.
        if (!is_object($reg)) {
            continue;
        }

        // Skip if hash is missing or doesn't match the requested hash.
        if (!isset($reg->hash) || $reg->hash !== $hash) {
            continue;
        }

        // Found the registration; now extract eventid from the stored GET
        // parameters. The GET container may be either an array (legacy/one
        // format) or an object, so handle both safely.
        if (isset($reg->GET)) {
            if (is_array($reg->GET) && isset($reg->GET['eventid'])) {
                return $reg->GET['eventid'];
            } elseif (is_object($reg->GET) && isset($reg->GET->eventid)) {
                return $reg->GET->eventid;
            }
        }
        // If the registration matched by hash but no eventid was present in
        // its GET data, treat it as not found and continue searching.
    }

    // No matching registration or no eventid found for the provided hash.
    return false;
}

/**
 * Applies or removes a promo code to a registration in the cart.
 *
 * Retrieves the promo code from request and validates it against the event.
 * If valid, adds the promo code and name to the registration item.
 * If invalid, removes any existing promo code from the registration.
 *
 * @global array $_SESSION Stores user registration data.
 * @return void Outputs JSON response with updated cart HTML and error status.
 */
function applypromo() {
    global $_SESSION;
    $checkout = clean_myvar_opt("checkout", "bool", false);
    $hash = clean_myvar_opt("hash", "string", false);
    $code = clean_myvar_opt("code", "string", false);

    // Get eventid of cart item.
    $eventid = get_eventid_from_hash($hash);

    if (!isset($_SESSION['registrations'])) {
        $return = print_registration_cart($checkout);
        ajax_return($return, "");
        return;
    }

    if ($eventid && $promo = get_promo_code_match($eventid, $code)) {
        // Apply valid promo code to matching registration
        foreach ($_SESSION['registrations'] as $key => $reg) {
            if ($reg->hash == $hash) {
                $_SESSION['registrations'][$key]->item["promocode"] = $code;
                $_SESSION['registrations'][$key]->item["promoname"] = $promo['name'];
                break;
            }
        }
    } else {
        // Remove invalid or missing promo code
        foreach ($_SESSION['registrations'] as $key => $reg) {
            if ($reg->hash == $hash) {
                unset($_SESSION['registrations'][$key]->item["promocode"]);
                unset($_SESSION['registrations'][$key]->item["promoname"]);
                break;
            }
        }
    }

    $return = print_registration_cart($checkout);
    ajax_return($return, "");
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

                $emailsubject = $CFG->sitename . " Registration";
                $usingpromo = empty($newreg["campership"]) ? "" : " (Campership: " . $newreg["campership"] . ")";
                try {
                    if (\send_email($touser, $fromuser, $emailsubject, $message)) {
                        \send_email($fromuser, $fromuser, $emailsubject . $usingpromo, $message);
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
        unset($_SESSION["registrations"]);
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        unset($_SESSION["payment_cart"]);
        unset($_SESSION["completed_registrations"]);
        $error .= $e->getMessage();
    }

    ajax_return($return, $error);
}
?>