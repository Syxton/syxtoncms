<?php
/***************************************************************************
 * template.php - Camp Wabashi Template page
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 03/28/2025
 * $Revision: 0.0.1
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
if (!defined('FORMLIB')) { include_once($CFG->dirroot . '/lib/formlib.php'); }

// Include template specific functions.
include_once($CFG->dirroot . "/features/events/templates/camp_2025/lib.php");

// Retrieve from Javascript
global $MYVARS, $_SESSION;
collect_vars();

$email = $payment_method = $disable = "";

// Create general data array for passing to templates and form variables.
$data = [];

// Get full event info
$eventid = clean_myvar_opt("eventid", "int", false);
if ($eventid) {
    $event = get_event($eventid);
    $data["event"] = $event;
    $template_id = $event['template_id'];
} else {
    $template_id = clean_myvar_opt("template_id", "int", false);
}

// Set defaults on some data elements.
$data["autofill"] = false;
$data["preview"] = false;

// If a registration hash is supplied, we are copying this registration and autofilling.
$hash = clean_myvar_opt("hash", "string", false);
if ($hash) {
    // Look for registration hash in cart.
    if ($reg = find_registration_hash($_SESSION['registrations'], $hash)) {
        $data["autofill"] = cleanup_registration_array($reg->GET);
    }
}

// If a registration hash is supplied, we are copying this registration and autofilling.
$regid = clean_myvar_opt("regid", "int", false);
if ($regid) {
    // Look for registration hash in cart.
    if ($reg = get_registration_for_autofill($regid)) {
        $data["autofill"] = cleanup_registration_array($reg->GET);
    }
}

// Preview of template.
if (isset($preview)) {
    $data["preview"] = true;
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

$template = get_event_template($template_id);
$elements = get_template_formlist($template_id);
$form_elements = make_form_elements($elements, $data);
$count_in_cart = isset($_SESSION['registrations']) ? count($_SESSION['registrations']) : 0;

// Beginning of form document.
echo '
    <!DOCTYPE HTML>
        <html>
            <head>
            ' . get_js_tags(["jquery", "validate"]) . '
            ' . get_js_tags(["features/events/templates/camp_2025/ajax.js"]) . '
            </head>
            <body>
                <form class="event_template_form" name="form1" id="form1">
                    <fieldset class="formContainer">
                        <input type="hidden" name="eventid" id="eventid" value="' . $eventid . '" />
                        <input type="hidden" id="event_begin_date" value="' . date("Y-m-d", $event["event_begin_date"]) . '" />
                        <div style="display: flex;justify-content: center;">
                            <div style="font-size:15px;text-align:center;font-weight:bold">
                                Camp Wabashi Online Pre-Registration
                                <div style="font-size:13px;text-align:center;font-weight:bold">
                                ' . $event["name"] . '
                                </div>
                            </div>
                            <div style="width: 50px;">
                                <input type="hidden" id="count_in_cart" value="' . $count_in_cart . '" />
                                <button type="button" class="registration_cart_menu alike">
                                    ' . icon([
                                            ["icon" => "cart-shopping", "stacksize" => 3, "color" => "green"],
                                            ["content" => $count_in_cart, "style" => "font-size: .4em;top: 7px;width: 100%;text-align: center;color: white;"],
                                        ]) . '
                                </button>
                                <div id="refreshableregcart">
                                    ' . print_registration_cart(false) . '
                                </div>
                            </div>
                        </div>
                        <p>
                            <a target="policy" href="' . $CFG->wwwroot . '/features/events/templates/camp_2025/regpolicy.html">
                                Registration Policy
                            </a>
                        </p>
                        ' . $form_elements . '
                        <br /><br />
                        <input class="displayOnFinalSection submit" name="submit" type="submit" value="Add Application to Cart" style="display: block; margin: auto;background: green;color: white;" ' . $disable . ' />
                        <br />
                        <input class="displayOnFinalSection" name="reset" type="reset" onclick="resetRegistration();" style="display: block; margin: auto;cursor:pointer;background: red;color: white;" ' . $disable . '/>
                    </fieldset>
                </form>' . keepalive();

$value_in_cart = $count_in_cart > 0 ? get_value_in_cart($_SESSION['registrations']) : 0;

ajaxapi([
    "id" => "remove_camp_2025_registration",
    "if" => "($('#count_in_cart').val() > 0) && confirm('Are you sure you want to delete this registration?')",
    "paramlist" => "hash",
    "url" => "/features/events/templates/camp_2025/backend.php",
    "data" => [
        "action" => "remove_registration",
        "hash" => "js||hash||js",
        "checkout" => false,
    ],
    "ondone" => "$('#count_in_cart').val(parseInt($('#count_in_cart').val())-1); $('.registration_cart_menu span.fa-layers-text').text(parseInt($('#count_in_cart').val()));",
    "display" => "refreshableregcart",
    "event" => "none",
]);

ajaxapi([
    "id" => "camp_2025_add_registration_to_cart",
    "url" => "/features/events/templates/camp_2025/backend.php",
    "data" => [
        "action" => "add_registration_to_cart",
    ],
    "reqstring" => "form1",
    "display" => "registration_div",
    "event" => "none",
]);

//Finalize and activate validation code
echo create_validation_script("form1", "camp_2025_add_registration_to_cart();");
?>