<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.2
***************************************************************************/

function forum_upgrade() {
	global $CFG;

    $version = get_db_field("version", "features", "feature='forum'");		
    $thisversion = 20100819;
	if ($version < $thisversion) { execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='forum'"); }

	$thisversion = 20240523;
	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
		$SQL = "ALTER TABLE `forum_posts` CHANGE `ownerid` `userid` INT(11) NOT NULL DEFAULT '0'";
		if (execute_db_sql($SQL)) { // If successful upgrade
			execute_db_sql("UPDATE features SET version = '$thisversion' WHERE feature = 'forum'");
		}
	}

	$thisversion = 20240524;
	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
		$SQL = "ALTER TABLE `forum_discussions` CHANGE `ownerid` `userid` INT(11) NOT NULL DEFAULT '0'";
		if (execute_db_sql($SQL)) { // If successful upgrade
			execute_db_sql("UPDATE features SET version = '$thisversion' WHERE feature = 'forum'");
		}
	}
}
?>