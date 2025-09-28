# User Activity Tracker - Fixed Issues

## Problem Fixed
The module was not logging user activity due to several technical issues.

## Root Causes Identified and Fixed

### 1. SQL Column Name Mismatch
**Problem**: The hooks class was using incorrect SQL column names that didn't match the actual database table schema.
- Used: `datec`, `fk_user`, `fk_object`, `session_id`  
- Actual: `datestamp`, `userid`, `object_id`, (no session_id column)

**Fix**: Updated the SQL queries in `core/hooks/interface_99_modUserActivityTracker_Hooks.class.php` to use the correct column names matching the schema defined in `sql/llx_alt_user_activity.sql`.

### 2. Missing Global Tracking Toggle
**Problem**: The hooks were not consistently checking the global tracking configuration setting.
- Some methods checked only module enablement but not the tracking toggle
- `USERACTIVITYTRACKER_ENABLE_TRACKING` constant was not being respected

**Fix**: Added `getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)` checks to all hook methods and improved the trigger logic.

### 3. Inconsistent Module Enablement Checks
**Problem**: The trigger class was missing the module enablement check while hooks had it.

**Fix**: Added `if (empty($conf->useractivitytracker->enabled)) return 0;` check to the trigger's `runTrigger` method.

### 4. Missing Table Creation Fallback
**Problem**: If the database table was missing, the module would fail silently without creating it.

**Fix**: Added `createTableIfMissing()` method to the hooks class with automatic table creation and retry logic.

## Technical Changes Made

### Modified Files:
1. `core/hooks/interface_99_modUserActivityTracker_Hooks.class.php`
   - Fixed SQL column names in `logActivity()` method
   - Added global tracking checks to all hook methods
   - Added `createTableIfMissing()` method with proper error handling
   - Enhanced error logging with table existence checking

2. `core/triggers/interface_99_modUserActivityTracker_Trigger.class.php`
   - Added module enablement check at the start of `runTrigger()`

## Verification
The fixes have been verified with:
- PHP syntax checking (no errors)
- Unit test simulation showing correct SQL generation
- Configuration verification script confirming all fixes are in place
- Column name mapping confirmed against actual database schema

## Expected Result
After these fixes, the module should now:
1. ✅ Properly log user activities to the database
2. ✅ Respect the global tracking toggle setting
3. ✅ Handle missing database tables gracefully
4. ✅ Work consistently for both trigger-based and hook-based logging
5. ✅ Provide proper error logging for debugging

## Testing Instructions
1. Enable the "User Activity Tracker" module in Dolibarr
2. Go to "Activity Tracker → Settings" and ensure "Enable user tracking" is checked
3. Perform various actions (login/logout, create/edit records, navigate pages)
4. Check "Activity Tracker → Dashboard" to verify activities are being logged

The logging should now work properly for:
- User login/logout events (via hooks)
- Database record changes (via triggers)  
- Page navigation (via hooks)
- User actions like validate/confirm/delete (via hooks)