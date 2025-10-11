# User Activity Tracker (Dolibarr Module)

**Version:** 2.8.1
**Compatibility:** Dolibarr 14.0+ to 22.0.0+, PHP 7.4+
**Namespace/Dir:** `custom/useractivitytracker`

Track user activity across Dolibarr with a comprehensive dashboard, advanced analytics, CSV/XLS export, webhook alerts, anomaly detection, and retention cleanup.

## ✨ New in v2.8.1

- **📋 Canonical Table Names**: Migrated from `alt_user_activity` to `useractivitytracker_activity` and `useractivitytracker_log`
- **🔄 Automatic Migration**: Safe automatic data migration from legacy tables on first use
- **🏗️ UserActivityTables Helper**: DRY helper class for consistent table name usage across all code
- **🔧 Schema Improvements**: Updated schema with proper column names (`fk_user`, `element_id` instead of `userid`, `object_id`)
- **📦 Installation SQL**: New `install.sql` with canonical table definitions
- **⬆️ Upgrade Path**: Automatic migration via trigger with column intersection for safety
- **🎯 Entity Scoping**: All tables properly entity-scoped with `entity` column
- **📚 Migration Guide**: Comprehensive documentation for upgrade process

See [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) for details on upgrading from v2.8.0 or earlier.

## ✨ Features in v2.8.0

- **🔧 Unified Configuration Checks**: Standardized configuration checking across triggers and hooks for consistency
- **🛡️ Robust Error Handling**: Added try/catch blocks for all database operations with graceful degradation
- **🔄 Event Deduplication**: Prevents duplicate logging between triggers and hooks using a time-based cache
- **⚙️ Enhanced Configuration Coordination**: Proper coordination between MASTER_ENABLED and ENABLE_TRACKING switches
- **📝 Improved Logging**: Better error messages and logging for troubleshooting database issues
- **🎯 Race Condition Prevention**: Deduplication mechanism prevents race conditions between trigger and hook logging
- **💪 Graceful Degradation**: Module continues to function even if logging fails, with proper error logging

## ✨ Previous Features (v2.7.0)

- **🔐 Enhanced Security**: Parameterized queries, CSRF protection, strict input validation, entity scoping enforcement
- **🚀 Performance Boost**: New composite indexes (entity+datestamp, entity+userid+datestamp) for faster queries
- **📊 Server-Side Pagination**: Efficient handling of large datasets with configurable page sizes
- **🎛️ Master Tracking Switch**: Central `UAT_MASTER_ENABLED` gate across all triggers and hooks
- **⚙️ Idempotent Migrations**: Safe index creation on upgrade with no data loss
- **🔒 Privacy Controls**: Granular capture toggles for IP addresses and payloads (off/truncated/full)
- **⏱️ Retention Management**: Automated cron script for purging old records with dry-run mode
- **📏 Payload Caps**: Configurable `UAT_PAYLOAD_MAX_BYTES` with ellipsis indicators
- **🏗️ DX Improvements**: Constants for actions/severity, cleaner code organization
- **📋 Config Flags**: `MASTER_ENABLED`, `RETENTION_DAYS`, `PAYLOAD_MAX_BYTES`, `CAPTURE_IP`, `CAPTURE_PAYLOAD`

## ✨ Previous Features (v2.5.0)

- **🔧 Activity Tracking Fixed**: Resolved the core issue where no activity data was being tracked due to default configuration
- **🎛️ Master Tracking Switch**: Added global toggle to enable/disable all user activity tracking
- **📋 Enhanced Diagnostics**: Improved diagnostic checks to identify and resolve tracking configuration issues
- **⚙️ Better Default Settings**: Module now installs with tracking enabled by default for immediate functionality
- **🔍 Clearer Setup Interface**: Enhanced setup page with better organization and explanations
- **🚀 Trigger Logic Improvements**: More robust trigger system with proper default behavior

## ✨ Previous Features (v2.3.0)

- **🎨 Enhanced Dashboard UI**: Completely redesigned with modern card layouts, improved spacing, and better visual hierarchy
- **🧭 Advanced Navigation**: New sidebar navigation system with breadcrumbs and quick action buttons
- **📊 Timeline Visualization**: Interactive activity timeline with filtering and real-time updates
- **📄 Complete PDF Export**: Full PDF export functionality replacing previous placeholder implementation  
- **🔍 Smart Quick Filters**: Predefined date ranges and action type filters with intuitive interface
- **🎯 Drag & Drop Widgets**: Rearrangeable dashboard components with persistent layout saving
- **🎭 Enhanced Animations**: Smooth transitions, staggered loading effects, and improved user feedback
- **📱 Mobile First**: Completely responsive design with optimized mobile navigation
- **⚡ Performance Optimized**: Refactored JavaScript and CSS for faster loading and better efficiency
- **🌓 Enhanced Themes**: Improved dark/light mode with better color schemes and transitions

