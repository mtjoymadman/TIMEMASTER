# TIMEMASTER

A time tracking system for Red Lion Salvage employees.

## Current Status

### Completed Features
- **Core Time Tracking**
  - Clock in/out functionality
  - Break management (start/end breaks)
  - Real-time duration tracking
  - Auto-break system for shifts over 8 hours
  - Break duration tracking and notifications

- **Employee Interface**
  - Clean, modern dark theme UI
  - Current status display
  - Hours worked tracking
  - Time records view with search
  - External time tracking in popup modal
  - Role-based access control

- **Admin Interface**
  - Employee management (add, modify, delete, suspend)
  - Time record management
  - Holiday pay management
  - Weekly summary view
  - Clock out all employees feature
  - Role management

- **System Features**
  - Automatic FTP upload on Git commits
  - Email notifications for clock events
  - Role-based access control
  - Session management
  - Input validation and security measures

### Pending Tasks
1. **UI/UX Improvements**
   - [ ] Add loading indicators for actions
   - [ ] Implement error message animations
   - [ ] Add confirmation dialogs for important actions
   - [ ] Improve mobile responsiveness

2. **Functionality Enhancements**
   - [ ] Add bulk actions in admin interface
   - [ ] Implement time record export to CSV/Excel
   - [ ] Add custom break duration settings
   - [ ] Implement shift templates
   - [ ] Add overtime tracking and alerts

3. **Reporting Features**
   - [ ] Enhanced weekly/monthly reports
   - [ ] Custom report builder
   - [ ] Export reports to multiple formats
   - [ ] Automated report scheduling

4. **System Improvements**
   - [ ] Add database backup functionality
   - [ ] Implement system health monitoring
   - [ ] Add audit logging for admin actions
   - [ ] Improve error handling and recovery

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

- Added automatic FTP upload on Git commits
- Implemented external time tracking in popup modal
- Added "Clock Out All" feature for admins
- Enhanced break management system
- Improved UI with centered buttons and consistent styling
- Added weekly summary functionality
- Enhanced employee management interface
- Added holiday pay feature

## Security Notes

- System uses username-only authentication (no passwords)
- Role-based access control for sensitive functions
- Input validation and SQL injection prevention
- Session-based authentication

## Support

For support or questions, contact the system administrator.

