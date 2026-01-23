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
    $min_age_error = empty($min_age) ? "" : ' data-msg-min="' . getlang("error_age_min", "/features/events/templates/camp_2025", $min_age) . '"';
    return empty($min_age) ? "" : " $min_age_error data-rule-min=\"$min_age\"";
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
    $max_age_error = empty($max_age) ? "" : ' data-msg-max="' . getlang("error_age_max", "/features/events/templates/camp_2025", $min_age) . '"';
    return empty($max_age) ? "" : " $max_age_error data-rule-max=\"$max_age\"";
}

function customvalue_email($data = []) {
    global $USER;
    if (isset($USER->email)) {
        return $USER->email;
    }

    return "";
}

function customtype_reglookup($element, $data = []) {
    global $USER, $_SESSION;
    $event = clean_param_opt($data, "event", "array", []);

    if (empty($event)) { // No event data given, can't proceed.
        return "";
    }

    $returnme = "";
    $autofill = [];

    // Get registration cart users.
    if (isset($_SESSION["registrations"])) {
        foreach ($_SESSION["registrations"] as $reg) {
            // Skip if cart registration is for this event.
            if ($reg->GET["eventid"] == $event["eventid"]) {
                continue;
            }
            $autofill[] = $reg;
        }
    }

    if (is_logged_in()) {
        // Get registrations from db.
        $autofill = array_merge($autofill, get_like_event_autofill_registrations($data));
    }

    $options = ""; $alreadyadded = [];
    $count = 0;
    if (!empty($autofill)) {
        foreach ($autofill as $reg) {
            // Limit of 6 possible regristration autofills.
            if ($count >= 6) {
                continue;
            }

            // Skip if autofill registration is for this event.
            if (isset($reg->event) && $event["eventid"] === $reg->event["eventid"]) {
                continue;
            }

            // Prepare names.
            $camper_names = get_camper_names($reg->GET);

            // Birthday required to find unique registrations
            if (!isset($reg->GET["camper_birth_date"])) {
                continue;
            }

            // Check for unique hash.
            $hash = create_unique_registration_hash(1, $camper_names["full"], $reg->GET["camper_birth_date"]);
            if (isset($alreadyadded[$hash])) {
                continue;
            }

            $alreadyadded[$hash] = $camper_names["full"];
            $carthash = isset($reg->hash) ? $reg->hash : 'false';
            $regid = isset($reg->regid) ? $reg->regid : 'false';
            $icon = isset($reg->regid) ? icon("database", 1, "", "navy") : icon("cart-arrow-down", 1, "", "green");
            $title = isset($reg->regid) ? "Autofill from prior registration" : "Autofill from current cart item";

            // Allow registrations that are already registerd but alert them.
            if (already_registered($event["eventid"], $camper_names["full"], $reg->GET["camper_birth_date"])) {
                $icon = icon("triangle-exclamation", 1, "", "red");
                $title = "Already registered for this event";
            }

            $options .= '
                <button
                    title="' . $title . '"
                    type="button" id="' . $hash . '"
                    class="autofillbutton"
                    onclick="if(confirm(\'Are you sure you want to autofill this form?\')) { show_form_again(' . $event["eventid"] . ",'" . $carthash . "','" . $regid . '\'); }">
                ' . $icon . ' <span>' . $camper_names["full"] . '</span>
                </button>';
            $count++;
        }

        if (!empty($options)) {
            $returnme = '
            <div class="rowContainer">
                <label class="rowTitle">Autofill Options</label>
                <div class="autofilloptions">
                ' . $options . '
                </div>
                <div class="spacer" style="clear: both;"></div>
            </div>
            ';
        }
    }

    return $returnme;
}

function customvalue_health_consent_from($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["event_begin_date"])) {
        error_log("No begin date found");
        return 0;
    }
    $event = $data["event"];
    return date("Y-m-d", $event["event_begin_date"]);
}

function customvalue_health_consent_to($data = []) {
    if (!isset($data["event"]) || !isset($data["event"]["event_end_date"])) {
        error_log("No end date found");
        return 0;
    }
    $event = $data["event"];
    return date("Y-m-d", $event["event_end_date"]);
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
        $keys = array_keys($shirt_sizes);
        $shirt_sizes[$keys[0]] = "No";
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

    return getlang("help_shirt_size", "/features/events/templates/camp_2025") . " ($shirt_price)";
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

    return getlang("help_pictures", "/features/events/templates/camp_2025") . ' ($' . $pictures_price . '.00 for 8x10 group photo)';
}
