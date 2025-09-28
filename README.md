# User Activity Tracker (Dolibarr Module)

**Version:** 2025-09-27.beta-1
**Compatibility:** Dolibarr 14+ (tested on 20–22), PHP 8.1+
**Namespace/Dir:** `custom/useractivitytracker`

Track user activity across Dolibarr with a dashboard, CSV/XLS export, webhook alerts, and retention cleanup.

## Features
- Logs all Dolibarr triggers via a single trigger class (action name, element type, IDs, user, company, IP, and payload snapshot).
- Dashboard: Activity by Type, by User, and Timeline (daily buckets).
- CSV / XLS export with date filters.
- Webhook push (optional) with built‑in “Test Webhook” button.
- Retention auto‑cleanup (runs opportunistically on each dashboard/settings visit).
- Permissions: read/export/admin, assignable to groups.
- Multi‑entity compatible (stores `entity` column).

## Install
1. Copy this folder to `htdocs/custom/useractivitytracker` (or `custom/useractivitytracker` in your setup).
2. In Dolibarr: **Home → Setup → Modules/Applications → Deploy/Scan** then enable **User Activity Tracker**.
3. Visit **Setup → Modules → User Activity Tracker** to configure settings and permissions.
4. Open **Tools → User Activity → Dashboard** to view analytics.

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
