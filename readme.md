# TIMEMASTER

A time tracking system for Red Lion Salvage employees.

## Features

- **Simple Login System**
  - No passwords required
  - Username-only authentication
  - Automatic role-based redirection (admin/employee)

- **Employee Features**
  - Clock in/out
  - Start/end breaks
  - View time records
  - View current status and hours worked
  - Search records by date
  - Switch to admin mode (if authorized)

- **Admin Features**
  - Manage employees (add, modify, delete, suspend)
  - View all employee records
  - Clock in/out employees
  - Manage breaks for employees
  - Add holiday pay
  - Generate reports
  - Switch to employee mode

- **Break Management**
  - Manual break tracking
  - Auto-break system for shifts over 8 hours
  - Break duration tracking
  - Break notifications

- **Role System**
  - Employee (default)
  - Admin
  - Baby Admin
  - Driver
  - Yardman
  - Office

## Database Schema

```sql
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

CREATE TABLE external_time_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    start_time DATETIME,
    end_time DATETIME,
    reason TEXT,
    FOREIGN KEY (username) REFERENCES employees(username)
);
```

## Setup

1. Create a MySQL database
2. Import the schema.sql file
3. Configure database connection in config.php
4. Set up email notifications in config.php (optional)
5. Ensure Python is installed for notification system
6. Set up web server (Apache/Nginx) with PHP support

## Configuration

Edit `config.php` to set up:
- Database connection
- Email notifications
- Time zone settings

## File Structure

```
/
├── admin/
│   ├── index.php
│   └── report.php
├── css/
│   └── styles.css
├── images/
│   └── logo.png
├── python/
│   └── send_notification.py
├── config.php
├── functions.php
├── index.php
└── login.php
```

## Recent Changes

- Added support for admins to clock in/out in employee mode
- Improved role checking for time-related functions
- Added external time records functionality
- Enhanced error handling and logging
- Improved UI with centered buttons and consistent styling
- Added weekly summary functionality
- Enhanced break management system
- Added holiday pay feature
- Improved employee management interface

## Security Notes

- System uses username-only authentication (no passwords)
- Role-based access control for sensitive functions
- Input validation and SQL injection prevention
- Session-based authentication

## Support

For support or questions, contact the system administrator.

