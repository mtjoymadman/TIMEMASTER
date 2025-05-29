-- Rename columns (will fail silently if columns don't exist)
ALTER TABLE break_records 
CHANGE COLUMN break_start break_in DATETIME NOT NULL,
CHANGE COLUMN break_end break_out DATETIME DEFAULT NULL,
CHANGE COLUMN record_id time_record_id INT NOT NULL;

-- Add auto_added column if it doesn't exist
ALTER TABLE break_records 
ADD COLUMN auto_added TINYINT(1) DEFAULT 0;

-- Add the foreign key constraint
ALTER TABLE break_records 
ADD CONSTRAINT fk_break_time_record 
FOREIGN KEY (time_record_id) 
REFERENCES time_records(id) 
ON DELETE CASCADE; 