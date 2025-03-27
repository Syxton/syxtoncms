<?php
/***************************************************************************
 * template.php - Camp Wabashi Template page
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * Date: 5/14/2024
 * $Revision: 2.1.2
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

// Retrieve from Javascript
global $MYVARS;
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
                        <input tabindex="1000" name="print" value="Print" onclick="window.print()" style="position: fixed;top: 10px;right: 10px;font-size: .7em;" type="button" ' . $disable . '/><br /><br />
                        <input tabindex="1001" class="displayOnFinalSection submit" name="submit" type="submit" value="Send Application" style="background: green;color: white;" ' . $disable . ' />
                        <input tabindex="1002" class="displayOnFinalSection"name="reset" type="reset" onclick="resetRegistration();" style="cursor:pointer;background: red;color: white;float:right;" ' . $disable . '/>
                    </fieldset>
                </form>' . keepalive();

ajaxapi([
    "id" => "save_camp_2025_registration",
    "url" => "/features/events/templates/camp_2025/backend.php",
    "data" => [
        "action" => "save_registration",
    ],
    "reqstring" => "form1",
    "display" => "registration_div",
    "event" => "none",
]);

//Finalize and activate validation code
echo create_validation_script("form1", "save_camp_2025_registration();");
?>