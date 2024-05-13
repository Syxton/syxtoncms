<?php
/***************************************************************************
 * install.php - reg page installer
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 02/17/2012
 * $Revision: 0.0.5
 ***************************************************************************/
//Form name:Section:Title
$templatename = 'Camp Wabashi Week 2.0';
$templatefolder = 'camp_new';
$registrant_name = 'Camper_Name';
$orderbyfield = 'Camper_Name';

$formlist = '
Camper_Name:Camper:Name;
Camper_Name_First:Camper:First Name;
Camper_Name_Last:Camper:Last Name;
Camper_Name_Middle:Camper:Middle Initial;
Camper_Age:Camper:Age;
Camper_Gender:Camper:Gender;
Camper_Birth_Date:Camper:Birthday;
Camper_Grade:Camper:Grade;
Camper_Shirt:Pay:Shirt;
Camper_Shirt_Size:Camper:Shirt Size;
Camper_Picture:Pay:Camper Picture;
total_owed:Pay:Amount Owed;
paid:Pay:Amount Paid;
payment_method:Pay:Payment Method;
campership:Pay:Campership;
Camper_Home_Congregation:Camper:Congregation;
Parent_Name1:Parent:Parent/Guardian 1;
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
HealthTetanusDate:Health:Tetanus Date';

//Event template specific settings
$settings = [
    [
        'setting_name' => 'template_setting_min_age',
        'display'=> 'Minimum Age',
        'inputtype' => 'text',
        'numeric' => true,
        'defaultsetting' => '0',
    ],
    [
        'setting_name' => 'template_setting_pictures',
        'display'=> 'Pictures',
        'inputtype' => 'yes/no',
        'numeric' => false,
        'defaultsetting' => "0",
    ],
    [
        'setting_name' => 'template_setting_pictures_price',
        'display'=> 'Pictures Price',
        'inputtype' => 'text',
        'numeric' => false,
        'defaultsetting' => '0',
    ],
    [
        'setting_name' => 'template_setting_shirt',
        'display'=> 'Shirts',
        'inputtype' => 'yes/no',
        'defaultsetting' => "0",
    ],
    [
        'setting_name' => 'template_setting_shirt_price',
        'display'=> 'Shirt Price',
        'inputtype' => 'text',
        'numeric' => false,
        'defaultsetting' => '0',
    ],
    [
        'global' => true,
        'setting_name' => 'facebookappid',
        'display'=> 'Facebook App ID',
        'inputtype' => 'text',
        'defaultsetting' => '',
    ],
    [
        'global' => true,
        'setting_name' => 'facebooksecret',
        'display'=> 'Facebook App Secret',
        'inputtype' => 'text',
        'defaultsetting' => '',
    ],
];

$settings = dbescape(serialize($settings));

//If it is already installed, don't install it again.
if (!$template = get_db_row("SELECT * FROM events_templates WHERE name = '$templatename'")) {
	$SQL = "INSERT INTO events_templates
		                  (name, folder, formlist, registrant_name, orderbyfield, settings)
		           VALUES ('$templatename', '$templatefolder','" . str_replace(["\r", "\n", "\t"], '', $formlist) . "', '$registrant_name', '$orderbyfield', '$settings')";

    execute_db_sql($SQL);
    $templateid = get_db_field("template_id", "events_templates", "name = '$templatename'");
} else { // Update formslist, settings, and orderbyfield in case they have changed.
    $templateid = $template["template_id"];
	$SQL = "UPDATE events_templates
				 SET formlist = '" . str_replace(["\r", "\n", "\t"], '', $formlist) . "',
			 			 settings = '$settings',
                   orderbyfield = '$orderbyfield'
			 WHERE name = '$templatename'
				 AND folder = '$templatefolder'";
    execute_db_sql($SQL);
}

$globalsettings = [
    [
        "type" => "events_template_global",
        "featureid" => $templateid,
        "setting_name" => "facebookappid",
        "defaultsetting" => "",
    ],
    [
        "type" => "events_template_global",
        "featureid" => $templateid,
        "setting_name" => "facebooksecret",
        "defaultsetting" => "",
    ],
];

// Make sure that global settings exist.
save_batch_settings($globalsettings);

?>