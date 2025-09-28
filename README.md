# User Activity Tracker (Dolibarr Module)

**Version:** 1.0.0
**Compatibility:** Dolibarr 14.0+ to 22.0.0+, PHP 7.4+
**Namespace/Dir:** `custom/useractivitytracker`

Track user activity across Dolibarr with a comprehensive dashboard, advanced analytics, CSV/XLS export, webhook alerts, anomaly detection, and retention cleanup.

## ✨ New in v1.0.0
- **Dolibarr 22.0.0 Compatibility**: Full compatibility with Dolibarr 22.0.0 and modern PHP versions
- **Updated APIs**: Replaced deprecated `$conf->global` with modern `getDolGlobalString()` functions
- **Improved Installation**: Better table creation process with proper SQL file handling
- **Top Menu Integration**: Module now appears in the main menu bar for easy access
- **Enhanced Dashboard**: Improved analytics with visual indicators and recent activity feed  
- **Advanced Search**: Filter activities by action, user, element type with smart search
- **Session Tracking**: Enhanced user session monitoring and context tracking
- **Anomaly Detection**: Built-in security monitoring for suspicious activities
- **Improved Webhooks**: Retry logic, HMAC signatures, better error handling
- **Better Performance**: Optimized queries and reduced payload sizes
- **Security Enhancements**: Sensitive data filtering and improved IP detection
- **Modern UI**: Updated interface with better organization and user experience

## Features
- Logs all Dolibarr triggers via a single trigger class (action name, element type, IDs, user, company, IP, and payload snapshot).
- Dashboard: Activity by Type, by User, and Timeline (daily buckets).
- CSV / XLS export with date filters.
- Webhook push (optional) with built‑in “Test Webhook” button.
- Retention auto‑cleanup (runs opportunistically on each dashboard/settings visit).
- Permissions: read/export/admin, assignable to groups.
- Multi‑entity compatible (stores `entity` column).

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

## Configuration Options
- **Retention Period**: Configure how long to keep activity data (default: 365 days)
- **Session Tracking**: Enhanced tracking with user agent and request context
- **Sensitive Data Filtering**: Automatically filter passwords and tokens from logs
- **Payload Size Limits**: Control maximum JSON payload size to prevent database issues
- **Webhook Integration**: Real-time notifications with HMAC signatures and retry logic
- **Anomaly Detection**: Automatic detection of suspicious activities and bulk operations

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

## Security Features
- **Anomaly Detection**: Detects unusual login patterns and bulk operations
- **Sensitive Data Protection**: Filters passwords, tokens from activity logs
- **IP Tracking**: Enhanced IP detection with X-Forwarded-For support
- **Session Monitoring**: Tracks user sessions and request context
- **Webhook Security**: HMAC-SHA256 signatures for webhook authentication

## Performance Considerations
- Automatic cleanup based on retention settings
- Optimized database queries with proper indexing
- Payload size limits to prevent memory issues
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
