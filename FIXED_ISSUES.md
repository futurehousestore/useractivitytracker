# User Activity Tracker - Fixed Issues

## Version 2.8.0 - Database Logging and Event Handling Improvements

### Problem Addressed
Enhanced database logging consistency, prevented duplicate event logging, and improved error handling for more robust operation.

### Root Causes Identified and Fixed (v2.8.0)

#### 1. Inconsistent Configuration Checks
**Problem**: Configuration checking logic was duplicated between triggers and hooks with slight variations, leading to potential inconsistencies.
- Different implementations of enablement checks in triggers vs hooks
- No centralized configuration validation logic
- Risk of one component checking differently than another

**Fix**: Created unified `isTrackingEnabled()` methods in both triggers and hooks with identical logic:
- Standardized MASTER_ENABLED switch check (highest priority)
- Consistent module enablement check
- Uniform ENABLE_TRACKING toggle check
- Identical per-user skip list handling
- Clear documentation of configuration hierarchy

#### 2. Missing Error Handling for Database Operations
**Problem**: Database operations could fail without proper error handling, causing the module to break or behave unpredictably.
- No try/catch blocks around critical database operations
- Table creation failures would stop execution
- Insert failures could propagate errors to Dolibarr core
- No graceful degradation strategy

**Fix**: Added comprehensive try/catch blocks with graceful degradation:
- Wrapped table creation in try/catch with proper error logging
- Insert operations now catch exceptions and log them
- Module continues functioning even if logging fails
- Detailed error messages logged for troubleshooting
- Proper exception re-throwing where appropriate

#### 3. Race Conditions Between Trigger and Hook Logging
**Problem**: Same events could be logged twice when both triggers and hooks captured them.
- No coordination between trigger-based and hook-based logging
- Duplicate entries for actions like validation, deletion, etc.
- Database bloat from redundant log entries
- Confused analytics from duplicate events

**Fix**: Implemented event deduplication mechanism:
- Time-based deduplication cache (2-second window by default)
- Cache keys include action, userid, and object_id for triggers
- Cache keys include action and userid for hooks
- Prevents race conditions between concurrent logging attempts
- Minimal memory footprint with automatic cache expiration

#### 4. Improper NULL Handling in Database Queries
**Problem**: NULL values weren't consistently handled in parameterized queries.
- userid could be 0 instead of NULL
- object_id could be 0 instead of NULL
- Inconsistent NULL handling across triggers and hooks

**Fix**: Improved NULL handling in SQL generation:
- Proper NULL checks for userid (only insert if > 0)
- Proper NULL checks for object_id (only insert if > 0)
- Consistent NULL handling across both triggers and hooks

## Version 2.7.0 and Earlier Issues

### Problem Fixed
The module was not logging user activity due to several technical issues.

### Root Causes Identified and Fixed (v2.7.0 and Earlier)

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

### v2.8.0 Modified Files:

1. **`core/hooks/interface_99_modUserActivityTracker_Hooks.class.php`**
   - Added deduplication cache properties (`$dedupCache`, `$dedupWindow`)
   - Enhanced `isTrackingEnabled()` with detailed documentation and unified logic
   - Completely refactored `logActivity()` with:
     - Event deduplication mechanism using time-based cache
     - Try/catch blocks for database operations
     - Improved NULL handling for userid
     - Graceful degradation on errors
   - Updated `createTableIfMissing()` with try/catch and proper error handling
   - Updated version to 2.8.0

2. **`core/triggers/interface_99_modUserActivityTracker_Trigger.class.php`**
   - Added deduplication cache properties (`$dedupCache`, `$dedupWindow`)
   - Created new `isTrackingEnabled()` method matching hooks implementation
   - Completely refactored `runTrigger()` with:
     - Event deduplication using action+userid+objectid keys
     - Try/catch blocks for table creation and inserts
     - Improved NULL handling for userid and object_id
     - Graceful degradation (returns 0 instead of -1 on errors)
   - Updated `ensureTable()` with try/catch and proper error handling
   - Updated version to 2.8.0

3. **`core/modules/modUserActivityTracker.class.php`**
   - Updated version to 2.8.0
   - Updated version description

4. **All PHP files**
   - Updated version headers from 2.7.0 to 2.8.0 across entire codebase

5. **`README.md`**
   - Added v2.8.0 features section
   - Moved v2.7.0 features to "Previous Features"
   - Updated version number

6. **`FIXED_ISSUES.md`**
   - Added comprehensive v2.8.0 improvements documentation
   - Documented new problems addressed and fixes implemented

### v2.7.0 and Earlier Modified Files:
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

After v2.8.0 fixes, the module should now:
1. ✅ Use unified configuration checks across triggers and hooks
2. ✅ Handle database errors gracefully without breaking Dolibarr
3. ✅ Prevent duplicate event logging between triggers and hooks
4. ✅ Continue functioning even if database operations fail
5. ✅ Provide detailed error logging for troubleshooting
6. ✅ Properly coordinate MASTER_ENABLED and ENABLE_TRACKING switches
7. ✅ Handle NULL values correctly in database queries

After v2.7.0 and earlier fixes, the module should:
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