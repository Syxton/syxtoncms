<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.2
***************************************************************************/

function participants_upgrade() {
	global $CFG;
    $version = get_db_field("version", "features", "feature='participants'");		
    $thisversion = 20100819;
	if ($version < $thisversion) { execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='participants'"); }
        
//	$thisversion = 20100802;
//	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
//		$SQL = "";
//		if (execute_db_sql($SQL)) //if successful upgrade
//		{
//			execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='participants'");
//		}
//	}

}

function participants_install() {
    //Make sure this hasn't already been done
    if (!get_db_row("SELECT * FROM features WHERE feature='participants'")) {
        $thisversion = 20100801;
  		//ADD AS FEATURE
  		execute_db_sql("INSERT INTO features (feature,feature_title,multiples_allowed,site_multiples_allowed,default_area,rss,allowed) VALUES('participants','Participants','0','0','side','0','1')");
  		
  		//CREATE ROLE ABILITIES
  		add_role_ability('participants','viewparticipants','Participants','1','View participants of a page','1','1','1','0');
        
        //first version number
        execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='participants'");        
    }
}
?>