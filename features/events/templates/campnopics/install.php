<?php
/***************************************************************************
 * install.php - reg page installer
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 4/21/08
 * $Revision: .12
 ***************************************************************************/
//Form name:Section:Title
$templatename = 'Camp Wabashi Week (no pics)';
$templatefolder = 'campnopics';
$registrant_name = 'Camper_Name';
$orderbyfield = 'Camper_Name';

$formlist = '
Camper_Name:Camper:Name;
Camper_Age:Camper:Age;
Camper_Gender:Camper:Gender;
Camper_Birth_Date:Camper:Birthday;
Camper_Grade:Camper:Grade;
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

//If it is already installed, don't install it again.
if(!get_db_row("SELECT * FROM events_templates WHERE name = '$templatename'"))
{
	$SQL = "INSERT INTO events_templates
	(name, folder, formlist, registrant_name, orderbyfield)
	VALUES 
	('$templatename','$templatefolder','".str_replace(array("\r", "\n", "\t"), '', $formlist)."', '$registrant_name', '$orderbyfield')";

	execute_db_sql($SQL);
}

?>