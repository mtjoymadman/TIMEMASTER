-- Add suspended column to employees table in time.redlionsalvage.net database
USE salvageyard_grok;
ALTER TABLE employees ADD COLUMN suspended TINYINT(1) DEFAULT 0; 