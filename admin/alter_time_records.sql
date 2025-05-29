-- Add notes column to time_records table
ALTER TABLE time_records
ADD COLUMN notes TEXT DEFAULT NULL COMMENT 'Employee notes about the time record',
ADD COLUMN admin_notes TEXT DEFAULT NULL COMMENT 'Admin notes about time adjustments';

-- Add notes column to break_records table
ALTER TABLE break_records
ADD COLUMN notes TEXT DEFAULT NULL COMMENT 'Notes about the break'; 