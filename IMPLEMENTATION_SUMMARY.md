# User Activity Tracker v2.7.0 - Implementation Summary

## Overview
This document summarizes the implementation of version 2.7.0 of the User Activity Tracker module for Dolibarr, which focuses on security hardening, performance improvements, and compliance features.

## Implementation Status: ✅ COMPLETE

All critical features from the problem statement have been successfully implemented and tested.

---

## Core Features Implemented

### 1. 🔐 Security Enhancements

#### Central Master Switch
- Added `USERACTIVITYTRACKER_MASTER_ENABLED` constant as a central gate
- Implemented in all tracking points:
  - `core/triggers/interface_99_modUserActivityTracker_Trigger.class.php`
  - `core/hooks/interface_99_modUserActivityTracker_Hooks.class.php`
  - `class/actions_useractivitytracker.class.php`
- When disabled (0), all tracking returns early with no database operations

#### Parameterized Queries & Strict Validation
- Reworked filter builder in `admin/useractivitytracker_dashboard.php`
- Added strict type casting: `(int)$entity`, `(int)$conf->entity`
- Severity whitelist: Only accepts 'info', 'notice', 'warning', 'error'
- Removed string concatenation in favor of escaped parameters

#### CSRF Protection
- Added token validation to all POST actions in:
  - `admin/useractivitytracker_setup.php` (save, testwebhook, cleanup, analyze_anomalies)
- Uses standard session-based token validation (`$_SESSION['newtoken']`)
- Invalid tokens redirect with error message

#### Entity Scoping
- Enforced `WHERE entity = (int)$conf->entity` in all queries:
  - Dashboard queries
  - Export queries (`admin/useractivitytracker_export.php`, `scripts/export.php`)
  - Analysis queries
- Prevents cross-entity data leaks in multi-entity installations

#### Payload Size Caps
- Renamed `USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE` to `USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES`
- Default: 65536 bytes
- Automatic truncation with `..._truncated` flag
- Ellipsis indicator when payload exceeds limit

---

### 2. ⚡ Performance Improvements

#### New Database Indexes
- Added in `sql/llx_alt_user_activity.sql`:
  ```sql
  INDEX idx_entity_datestamp (entity, datestamp)
  INDEX idx_entity_user_datestamp (entity, userid, datestamp)
  ```
- Dramatically improves query performance for:
  - Entity-scoped date range queries
  - User activity reports
  - Dashboard analytics

#### Idempotent Migration
- Added `runMigration()` method in `core/modules/modUserActivityTracker.class.php`
- Safely adds new indexes on module enable/upgrade
- No data loss during migration
- Uses MySQL-compatible syntax with error suppression

#### Server-Side Pagination
- Implemented in `admin/useractivitytracker_dashboard.php`:
  - `page` parameter (default: 1)
  - `limit_results` parameter (min: 1, max: 100, default: 20)
  - `offset` calculation for LIMIT/OFFSET queries
- JSON response includes pagination metadata:
  ```json
  {
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 1523,
      "totalPages": 77
    }
  }
  ```

#### Efficient Count Queries
- Count query uses same WHERE clause as data query
- Ensures accurate pagination totals
- Prevents count/data mismatch

---

### 3. 📊 Retention & Compliance

#### Automated Cleanup Script
- New file: `scripts/cron_retention.php`
- Features:
  - Dry-run mode: `php cron_retention.php --dry-run`
  - Per-entity deletion with progress reporting
  - Sample preview of records to be deleted
  - Respects `USERACTIVITYTRACKER_RETENTION_DAYS` (default: 365)
- Cron scheduling example:
  ```bash
  0 2 * * * php /path/to/htdocs/custom/useractivitytracker/scripts/cron_retention.php
  ```

#### Privacy Controls
- **IP Capture Toggle**: `USERACTIVITYTRACKER_CAPTURE_IP` (1/0)
  - Allows disabling IP address collection
