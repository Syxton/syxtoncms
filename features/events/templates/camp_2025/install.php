<?php
/***************************************************************************
 * install.php - Registration Plugin installer
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 02/17/2012
 * $Revision: 0.0.1
 ***************************************************************************/
//Form name:Section:Title
$thisversion = 2025031400;
$templatename = 'Camp Wabashi Week 3.0';
$templatefolder = 'camp_2025';
$registrant_name = 'camper_name';
$orderbyfield = 'camper_name';

if (!defined('FORMLIB')) { include_once($CFG->dirroot . '/lib/formlib.php'); }

// Event template form list
$formlist = include("formlist.php");

// Event template specific settings
$settings = include("settings.php");

$formlist = serialize($formlist);
$settings = serialize($settings);

// If it is already installed, don't install it again.
if (!$template = get_db_row("SELECT * FROM events_templates WHERE name = ||name||", ["name" => $templatename])) {
    // Install new registration template.
    $SQL = "INSERT INTO events_templates (name, folder, formlist, registrant_name, orderbyfield, settings)
                 VALUES (||name||, ||folder||, ||formlist||, ||registrant_name||, ||orderbyfield||, ||settings||)";

    $templateid = execute_db_sql(
        $SQL, [
            "name" => $templatename,
            "folder" => $templatefolder,
            "formlist" => "formlist.php",
            "registrant_name" => $registrant_name,
            "orderbyfield" => $orderbyfield,
            "settings" => $settings,
        ]
    );

    // Save the version number of the new template.
    $SQL = "INSERT INTO settings (type, pageid, featureid, setting_name, setting, extra)
                 VALUES (||type||, ||pageid||, ||featureid||, ||setting_name||, ||setting||, ||extra||)";
    execute_db_sql(
        $SQL, [
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
        $SQL = "INSERT INTO settings (type, pageid, featureid, setting_name, setting, extra)
                 VALUES (||type||, ||pageid||, ||featureid||, ||setting_name||, ||setting||, ||extra||)";
        execute_db_sql(
            $SQL, [
                "type" => "events_template",
                "pageid" => 0,
                "featureid" => 0,
                "setting_name" =>
                "version",
                "setting" => $thisversion,
                "extra" => $templatefolder,
            ]
        );
    }

    // $thisversion = 2024072500;
    // if ($version < $thisversion) {
    //     $SQL = "UPDATE settings
    //                SET setting = ||setting||
    //              WHERE setting_name = ||setting_name||
    //                AND type = ||type||
    //                AND extra = ||extra||";
    //     execute_db_sql(
    //          $SQL, [
    //              "setting" => $thisversion,
    //              "setting_name" => "version",
    //              "type" => "events_template",
    //              "extra" => $templatefolder,
    //          ]
    //     );
    // }
}

$globalsettings = include("globalsettings.php");

// Make sure that global settings exist.
save_batch_settings($globalsettings);
?>