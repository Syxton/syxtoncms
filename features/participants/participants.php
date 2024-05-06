<?php
/***************************************************************************
* participants.php - View page participants
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2011
* Revision: 0.0.5
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) { include('../header.php'); } 
    if (!isset($HTMLLIB)) { include_once($CFG->dirroot . '/features/html/htmllib.php'); }
    
    callfunction();
    
    echo '</body></html>';
}

function view_participants() {
global $MYVARS, $CFG, $USER;
	$pageid = $MYVARS->GET['pageid'];
    $featureid = $MYVARS->GET['featureid'];
    
    $feature = "participants";
    if (!$settings = fetch_settings($feature, $featureid, $pageid)) {
		save_batch_settings(default_settings($feature, $pageid, $featureid));
		$settings = fetch_settings($feature, $featureid, $pageid);
	}
	
	$limit = $settings->$feature->$featureid->viewable_limit->setting;
    $show_total = $settings->$feature->$featureid->show_total->setting;
    
    if (!user_is_able($USER->userid, "viewparticipants", $pageid)) { debugging(error_string("no_permission", ["viewparticipants"]), 2); return; }

    $SQL = "SELECT * FROM roles_assignment ra JOIN users u ON u.userid=ra.userid JOIN roles r ON r.roleid = ra.roleid WHERE ra.pageid='$pageid' AND ra.confirm=0 ORDER BY r.display_name,u.lname";
	if ($results = get_db_result($SQL . " LIMIT $limit")) {
        if ($show_total) { $total = get_db_count($SQL); echo "<div style='text-align:center;'><strong>Total:</strong> $total</div>";}
		echo '<table style="border:1px solid silver;border-collapse:collapse;margin:5px;width:98%">';
		echo '<tr><td style="width:50%;padding:2px 5px;white-space:nowrap"><img src="' . $CFG->wwwroot . '/images/user.png" style="vertical-align:bottom;" /><strong>Name</strong></td><td style="width:5%;"></td><td style="width:45%;text-align:center;white-space:nowrap;padding:2px 5px;"><img src="' . $CFG->wwwroot . '/images/key.png" style="vertical-align:bottom;" /> <strong>Page Role</strong></td></tr>';
		
		$toggle=true;
		while ($row = fetch_row($results)) {	
			$color = $toggle ? "#FAFAFA" : "#F2F2F2";
			$toggle = $toggle ? false : true;
			
			echo '<tr style="background-color:' . $color . '"><td style="width:50%;padding:2px 5px;white-space:nowrap">' . $row["fname"] . ' ' . $row["lname"] . '</td><td style="float:left;width:5%;"></td><td style="width:45%;text-align:center;white-space:nowrap;padding:2px 5px;">' . $row["display_name"] . '</td></tr>';
		}
		echo '</table>';	
	}
}

function participants_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "participants";

	//Default Settings	
	$default_settings = default_settings($feature, $pageid, $featureid);
    
	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { participants_settings(); }
	}
}
?>