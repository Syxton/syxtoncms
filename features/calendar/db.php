<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2010
* Revision: 0.0.2
***************************************************************************/

function calendar_upgrade()
{
	global $CFG;
    
    $version = get_db_field("version","features","feature='calendar'");	    
    $thisversion = 20100819;
	if ($version < $thisversion) { execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='calendar'"); }

//	$thisversion = 1;
//	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
//		$SQL = "";
//		if (execute_db_sql($SQL)) //if successful upgrade
//		{
//			execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='calendar'");
//		}
//	}

}
?>