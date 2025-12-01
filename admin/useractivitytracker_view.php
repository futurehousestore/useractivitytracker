<?php
/**
 * Activity Details Viewer
 * Path: custom/useractivitytracker/admin/useractivitytracker_view.php
 * Version: 3.0.0 — OroCommerce UI, entity scoping, payload viewer enhancements
 */

/* ---- Locate htdocs/main.inc.php (top-level, not inside a function!) ---- */
$dir  = __DIR__;
$main = null;
for ($i = 0; $i < 10; $i++) {
    $candidate = $dir . '/main.inc.php';
    if (is_file($candidate)) { $main = $candidate; break; }
    $dir = dirname($dir);
}
if (!$main) {
    // Fallbacks for common layouts
    $fallbacks = array('../../../main.inc.php', '../../../../main.inc.php', '../../main.inc.php');
    foreach ($fallbacks as $f) {
        $p = __DIR__ . '/' . $f;
        if (is_file($p)) { $main = $p; break; }
    }
}
if (!$main) {
    header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error');
    echo 'Fatal: Unable to locate Dolibarr main.inc.php from ' . __FILE__;
    exit;
}
require $main;

dol_include_once('/useractivitytracker/class/useractivity.class.php');

if (empty($user->rights->useractivitytracker->read)) accessforbidden();

$id = (int) GETPOST('id', 'int');
if ($id <= 0) {
    accessforbidden('Invalid activity ID');
}

$activity = new UserActivity($db);
if (!$activity->fetch($id)) {
    accessforbidden('Activity not found');
}

// Entity scoping
if (!empty($conf->multicompany->enabled) && (int) $activity->entity !== (int) $conf->entity) {
    accessforbidden('Activity does not belong to this entity');
}

// URLs for navigation
$dashboard_url = dol_buildpath('/useractivitytracker/admin/useractivitytracker_dashboard.php', 1);
$analysis_url  = dol_buildpath('/useractivitytracker/admin/useractivitytracker_analysis.php', 1);
$export_url    = dol_buildpath('/useractivitytracker/admin/useractivitytracker_export.php', 1);
$setup_url     = dol_buildpath('/useractivitytracker/admin/useractivitytracker_setup.php', 1);

$dt     = dol_stringtotime($activity->datestamp);
$day    = $dt ? dol_print_date($dt, '%Y-%m-%d') : '';
$day_hr = $dt ? dol_print_date($dt, 'dayhour') : '';

$sev_raw = strtolower($activity->severity ?: 'info');
$allowed_severity = array('info', 'notice', 'warning', 'error');
if (!in_array($sev_raw, $allowed_severity, true)) $sev_raw = 'info';
$sev_label = strtoupper($sev_raw);

// Severity class for badges
$sev_class = 'uat-severity-' . $sev_raw;

llxHeader('', 'Activity Details #'.$id);

/* ----------------- OroCommerce-Inspired UI CSS ----------------- */
print '<style>
:root {
    --uat-primary: #2563eb;
    --uat-primary-dark: #1d4ed8;
    --uat-secondary: #64748b;
    --uat-success: #10b981;
    --uat-warning: #f59e0b;
    --uat-danger: #ef4444;
    --uat-info: #06b6d4;
    --uat-bg: #f8fafc;
    --uat-surface: #ffffff;
    --uat-border: #e2e8f0;
    --uat-text: #1e293b;
    --uat-text-secondary: #64748b;
    --uat-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --uat-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
    --uat-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --uat-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
}

.uat-dark {
    --uat-bg: #0f172a;
    --uat-surface: #1e293b;
    --uat-border: #334155;
    --uat-text: #f1f5f9;
    --uat-text-secondary: #94a3b8;
}

.uat-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 1.5rem;
    background: var(--uat-bg);
    min-height: 100vh;
}

