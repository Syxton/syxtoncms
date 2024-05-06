<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2010
* Revision: 0.0.2
***************************************************************************/

function news_upgrade() {
	global $CFG;

    $version = get_db_field("version", "features", "feature='news'");		
    $thisversion = 20100819;
	if ($version < $thisversion) { execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='news'"); }
    
	global $CFG;

	// Get the current version
	$version = get_db_field("version", "features", "feature = 'news'");

	/**
	 * This is the version number of this upgrade.
	 * If this is the first time the upgrade is run, this variable should be set to 1
	 * @var int
	 */
	$thisversion = 20100819;

	// If the version is less than this version number, perform the upgrade
	if ($version < $thisversion) {
		execute_db_sql("UPDATE features SET version = '$thisversion' WHERE feature = 'news'");
	}

	/**
	 * This is the version number of this upgrade.
	 * If this is the first time the upgrade is run, this variable should be set to 1
	 * @var int
	 */
	$thisversion = 20240429;

	// If the version is less than this version number, perform the upgrade
	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
		// Delete any pageviewable or siteviewable settings, they are never used.
		$SQL = "ALTER TABLE news DROP COLUMN section;";
		
		// If the delete is successful, update the version number
		if (execute_db_sql($SQL)) {
			execute_db_sql("UPDATE features SET version = '$thisversion' WHERE feature = 'news'");
		}
	}

}
?>