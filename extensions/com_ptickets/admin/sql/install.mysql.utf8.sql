CREATE TABLE IF NOT EXISTS `#__ptickets_tickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ref` varchar(20) NOT NULL DEFAULT '',
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `type` varchar(40) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `details` mediumtext NOT NULL COMMENT 'JSON: answers to the type-specific questions',
  `phone` varchar(40) NOT NULL DEFAULT '',
  `contact_method` varchar(20) NOT NULL DEFAULT 'email',
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `assigned_to` int unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `modified` datetime NULL DEFAULT NULL,
  `closed` datetime NULL DEFAULT NULL,
  `external_system` varchar(40) NOT NULL DEFAULT '' COMMENT 'e.g. rsup, once synced to the support server',
  `external_id` varchar(100) NOT NULL DEFAULT '',
  `synced` datetime NULL DEFAULT NULL,
  `checked_out` int unsigned NOT NULL DEFAULT 0,
  `checked_out_time` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_external` (`external_system`, `external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ptickets_replies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int unsigned NOT NULL DEFAULT 0,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `body` mediumtext NOT NULL,
  `internal` tinyint NOT NULL DEFAULT 0 COMMENT '1 = staff-only note',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