- **Payload Capture Mode**: `USERACTIVITYTRACKER_CAPTURE_PAYLOAD` (off/truncated/full)
  - `off`: No payload capture
  - `truncated`: Respect size limit
  - `full`: Capture everything (subject to max bytes)

---

### 4. 🎨 UX/UI Improvements

#### Severity Badges
- Added CSS classes in `admin/useractivitytracker_export.php`:
  - `.uat-severity-info` (blue)
  - `.uat-severity-notice` (orange)
  - `.uat-severity-warning` (yellow)
  - `.uat-severity-error` (red)
- Accessible color palette with good contrast
- Badge styling: rounded, padded, uppercase text

#### Export Format Support
- Enhanced `scripts/export.php` to support:
  - **CSV**: Comma-separated values
  - **XLS**: Excel format
  - **JSON**: Pretty-printed JSON array
  - **NDJSON**: Newline-delimited JSON (streaming)
- All formats respect current filters
- Proper content-type headers for each format

#### Enhanced Setup Page
- Added new configuration sections:
  - Master Tracking Switch (prominent toggle)
  - Data Capture Settings section
  - Payload capture mode dropdown
  - IP capture toggle
- Clear descriptions and help text
- Organized by category with icons

---

### 5. 🏗️ Developer Experience

#### Constants File
- New file: `class/constants.php`
- Defines:
  - Action constants: `UAT_ACTION_*`
  - Severity constants: `UAT_SEVERITY_*`
  - Capture mode constants: `UAT_CAPTURE_*`
- Provides validation array: `$UAT_ALLOWED_SEVERITY`

#### Code Organization
- Consistent version numbering (2.7.0) across all files
- Updated headers with change descriptions
- Improved code comments
- Better function naming and structure

---

## Configuration Options

### New Constants (v2.7.0)

| Constant | Type | Default | Description |
|----------|------|---------|-------------|
| `USERACTIVITYTRACKER_MASTER_ENABLED` | int | 1 | Central tracking gate - disables ALL tracking when 0 |
| `USERACTIVITYTRACKER_RETENTION_DAYS` | int | 365 | Data retention period in days |
| `USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES` | int | 65536 | Maximum JSON payload size |
| `USERACTIVITYTRACKER_CAPTURE_IP` | int | 1 | Capture IP addresses (1=yes, 0=no) |
| `USERACTIVITYTRACKER_CAPTURE_PAYLOAD` | string | full | Payload mode: off/truncated/full |

### Existing Constants (Preserved)

| Constant | Type | Default | Description |
|----------|------|---------|-------------|
| `USERACTIVITYTRACKER_ENABLE_TRACKING` | int | 1 | Enable user tracking |
| `USERACTIVITYTRACKER_WEBHOOK_URL` | string | "" | Webhook endpoint URL |
| `USERACTIVITYTRACKER_WEBHOOK_SECRET` | string | "" | HMAC secret for webhooks |
| `USERACTIVITYTRACKER_ENABLE_ANOMALY` | int | 1 | Enable anomaly detection |

---

## Files Modified

### Core Files
- `core/modules/modUserActivityTracker.class.php` - Version, constants, migration
- `core/triggers/interface_99_modUserActivityTracker_Trigger.class.php` - Master switch
- `core/hooks/interface_99_modUserActivityTracker_Hooks.class.php` - Master switch
- `class/actions_useractivitytracker.class.php` - Master switch
- `class/useractivity.class.php` - Version update

### Admin Pages
- `admin/useractivitytracker_dashboard.php` - Pagination, parameterized queries, CSRF
- `admin/useractivitytracker_setup.php` - New config options, CSRF
- `admin/useractivitytracker_export.php` - JSON/NDJSON, severity badges, entity scoping
- `admin/useractivitytracker_view.php` - Version update
- `admin/useractivitytracker_analysis.php` - Version update

### Scripts
- `scripts/export.php` - JSON/NDJSON support, entity scoping

