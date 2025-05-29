-- Create break_records table if it doesn't exist
CREATE TABLE IF NOT EXISTS `break_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `time_record_id` int(11) NOT NULL COMMENT 'References time_records.id',
  `break_in` datetime NOT NULL,
  `break_out` datetime DEFAULT NULL,
  `break_time` int(11) DEFAULT NULL COMMENT 'Break duration in minutes',
  `auto_added` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `time_record_id` (`time_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key if needed (optional)
-- ALTER TABLE `break_records` ADD CONSTRAINT `fk_break_time_record` FOREIGN KEY (`time_record_id`) REFERENCES `time_records` (`id`) ON DELETE CASCADE; 