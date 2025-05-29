-- Script to create the break_records table if it doesn't exist
-- or update it to have the correct column names

-- Check if break_records table exists
SELECT COUNT(*) INTO @break_records_exists 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'break_records';

-- Check if breaks table exists
SELECT COUNT(*) INTO @breaks_exists 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'breaks';

-- If break_records doesn't exist but breaks does, create break_records table from breaks
SET @sql = IF(@break_records_exists = 0 AND @breaks_exists = 1,
    'CREATE TABLE break_records LIKE breaks',
    'SELECT "break_records table already exists or breaks table does not exist" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- If we just created break_records table, rename the columns
SET @sql = IF(@break_records_exists = 0 AND @breaks_exists = 1,
    'ALTER TABLE break_records 
    CHANGE COLUMN time_record_id record_id INT(11) NOT NULL,
    CHANGE COLUMN break_in break_start DATETIME NOT NULL,
    CHANGE COLUMN break_out break_end DATETIME NULL DEFAULT NULL',
    'SELECT "No column renaming needed" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- If we just created and modified break_records, copy data from breaks
SET @sql = IF(@break_records_exists = 0 AND @breaks_exists = 1,
    'INSERT INTO break_records 
    SELECT id, time_record_id as record_id, break_in as break_start, 
    break_out as break_end, break_time, auto_added 
    FROM breaks',
    'SELECT "No data copy needed" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Display the current structure of break_records if it exists
SET @sql = IF(@break_records_exists = 1 OR (@break_records_exists = 0 AND @breaks_exists = 1),
    'DESCRIBE break_records',
    'SELECT "break_records table does not exist" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 