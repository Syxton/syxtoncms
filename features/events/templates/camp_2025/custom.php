<?php
/***************************************************************************
 * custom.php - Camp Wabashi Template 3.0 Custom Functions
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * Date: 3/14/2025
 * $Revision: 0.0.1
 ***************************************************************************/

function customrule_min_age($data = []) {
    if (!isset($data["eventid"])) {
        return "";
    }

    $eventid = $data["eventid"];
    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_min_age",
    ];
    $min_age = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $min_age_error = empty($min_age) ? "" : ' data-msg-min="' . error_string('error_age_min:events:templates/camp_2025') . '"';
    return empty($min_age) ? "" : " data-rule-min=\"$min_age\"";
}

function customrule_max_age($data = []) {
    if (!isset($data["eventid"])) {
        return "";
    }

    $eventid = $data["eventid"];
    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_max_age",
    ];
    $max_age = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $max_age_error = empty($max_age) ? "" : ' data-msg-max="' . error_string('error_age_max:events:templates/camp_2025') . '"';
    return empty($max_age) ? "" : " data-rule-max=\"$max_age\"";
}

function customvalue_shirt($data = []) {
    if (!isset($data["eventid"])) {
        return 0;
    }

    $eventid = $data["eventid"];
    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_shirt",
    ];
    $shirt = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    return empty($shirt) ? 0 : 1;
}

function customvalue_shirt_price($data = []) {
    if (!isset($data["eventid"])) {
        return 0;
    }

    $eventid = $data["eventid"];
    $event = get_event($eventid);

    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_shirt",
    ];
    $shirt = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);

    if (!$shirt) {
        return 0;
    }

    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_shirt_price",
    ];
    $shirt_price = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);

    return empty($shirt_price) ? "0" : $shirt_price;
}

function customoptions_shirt($data = []) {
    if (!isset($data["eventid"])) {
        return 0;
    }

    $eventid = $data["eventid"];
    $event = get_event($eventid);

    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_shirt",
    ];
    $shirt = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);

    if (!$shirt) {
        return 0;
    }

    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_shirt_price",
    ];
    $shirt_price = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $shirt_price = empty($shirt_price) ? "0" : $shirt_price;

    $shirt_sizes = ["Youth XS", "Youth S", "Youth M", "Youth L", "Youth XL", "Adult S", "Adult M", "Adult L", "Adult XL", "Adult XXL"];
    if ($event['fee_full'] > 0 && $shirt_price > 0) {
        array_unshift($shirt_sizes, "No");
    }

    return $shirt_sizes;
}

function customoptions_pictures($data = []) {
    if (!isset($data["eventid"])) {
        return 0;
    }

    $eventid = $data["eventid"];
    $event = get_event($eventid);

    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_pictures",
    ];
    $pictures = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);

    if (!$pictures) {
        return 0;
    }

    $params = [
        "extra" => $eventid,
        "type" => "events_template",
        "setting_name" => "template_setting_pictures_price",
    ];
    $pictures_price = get_db_field("setting", "settings", "type = ||type|| AND extra = ||extra|| AND setting_name = ||setting_name||", $params);
    $pictures_price = empty($pictures_price) ? "0" : $pictures_price;

    if ($event['fee_full'] > 0 && $pictures_price > 0) {
        return [
            '0' => 'No',
            $pictures_price => 'Yes',
        ];
    }

    return 1;
}