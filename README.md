# User Activity Tracker (Dolibarr Module)

**Version:** 1.0.0
**Compatibility:** Dolibarr 14+ (tested on 20–22), PHP 7.4+
**Namespace/Dir:** `custom/useractivitytracker`

Track user activity across Dolibarr with a comprehensive dashboard, advanced analytics, CSV/XLS export, webhook alerts, anomaly detection, and retention cleanup.

## ✨ New in v1.0.0
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

## Files
- `core/modules/modUserActivityTracker.class.php` — module descriptor, rights, menus, constants.
- `core/triggers/interface_99_modUserActivityTracker_Trigger.class.php` — universal logger.
- `class/useractivity.class.php` — DAO + helpers.
- `admin/useractivitytracker_setup.php` — settings (webhook URL, retention days, pattern detection toggles) + test webhook.
- `admin/useractivitytracker_dashboard.php` — analytics dashboard.
- `scripts/export.php` — CSV/XLS export endpoint (permission required).
- `sql/llx_alt_user_activity.sql` — install DDL.
- `sql/updates/alt_user_activity__*.sql` — future migrations.

## License
MIT
