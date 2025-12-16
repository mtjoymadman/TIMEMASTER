-- IP Access Control Table for TIMEMASTER
-- Tracks IP addresses and their access status (whitelist system)

CREATE TABLE IF NOT EXISTS `ip_access_control` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
  `ip_range` VARCHAR(50) NULL COMMENT 'CIDR notation (e.g., 192.168.1.0/24) for range matching',
  `status` ENUM('pending', 'allowed', 'banned') DEFAULT 'pending' COMMENT 'pending = new IP, needs review; allowed = whitelisted; banned = blocked',
  `system` ENUM('grok', 'timemaster', 'both') DEFAULT 'both' COMMENT 'Which system(s) this IP applies to',
  `first_seen` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'First time this IP was seen',
  `last_seen` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last time this IP attempted access',
  `attempt_count` INT DEFAULT 1 COMMENT 'Number of access attempts',
  `allowed_by` VARCHAR(50) NULL COMMENT 'Username who approved/denied this IP',
  `notes` TEXT NULL COMMENT 'Admin notes about this IP',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_ip` (`ip_address`),
  INDEX `idx_status` (`status`),
  INDEX `idx_system` (`system`),
  INDEX `idx_ip_range` (`ip_range`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
