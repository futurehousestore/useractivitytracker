-- Migration script for v2.8.1: Migrate legacy alt_user_activity to useractivitytracker_activity
-- Note: This is optional - the PHP migration in the trigger is the recommended approach
-- as it automatically copies only intersecting columns and won't break if schemas differ.

-- Create new tables if they don't exist
-- (Module init will handle this, but this provides a pure-SQL path if needed)

-- Migration is safer in PHP via the trigger's migrateLegacy() method
-- which handles column intersection automatically
