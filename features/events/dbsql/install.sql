install||
CREATE TABLE IF NOT EXISTS `events` (
    `eventid` int(11) NOT NULL AUTO_INCREMENT,
    `pageid` int(11) DEFAULT NULL,
    `template_id` int(11) DEFAULT NULL,
    `name` varchar(50) DEFAULT NULL,
    `extrainfo` longtext,
    `category` int(11) DEFAULT NULL,
    `contact` int(11) DEFAULT NULL,
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
        KEY `workers` (`workers`)
    ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `events_locations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `location` varchar(200) DEFAULT NULL,
    `address_1` varchar(200) DEFAULT NULL,
    `address_2` varchar(200) DEFAULT NULL,
    `zip` varchar(20) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `userid` text,
    `shared` int(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `events_registrations` (
    `regid` int(11) NOT NULL AUTO_INCREMENT,
    `eventid` int(11) DEFAULT NULL,
    `date` int(11) DEFAULT NULL,
    `queue` tinyint(1) DEFAULT '0',
    `email` varchar(100) DEFAULT NULL,
    `code` varchar(50) DEFAULT NULL,
    PRIMARY KEY (`regid`),
    KEY `eventid` (`eventid`),
    KEY `code` (`code`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `events_registrations_values` (
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
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `events_templates` (
    `template_id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) DEFAULT NULL,
    `folder` varchar(50) DEFAULT NULL,
    `formlist` longtext,
    `intro` longtext,
    `registrant_name` varchar(100) NOT NULL DEFAULT '',
    `orderbyfield` varchar(200) NOT NULL,
    `activated` tinyint(1) NOT NULL DEFAULT '1',
        PRIMARY KEY (`template_id`),
    KEY `activated` (`activated`),
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `events_templates_forms` (
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
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- Staff applications: retained identity/consent/bgcheck columns + form_data JSON for answers.
-- Dynamic questions are defined in events_staff_form_fields (no classic answer columns).
CREATE TABLE IF NOT EXISTS `events_staff` (
    `staffid` int(11) NOT NULL AUTO_INCREMENT,
    `pageid` int(11) NOT NULL DEFAULT '0',
    `userid` int(11) NOT NULL,
    `name` varchar(200) NOT NULL DEFAULT '',
    `phone` varchar(20) NOT NULL DEFAULT '',
    `dateofbirth` int(11) NOT NULL DEFAULT '0',
    `parentalconsent` varchar(200) NOT NULL DEFAULT '',
    `parentalconsentsig` varchar(10) NOT NULL DEFAULT '',
    `workerconsent` varchar(200) NOT NULL DEFAULT '',
    `workerconsentsig` varchar(10) NOT NULL DEFAULT '',
    `workerconsentdate` int(11) NOT NULL DEFAULT '0',
    `bgcheckpassdate` int(11) NOT NULL DEFAULT '0',
    `form_data` longtext DEFAULT NULL,
    PRIMARY KEY (`staffid`),
    KEY `userid` (`userid`),
    KEY `pageid` (`pageid`),
    KEY `dateofbirth` (`dateofbirth`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `events_staff_archive` (
    `archiveid` int(11) NOT NULL AUTO_INCREMENT,
    `staffid` int(11) NOT NULL,
    `userid` int(11) NOT NULL,
    `pageid` int(11) NOT NULL,
    `year` int(11) NOT NULL,
    `name` varchar(200) NOT NULL DEFAULT '',
    `phone` varchar(20) NOT NULL DEFAULT '',
    `dateofbirth` int(11) NOT NULL DEFAULT '0',
    `parentalconsent` varchar(200) NOT NULL DEFAULT '',
    `parentalconsentsig` varchar(10) NOT NULL DEFAULT '',
    `workerconsent` varchar(200) NOT NULL DEFAULT '',
    `workerconsentsig` varchar(10) NOT NULL DEFAULT '',
    `workerconsentdate` int(11) NOT NULL DEFAULT '0',
    `bgcheckpassdate` int(11) NOT NULL DEFAULT '0',
    `form_data` longtext DEFAULT NULL,
    PRIMARY KEY (`archiveid`),
    KEY `staffid` (`staffid`),
    KEY `pageid` (`pageid`),
    KEY `userid` (`userid`),
    KEY `year` (`year`),
    KEY `dateofbirth` (`dateofbirth`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- Dynamic staff application form field definitions (pageid 0 = global defaults)
CREATE TABLE IF NOT EXISTS `events_staff_form_fields` (
    `fieldid` int(11) NOT NULL AUTO_INCREMENT,
    `pageid` int(11) NOT NULL DEFAULT '0' COMMENT '0 = global/default form',
    `field_key` varchar(100) NOT NULL,
    `label` varchar(255) NOT NULL,
    `type` varchar(50) NOT NULL DEFAULT 'text',
    `options` longtext DEFAULT NULL,
    `required` tinyint(1) NOT NULL DEFAULT '0',
    `sortorder` int(11) NOT NULL DEFAULT '0',
    `section` varchar(200) DEFAULT NULL,
    `helptext` text,
    `validation` longtext DEFAULT NULL,
    `is_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = maps to a static DB column',
    `active` tinyint(1) NOT NULL DEFAULT '1',
    `extra_attrs` longtext DEFAULT NULL,
    `visibility` longtext DEFAULT NULL COMMENT 'JSON show/hide rules',
    `required_when` longtext DEFAULT NULL COMMENT 'JSON conditional required rules',
    `created` int(11) NOT NULL DEFAULT '0',
    `modified` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`fieldid`),
    UNIQUE KEY `page_field` (`pageid`,`field_key`),
    KEY `pageid` (`pageid`),
    KEY `sortorder` (`sortorder`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `events_contacts` (
    `contactid` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(200) NOT NULL,
    `email` varchar(200) NOT NULL,
    `phone` varchar(20) NOT NULL,
    `pageid` INT NOT NULL ,
        PRIMARY KEY (`contactid`),
    KEY `pageid` (`pageid`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;
||install