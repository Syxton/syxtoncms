<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 1/24/2012
* Revision: 0.0.4
***************************************************************************/

function adminpanel_upgrade() {
	global $CFG;

    $version = get_db_field("version", "features", "feature='adminpanel'");	
    $thisversion = 20100819;
	if ($version < $thisversion) { execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='adminpanel'"); }

	$thisversion = 20120113;
	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
		add_role_ability('user','manage_files','User','1','Manage Files','1','1','1','0');
		execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='adminpanel'");
	}
    
    $thisversion = 20120123;
	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
		add_role_ability('roles','edit_group_abilities','Roles','3','Edit group abilities on page','1','0','0','0');
        add_role_ability('roles','edit_feature_group_abilities','Roles','3','Edit group abilities of a specific feature','1','0','0','0');
		execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='adminpanel'");
	}
    
    $thisversion = 20120124;
	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
		add_role_ability('roles','manage_groups','Roles','3','Add/Edit/Delete Groups','1','0','0','0');
		execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='adminpanel'");
	}
}
?>