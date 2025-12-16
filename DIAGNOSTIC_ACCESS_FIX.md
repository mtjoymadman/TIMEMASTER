# Diagnostic File Access Issue - Current Status

## Problem
Diagnostic files (`diagnose_email_issue.php`, `email_diag.php`, `diag/email.php`) are redirecting to `employee_portal.php` instead of loading.

## Root Cause Analysis
The `.htaccess` file should prevent `index.php` from being called for existing files, but it's not working. This suggests:
1. The `.htaccess` file might not be deployed to the server
2. Server-level configuration might be overriding `.htaccess`
3. The file existence check might not be working as expected

## Current `.htaccess` Configuration
- First rule: Check if file exists → serve directly (no rewrites)
- Second rule: Exclude `/diag/` directory completely
- Admin rules: Only for non-existent files
- Final rule: Redirect to `index.php` only for non-existent files

## Files Created
1. `diag/email.php` - Diagnostic file in excluded subdirectory
2. `diag/test.php` - Minimal test file
3. `diag/.htaccess` - Disables rewrites in this directory
4. `email_diag.php` - Standalone diagnostic file

## Next Steps
1. Verify `.htaccess` file is on the server and being read
2. Check server error logs for rewrite rule processing
3. Test with `diag/test.php` first (minimal file)
4. If still not working, may need server-level configuration change

## Test URLs
- `https://time.redlionsalvage.net/diag/test.php` (minimal test)
- `https://time.redlionsalvage.net/diag/email.php` (full diagnostic)
- `https://time.redlionsalvage.net/email_diag.php` (standalone)

