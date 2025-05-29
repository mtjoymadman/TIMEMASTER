# TIMEMASTER - Time Tracking System

TIMEMASTER is a comprehensive time tracking system for Red Lion Salvage employees, allowing clock in/out functionality, break management, and detailed time reporting.

## Important Timezone Information

**TIMEMASTER is hardcoded to use Eastern Time (America/New_York) exclusively.**

### Timezone Configuration Details:

- All dates and times are displayed in Eastern Time (America/New_York)
- The application enforces this timezone in every PHP file to override the server's default timezone
- Database connections use Eastern Time settings
- No other timezone is supported or allowed in this application
- Server is located in a different timezone, so every PHP file must explicitly set the timezone to avoid reverting to UTC

### Implementation Notes:

- Every PHP file includes: `date_default_timezone_set('America/New_York');`
- Database connection uses: `SET time_zone = '-04:00';`
- All DateTime objects use: `new DateTime($time, new DateTimeZone('America/New_York'));`
- No timezone conversions are performed; everything stays in Eastern Time

## Features

- Employee clock in/out
- Break management
- Real-time duration tracking
- Administrative time editing
- Reporting capabilities
- Employee management

## Installation

1. Upload all files to your web server
2. Import the database schema (see database_schema.sql)
3. Update the config.php file with your database credentials
4. Ensure PHP timezone functions are properly configured for America/New_York

## Configuration

Edit the config.php file to set your:
- Database connection details
- SMTP settings for email notifications
- Additional application settings

## Security

Access is restricted by role-based permissions:
- Regular employees can only manage their own time
- Administrators can manage all employees and time records

## License

Proprietary software for Red Lion Salvage. Unauthorized use is prohibited.

## Development Guidelines

### IMPORTANT: Read Before Making Any Changes

1. **Database Structure**
   - The database uses specific table and column names that must not be changed
   - Break records use the table name `break_records` 
   - Column names are `time_record_id`, `break_in`, and `break_out`
   - Never attempt to rename database columns or tables

2. **Conservative Development Approach**
   - This is a production system - always be conservative with changes
   - Fix only what is broken, using the exact same coding patterns
   - Do not introduce new approaches or patterns 
   - Do not refactor existing code unless absolutely necessary

3. **Making Changes**
   - When fixing an issue, identify the exact error and fix only that specific problem
   - Test changes thoroughly before deploying
   - Do not modify multiple files unnecessarily
   - Keep changes minimal and focused on the specific issue

4. **Timezone Handling**
   - All times must be handled in Eastern Time (America/New_York)
   - Do not introduce any timezone conversions
   - Every new PHP file must include: `date_default_timezone_set('America/New_York');`

5. **Backward Compatibility**
   - All changes must maintain backward compatibility with existing data
   - Be extremely careful when modifying SQL queries
   - Maintain existing function signatures and return values

Following these guidelines will ensure the stability of the TIMEMASTER system and prevent service disruptions for the business. 