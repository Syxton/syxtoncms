<?php
/***************************************************************************
 * install.php - Registration Plugin installer
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 02/17/2012
 * $Revision: 0.0.1
 ***************************************************************************/
//Form name:Section:Title
$thisversion = 2025042408;
$templatename = 'Camp Wabashi Week 3.0';
$templatefolder = 'camp_2025';
$registrant_name = 'camper_name';
$orderbyfield = 'camper_name';

if (!defined('FORMLIB')) { include_once($CFG->dirroot . '/lib/formlib.php'); }
include_once($CFG->dirroot . "/features/events/templates/camp_2025/lib.php");

// Event template form list
$formlist = include("formlist.php");

// Event template specific settings
$settings = include("settings.php");

$formlist = serialize($formlist);
$settings = serialize($settings);

// If it is already installed, don't install it again.
if (!$template = get_event_template_by_name($templatename)) {
    // Install new registration template.
    $templateid = execute_db_sql(
        fetch_template("dbsql/events.sql", "insert_events_template", "events"),
        [
            "name" => $templatename,
            "folder" => $templatefolder,
            "formlist" => "formlist.php",
            "registrant_name" => $registrant_name,
            "orderbyfield" => $orderbyfield,
            "settings" => $settings,
        ]
    );

    // Save the version number of the new template.
    execute_db_sql(
        fetch_template("dbsql/settings.sql", "insert_setting"),
        [
            "type" => "events_template",
            "pageid" => 0,
            "featureid" => 0,
            "setting_name" => "version",
            "setting" => $thisversion,
            "extra" => $templatefolder,
        ]
    );
} else { // Update formslist, settings, and orderbyfield in case they have changed.
    $templateid = $template["template_id"];

    // Retrieve current version number of template.
    $version = get_db_field("setting", "settings", "setting_name='version' AND type = 'events_template' AND extra = '$templatefolder'");

    // If there is no version number, insert one.
    if (!$version) {
        execute_db_sql(
            fetch_template("dbsql/settings.sql", "insert_setting"),
            [
                "type" => "events_template",
                "pageid" => 0,
                "featureid" => 0,
                "setting_name" => "version",
                "setting" => $thisversion,
                "extra" => $templatefolder,
            ]
        );
    }

    // Adding promocode setting.
    if ($version < $thisversion) {
        execute_db_sql(
            fetch_template("dbsql/events.sql", "update_events_template", "events"),
            [
                "template_id" => $templateid,
                "name" => $templatename,
                "folder" => $templatefolder,
                "formlist" => "formlist.php",
                "registrant_name" => $registrant_name,
                "orderbyfield" => $orderbyfield,
                "settings" => $settings,
            ]
        );

        execute_db_sql(
            fetch_template("dbsql/settings.sql", "update_setting_by_extra"),
            [
                "setting" => $thisversion,
                "setting_name" => "version",
                "type" => "events_template",
                "extra" => $templatefolder,
            ]
        );
    }
}

$globalsettings = include("globalsettings.php");

// Make sure that global settings exist.
save_batch_settings($globalsettings);
?>