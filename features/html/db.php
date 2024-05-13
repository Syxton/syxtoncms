<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
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

    /**
	 * This is the version number of this upgrade.
	 * If this is the first time the upgrade is run, this variable should be set to 1
	 * @var int
	 */
	$thisversion = 20240515;

	// If the version is less than this version number, perform the upgrade
	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
		// Add parentid and commenttime to html_comments table.
        $SQL = "ALTER TABLE html_comments
                ADD COLUMN `parentid` INT NOT NULL DEFAULT 0 AFTER `commentid`,
                ADD COLUMN `created` INT NOT NULL DEFAULT 0 AFTER `htmlid`,
                ADD COLUMN `modified` INT NOT NULL DEFAULT 0 AFTER `created`";
        execute_db_sql($SQL);

        // Loop through each comment that has replies, then Loop through those replies and add them to the comments table.
        $SQL = "SELECT * FROM html_comments WHERE commentid IN (SELECT commentid FROM html_replies)";
        if ($comments = get_db_result($SQL)) {
            while ($comment = fetch_row($comments)) {
                $params = [
                    'htmlid' => $comment['htmlid'],
                    'parentid' => $comment['commentid'],
                ];
                $SQL = "SELECT * FROM html_replies WHERE commentid = '" . $comment['commentid'] . "' ORDER BY replyid";
                if ($replies = get_db_result($SQL)) {
                    while ($reply = fetch_row($replies)) {
                        $params['userid'] = $reply['userid'];
                        $params['reply'] = $reply['reply'];
                        $SQL = fill_string("INSERT INTO html_comments (userid, comment, htmlid, parentid) VALUES ('{userid}', '{reply}', '{htmlid}', '{parentid}')", $params);
                        execute_db_sql($SQL);
                    }
                }
            }
        }

        // Delete html_replies table.
		$SQL = "DROP TABLE html_replies";
		// If the delete is successful, update the version number
		if (execute_db_sql($SQL)) {
			execute_db_sql("UPDATE features SET version = '$thisversion' WHERE feature = 'html'");
		}
	}
}
?>