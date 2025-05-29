-- TIMEMASTER Database Schema

-- Drop tables if they exist (for clean setup)
DROP TABLE IF EXISTS breaks;
DROP TABLE IF EXISTS time_records;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS activity_log;

-- Create employees table
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    role VARCHAR(255) NOT NULL DEFAULT 'employee',
    flag_auto_break TINYINT(1) NOT NULL DEFAULT 0,
    suspended TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create time_records table
CREATE TABLE time_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    clock_in DATETIME NOT NULL,
    clock_out DATETIME NULL,
    FOREIGN KEY (username) REFERENCES employees(username) ON DELETE CASCADE
);

-- Create breaks table
CREATE TABLE breaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    time_record_id INT NOT NULL,
    break_in DATETIME NOT NULL,
    break_out DATETIME NULL,
    break_time INT NULL,
    FOREIGN KEY (time_record_id) REFERENCES time_records(id) ON DELETE CASCADE
);

-- Create activity_log table
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add some default employees
INSERT INTO employees (username, role, flag_auto_break) VALUES
('admin', 'admin,employee', 0),
('employee1', 'employee', 1),
('employee2', 'employee', 0),
('manager', 'admin,employee', 0); 