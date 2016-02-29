<?php
/***************************************************************************
 * install.php - reg page installer
 * -------------------------------------------------------------------------
 * $Author: Matthew Davidson
 * $Date: 02/17/2012
 * $Revision: 0.0.5
 ***************************************************************************/
//Form name:Section:Title
$templatename = 'Simple Contact Form';
$templatefolder = 'simple_contact_form';
$registrant_name = 'Name';
$orderbyfield = 'Name';

$formlist = '
Name:Info:Name;
Name_First:Info:First Name;
Name_Last:Info:Last Name;
Address_Line1:Info:Address 1;
Address_Line2:Info:Address 2;
Address_City:Info:City;
Address_State:Info:State;
Address_Zipcode:Info:Zipcode;
Phone:Info:Phone;
Overnight:Info:Overnight;
total_owed:Pay:Amount Owed;
paid:Pay:Amount Paid';

//Event template specific settings
$settings = ''; // No specific settings

//If it is already installed, don't install it again.
if(!get_db_row("SELECT * FROM events_templates WHERE name = '$templatename'")) {
    // Uninstall the father's day template
    $SQL = "DELETE FROM events_templates WHERE folder = 'father_coaching_weekend'";
	execute_db_sql($SQL);
    
	$SQL = "INSERT INTO events_templates
	(name, folder, formlist, registrant_name, orderbyfield, settings)
	VALUES 
	('$templatename','$templatefolder','".str_replace(array("\r", "\n", "\t"), '', $formlist)."', '$registrant_name', '$orderbyfield', '$settings')";

	execute_db_sql($SQL);
}
?>