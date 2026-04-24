<?php
/***************************************************************************
 * install.php - reg page installer
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 4/21/08
 * $Revision: .12
 ***************************************************************************/
//Form name:Section:Title
$thisversion = 2018062700;
$templatename = 'Camp Wabashi Week';
$templatefolder = 'camp';
$registrant_name = 'Camper_Name';
$orderbyfield = 'Camper_Name';

$formlist = '
Camper_Name:Camper:Name;
Camper_Age:Camper:Age;
Camper_Gender:Camper:Gender;
Camper_Birth_Date:Camper:Birthday;
Camper_Grade:Camper:Grade;
Camper_Picture:Pay:Camper Picture;
total_owed:Pay:Amount Owed;
paid:Pay:Amount Paid;
payment_method:Pay:Payment Method;
Camper_Home_Congregation:Camper:Congregation;
Parent_Address_Line1:Parent:Address 1;
Parent_Address_Line2:Parent:Address 2;
Parent_Address_City:Parent:City;
Parent_Address_State:Parent:State;
Parent_Address_Zipcode:Parent:Zipcode;
Parent_Phone1:Parent:Phone 1;
Parent_Phone2:Parent:Phone 2;
Parent_Phone3:Parent:Phone 3;
Parent_Phone4:Parent:Phone 4;
HealthAccount:Health:Account;
HealthAllergies:Health:Allergies;
HealthBenefitCode:Health:Benefit Code;
HealthConsentFrom:Health:Consent From;
HealthConsentTo:Health:Consent To;
HealthExisting:Health:Existing Conditions;
HealthExpirationDate:Health:Expiration Date;
HealthHistory:Health:History;
HealthIdentification:Health:Identification;
HealthInsurance:Health:Insurance;
HealthMedicines:Health:Medicines;
HealthMemberName:Health:Member Name;
HealthRelationship:Health:Relationship;
HealthTetanusDate:Health:Tetanus Date;';

$settings = [];

$settings = dbescape(serialize($settings));

//If it is already installed, don't install it again.
if (!$template = get_event_template_by_name($templatename)) {
    // Install new registration template.
    $templateid = execute_db_sql(
        fetch_template("dbsql/events.sql", "insert_events_template", "events"),
        [
            "name" => $templatename,
            "folder" => $templatefolder,
            "formlist" => str_replace(["\r", "\n", "\t"], '', $formlist),
            "registrant_name" => $registrant_name,
            "orderbyfield" => $orderbyfield,
            "settings" => $settings,
        ]
    );

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
    $version = get_db_field("setting", "settings", "setting_name='version' AND type='events_template' AND extra='$templatefolder'");

    $thisversion = 2018062700;
    if (!$version || $version < $thisversion) {
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

    //$thisversion = ;
    //if ($version < $thisversion) {
    //    execute_db_sql(
    //     fetch_template("dbsql/settings.sql", "update_setting_by_extra"),
    //     [
    //         "setting" => $thisversion,
    //         "setting_name" => "version",
    //         "type" => "events_template",
    //         "extra" => $templatefolder,
    //     ]
    // );
    //}
}

$globalsettings = [];

// Make sure that global settings exist.
save_batch_settings($globalsettings);
?>