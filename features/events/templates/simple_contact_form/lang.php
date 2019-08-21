<?php
/***************************************************************************
* lang.php - Event Lang library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 02/17/2012
* Revision: 0.0.3
***************************************************************************/

unset($HELP);
unset($ERRORS);

//REGISTRATION
//HELP LANG
$HELP = new stdClass();
$HELP->help_email = "Please enter a valid email address to send your registration information.";
$HELP->help_address = "Please enter the mailing address of the camper.";
$HELP->help_city = "Please enter the home city of the camper.";
$HELP->help_phone = "Please enter a phone number beginning with the area code.";
$HELP->help_state = "Please select the home state of the camper.";
$HELP->help_zip = "Please enter the zipcode of the camper.";
$HELP->help_paywithapp = "Please enter the amount you wish to pay up front.";
$HELP->help_overnight = "Do you plan to stay overnight on-site?";
$HELP->help_gender = "Are you a man or a woman?";

//ERRORS
$ERRORS = new stdClass();
?>