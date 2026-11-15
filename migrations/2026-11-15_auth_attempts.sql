-- Login and signup rate limiting. Needed by installs created before 2026-11-15.
CREATE TABLE IF NOT EXISTS `auth_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(20) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `identifier` varchar(100) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action_ip_time` (`action`,`ip`,`attempted_at`),
  KEY `idx_action_identifier_time` (`action`,`identifier`,`attempted_at`)
) ENGINE=InnoDB;
