<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 04/29/2024
* Revision: 0.0.3
***************************************************************************/

/**
 * Upgrades the HTML feature
 *
 * @return void
 * @author Matthew Davidson <http://matthewdavidson.co.uk/>
 * @date 2010-08-19
 */
function html_upgrade() {
	global $CFG;

	// Get the current version
	$version = get_db_field("version", "features", "feature = 'html'");

	/**
	 * This is the version number of this upgrade.
	 * If this is the first time the upgrade is run, this variable should be set to 1
	 * @var int
	 */
	$thisversion = 20100819;

	// If the version is less than this version number, perform the upgrade
	if ($version < $thisversion) {
		execute_db_sql("UPDATE features SET version = '$thisversion' WHERE feature = 'html'");
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
		$SQL = "DELETE FROM settings WHERE type = 'html' AND (setting_name = 'siteviewable' OR setting_name = 'pageviewable')";
		
		// If the delete is successful, update the version number
		if (execute_db_sql($SQL)) {
			execute_db_sql("UPDATE features SET version = '$thisversion' WHERE feature = 'html'");
		}
	}
}
?>