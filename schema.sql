CREATE TABLE employees (
    username VARCHAR(50) PRIMARY KEY,
    flag_auto_break TINYINT(1) DEFAULT 0,
    role VARCHAR(100) DEFAULT 'employee',
    suspended TINYINT(1) DEFAULT 0
);

CREATE TABLE time_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    clock_in DATETIME,
    clock_out DATETIME,
    FOREIGN KEY (username) REFERENCES employees(username)
);

CREATE TABLE breaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    time_record_id INT,
    break_in DATETIME,
    break_out DATETIME,
    break_time INT DEFAULT 0,
    FOREIGN KEY (time_record_id) REFERENCES time_records(id)
);

INSERT INTO employees (username, role) VALUES ('admin', 'admin,employee');