/* Header */
.uat-header {
    background: linear-gradient(135deg, var(--uat-primary) 0%, var(--uat-primary-dark) 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: var(--uat-shadow-lg);
}

.uat-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.uat-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.uat-header-title h1 {
    margin: 0;
    font-size: 1.875rem;
    font-weight: 700;
    color: white;
}

.uat-header-subtitle {
    font-size: 0.875rem;
    opacity: 0.9;
    margin-top: 0.25rem;
}

.uat-header-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.uat-header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.uat-header-meta {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.875rem;
    flex-wrap: wrap;
}

.uat-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Cards */
.uat-card {
    background: var(--uat-surface);
    border: 1px solid var(--uat-border);
    border-radius: 8px;
    box-shadow: var(--uat-shadow);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.uat-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--uat-border);
    background: linear-gradient(to bottom, var(--uat-surface) 0%, rgba(37, 99, 235, 0.02) 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.uat-card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--uat-text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.uat-card-title i {
    color: var(--uat-primary);
}

.uat-card-body {
    padding: 1.5rem;
}

.uat-card-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--uat-border);
    background: rgba(37, 99, 235, 0.02);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Tables */
.uat-table-wrapper {
    overflow-x: auto;
}

.uat-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.uat-table thead {
    background: linear-gradient(to bottom, rgba(37, 99, 235, 0.05) 0%, rgba(37, 99, 235, 0.02) 100%);
}

.uat-table th {
    padding: 0.75rem 0.9rem;
    text-align: left;
    font-weight: 600;
    color: var(--uat-text);
    border-bottom: 2px solid var(--uat-border);
    font-size: 0.813rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.uat-table td {
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid var(--uat-border);
    color: var(--uat-text);
}

/* Definition-style info table */
.uat-info-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.uat-info-table tr:nth-child(even) {
    background: rgba(0, 0, 0, 0.01);
}

.uat-info-table td {
    padding: 0.5rem 0.25rem;
}

.uat-info-label {
    width: 160px;
    font-weight: 600;
    color: var(--uat-text-secondary);
    white-space: nowrap;
}

/* Buttons */
.uat-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border: 1px solid transparent;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.uat-btn-primary {
    background: linear-gradient(135deg, var(--uat-primary) 0%, var(--uat-primary-dark) 100%);
    color: white;
    box-shadow: var(--uat-shadow);
}

.uat-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: var(--uat-shadow-md);
}

.uat-btn-secondary {
    background: var(--uat-surface);
    color: var(--uat-text);
    border-color: var(--uat-border);
}

.uat-btn-secondary:hover {
    background: var(--uat-bg);
}

.uat-btn-ghost {
    background: transparent;
    color: white;
    border-color: rgba(255, 255, 255, 0.4);
}

.uat-btn-ghost:hover {
    background: rgba(255, 255, 255, 0.08);
}

.uat-btn-sm {
    padding: 0.4rem 0.9rem;
    font-size: 0.813rem;
}

.uat-btn-icon {
    padding: 0.5rem;
    width: 36px;
    height: 36px;
    justify-content: center;
}

/* Badges */
.uat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
}

