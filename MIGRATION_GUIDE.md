# Migration Guide - v2.8.1 Table Canonicalization

## Overview

Version 2.8.1 introduces canonical table names for the User Activity Tracker module:

- **Old (legacy):** `llx_alt_user_activity`
- **New (canonical):** 
  - `llx_useractivitytracker_activity` (primary event store)
  - `llx_useractivitytracker_log` (optional raw page-hit log)

## Migration Strategy

### Automatic Migration

The migration happens automatically via the trigger on first execution after upgrade:

1. The new canonical tables are created if they don't exist
2. Data from the legacy `llx_alt_user_activity` table is copied to `llx_useractivitytracker_activity`
3. Only intersecting columns are copied (safe for schema differences)
4. The legacy table is preserved for safety (can be manually dropped after verification)

### Manual Verification

After upgrading to v2.8.1:

1. **Check new tables exist:**
   ```sql
   SHOW TABLES LIKE 'llx_useractivitytracker_%';
   ```

2. **Verify data migration:**
   ```sql
   SELECT COUNT(*) FROM llx_useractivitytracker_activity;
   SELECT COUNT(*) FROM llx_alt_user_activity; -- Legacy table (if exists)
   ```

3. **Test dashboard:**
   - Navigate to: User Activity Tracker → Dashboard
   - Verify statistics are displayed correctly
   - Check that recent activities show up

### Cleanup (Optional)

After verifying the migration is successful, you can optionally drop the legacy table:

```sql
DROP TABLE IF EXISTS llx_alt_user_activity;
```

**Warning:** Only do this after confirming all data has migrated correctly.

## What Changed

### Database Schema

#### New Primary Table: `llx_useractivitytracker_activity`

```sql
CREATE TABLE llx_useractivitytracker_activity (
  rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  datestamp DATETIME NOT NULL,
  fk_user INTEGER NULL,              -- Renamed from userid
  username VARCHAR(128) NULL,
  action VARCHAR(64) NOT NULL,
  element_type VARCHAR(64) NULL,
  element_id INTEGER NULL,           -- Renamed from object_id
  ref VARCHAR(255) NULL,
  severity ENUM('info','notice','warning','error') NOT NULL DEFAULT 'info',
  ip VARCHAR(64) NULL,
  ua VARCHAR(255) NULL,              -- User agent (was payload)
  uri TEXT NULL,                     -- Request URI
  kpi1 BIGINT NULL,
  kpi2 BIGINT NULL,
  note TEXT NULL,
  entity INTEGER NOT NULL DEFAULT 1,
  extraparams TEXT NULL,
  KEY idx_entity_date (entity, datestamp),
  KEY idx_action (action),
  KEY idx_user (username),
  KEY idx_element (element_type, element_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### New Log Table: `llx_useractivitytracker_log`

```sql
CREATE TABLE llx_useractivitytracker_log (
  rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  datestamp DATETIME NOT NULL,
  fk_user INTEGER NULL,
  username VARCHAR(128) NULL,
  uri TEXT NULL,
  ip VARCHAR(64) NULL,
  ua VARCHAR(255) NULL,
  entity INTEGER NOT NULL DEFAULT 1,
  KEY idx_entity_date (entity, datestamp),
  KEY idx_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Code Changes

All PHP code now uses the `UserActivityTables` helper class:

```php
require_once DOL_DOCUMENT_ROOT.'/custom/useractivitytracker/class/UserActivityTables.php';

// Get table names
$activityTable = UserActivityTables::activity($db);
$logTable = UserActivityTables::log($db);
```

### Files Updated

- ✅ `class/UserActivityTables.php` - Helper for DRY table names
- ✅ `core/triggers/interface_99_modUserActivityTracker_Trigger.class.php` - Migration logic
- ✅ `core/hooks/interface_99_modUserActivityTracker_Hooks.class.php` - Simplified log table
- ✅ `admin/useractivitytracker_dashboard.php` - All queries updated
- ✅ `admin/useractivitytracker_analysis.php` - All queries updated
- ✅ `admin/useractivitytracker_view.php` - All queries updated
- ✅ `admin/useractivitytracker_export.php` - All queries updated
- ✅ `admin/useractivitytracker_setup.php` - All queries updated
- ✅ `scripts/cron_retention.php` - Cleanup script updated
- ✅ `scripts/tracktime.php` - Timing script updated
- ✅ `scripts/export.php` - Export script updated
- ✅ `class/useractivity.class.php` - DAO updated
- ✅ `core/modules/modUserActivityTracker.class.php` - Module descriptor updated
- ✅ `sql/install.sql` - New installation DDL
- ✅ `sql/upgrade/2.8.1_migrate_tables.sql` - Upgrade SQL (optional)

## Troubleshooting

### Dashboard Shows No Data

1. Check that the new table exists and has data
2. Verify entity scoping is correct
3. Check Dolibarr error logs

### Migration Didn't Run

The migration runs automatically on first trigger execution. To manually trigger:
1. Visit any Dolibarr page (this fires the trigger)
2. Check the new table for data

### Old Table Still Referenced

All code should now use the new canonical names. If you see references to `alt_user_activity` in error logs:
1. Clear Dolibarr cache
2. Disable and re-enable the module
3. Check for any custom modifications

## Benefits

1. **Namespace clarity:** Table names now clearly belong to the module
2. **Multi-entity support:** All tables properly entity-scoped
3. **Future-proof:** Canonical naming makes module identification easier
4. **Maintainability:** Single source of truth via `UserActivityTables` helper
5. **Safety:** Migration preserves legacy data

## Support

For issues or questions:
- Check Dolibarr logs: `documents/dolibarr.log`
- Module logs: Look for "User Activity Tracker" entries
- GitHub issues: https://github.com/futurehousestore/useractivitytracker/issues
