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
                update_feature($thisversion, "events");
            }
        }

        // Events templates upgrade ///////////////////////////////////////////////////
        $thisversion = 20120210;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE events_templates ADD settings TEXT NULL";
            if (execute_db_sql($SQL)) { //if successful upgrade
                update_feature($thisversion, "events");
            }
        }

        // Register upon payment
        $thisversion = 20130409;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events_registrations` ADD  `verified` INT( 1 ) NOT NULL DEFAULT  '1', ADD INDEX (  `verified` )";
            if (execute_db_sql($SQL)) { //if successful upgrade
                update_feature($thisversion, "events");
            }
        }

        // Manually entered vs online registration field
        $thisversion = 20150625;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events_registrations` ADD  `manual` INT( 1 ) NOT NULL DEFAULT  '0', ADD INDEX (  `manual` )";
                $SQL2 = "UPDATE `events_registrations` SET `manual` = 1 WHERE `code` = ''";
            if (execute_db_sql($SQL) && execute_db_sql($SQL2)) { //if successful upgrade
                update_feature($thisversion, "events");
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
                update_feature($thisversion, "events");
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
                update_feature($thisversion, "events");
            }
        }

        $thisversion = 20160128;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events_staff` ADD `pageid` INT( 11 ) NOT NULL DEFAULT '0', ADD INDEX ( `pageid` )";
            execute_db_sql($SQL);
            if (get_db_row("SELECT * FROM events WHERE pageid >= 0")) { //if successful upgrade
                update_feature($thisversion, "events");
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
                start_db_transaction();
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
                update_feature($thisversion, "events");
            }
        }

        $thisversion = 20160408;
        if ($version < $thisversion) {
            $SQL = "ALTER TABLE `events` ADD `description` LONGTEXT NULL";
            execute_db_sql($SQL);
            $SQL = "ALTER TABLE `events` CHANGE `extrainfo` `byline` LONGTEXT NULL";
            execute_db_sql($SQL);

            start_db_transaction();
            if (get_db_row("SELECT * FROM events WHERE description != 'xxxxxxxx'")) { //if successful upgrade
                update_feature($thisversion, "events");
            }
        }

        $thisversion = 20160516;
        if ($version < $thisversion) {
            start_db_transaction();
            $SQL1 = "UPDATE `events_staff` SET agerange=1, parentalconsent='', parentalconsentsig='' WHERE agerange=0 AND (UNIX_TIMESTAMP() - dateofbirth) > 567648000";
            $SQL2 = "UPDATE `events_staff_archive` SET agerange=1, parentalconsent='', parentalconsentsig='' WHERE year=2016 AND agerange=0 AND (UNIX_TIMESTAMP() - dateofbirth) > 567648000";
            if (execute_db_sql($SQL1) && execute_db_sql($SQL2)) {
                update_feature($thisversion, "events");
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
                update_feature($thisversion, "events");
            }
        }

        $thisversion = 20260417;
        if ($version < $thisversion) {
            // Create separate contacts table and move contact info there for better security and to allow multiple contacts per event.
            $SQL1 = "CREATE TABLE IF NOT EXISTS `events_contacts` (
                `contactid` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(200) NOT NULL,
                `email` varchar(200) NOT NULL,
                `phone` varchar(20) NOT NULL,
                `pageid` INT NOT NULL ,
                PRIMARY KEY `contactid` (`contactid`),
                KEY `pageid` (`pageid`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;";

            execute_db_sql($SQL1);

            start_db_transaction("Moving contact info to new table");

            // Move contact info to new table and link to events.
            $SQL2 = "SELECT t1.eventid, t1.contact, t1.email, t1.phone, t1.pageid
                FROM events t1
                INNER JOIN (
                    SELECT MAX(eventid) as latestid
                    FROM events
                    GROUP BY contact
                ) t2 ON t1.eventid = t2.latestid;";
            if ($result = get_db_result($SQL2)) {
                while ($row = fetch_row($result)) {
                    $SQL3 = fetch_template("dbsql/events.sql", "add_contact", "events");
    
                    if ($contactid = execute_db_sql($SQL3, [
                        "pageid" => $row["pageid"],
                        "name" => $row["contact"],
                        "email" => $row["email"],
                        "phone" => $row["phone"],
                    ])) {
                        execute_db_sql("UPDATE events SET contact = '$contactid' WHERE contact='" . $row["contact"] . "'");
                    }
                }
            }

            // Check and make sure that all events have a contact id that exists in the contacts table. If not, set to 0.
            $SQL4 = "UPDATE events t1 LEFT JOIN events_contacts t2 ON t1.contact = t2.contactid SET t1.contact = 0 WHERE t2.contactid IS NULL";
            execute_db_sql($SQL4);

            // Drop old contact fields and convert contact field to int.
            $SQL5 = "ALTER TABLE `events` DROP `email`, DROP `phone`, MODIFY `contact` int(11) DEFAULT NULL";
            execute_db_sql($SQL5);

            // Add db manage abilities. (locations, contacts)
            add_event_abilities();

            update_feature($thisversion, "events");
        }

        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
}


function events_install() {
    $thisversion = 20260417;
    if (!get_db_row("SELECT * FROM features WHERE feature='events'")) {
        $SQL = fetch_template("dbsql/events.sql", "install", "events");

        if (execute_db_sql($SQL)) { // if successful install.
            try {
                // Add all current event abilities.
                add_event_abilities();
                update_feature($thisversion, "events");  

            } catch (\Throwable $e) {
                rollback_db_transaction($e->getMessage());
            }
        }
    }
}

function add_event_abilities() {
    // section, ability name, section display, power, ability display, creator, editor, guest, visitor
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
    
    add_role_ability('events','staffapply','Events','1','Apply as staff','1','1','1');

    add_role_ability('events','managepromocodes','Events','2','Manage Promo Codes','1','1','0','0');
    
    add_role_ability('events','managelocations','Events','2','Manage Locations','1','1','0','0');
    add_role_ability('events','managecontacts','Events','2','Manage Contacts','1','1','0','0');
}
?>