## ✨ Previous Features (v2.0.0)
- **🎨 Enhanced Modern UI**: Professional Font Awesome icons throughout all admin pages for improved visual appeal and intuitive navigation
- **📊 Interactive Charts**: Chart.js integration with doughnut, bar, and line charts for comprehensive data visualization
- **🔄 Real-Time Updates**: AJAX-powered live data refresh with configurable auto-refresh intervals
- **🌙 Dark Mode**: Full theme support with localStorage persistence and smooth transitions
- **📱 Mobile-First Design**: Responsive layout optimized for all device sizes with touch-friendly controls
- **👥 User Comparison**: Advanced tool to compare up to 4 users with side-by-side metrics and charts
- **🔥 Activity Heatmap**: GitHub-style heatmap visualization for activity patterns analysis
- **📈 Trend Analysis**: Comprehensive trend charts with automated insights and recommendations
- **⚙️ Dashboard Settings**: Customizable preferences for appearance, behavior, and data display
- **🔍 Enhanced Filters**: Advanced search panel with element type, severity, and IP filtering
- **📤 Modern Export**: Improved CSV/XLS export with better styling and PDF export foundation
- **🔐 Comprehensive Login/Logout Tracking**: New hooks system captures all login/logout events with detailed session context
- **🎯 Cross-Platform Activity Tracking**: Extended tracking capabilities across all Dolibarr modules and pages via hooks
- **🏗️ Hooks Architecture**: New `ActionsUserActivityTracker` class supplements triggers for comprehensive activity capture
- **🎭 Professional Icon System**: Consistent Font Awesome icon implementation across setup, dashboard, and analysis pages
- **📱 Enhanced Responsive Design**: Improved mobile experience with optimized touch controls and flexible layouts
- **🔄 Version 2.0.0 Upgrade**: Complete version harmonization across all components for consistent deployment
- **Dolibarr 22.0.0 Compatibility**: Full compatibility with Dolibarr 22.0.0 and modern PHP versions
- **Updated APIs**: Replaced deprecated `$conf->global` with modern `getDolGlobalString()` functions
- **Improved Installation**: Better table creation process with proper SQL file handling
- **Top Menu Integration**: Module now appears in the main menu bar for easy access
- **Advanced Search**: Filter activities by action, user, element type with smart search
- **Session Tracking**: Enhanced user session monitoring and context tracking
- **Anomaly Detection**: Built-in security monitoring for suspicious activities
- **Improved Webhooks**: Retry logic, HMAC signatures, better error handling
- **Better Performance**: Optimized queries and reduced payload sizes
- **Security Enhancements**: Sensitive data filtering and improved IP detection

## Features
- **Comprehensive Activity Tracking**: Captures all Dolibarr triggers plus enhanced hook-based tracking for login/logout events and page navigation
- **Modern Dashboard Interface**: Professional dashboard with charts, statistics, and real-time updates
- **Advanced Export Options**: CSV/XLS export with comprehensive filtering and date ranges
- Webhook push (optional) with built‑in “Test Webhook” button.
- **Smart Retention Management**: Automatic cleanup based on configurable retention periods
- **Flexible Permissions**: Read/export/admin permissions assignable to users and groups
- **Multi-Entity Support**: Full compatibility with Dolibarr's multi-entity architecture
- **Enhanced Login/Logout Tracking**: Dedicated hooks for complete session monitoring including failed login attempts
- **Cross-Platform Monitoring**: Tracks user activities across all Dolibarr modules and pages
- **Professional UI with Icons**: Intuitive Font Awesome icons throughout all admin interfaces
- **Responsive Design**: Mobile-optimized interface with touch-friendly controls
- **Security Features**: Anomaly detection, sensitive data filtering, and enhanced IP tracking

## Installation
1. Copy this folder to `htdocs/custom/useractivitytracker` (or `custom/useractivitytracker` in your setup)
2. In Dolibarr: **Home → Setup → Modules/Applications → Deploy/Scan** then enable **User Activity Tracker**
3. Configure module settings: **Activity Tracker → Settings** (or via **Setup → Modules → User Activity Tracker**)
4. Assign permissions to users/groups in **Home → Setup → Users & Groups**
5. Access the dashboard: **Activity Tracker → Dashboard** (main menu) or **Tools → User Activity**

