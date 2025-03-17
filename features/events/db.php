<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.7
***************************************************************************/

function events_upgrade() {
    global $CFG;
    try {
        start_db_transaction();

        $version = get_db_field("version", "features", "feature='events'");

        //Events request upgrade ///////////////////////////////////////////////////
        $thisversion = 20100715;
        if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
            $SQL = "CREATE TABLE IF NOT EXISTS `events_requests` (
                `reqid` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `featureid` INT NOT NULL ,
                `contact_name` VARCHAR( 150 ) NOT NULL ,
                `contact_email` VARCHAR( 150 ) NOT NULL ,
                `contact_phone` VARCHAR( 15 ) NOT NULL ,
                `event_name` VARCHAR( 150 ) NOT NULL ,
                `participants` INT NOT NULL ,
                `startdate` INT NOT NULL ,
                `enddate` INT NOT NULL ,
                `description` TEXT NOT NULL ,
                `votes_for` INT NOT NULL ,
                `votes_against` INT NOT NULL ,
                `voted` VARCHAR( 255 ) NOT NULL ,
                INDEX ( `voted` ),
                INDEX ( `featureid` )
                ) ENGINE = InnoDB ;";


                $SQL2 = "CREATE TABLE IF NOT EXISTS `events_requests_questions` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `reqid` INT NOT NULL ,
                `question` TEXT NOT NULL ,
                `answer` TEXT NOT NULL ,
                `question_time` INT NOT NULL ,
                `answer_time` INT NOT NULL ,
                INDEX ( `reqid` , `question_time` , `answer_time` )
                ) ENGINE = InnoDB ;";

            if (execute_db_sql($SQL) && execute_db_sql($SQL2)) { //if successful upgrade
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        // Events templates upgrade ///////////////////////////////////////////////////
        $thisversion = 20120210;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE events_templates ADD settings TEXT NULL";
            if (execute_db_sql($SQL)) { //if successful upgrade
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        // Register upon payment
        $thisversion = 20130409;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events_registrations` ADD  `verified` INT( 1 ) NOT NULL DEFAULT  '1', ADD INDEX (  `verified` )";
            if (execute_db_sql($SQL)) { //if successful upgrade
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        // Manually entered vs online registration field
        $thisversion = 20150625;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events_registrations` ADD  `manual` INT( 1 ) NOT NULL DEFAULT  '0', ADD INDEX (  `manual` )";
                $SQL2 = "UPDATE `events_registrations` SET manual = 1 WHERE code = ''";
            if (execute_db_sql($SQL) && execute_db_sql($SQL2)) { //if successful upgrade
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        // Activate and Deactivate templates
        $thisversion = 20151223;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events_templates` ADD `activated` INT( 1 ) NOT NULL DEFAULT  '1', ADD INDEX (  `activated` )";
            execute_db_sql($SQL);
            $SQL2 = "ALTER TABLE `events` ADD `workers` INT( 1 ) NOT NULL DEFAULT  '0', ADD INDEX (  `workers` )";
            execute_db_sql($SQL2);
            if (get_db_row("SELECT * FROM events WHERE workers >= 0") && get_db_row("SELECT * FROM events_templates WHERE activated >= 0")) { //if successful upgrade
                add_role_ability('events','manageevents','Events','1','Manage Events','1','1');
                add_role_ability('events','manageapplications','Events','2','Manage worker applications','1','1');
                add_role_ability('events','manageeventtemplates','Events','2','Manage event templates','1','1');
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        // Activate and Deactivate templates
        $thisversion = 20160125;
        if ($version < $thisversion) {
            $SQL = "CREATE TABLE IF NOT EXISTS `events_staff` (
                `staffid` int(11) NOT NULL AUTO_INCREMENT,
                `userid` int(11) NOT NULL,
                `name` varchar(200) NOT NULL,
                `dateofbirth` int(11) NOT NULL,
                `phone` varchar(20) NOT NULL,
                `address` varchar(200) NOT NULL,
                `agerange` varchar(2) NOT NULL,
                `cocmember` varchar(2) NOT NULL,
                `congregation` varchar(200) NOT NULL,
                `priorwork` varchar(2) NOT NULL,
                `q1_1` varchar(2) NOT NULL,
                `q1_2` varchar(2) NOT NULL,
                `q1_3` varchar(2) NOT NULL,
                `q2_1` varchar(2) NOT NULL,
                `q2_2` varchar(2) NOT NULL,
                `q2_3` varchar(200) NOT NULL,
                `parentalconsent` varchar(200) NOT NULL,
                `parentalconsentsig` varchar(10) NOT NULL,
                `workerconsent` varchar(200) NOT NULL,
                `workerconsentsig` varchar(10) NOT NULL,
                `workerconsentdate` int(11) NOT NULL,
                `ref1name` varchar(200) NOT NULL,
                `ref1relationship` varchar(200) NOT NULL,
                `ref1phone` varchar(20) NOT NULL,
                `ref2name` varchar(200) NOT NULL,
                `ref2relationship` varchar(200) NOT NULL,
                `ref2phone` varchar(20) NOT NULL,
                `ref3name` varchar(200) NOT NULL,
                `ref3relationship` varchar(200) NOT NULL,
                `ref3phone` varchar(20) NOT NULL,
                `bgcheckpass` varchar(2) NOT NULL,
                `bgcheckpassdate` int(11) NOT NULL,
                PRIMARY KEY `staffid` (`staffid`),
                KEY `userid` (`userid`),
                KEY `dateofbirth` (`dateofbirth`),
                KEY `priorwork` (`priorwork`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";
            if (execute_db_sql($SQL)) { //if successful upgrade
                add_role_ability('events','staffapply','Events','1','Apply as staff','1','1','1');
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        $thisversion = 20160128;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events_staff` ADD `pageid` INT( 11 ) NOT NULL DEFAULT '0', ADD INDEX ( `pageid` )";
            execute_db_sql($SQL);
            if (get_db_row("SELECT * FROM events WHERE pageid >= 0")) { //if successful upgrade
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        // Activate and Deactivate templates
        $thisversion = 20160201;
        if ($version < $thisversion) {
            $SQL = "CREATE TABLE IF NOT EXISTS `events_staff_archive` (
                `archiveid` int(11) NOT NULL AUTO_INCREMENT,
                `staffid` int(11) NOT NULL,
                `userid` int(11) NOT NULL,
                `pageid` int(11) NOT NULL,
                `year` int(11) NOT NULL,
                `name` varchar(200) NOT NULL,
                `dateofbirth` int(11) NOT NULL,
                `phone` varchar(20) NOT NULL,
                `address` varchar(200) NOT NULL,
                `agerange` varchar(2) NOT NULL,
                `cocmember` varchar(2) NOT NULL,
                `congregation` varchar(200) NOT NULL,
                `priorwork` varchar(2) NOT NULL,
                `q1_1` varchar(2) NOT NULL,
                `q1_2` varchar(2) NOT NULL,
                `q1_3` varchar(2) NOT NULL,
                `q2_1` varchar(2) NOT NULL,
                `q2_2` varchar(2) NOT NULL,
                `q2_3` varchar(200) NOT NULL,
                `parentalconsent` varchar(200) NOT NULL,
                `parentalconsentsig` varchar(10) NOT NULL,
                `workerconsent` varchar(200) NOT NULL,
                `workerconsentsig` varchar(10) NOT NULL,
                `workerconsentdate` int(11) NOT NULL,
                `ref1name` varchar(200) NOT NULL,
                `ref1relationship` varchar(200) NOT NULL,
                `ref1phone` varchar(20) NOT NULL,
                `ref2name` varchar(200) NOT NULL,
                `ref2relationship` varchar(200) NOT NULL,
                `ref2phone` varchar(20) NOT NULL,
                `ref3name` varchar(200) NOT NULL,
                `ref3relationship` varchar(200) NOT NULL,
                `ref3phone` varchar(20) NOT NULL,
                `bgcheckpass` varchar(2) NOT NULL,
                `bgcheckpassdate` int(11) NOT NULL,
                PRIMARY KEY `archiveid` (`archiveid`),
                KEY `staffid` (`staffid`),
                KEY `pageid` (`pageid`),
                KEY `userid` (`userid`),
                KEY `dateofbirth` (`dateofbirth`),
                KEY `priorwork` (`priorwork`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";
            if (execute_db_sql($SQL)) { //if successful upgrade
                if ($result = get_db_result("SELECT * FROM events_staff")) {
                    while ($row = fetch_row($result)) {
                        $SQL = "INSERT INTO events_staff_archive
                        (staffid,userid,pageid,year,name,phone,dateofbirth,address,agerange,cocmember,congregation,priorwork,q1_1,q1_2,q1_3,q2_1,q2_2,q2_3,parentalconsent,parentalconsentsig,workerconsent,workerconsentsig,workerconsentdate,ref1name,ref1relationship,ref1phone,ref2name,ref2relationship,ref2phone,ref3name,ref3relationship,ref3phone,bgcheckpass,bgcheckpassdate)
                        VALUES('" . $row["staffid"] . "','" . $row["userid"] . "','" . $row["pageid"] . "','" . date("Y", $row["workerconsentdate"]) . "','" . $row["name"] . "','" . $row["phone"] . "',
                            '" . $row["dateofbirth"] . "','" . $row["address"] . "','" . $row["agerange"] . "','" . $row["cocmember"] . "','" . $row["congregation"] . "',
                            '" . $row["priorwork"] . "','" . $row["q1_1"] . "','" . $row["q1_2"] . "','" . $row["q1_3"] . "','" . $row["q2_1"] . "','" . $row["q2_2"] . "','" . $row["q2_3"] . "',
                            '" . $row["parentalconsent"] . "','" . $row["parentalconsentsig"] . "','" . $row["workerconsent"] . "','" . $row["workerconsentsig"] . "','" . $row["workerconsentdate"] . "',
                            '" . $row["ref1name"] . "','" . $row["ref1relationship"] . "','" . $row["ref1phone"] . "','" . $row["ref2name"] . "','" . $row["ref2relationship"] . "','" . $row["ref2phone"] . "','" . $row["ref3name"] . "','" . $row["ref3relationship"] . "','" . $row["ref3phone"] . "','" . $row["bgcheckpass"] . "','" . $row["bgcheckpassdate"] . "')";
                        execute_db_sql($SQL);
                    }
                }
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        $thisversion = 20160408;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events` ADD `description` LONGTEXT NULL";
            execute_db_sql($SQL);
            $SQL = "ALTER TABLE `events` CHANGE `extrainfo` `byline` LONGTEXT NULL";
            execute_db_sql($SQL);
            if (get_db_row("SELECT * FROM events WHERE description != 'xxxxxxxx'")) { //if successful upgrade
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        $thisversion = 20160516;
        if ($version < $thisversion) {
            $SQL1 = "UPDATE `events_staff` SET agerange=1, parentalconsent='', parentalconsentsig='' WHERE agerange=0 AND (UNIX_TIMESTAMP() - dateofbirth) > 567648000";
            $SQL2 = "UPDATE `events_staff_archive` SET agerange=1, parentalconsent='', parentalconsentsig='' WHERE year=2016 AND agerange=0 AND (UNIX_TIMESTAMP() - dateofbirth) > 567648000";
            if (execute_db_sql($SQL1) && execute_db_sql($SQL2)) {
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }

        $thisversion = 20250425;
        if ($version < $thisversion) {
            // Create promo code set tables.
            $SQL1 = "CREATE TABLE IF NOT EXISTS `events_promo_set` (
                `setid` int(11) NOT NULL AUTO_INCREMENT,
                `setname` varchar(200) NOT NULL,
                `pageid` int(11) NOT NULL,
                `created` INT NOT NULL ,
                PRIMARY KEY `setid` (`setid`),
                KEY `pageid` (`pageid`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;";

            $SQL2 = "CREATE TABLE IF NOT EXISTS `events_promo_set_codes` (
                `codeid` int(11) NOT NULL AUTO_INCREMENT,
                `setid` int(11) NOT NULL,
                `codename` varchar(200) NOT NULL,
                `code` varchar(200) NOT NULL,
                `reduction` varchar(10) NOT NULL,
                `created` INT NOT NULL ,
                PRIMARY KEY `codeid` (`codeid`),
                KEY `setid` (`setid`),
                KEY `codename` (`codename`),
                KEY `created` (`created`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;";

            // Add promo code editor ability
            add_role_ability('events','managepromocodes','Events','2','Manage Promo Codes','1','1','0','0');

            if (execute_db_sql($SQL1) && execute_db_sql($SQL2)) {
                execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");
            }
        }
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }
}

function events_install() {
    $thisversion = 20151223;
    if (!get_db_row("SELECT * FROM features WHERE feature='events'")) {
      $SQL = "CREATE TABLE IF NOT EXISTS `events` (
            `eventid` int(11) NOT NULL AUTO_INCREMENT,
            `pageid` int(11) DEFAULT NULL,
            `template_id` int(11) DEFAULT NULL,
            `name` varchar(50) DEFAULT NULL,
            `extrainfo` longtext,
            `category` int(11) DEFAULT NULL,
            `contact` varchar(50) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `phone` varchar(15) DEFAULT NULL,
            `location` varchar(50) DEFAULT NULL,
            `allowinpage` int(11) DEFAULT '0',
            `workers` tinyint(1) DEFAULT '0',
            `start_reg` int(11) DEFAULT '0',
            `stop_reg` int(11) DEFAULT '0',
            `max_users` int(11) DEFAULT '0',
            `hard_limits` longtext,
            `soft_limits` longtext,
            `allday` tinyint(1) DEFAULT '0',
            `event_begin_date` int(11) DEFAULT '0',
            `event_begin_time` varchar(5) DEFAULT NULL,
            `event_end_date` int(11) DEFAULT '0',
            `event_end_time` varchar(5) DEFAULT NULL,
            `caleventid` varchar(30) DEFAULT '0',
            `siteviewable` tinyint(4) DEFAULT '0',
            `paypal` varchar(50) DEFAULT '0',
            `fee_min` int(11) DEFAULT '0',
            `fee_full` int(11) DEFAULT '0',
            `sale_fee` int(11) DEFAULT '0',
            `sale_end` int(11) DEFAULT NULL,
            `payableto` varchar(100) DEFAULT NULL,
            `checksaddress` text,
            `confirmed` tinyint(1) DEFAULT '0',
            PRIMARY KEY (`eventid`),
            KEYKEY `workers` (`workers`)
            ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";

          $SQL2 = "CREATE TABLE IF NOT EXISTS `events_locations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `location` varchar(200) DEFAULT NULL,
            `address_1` varchar(200) DEFAULT NULL,
            `address_2` varchar(200) DEFAULT NULL,
            `zip` varchar(20) DEFAULT NULL,
            `phone` varchar(20) DEFAULT NULL,
            `userid` text,
            `shared` int(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";

          $SQL3 = "CREATE TABLE IF NOT EXISTS `events_registrations` (
            `regid` int(11) NOT NULL AUTO_INCREMENT,
            `eventid` int(11) DEFAULT NULL,
            `date` int(11) DEFAULT NULL,
            `queue` tinyint(1) DEFAULT '0',
            `email` varchar(100) DEFAULT NULL,
            `code` varchar(50) DEFAULT NULL,
            PRIMARY KEY (`regid`),
            KEY `eventid` (`eventid`),
            KEY `code` (`code`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";

          $SQL4 = "CREATE TABLE IF NOT EXISTS `events_registrations_values` (
            `entryid` int(11) NOT NULL AUTO_INCREMENT,
            `regid` int(11) DEFAULT NULL,
            `elementid` int(11) DEFAULT NULL,
            `value` longtext,
            `eventid` int(11) DEFAULT NULL,
            `elementname` varchar(50) DEFAULT NULL,
            PRIMARY KEY (`entryid`),
            KEY `regid` (`regid`),
            KEY `elementid` (`elementid`),
            KEY `eventid` (`eventid`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";

          $SQL5 = "CREATE TABLE IF NOT EXISTS `events_templates` (
            `template_id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) DEFAULT NULL,
            `folder` varchar(50) DEFAULT NULL,
            `formlist` longtext,
            `intro` longtext,
            `registrant_name` varchar(100) NOT NULL DEFAULT '',
            `orderbyfield` varchar(200) NOT NULL,
            `activated` tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY `template_id` (`template_id`),
            KEY `activated` (`activated`),
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";

          $SQL6 = "CREATE TABLE IF NOT EXISTS `events_templates_forms` (
            `elementid` int(11) NOT NULL AUTO_INCREMENT,
            `template_id` int(11) DEFAULT NULL,
            `type` varchar(50) DEFAULT NULL,
            `display` varchar(100) DEFAULT NULL,
            `hint` longtext,
            `optional` tinyint(1) DEFAULT '0',
            `list` longtext,
            `sort` int(11) DEFAULT NULL,
            `length` int(11) DEFAULT '0',
            `allowduplicates` int(11) DEFAULT '1',
            `nameforemail` tinyint(4) DEFAULT '0',
            PRIMARY KEY (`elementid`),
            KEY `template_id` (`template_id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";

          $SQL7 = "CREATE TABLE IF NOT EXISTS `events_staff` (
            `staffid` int(11) NOT NULL AUTO_INCREMENT,
            `pageid` int(11) NOT NULL DEFAULT '0',
            `userid` int(11) NOT NULL,
            `name` varchar(200) NOT NULL,
            `dateofbirth` int(11) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `address` varchar(200) NOT NULL,
            `agerange` varchar(2) NOT NULL,
            `cocmember` varchar(2) NOT NULL,
            `congregation` varchar(200) NOT NULL,
            `priorwork` varchar(2) NOT NULL,
            `q1_1` varchar(2) NOT NULL,
            `q1_2` varchar(2) NOT NULL,
            `q1_3` varchar(2) NOT NULL,
            `q2_1` varchar(2) NOT NULL,
            `q2_2` varchar(2) NOT NULL,
            `q2_3` varchar(200) NOT NULL,
            `parentalconsent` varchar(200) NOT NULL,
            `parentalconsentsig` varchar(10) NOT NULL,
            `workerconsent` varchar(200) NOT NULL,
            `workerconsentsig` varchar(10) NOT NULL,
            `workerconsentdate` int(11) NOT NULL,
            `ref1name` varchar(200) NOT NULL,
            `ref1relationship` varchar(200) NOT NULL,
            `ref1phone` varchar(20) NOT NULL,
            `ref2name` varchar(200) NOT NULL,
            `ref2relationship` varchar(200) NOT NULL,
            `ref2phone` varchar(20) NOT NULL,
            `ref3name` varchar(200) NOT NULL,
            `ref3relationship` varchar(200) NOT NULL,
            `ref3phone` varchar(20) NOT NULL,
            `bgcheckpass` varchar(2) NOT NULL,
            `bgcheckpassdate` int(11) NOT NULL,
            PRIMARY KEY `staffid` (`staffid`),
            KEY `userid` (`userid`),
            KEY `dateofbirth` (`dateofbirth`),
            KEY `priorwork` (`priorwork`),
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";

          $SQL8 = "CREATE TABLE IF NOT EXISTS `events_staff_archive` (
            `archiveid` int(11) NOT NULL AUTO_INCREMENT,
            `staffid` int(11) NOT NULL,
            `userid` int(11) NOT NULL,
            `pageid` int(11) NOT NULL,
            `year` int(11) NOT NULL,
            `name` varchar(200) NOT NULL,
            `dateofbirth` int(11) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `address` varchar(200) NOT NULL,
            `agerange` varchar(2) NOT NULL,
            `cocmember` varchar(2) NOT NULL,
            `congregation` varchar(200) NOT NULL,
            `priorwork` varchar(2) NOT NULL,
            `q1_1` varchar(2) NOT NULL,
            `q1_2` varchar(2) NOT NULL,
            `q1_3` varchar(2) NOT NULL,
            `q2_1` varchar(2) NOT NULL,
            `q2_2` varchar(2) NOT NULL,
            `q2_3` varchar(200) NOT NULL,
            `parentalconsent` varchar(200) NOT NULL,
            `parentalconsentsig` varchar(10) NOT NULL,
            `workerconsent` varchar(200) NOT NULL,
            `workerconsentsig` varchar(10) NOT NULL,
            `workerconsentdate` int(11) NOT NULL,
            `ref1name` varchar(200) NOT NULL,
            `ref1relationship` varchar(200) NOT NULL,
            `ref1phone` varchar(20) NOT NULL,
            `ref2name` varchar(200) NOT NULL,
            `ref2relationship` varchar(200) NOT NULL,
            `ref2phone` varchar(20) NOT NULL,
            `ref3name` varchar(200) NOT NULL,
            `ref3relationship` varchar(200) NOT NULL,
            `ref3phone` varchar(20) NOT NULL,
            `bgcheckpass` varchar(2) NOT NULL,
            `bgcheckpassdate` int(11) NOT NULL,
            PRIMARY KEY `archiveid` (`archiveid`),
            KEY `staffid` (`staffid`),
            KEY `pageid` (`pageid`),
            KEY `userid` (`userid`),
            KEY `dateofbirth` (`dateofbirth`),
            KEY `priorwork` (`priorwork`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";

        if (execute_db_sql($SQL) && execute_db_sql($SQL2) && execute_db_sql($SQL3) &&
            execute_db_sql($SQL4) && execute_db_sql($SQL5) && execute_db_sql($SQL6) &&
            execute_db_sql($SQL7) && execute_db_sql($SQL8)) { //if successful upgrade
            execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='events'");

            //CREATE ROLE ABILITIES
            add_role_ability('events','viewevents','Events','1','View Events','1','1','1','1');
            add_role_ability('events','addevents','Events','2','Create new events','1','0','0','0');
            add_role_ability('events','editevents','Events','2','Edit events','1','1','0','0');
            add_role_ability('events','editopenevents','Events','3','Edit completed events','1','0','0','0');
            add_role_ability('events','signupforevents','Events','1','Signup for events','1','1','1','1');
            add_role_ability('events','confirmevents','Events','3','Allow site viewability and add to calendar.','1','0','0','0');
            add_role_ability('events','exportcsv','Events','2','Export registration list to CSV','1','1','0','0');
            add_role_ability('events','manageevents','Events','1','Manage Events','1','1');
            add_role_ability('events','manageapplications','Events','2','Manage worker applications','1','1');
            add_role_ability('events','manageeventtemplates','Events','2','Manage event templates','1','1');
        }
    }
}
?>