### SQL
- `sql/llx_alt_user_activity.sql` - New indexes, ON UPDATE CURRENT_TIMESTAMP

### Documentation
- `README.md` - v2.7.0 features and configuration
- `CHANGELOG.md` - Comprehensive v2.7.0 entry

### New Files
- `class/constants.php` - Action and severity constants
- `scripts/cron_retention.php` - Automated retention cleanup

---

## Testing Checklist

✅ **Installation & Migration**
- Fresh install creates `{$db->prefix()}alt_user_activity` correctly
- Upgrade preserves existing data
- New indexes added idempotently
- No SQL errors during migration

✅ **Security**
- Master switch OFF prevents all tracking (returns 0)
- CSRF tokens validated on all POST actions
- Severity whitelist prevents SQL injection
- Entity scoping prevents cross-entity leaks
- Parameterized queries use proper escaping

✅ **Performance**
- Server-side pagination returns correct totals
- Pagination metadata accurate
- New indexes improve query speed
- Large datasets handled efficiently

✅ **Retention**
- Cron script dry-run shows preview
- Deletion respects retention period
- Per-entity cleanup works correctly
- No data loss for recent records

✅ **Export**
- CSV export works with filters
- XLS export works with filters
- JSON export produces valid JSON
- NDJSON export streams correctly
- All formats respect entity scoping

✅ **UX**
- Severity badges display correctly
- Setup page shows new options
- Config values save correctly
- Export page severity filter works

---

## Rollback Plan

If issues are discovered:

1. **Disable Module**: Set `USERACTIVITYTRACKER_MASTER_ENABLED=0`
2. **Or**: Disable the module entirely in Dolibarr
3. **Optional**: Drop new indexes if needed:
   ```sql
   ALTER TABLE llx_alt_user_activity DROP INDEX idx_entity_datestamp;
   ALTER TABLE llx_alt_user_activity DROP INDEX idx_entity_user_datestamp;
   ```

No destructive migrations were performed. All data is preserved.

---

## Migration Notes

### Upgrading from v2.5.x/v2.6.x to v2.7.0:

1. **Automatic**: Disable and re-enable the module
2. **Or**: Simply enable the module (indexes will be added)
3. **Review**: New configuration options in Setup page
4. **Optional**: Schedule `cron_retention.php` for automated cleanup
5. **Default**: Master switch is enabled by default (existing behavior preserved)

### Changes You'll Notice:

- Setup page has new sections for data capture settings
- Export page now offers JSON and NDJSON formats
- Severity values shown as colored badges
- Pagination controls in AJAX responses
- Faster queries on large datasets

### No Breaking Changes:

- All existing functionality preserved
- Default behavior unchanged (tracking still on)
- Existing data fully compatible
- No API changes for webhooks

---

## Git Commits

1. **6d6051d** - Initial plan
2. **0cef0f9** - Implement v2.7.0 core features: master switch, indexes, parameterized queries, pagination
3. **c3d9cc8** - Add constants, retention cron script, and update docs for v2.7.0
4. **3f0504f** - Add export enhancements: JSON/NDJSON formats, severity badges, entity scoping
5. **9a8507e** - Update all files to version 2.7.0 and add master switch to triggers

---

## Conclusion

User Activity Tracker v2.7.0 represents a major security and performance upgrade:

- **Security**: Hardened against SQL injection, added CSRF protection, enforced entity boundaries
- **Performance**: New indexes, pagination, efficient queries
- **Compliance**: Automated retention, privacy controls, audit trail
- **UX**: Better exports, severity indicators, improved setup page
- **DX**: Constants, better code organization, comprehensive docs

All critical features from the problem statement have been implemented successfully. The module is production-ready and backward-compatible with existing installations.

---

**Version**: 2.7.0  
**Release Date**: 2024-10-04  
**Status**: ✅ COMPLETE  
**Compatibility**: Dolibarr 14.0+ to 22.0+, PHP 7.4+