### Quick Setup Guide
1. **Enable Module**: Find and activate "User Activity Tracker" in the modules list
2. **Configure Settings**: Set retention period, enable features, configure webhooks if needed
3. **Set Permissions**: Assign "Read activity dashboard", "Export activity", or "Administer module" to users
4. **Start Monitoring**: The module begins tracking immediately - visit the dashboard to see activity

## SQL
On enable, the module auto-creates the table `llx_alt_user_activity` (prefix-aware).

## Uninstall
Disable module → the table will **not** be dropped by default (safety). You can drop it manually if desired.

## Files Structure
- `core/modules/modUserActivityTracker.class.php` — module descriptor, rights, menus, constants
- `core/triggers/interface_99_modUserActivityTracker_Trigger.class.php` — universal activity logger
- `class/useractivity.class.php` — data access object with analytics methods
- `admin/useractivitytracker_dashboard.php` — main analytics dashboard
- `admin/useractivitytracker_setup.php` — configuration and settings page
- `admin/useractivitytracker_analysis.php` — advanced analysis and security monitoring
- `scripts/export.php` — CSV/XLS export endpoint with filtering
- `sql/llx_alt_user_activity.sql` — database table definition
- `langs/useractivitytracker.lang` — language strings (English)

## Configuration Options (v2.7)
- **Master Switch**: `USERACTIVITYTRACKER_MASTER_ENABLED` - Central gate to disable all tracking (default: 1)
- **Retention Period**: `USERACTIVITYTRACKER_RETENTION_DAYS` - How long to keep data (default: 365 days)
- **Payload Capture**: `USERACTIVITYTRACKER_CAPTURE_PAYLOAD` - off/truncated/full (default: full)
- **Payload Size Limit**: `USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES` - Max JSON size (default: 65536)
- **IP Capture**: `USERACTIVITYTRACKER_CAPTURE_IP` - Capture IP addresses (default: 1)
- **Session Tracking**: Enhanced tracking with user agent and request context
- **Sensitive Data Filtering**: Automatically filter passwords and tokens from logs
- **Webhook Integration**: Real-time notifications with HMAC signatures and retry logic
- **Anomaly Detection**: Automatic detection of suspicious activities and bulk operations

### Cron Job for Retention
Schedule automatic cleanup:
```bash
# Daily at 2 AM
0 2 * * * php /path/to/htdocs/custom/useractivitytracker/scripts/cron_retention.php

# Dry run first to preview
php cron_retention.php --dry-run
```

## Webhook Format
```json
{
  "action": "COMPANY_CREATE",
  "element_type": "societe", 
  "object_id": 123,
  "ref": "CUST001",
  "userid": 1,
  "username": "admin",
  "ip": "192.168.1.100",
  "entity": 1,
  "datestamp": "2024-01-15 10:30:45",
  "severity": "info",
  "session_id": "abc123..."
}
```

## Security Features (v2.7 Enhanced)
- **CSRF Protection**: All POST/AJAX endpoints validate security tokens
- **Parameterized Queries**: Strict casting and whitelisting in filter builders
- **Entity Scoping**: All queries enforce entity boundaries for multi-entity safety
- **Anomaly Detection**: Detects unusual login patterns and bulk operations
- **Sensitive Data Protection**: Filters passwords, tokens from activity logs
- **IP Tracking**: Enhanced IP detection with X-Forwarded-For support (configurable)
- **Session Monitoring**: Tracks user sessions and request context
- **Webhook Security**: HMAC-SHA256 signatures for webhook authentication

## Performance Considerations (v2.7 Enhanced)
- **New Indexes**: Composite indexes on (entity, datestamp) and (entity, userid, datestamp)
- **Server-Side Pagination**: Efficient handling of millions of records
- Automatic cleanup based on retention settings (cron script provided)
- Optimized database queries with proper indexing
- Payload size limits to prevent memory issues
- Efficient count queries with same WHERE clause as data queries
- Efficient data structures for large datasets
- Background cleanup during regular usage

## Troubleshooting
- **No activities showing**: Check if module is enabled and triggers are working
- **Webhook not working**: Verify cURL is available and URL is accessible
- **Performance issues**: Consider reducing retention period or enabling cleanup
- **Menu not visible**: Check user permissions and module configuration
- **Missing data**: Verify database table was created correctly

## License
MIT
