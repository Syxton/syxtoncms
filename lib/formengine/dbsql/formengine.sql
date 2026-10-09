-- Core form engine tables (shared by all form_key consumers)
-- Not owned by Events; Events only declares required hooks for staff_app.

CREATE TABLE IF NOT EXISTS `form_fields` (
  `fieldid` int NOT NULL AUTO_INCREMENT,
  `form_key` varchar(64) NOT NULL DEFAULT 'staff_app',
  `pageid` int NOT NULL DEFAULT 0 COMMENT '0 = global/default form',
  `field_key` varchar(64) NOT NULL,
  `label` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(32) NOT NULL DEFAULT 'text',
  `section` varchar(128) DEFAULT NULL,
  `sortorder` int NOT NULL DEFAULT 0,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `helptext` text,
  `options` longtext COMMENT 'JSON: select options, file_viewer config, etc.',
  `validation` longtext COMMENT 'JSON: validation rules',
  `extra_attrs` longtext COMMENT 'JSON: onblur, onchange, class, etc.',
  `visibility` longtext COMMENT 'JSON: show-when conditions',
  `required_when` longtext COMMENT 'JSON: required-when conditions',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created` int DEFAULT NULL,
  `modified` int DEFAULT NULL,
  PRIMARY KEY (`fieldid`),
  UNIQUE KEY `uq_form_page_key` (`form_key`,`pageid`,`field_key`),
  KEY `idx_form_sort` (`form_key`,`pageid`,`sortorder`),
  KEY `idx_form_active` (`form_key`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `form_hook_bindings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `form_key` varchar(64) NOT NULL,
  `pageid` int NOT NULL DEFAULT 0,
  `hook_id` varchar(96) NOT NULL,
  `field_key` varchar(64) DEFAULT NULL COMMENT 'NULL = unbound',
  `required` tinyint(1) NOT NULL DEFAULT 1,
  `meta` longtext COMMENT 'JSON extras',
  `modified` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_form_hook` (`form_key`,`pageid`,`hook_id`),
  KEY `idx_form_hook_field` (`form_key`,`field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
