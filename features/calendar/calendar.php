<?php
/***************************************************************************
* calendar.php - Calendar Page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/04/2013
* Revision: 0.3.6
***************************************************************************/

if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); } 
    
    callfunction(); 
    
    echo '</body></html>';
}

function calendar_settings() {
global $MYVARS;
	$featureid = dbescape($MYVARS->GET['featureid']);
	$pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "calendar";

	//Default Settings	
	$default_settings = default_settings($feature, $pageid, $featureid);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { calendar_settings(); }
	}
}
?>