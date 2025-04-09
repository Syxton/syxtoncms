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

$regid = clean_myvar_opt("regid", "int", false);
if ($regid) {
    $reg = get_reg($regid);
    $data["reg"] = $reg;
}

// show_again false -> first time through
// show_again true AND autofill true -> same person, so autofill all items
// show_again true AND autofill false -> different person, so autofill payment method
$show_again = clean_myvar_opt("show_again", "bool", false);
$autofill = clean_myvar_opt("autofill", "bool", false);
$data["show_again"] = $show_again;
$data["autofill"] = $autofill;

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

//output any passed on hidden info from previous registrations
$total_owed = clean_myvar_opt("total_owed", "float", 0);
$items = clean_myvar_opt("items", "string", "");

if ($show_again) { // This is not the first time through
    if ($autofill) { // Same person..so auto fill all items
        $last_reg = get_db_result(fetch_template("dbsql/events.sql", "get_registration_values", "events"), ["regid" => $regid]);
        while ($reginfo = fetch_row($last_reg)) {
            ${$reginfo["elementname"]} = $reginfo["value"];
        }
        $email = get_db_field("email", "events_registrations", "regid='$regid'");
    } else { // Different person...but auto fill the payment method and hide it.
        $payment_method = get_db_field("value", "events_registrations_values", "elementname='payment_method' AND regid='$regid'");
        $campership = get_db_field("value", "events_registrations_values", "elementname='campership' AND regid='$regid'");
    }
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
                    <div>
                        <input type="hidden" id="count_in_cart" value="' . $count_in_cart . '" />
                        <button type="button" class="registration_cart_menu alike">
                            ' . icon([
                                    ["icon" => "cart-shopping", "stacksize" => 3, "color" => "green"],
                                    ["content" => $count_in_cart, "style" => "font-size: .4em;top: 7px;width: 100%;text-align: center;color: white;"],
                                ]) . '
                        </button>
                        <div id="refreshableregcart">
                            ' . print_registration_cart(true) . '
                        </div>
                    </div>
                    <fieldset class="formContainer">
                        <input type="hidden" name="eventid" value="' . $eventid . '" />
                        <input type="hidden" id="event_begin_date" value="' . date("Y-m-d", $event["event_begin_date"]) . '" />
                        <input type="hidden" name="healthconsentfrom" id="healthconsentfrom" value="' . date("Y-m-d", $event["event_begin_date"]) . '" readonly />
                        <input type="hidden" name="healthconsentto" id="healthconsentto" value="' . date("Y-m-d", $event["event_end_date"]) . '" readonly />
                        <input type="hidden" name="paid" value="0" />
                        <input type="hidden" name="total_owed" id="total_owed" value="' . $total_owed . '" />
                        <input type="hidden" name="items" id="items" value="' . $items . '" />
                        <div style="font-size:15px;text-align:center;font-weight:bold">
                            Camp Wabashi Online Pre-Registration
                        </div>
                        <div style="font-size:13px;text-align:center;font-weight:bold">
                            ' . $event["name"] . '
                        </div>
                        <p>
                            <a target="policy" href="' . $CFG->wwwroot . '/features/events/templates/camp_2025/regpolicy.html">
                                Registration Policy
                            </a>
                        </p>
                        ' . $form_elements . '
                        <input class="displayOnFinalSection submit" name="submit" type="submit" value="Submit Application" style="display: block; margin: auto;background: green;color: white;" ' . $disable . ' />
                        <input class="displayOnFinalSection" name="reset" type="reset" onclick="resetRegistration();" style="display: block; margin: auto;cursor:pointer;background: red;color: white;float:right;" ' . $disable . '/>
                    </fieldset>
                </form>' . keepalive();

$total_in_cart = $count_in_cart > 0 ? get_total_in_cart($_SESSION['registrations']) : 0;
if ($total_in_cart > 0) {
    ajaxapi([
        "id" => "registration_cart_checkout",
        "url" => "/features/events/templates/camp_2025/backend.php",
        "data" => [
            "action" => "add_registration_to_cart",
            "checkout" => true,
        ],
        "display" => "registration_div",
        "event" => "click",
    ]);
}

ajaxapi([
    "id" => "remove_camp_2025_registration",
    "if" => "($('#count_in_cart').val() > 0) && confirm('Are you sure you want to delete this registration?')",
    "paramlist" => "hash",
    "url" => "/features/events/templates/camp_2025/backend.php",
    "data" => [
        "action" => "remove_registration",
        "hash" => "js||hash||js",
        "checkout" => true,
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