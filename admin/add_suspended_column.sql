-- Add suspended column to employees table
SET @dbname = 'salvageyard_grok';
SET @tablename = 'employees';
SET @columnname = 'suspended';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column already exists'",
  "ALTER TABLE employees ADD COLUMN suspended TINYINT(1) NOT NULL DEFAULT 0"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update any existing records to have suspended = 0 by default
UPDATE employees SET suspended = 0 WHERE suspended IS NULL; 