.uat-badge-success { background: rgba(16, 185, 129, 0.1); color: #059669; }
.uat-badge-danger  { background: rgba(239, 68, 68, 0.1);  color: #dc2626; }
.uat-badge-info    { background: rgba(37, 99, 235, 0.1);  color: #1d4ed8; }
.uat-badge-warning { background: rgba(245, 158, 11, 0.1); color: #d97706; }

/* Severity badges */
.uat-severity-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.uat-severity-info {
    background-color: #e3f2fd;
    color: #1976d2;
}
.uat-severity-notice {
    background-color: #f5f3ff;
    color: #6b21a8;
}
.uat-severity-warning {
    background-color: #fff7ed;
    color: #c05621;
}
.uat-severity-error {
    background-color: #fef2f2;
    color: #b91c1c;
}

/* Code block (payload) */
.uat-code-block {
    background: #0b1120;
    color: #e5e7eb;
    padding: 1rem;
    border-radius: 6px;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12px;
    overflow-x: auto;
    border: 1px solid #111827;
}

/* Info box */
.uat-info-box {
    border-radius: 8px;
    border: 1px dashed var(--uat-border);
    padding: 1rem 1.25rem;
    background: rgba(37, 99, 235, 0.02);
    font-size: 0.875rem;
    color: var(--uat-text-secondary);
}

.uat-info-box h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.95rem;
    color: var(--uat-text);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* Layout */
.uat-grid-2 {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(0, 1.5fr);
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .uat-grid-2 {
        grid-template-columns: 1fr;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .uat-container {
        padding: 1rem;
    }
    .uat-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    .uat-header-actions {
        width: 100%;
    }
    .uat-header-actions .uat-btn {
        flex-grow: 1;
        justify-content: center;
    }
}

/* Print */
@media print {
    .uat-header-actions,
    .uat-btn {
        display: none !important;
    }
    .uat-container {
        background: #ffffff;
        padding: 0;
    }
}
</style>';

/* ----------------- CONTENT ----------------- */

print '<div class="uat-container">';

/* Header */
print '<div class="uat-header">';
print '<div class="uat-header-content">';
print '<div class="uat-header-title">';
print '<div class="uat-header-icon"><i class="fas fa-search"></i></div>';
print '<div>';
print '<h1>Activity Details #'.(int)$activity->id.'</h1>';
print '<div class="uat-header-subtitle">Deep dive into a single event, including payload and related context</div>';
print '</div>';
print '</div>'; // title

print '<div class="uat-header-actions">';
print '<a href="'.dol_escape_htmltag($dashboard_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>';
print '<a href="'.dol_escape_htmltag($analysis_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-chart-area"></i><span>Analysis</span></a>';
print '<a href="'.dol_escape_htmltag($export_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-file-export"></i><span>Export</span></a>';
print '<a href="'.dol_escape_htmltag($setup_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-cogs"></i><span>Settings</span></a>';
print '<button type="button" id="themeToggle" class="uat-btn uat-btn-ghost uat-btn-icon"><i class="fas fa-moon"></i></button>';
print '</div>'; // actions
print '</div>'; // header-content

// Header meta
print '<div class="uat-header-meta">';
print '<div class="uat-meta-item"><i class="fas fa-calendar"></i><span>'.($day_hr ?: 'N/A').'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-user"></i><span>'.dol_escape_htmltag($activity->username ?: '(unknown)').'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-sitemap"></i><span>Entity: '.(int)$activity->entity.'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-shield-alt"></i>';
print '<span class="uat-severity-badge '.$sev_class.'">'.$sev_label.'</span>';
print '</div>';
print '</div>'; // header-meta

print '</div>'; // header

/* GRID: Activity Info + Payload/Related */
print '<div class="uat-grid-2">';

/* LEFT COLUMN: Activity info */
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-info-circle"></i> Activity Information</div>';
print '</div>';
print '<div class="uat-card-body">';

print '<table class="uat-info-table">';
print '<tr><td class="uat-info-label">ID</td><td>'.(int)$activity->id.'</td></tr>';
print '<tr><td class="uat-info-label">Date/Time</td><td>'.($day_hr ?: 'N/A').'</td></tr>';
print '<tr><td class="uat-info-label">Action</td><td><code>'.dol_escape_htmltag($activity->action).'</code></td></tr>';
print '<tr><td class="uat-info-label">User</td><td>'.dol_escape_htmltag($activity->username).' (ID: '.(int)$activity->userid.')</td></tr>';
print '<tr><td class="uat-info-label">Element Type</td><td>'.dol_escape_htmltag($activity->element_type ?: 'N/A').'</td></tr>';
print '<tr><td class="uat-info-label">Object ID</td><td>'.($activity->object_id ?: 'N/A').'</td></tr>';
print '<tr><td class="uat-info-label">Reference</td><td>'.dol_escape_htmltag($activity->ref ?: 'N/A').'</td></tr>';
print '<tr><td class="uat-info-label">IP Address</td><td>'.dol_escape_htmltag($activity->ip ?: 'N/A').'</td></tr>';
print '<tr><td class="uat-info-label">Severity</td><td><span class="uat-severity-badge '.$sev_class.'">'.$sev_label.'</span></td></tr>';
print '<tr><td class="uat-info-label">Entity</td><td>'.(int)$activity->entity.'</td></tr>';

if (!empty($activity->note)) {
    print '<tr><td class="uat-info-label">Note</td><td>'.dol_escape_htmltag($activity->note).'</td></tr>';
}

print '</table>';

print '<div style="margin-top:1rem;" class="uat-info-box">';
print '<h3><span>🧭</span> How to use this view</h3>';
print '<ul style="margin:0 0 0 1.1rem;padding:0;font-size:0.825rem;">';
print '<li>Use this page when analysing <strong>specific incidents</strong> such as suspicious logins or failed operations.</li>';
print '<li>Check the <strong>payload</strong> on the right to see raw parameters and context passed to triggers.</li>';
print '<li>Review <strong>related activities</strong> (same user ±1 hour) to reconstruct the full story.</li>';
print '</ul>';
print '</div>';

print '</div>'; // card-body

// Card footer actions
print '<div class="uat-card-footer">';
print '<a class="uat-btn uat-btn-secondary" href="'.dol_escape_htmltag($dashboard_url).'"><i class="fas fa-arrow-left"></i><span>Back to Dashboard</span></a>';

if (!empty($user->rights->useractivitytracker->export) && $day) {
    $export_params = 'format=csv&from='.$day.'&to='.$day;
    $export_link   = dol_buildpath('/useractivitytracker/scripts/export.php', 1).'?'.$export_params;
    print '<a class="uat-btn uat-btn-primary" href="'.dol_escape_htmltag($export_link).'"><i class="fas fa-file-export"></i><span>Export This Day</span></a>';
}

print '</div>'; // card-footer
print '</div>'; // LEFT card

/* RIGHT COLUMN: Payload + Related */
print '<div>';

/* Payload card */
if ($activity->payload) {
    $payload_data = json_decode($activity->payload, true);

    print '<div class="uat-card">';
    print '<div class="uat-card-header">';
    print '<div class="uat-card-title"><i class="fas fa-database"></i> Payload Data</div>';
    print '</div>';
    print '<div class="uat-card-body">';

    if (is_array($payload_data)) {
        print '<pre class="uat-code-block">';
        print htmlspecialchars(json_encode($payload_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        print '</pre>';
    } else {
        print '<div class="uat-info-box" style="margin-bottom:1rem;">';
        print '<h3><span>⚠️</span> Invalid JSON payload</h3>';
        print '<p style="margin:0;">The payload could not be decoded as JSON. Raw content is shown below.</p>';
        print '</div>';
        print '<pre class="uat-code-block">';
        print htmlspecialchars($activity->payload);
        print '</pre>';
    }

    print '</div>'; // card-body
    print '</div>'; // card
}

/* Related activities (same user ±1 hour) */
$related_activities = array();
if ($activity->userid) {
    $related_sql = "SELECT rowid, datestamp, action, element_type, ref, severity 
                    FROM ".$db->prefix()."useractivitytracker_activity 
                    WHERE userid = ".(int)$activity->userid." 
                    AND entity = ".(int)$conf->entity."
                    AND rowid != ".(int)$activity->id."
                    AND datestamp BETWEEN DATE_SUB('".$db->escape($activity->datestamp)."', INTERVAL 1 HOUR) 
                    AND DATE_ADD('".$db->escape($activity->datestamp)."', INTERVAL 1 HOUR)
                    ORDER BY datestamp DESC 
                    LIMIT 10";

    $related_res = $db->query($related_sql);
    if ($related_res) {
        while ($obj = $db->fetch_object($related_res)) {
            $related_activities[] = $obj;
        }
        $db->free($related_res);
    }
}

print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-history"></i> Related Activities (±1 hour)</div>';
print '</div>';
print '<div class="uat-card-body">';

if (!empty($related_activities)) {
    print '<div class="uat-table-wrapper">';
    print '<table class="uat-table">';
    print '<thead>';
    print '<tr>';
    print '<th>Time</th>';
    print '<th>Action</th>';
    print '<th>Element</th>';
    print '<th>Ref</th>';
    print '<th>Severity</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';

    foreach ($related_activities as $rel) {
        $rel_dt   = dol_stringtotime($rel->datestamp);
        $rel_time = $rel_dt ? dol_print_date($rel_dt, 'hour') : '';
        $rel_sev_raw = strtolower($rel->severity ?: 'info');
        if (!in_array($rel_sev_raw, $allowed_severity, true)) $rel_sev_raw = 'info';
        $rel_sev_class = 'uat-severity-' . $rel_sev_raw;
        $rel_sev_label = strtoupper($rel_sev_raw);

        print '<tr>';
        print '<td>'.$rel_time.'</td>';
        print '<td><a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int)$rel->rowid.'">'.dol_escape_htmltag($rel->action).'</a></td>';
        print '<td>'.dol_escape_htmltag($rel->element_type ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($rel->ref ?: 'N/A').'</td>';
        print '<td><span class="uat-severity-badge '.$rel_sev_class.'">'.$rel_sev_label.'</span></td>';
        print '</tr>';
    }

    print '</tbody>';
    print '</table>';
    print '</div>';
} else {
    print '<div class="uat-info-box">';
    print '<h3><span>📭</span> No related events in ±1 hour</h3>';
    print '<p style="margin:0;">This activity appears isolated in the selected time window for this user.</p>';
    print '</div>';
}

print '</div>'; // card-body
print '</div>'; // related card

print '</div>'; // RIGHT column

print '</div>'; // main grid
print '</div>'; // container

/* Theme toggle JS (same behaviour as other pages) */
print '<script>
(function(){
  "use strict";
  var themeBtn = document.getElementById("themeToggle");
  if (themeBtn) {
    themeBtn.addEventListener("click", function() {
      document.documentElement.classList.toggle("uat-dark");
      var icon = themeBtn.querySelector("i");
      if (icon) {
        icon.className = document.documentElement.classList.contains("uat-dark")
          ? "fas fa-sun"
          : "fas fa-moon";
      }
      try {
        localStorage.setItem("uat-dark", document.documentElement.classList.contains("uat-dark") ? "1" : "0");
      } catch(e) {}
    });
  }
  try {
    if (localStorage.getItem("uat-dark") === "1") {
      document.documentElement.classList.add("uat-dark");
      if (themeBtn) {
        var icon2 = themeBtn.querySelector("i");
        if (icon2) icon2.className = "fas fa-sun";
      }
    }
  } catch(e) {}
})();
</script>';

llxFooter();
