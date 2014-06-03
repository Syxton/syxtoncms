<?php
/***************************************************************************
* chat.php - Chat modal page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 0.1.6
***************************************************************************/
if(empty($_POST["aslib"])){
    if(!isset($CFG)){ include('../header.php'); }
    
    callfunction();
    
    echo '</body></html>';
}


function chat_settings(){
global $CFG,$MYVARS,$USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "chat";

	//Default Settings	
	$default_settings = default_settings($feature,$pageid,$featureid);
	$setting_names = get_setting_names($default_settings);
    
	//Check if any settings exist for this feature
	if($settings = fetch_settings($feature,$featureid,$pageid)){
        echo make_settings_page($setting_names,$settings,$default_settings,$feature,$featureid,$pageid);
	}else{ //No Settings found...setup default settings
		if(make_or_update_settings_array($default_settings)){ chat_settings(); }
	}
}
?>