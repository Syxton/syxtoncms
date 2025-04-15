<?php
/***************************************************************************
 * custom.php - Camp Wabashi Template 3.0 Custom Functions
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * Date: 3/14/2025
 * $Revision: 0.0.1
 ***************************************************************************/

 // Include template specific functions.
include_once($CFG->dirroot . "/features/events/templates/camp_2025/lib.php");

function customrule_min_age($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["eventid"])) {
        return 0;
    }

    $event = $data["event"];
    $params = [
        "extra" => $event["eventid"],
        "type" => "events_template",
        "setting_name" => "template_setting_min_age",
    ];
    $min_age = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $min_age_error = empty($min_age) ? "" : ' data-msg-min="' . error_string('error_age_min:events:templates/camp_2025') . '"';
    return empty($min_age) ? "" : " data-rule-min=\"$min_age\"";
}

function customrule_max_age($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["eventid"])) {
        return 0;
    }

    $event = $data["event"];
    $params = [
        "extra" => $event["eventid"],
        "type" => "events_template",
        "setting_name" => "template_setting_max_age",
    ];
    $max_age = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $max_age_error = empty($max_age) ? "" : ' data-msg-max="' . error_string('error_age_max:events:templates/camp_2025') . '"';
    return empty($max_age) ? "" : " data-rule-max=\"$max_age\"";
}

function customvalue_email($data = []) {
    global $USER;
    if (isset($USER->email)) {
        return $USER->email;
    }

    return "";
}

function customtype_reglookup($element, $data = []) {
    global $USER;
    if (isset($USER->email)) {
        return '
            <div class="rowContainer costinfo paywithapp">
                <label class="rowTitle" for="payment_amount">' . $element['title'] . '</label>
                REGISTRATIONS FOUND: AUTOFILL?
                <div class="tooltipContainer info">' . $element['help'] . '</div>
                <div class="spacer" style="clear: both;"></div>
            </div>';
    }

    return "";
}

function customvalue_owed($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["fee_min"])) {
        error_log("No fee_min found");
        return 0;
    }

    $event = $data["event"];
    return empty($data["event"]["fee_min"]) ? 0 : $data["event"]["fee_min"];
}

function customvalue_shirt($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["eventid"])) {
        return 0;
    }

    $event = $data["event"];

    $params = [
        "extra" => $event["eventid"],
        "type" => "events_template",
        "setting_name" => "template_setting_shirt",
    ];
    $shirt = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    return empty($shirt) ? 0 : 1;
}

function customvalue_shirt_price($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["eventid"])) {
        return 0;
    }

    $event = $data["event"];

    $params = [
        "extra" => $event["eventid"],
        "type" => "events_template",
        "setting_name" => "template_setting_shirt",
    ];
    $shirt = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);

    if (!$shirt) {
        return 0;
    }

    $params["setting_name"] = "template_setting_shirt_price";
    $shirt_price = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);

    return empty($shirt_price) ? "0" : $shirt_price;
}

function customoptions_shirt($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["eventid"])) {
        return 0;
    }

    $event = $data["event"];
    $eventid = $event["eventid"];

    $params = [
        "extra" => $event["eventid"],
        "type" => "events_template",
        "setting_name" => "template_setting_shirt",
    ];
    $shirt = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);

    if (!$shirt) {
        return 0;
    }

    $params["setting_name"] = "template_setting_shirt_price";
    $shirt_price = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $shirt_price = empty($shirt_price) ? "0" : $shirt_price;

    $shirt_sizes = TEMP_PROPS["SHIRTSIZES"];
    if ($event['fee_full'] > 0 && $shirt_price > 0) {
        array_unshift($shirt_sizes, "No");
    }

    return $shirt_sizes;
}

function customhelp_shirt_size($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["eventid"])) {
        return 0;
    }

    $event = $data["event"];
    $eventid = $event["eventid"];

    $params = [
        "extra" => $event["eventid"],
        "type" => "events_template",
        "setting_name" => "template_setting_shirt_price",
    ];

    $shirt_price = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $shirt_price = empty($shirt_price) ? "no extra charge" : '+ $' . $shirt_price . '.00';

    return get_help("help_shirt_size:events:templates/camp_new") . " ($shirt_price)";
}

function customoptions_picture($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["eventid"])) {
        return 0;
    }

    $event = $data["event"];
    $eventid = $event["eventid"];

    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_pictures",
    ];
    $pictures = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);

    if (!$pictures) {
        return 0;
    }

    $params["setting_name"] = "template_setting_pictures_price";
    $pictures_price = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $pictures_price = empty($pictures_price) ? "0" : $pictures_price;

    if ($event['fee_full'] > 0 && $pictures_price > 0) {
        return [
            '0' => 'No',
            '1' => 'Yes',
        ];
    }

    return 1;
}

function customhelp_picture($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["eventid"])) {
        return 0;
    }

    $event = $data["event"];
    $eventid = $event["eventid"];

    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_pictures_price",
    ];
    $pictures_price = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $pictures_price = empty($pictures_price) ? "0" : $pictures_price;

    return get_help("help_pictures:events:templates/camp_new") . ' ($' . $pictures_price . '.00 for 8x10 group photo)';
}
