<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2010
* Revision: 0.0.2
***************************************************************************/

function pics_upgrade()
{
	global $CFG;
    $version = get_db_field("version","features","feature='pics'");	
	$thisversion = 20100819;
	if ($version < $thisversion) { execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='pics'"); }
    
//	$thisversion = 20100819;
//	if ($version < $thisversion) { 
//		$SQL = "";
//		if (execute_db_sql($SQL)) //if successful upgrade
//		{
//			execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='pics'");
//		}
//	}
}

?>