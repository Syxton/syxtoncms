<?php
/***************************************************************************
* onlineusers.php - Online Users Page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.4
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}
    
    callfunction();
    
    echo '</body></html>';
}

function onlineusers_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "onlineusers";

	//Default Settings	
	$default_settings = default_settings($feature, $pageid, $featureid);
    
	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { onlineusers_settings(); }
	}
}
?>