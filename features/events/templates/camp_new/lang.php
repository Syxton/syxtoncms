<?php
/***************************************************************************
* lang.php - Event Lang library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.3
***************************************************************************/
 
unset($HELP);
unset($ERRORS);

//REGISTRATION 
//HELP LANG
$HELP = new \stdClass;
$HELP->help_email = "Please enter a valid email address to send your registration information.";
$HELP->help_middlei = "Please enter a middle initial.";
$HELP->help_age = "The age will be calculated based on the birthdate entered.";
$HELP->help_bday = "Please enter the camper's date of birth.";
$HELP->help_grade = "Please enter the last grade the camper has finished. 0-12";
$HELP->help_gender = "Please select whether the camper is Male or Female.";
$HELP->help_congregation = "What, if any, church congregation does the camper attend?";
$HELP->help_parent = "Please enter the name of a parent or guardian of the camper.";
$HELP->help_address = "Please enter the mailing address of the camper.";
$HELP->help_campership = "Please enter the church/organization sponsering your campership.";
$HELP->help_city = "Please enter the home city of the camper.";
$HELP->help_state = "Please select the home state of the camper.";
$HELP->help_zip = "Please enter the zipcode of the camper.";
$HELP->help_healthfrom = "This waiver is active from this date.";
$HELP->help_healthto = "This waiver is active to this date.";
$HELP->help_membername = "Please enter the name associated with your insurance.";
$HELP->help_relationship = "Please enter the relationship between the member and the camper.";
$HELP->help_carrier = "Please enter the name of your insurance.";
$HELP->help_memberid = "Please enter your member ID number.";
$HELP->help_membercode = "Please enter your benefit code.";
$HELP->help_memberaccount = "Please enter the account # of your insurance.";
$HELP->help_healthto = "Please enter the expiration date of your insurance.";
$HELP->help_history = "Please enter any of the camper's important medical history.";
$HELP->help_alergies = "Please enter any of the camper's alergies.";
$HELP->help_existing = "Please enter any of the camper's existing medical issues.";
$HELP->help_meds = "Please enter any medications the camper is currently taking.";
$HELP->help_tetanus = "Please enter the date of the camper's last tetanus shot.";
$HELP->help_pictures = "Do you want to purchase a camp picture?";
$HELP->help_paywithapp = "Please enter the amount you wish to pay up front.";
$HELP->help_shirt = "Do you want a camp shirt?";
$HELP->help_shirt_size = "What size camp shirt do you want?";
$HELP->help_phone = "Please enter a phone number beginning with the area code.";

//ERRORS
$ERRORS = new \stdClass;
$ERRORS->error_age_min = "The birthdate you have selected does not meet the minimum age of {0}";
$ERRORS->error_age_max = "The birthdate you have selected exeeds the maximum age of {0}";
?>