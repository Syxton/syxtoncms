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

$formlist = "
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
Gender:Info:Gender;
total_owed:Pay:Amount Owed;
paid:Pay:Amount Paid";

// Format it for db;
$formlist = str_replace(array("\r", "\n", "\t"), '', $formlist);

// If it is already installed, don't install it again.
if (!get_db_row("SELECT * FROM events_templates WHERE name = '$templatename'")) {
    // Event template specific settings.
    $settings = ''; // No specific settings.

    // Uninstall the father's day template
    $SQL = "DELETE FROM events_templates WHERE folder = 'father_coaching_weekend'";
	execute_db_sql($SQL);

	$SQL = "INSERT INTO events_templates
	(name, folder, formlist, registrant_name, orderbyfield, settings)
	VALUES
	('$templatename','$templatefolder','$formlist', '$registrant_name', '$orderbyfield', '$settings')";

	execute_db_sql($SQL);
} else { // Update event template.
    $version = get_db_field("setting","settings","setting_name='version' AND type='events_template' AND extra='$templatefolder'");

	$thisversion = 2018031200;
	if ($version < $thisversion) {
	   $settings = [];
       $settings[] = array('name' => 'template_setting_overnight','title'=> 'Overnight Option','type' => 'yes/no','numeric' => false,'default' => "false", 'extravalidation' => '', 'extra_alert' => '');
       $settings = dbescape(serialize($settings));

       $SQL = "UPDATE events_templates SET settings = '$settings' WHERE folder='$templatefolder'";

	   if (execute_db_sql($SQL)) { // If successful upgrade.
	       execute_db_sql("INSERT INTO settings (type,pageid,featureid,setting_name,setting,extra) VALUES('events_template', 0, 0, 'version', '$thisversion', '$templatefolder')");
	   }
	}

    $thisversion = 2018082101;
	if ($version < $thisversion) {
       $SQL = "UPDATE events_templates SET formlist = '$formlist' WHERE folder='$templatefolder'";

	   execute_db_sql($SQL);
	}
}
?>