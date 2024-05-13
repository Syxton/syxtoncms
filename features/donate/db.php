<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.1
***************************************************************************/

function donate_upgrade() {
global $CFG;
	$version = get_db_field("version", "features", "feature='donate'");		
	$thisversion = 20130320;
	if ($version < $thisversion) { execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='donate'"); }

	$thisversion = 20130504;
	if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
		$SQL = "ALTER TABLE  `donate_donations` CHANGE  `amount`  `amount` DECIMAL(10,2) NOT NULL";
		$SQL2 = "ALTER TABLE  `donate_campaign` CHANGE  `goal_amount`  `goal_amount` DECIMAL(10,2) NOT NULL";
		if (execute_db_sql($SQL) && execute_db_sql($SQL2)) { //if successful upgrade
			execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='donate'");
		}
	}

}

function donate_install() {
	//Make sure this hasn't already been done
	if (!get_db_row("SELECT * FROM features WHERE feature='donate'")) {
		$thisversion = 20130320;
		//ADD AS FEATURE
		execute_db_sql("INSERT INTO features (feature,feature_title,multiples_allowed,site_multiples_allowed,default_area,rss,allowed) VALUES('donate','Donations','1','1','side','0','1')");
		execute_db_sql("CREATE TABLE IF NOT EXISTS `donate_campaign` (
							`campaign_id` int(11) NOT NULL AUTO_INCREMENT,
							`origin_page` int(11) NOT NULL,
							`title` varchar(100) NOT NULL,
							`goal_amount` double NOT NULL,
							`goal_description` TEXT NOT NULL,
							`paypal_email` VARCHAR(255) NOT NULL,
							`token` VARCHAR(255) NOT NULL,
							`shared` int(1) NOT NULL,
							`datestarted` int(11) NOT NULL,
							`metgoal` int(11) NOT NULL,
							PRIMARY KEY (`campaign_id`),
							KEY `origin_page` (`origin_page`),
							KEY `datestarted` (`datestarted`),
							KEY `metgoal` (`metgoal`),
							KEY `shared` (`shared`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;");
		execute_db_sql("CREATE TABLE IF NOT EXISTS `donate_instance` (
							`donate_id` int(11) NOT NULL AUTO_INCREMENT,
							`campaign_id` int(11) NOT NULL,
							PRIMARY KEY (`donate_id`),
							KEY `campaign_id` (`campaign_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;");
		execute_db_sql("CREATE TABLE IF NOT EXISTS `donate_donations` (
							`donationid` int(11) NOT NULL AUTO_INCREMENT,
							`campaign_id` int(11) NOT NULL,
							`name` varchar(255) NOT NULL,
							`paypal_TX` varchar(255) NOT NULL,
							`amount` double NOT NULL,
							`timestamp` int(11) NOT NULL,
							PRIMARY KEY (`donationid`),
							KEY `campaign_id` (`campaign_id`),
							KEY `name` (`name`),
							KEY `paypal_TX` (`paypal_TX`),
							KEY `timestamp` (`timestamp`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;");
		
		//CREATE ROLE ABILITIES
		add_role_ability('donate','makedonation','Donations','1','Make a donation','1','1','1','1');
		add_role_ability('donate','adddonation','Donations','1','Add offline donations','1','1','0','0');
		add_role_ability('donate','managedonation','Donations','1','Manage donation feature','1','1','0','0');
		
		//first version number
		execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='donate'");		
	}
}
?>