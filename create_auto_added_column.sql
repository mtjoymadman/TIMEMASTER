-- Add auto_added column to breaks table
ALTER TABLE breaks ADD COLUMN auto_added TINYINT(1) DEFAULT 0;

-- Update existing breaks to mark automatic ones
UPDATE breaks b
JOIN time_records t ON b.time_record_id = t.id
SET b.auto_added = 1
WHERE b.break_time = 30 
AND b.break_out IS NOT NULL
AND TIMESTAMPDIFF(HOUR, t.clock_in, t.clock_out) >= 8; 