<?php
/***************************************************************************
 * customrules.php - Camp Wabashi Template 3.0 Custom Rules
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * Date: 3/14/2025
 * $Revision: 0.0.1
 ***************************************************************************/

function customrule_min_age() {
    $eventid = CUSTOMRULEDATA["eventid"];
    $min_age = get_db_field("setting", "settings", "type='events_template' AND extra='$eventid' AND setting_name='template_setting_min_age'");
    $min_age_error = empty($min_age) ? "" : ' data-msg-min="' . error_string('error_age_min:events:templates/camp_2025') . '"';
    return empty($min_age) ? "" : " data-rule-min=\"$min_age\"";
}

function customrule_max_age() {
    $eventid = CUSTOMRULEDATA["eventid"];
    $max_age = get_db_field("setting", "settings", "type='events_template' AND extra='$eventid' AND setting_name='template_setting_max_age'");
    $max_age_error = empty($max_age) ? "" : ' data-msg-max="' . error_string('error_age_max:events:templates/camp_2025') . '"';
    return empty($max_age) ? "" : " data-rule-max=\"$max_age\"";
}