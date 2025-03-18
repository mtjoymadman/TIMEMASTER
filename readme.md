# TIMEMASTER

A time clock system for managing up to 50 employees, hosted at `time.redlionsalvage.net`.

## Permanent Credentials
- **MySQL Host:** `localhost`
- **MySQL User:** `salvageyard_time.redlionsalvage.net`
- **MySQL Password:** `7361dead`
- **MySQL DB Name:** `salvageyard_time` (Version 8.0)
- **SMTP Server:** `mail.supremecenter.com`
- **TIMEMASTER Email:** `time@time.redlionsalvage.net`
- **Email Password:** `7361dead`
- **Email Access:** `webmail.supremecluster.com`

## File Structure

TIMEMASTER/
├── admin/              # Admin interface
│   ├── index.php      # Admin dashboard
│   └── report.php     # Reporting page
├── css/
│   └── styles.css     # Dark theme styling
├── js/
│   └── script.js      # Client-side JavaScript
├── python/
│   └── email_report.py # Email reporting script
├── index.php          # Employee interface
├── config.php         # Configuration file
├── functions.php      # Shared functions
├── README.md          # This file
└── schema.sql         # Database schema

## Setup
1. Copy all files to `C:/TIMEMASTER/` locally.
2. Upload to shared server under `time.redlionsalvage.net`.
3. Import `schema.sql` into MySQL database `salvageyard_time`.
4. Ensure PHP, Python, and MySQL 8.0 are installed on the server.
5. Configure server to run Python scripts (e.g., via cron for scheduled reports).

## Features
- Clock in/out, break in/out for employees.
- Admin can add/modify/delete employees.
- Responsive dark theme for mobile/tablet/PC.
- Custom reporting (daily, weekly, monthly, all-time) via email or print.
- Auto 30-min break for >8-hour shifts if flag set.
- No passwords, username-only access.
- Local copy at `C:/TIMEMASTER/` editable via Cursor.

## URLs
- Employee: `time.redlionsalvage.net`
- Admin: `time.redlionsalvage.net/admin`

## Recent Changes
- Initial creation: March 17, 2025.

