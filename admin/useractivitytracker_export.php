<?php
/**
 * Export Data page
 * Path: custom/useractivitytracker/admin/useractivitytracker_export.php
 * Version: 3.0.0 — OroCommerce UI, entity scoping, strict validation, severity badges
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

if (empty($user->rights->useractivitytracker->export)) accessforbidden();

$langs->load('admin');
$langs->load('other');

$action          = GETPOST('action','alpha');
$from            = trim(GETPOST('from','alphanohtml'));
$to              = trim(GETPOST('to','alphanohtml'));
$search_action   = trim(GETPOST('search_action','alphanohtml'));
$search_user     = trim(GETPOST('search_user','alphanohtml'));
$search_element  = trim(GETPOST('search_element','alphanohtml'));
$severity_filter = trim(GETPOST('severity_filter','alphanohtml'));

// Validate severity
$allowed_severity = array('info', 'notice', 'warning', 'error');
if ($severity_filter !== '' && !in_array($severity_filter, $allowed_severity, true)) {
    $severity_filter = '';
}

if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if (empty($to))   $to   = dol_print_date(dol_now(), '%Y-%m-%d');

// Build export URLs
$export_base   = dol_buildpath('/useractivitytracker/scripts/export.php', 1);
$export_params = '&from='.urlencode($from).'&to='.urlencode($to)
    .'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user)
    .'&search_element='.urlencode($search_element).'&severity_filter='.urlencode($severity_filter);

// Preview query (20 rows max)
$entity = (int)$conf->entity;
$cond = " WHERE entity=" . $entity
      . " AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

if ($search_action !== '') {
    $cond .= " AND action LIKE '%".$db->escape($search_action)."%'";
}
if ($search_user !== '') {
    $cond .= " AND username LIKE '%".$db->escape($search_user)."%'";
}
if ($search_element !== '') {
    $cond .= " AND element_type LIKE '%".$db->escape($search_element)."%'";
}
if ($severity_filter !== '') {
    $cond .= " AND severity = '".$db->escape($severity_filter)."'";
}

$sql = "SELECT datestamp, action, element_type, username, ip, severity 
        FROM ".$db->prefix()."useractivitytracker_activity".$cond." 
        ORDER BY datestamp DESC 
        LIMIT 20";

$previewRows = array();
$res = $db->query($sql);
if ($res) {
    while ($obj = $db->fetch_object($res)) {
        $previewRows[] = $obj;
    }
    $db->free($res);
}
$numPreview = count($previewRows);

// Diagnostics only when needed (for "no data" message)
$diagnostics = null;
if ($numPreview === 0) {
    $activity    = new UserActivity($db);
    $diagnostics = $activity->getDiagnostics($conf->entity);
}

$from_h = dol_escape_htmltag($from);
$to_h   = dol_escape_htmltag($to);

$dashboard_url = dol_buildpath('/useractivitytracker/admin/useractivitytracker_dashboard.php', 1);
$analysis_url  = dol_buildpath('/useractivitytracker/admin/useractivitytracker_analysis.php', 1);
$setup_url     = dol_buildpath('/useractivitytracker/admin/useractivitytracker_setup.php', 1);

llxHeader('', 'User Activity — Export Data');

/* ----------------- OroCommerce-Inspired UI CSS (same system as other pages) ----------------- */
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

/* Form elements */
.uat-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 1.25rem;
}

.uat-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.uat-form-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--uat-text-secondary);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.uat-form-input,
.uat-form-select {
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--uat-border);
    border-radius: 6px;
    background: var(--uat-surface);
    color: var(--uat-text);
    font-size: 0.875rem;
    transition: all 0.2s;
    width: 100%;
    box-sizing: border-box;
}

.uat-form-input:focus,
.uat-form-select:focus {
    outline: none;
    border-color: var(--uat-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.uat-form-help {
    font-size: 0.75rem;
    color: var(--uat-text-secondary);
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

/* Severity badges (export preview) */
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

.uat-table tbody tr:nth-child(even) {
    background: rgba(0, 0, 0, 0.01);
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
    grid-template-columns: minmax(0, 2.1fr) minmax(0, 1.4fr);
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

/* ----------------- PAGE STRUCTURE ----------------- */
print '<div class="uat-container">';

/* Header */
print '<div class="uat-header">';
print '<div class="uat-header-content">';
print '<div class="uat-header-title">';
print '<div class="uat-header-icon"><i class="fas fa-file-export"></i></div>';
print '<div>';
print '<h1>User Activity — Export Data</h1>';
print '<div style="font-size:0.875rem;opacity:0.9;margin-top:0.25rem;">Generate CSV, Excel and JSON feeds for audits, BI and external tooling</div>';
print '</div>';
print '</div>'; // title

print '<div class="uat-header-actions">';
print '<a href="'.dol_escape_htmltag($dashboard_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>';
print '<a href="'.dol_escape_htmltag($analysis_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-chart-area"></i><span>Analysis</span></a>';
print '<a href="'.dol_escape_htmltag($setup_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-cogs"></i><span>Settings</span></a>';
print '<button type="button" id="themeToggle" class="uat-btn uat-btn-ghost uat-btn-icon"><i class="fas fa-moon"></i></button>';
print '</div>'; // actions
print '</div>'; // header-content

// Header meta
print '<div class="uat-header-meta">';
print '<div class="uat-meta-item"><i class="fas fa-calendar"></i><span>Period: '.$from_h.' — '.$to_h.'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-filter"></i><span>Filters applied: '
    .($search_action ? 'Action='.$search_action.'; ' : '')
    .($search_user ? 'User='.$search_user.'; ' : '')
    .($search_element ? 'Element='.$search_element.'; ' : '')
    .($severity_filter ? 'Severity='.$severity_filter : 'None')
    .'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-eye"></i><span>Preview: up to 20 rows</span></div>';
print '</div>'; // header-meta

print '</div>'; // header

/* Main grid: Filters + Export formats on left, Preview + Notes on right */
print '<div class="uat-grid-2">';

/* LEFT COLUMN */
print '<div>';

/* Filters card */
print '<form method="get" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'" class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-filter"></i> Export Filters</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-form-grid">';

print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-calendar-alt"></i> From</label>';
print '<input type="date" name="from" value="'.$from_h.'" class="uat-form-input">';
print '</div>';

print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-calendar-alt"></i> To</label>';
print '<input type="date" name="to" value="'.$to_h.'" class="uat-form-input">';
print '</div>';

print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-bolt"></i> Action</label>';
print '<input type="text" name="search_action" value="'.dol_escape_htmltag($search_action).'" class="uat-form-input" placeholder="e.g. LOGIN, CREATE">';
print '</div>';

print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-user"></i> User</label>';
print '<input type="text" name="search_user" value="'.dol_escape_htmltag($search_user).'" class="uat-form-input" placeholder="Username">';
print '</div>';

print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-shield-alt"></i> Severity</label>';
print '<select name="severity_filter" class="uat-form-select">';
print '<option value="">All</option>';
print '<option value="info"'.($severity_filter==='info'?' selected':'').'>Info</option>';
print '<option value="notice"'.($severity_filter==='notice'?' selected':'').'>Notice</option>';
print '<option value="warning"'.($severity_filter==='warning'?' selected':'').'>Warning</option>';
print '<option value="error"'.($severity_filter==='error'?' selected':'').'>Error</option>';
print '</select>';
print '</div>';

print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-cube"></i> Element</label>';
print '<input type="text" name="search_element" value="'.dol_escape_htmltag($search_element).'" class="uat-form-input" placeholder="e.g. thirdparty, invoice">';
print '<div class="uat-form-help">Use filters to narrow down export for specific modules, users or incident windows.</div>';
print '</div>';

print '</div>'; // form-grid
print '</div>'; // card-body
print '<div class="uat-card-footer">';
print '<button type="submit" class="uat-btn uat-btn-primary"><i class="fas fa-sync-alt"></i><span>Update Preview</span></button>';
print '</div>';
print '</form>';

/* Export formats card */
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-file-export"></i> Export Formats</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div style="display:flex;flex-wrap:wrap;gap:0.75rem;justify-content:flex-start;">';

print '<a class="uat-btn uat-btn-secondary" href="'.$export_base.'?format=csv'.$export_params.'">';
print '<i class="fas fa-file-csv"></i><span>Export CSV</span></a>';

print '<a class="uat-btn uat-btn-secondary" href="'.$export_base.'?format=xls'.$export_params.'">';
print '<i class="fas fa-file-excel"></i><span>Export Excel</span></a>';

print '<a class="uat-btn uat-btn-secondary" href="'.$export_base.'?format=json'.$export_params.'">';
print '<i class="fas fa-file-code"></i><span>Export JSON</span></a>';

print '<a class="uat-btn uat-btn-secondary" href="'.$export_base.'?format=ndjson'.$export_params.'">';
print '<i class="fas fa-stream"></i><span>Export NDJSON</span></a>';

print '</div>';

print '<div style="margin-top:1rem;" class="uat-info-box">';
print '<h3><span>📦</span> Export tips</h3>';
print '<ul style="margin:0 0 0 1.1rem;padding:0;font-size:0.825rem;">';
print '<li>Use <strong>CSV/Excel</strong> for spreadsheets and manual review.</li>';
print '<li>Use <strong>JSON</strong> for integrations and APIs.</li>';
print '<li>Use <strong>NDJSON</strong> for streaming loaders (e.g. Elastic, BigQuery).</li>';
print '</ul>';
print '</div>';

print '</div>';
print '</div>'; // export formats card

print '</div>'; // LEFT column

/* RIGHT COLUMN: Preview + diagnostics */
print '<div>';

/* Preview card */
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-eye"></i> Preview (first 20 matching events)</div>';
print '</div>';
print '<div class="uat-card-body">';

if ($numPreview === 0) {
    print '<div class="uat-info-box">';
    print '<h3><span>🔍</span> No data for current filters</h3>';

    if (is_array($diagnostics)) {
        if (empty($diagnostics["table_exists"])) {
            print '<p>❌ <strong>Database table missing</strong> — the module may not be properly installed.</p>';
            print '<ul style="margin-left:1.1rem;">';
            print '<li>Disable and re-enable the <strong>User Activity Tracker</strong> module to recreate tables.</li>';
            print '<li>Check database permissions for table creation.</li>';
            print '</ul>';
        } elseif (($diagnostics["recent_activity_count"] ?? 0) == 0) {
            print '<p>⚠️ <strong>No activities recorded recently</strong> — tracking may not be running.</p>';
            print '<ul style="margin-left:1.1rem;">';
            print '<li>Check server logs and trigger configuration.</li>';
            print '<li>Perform some actions in Dolibarr (login, create invoices, etc.) then refresh this page.</li>';
            print '</ul>';
        } else {
            $recent = (int)$diagnostics["recent_activity_count"];
            print '<p>📅 <strong>No rows match this filter, but '.$recent.' events exist in the last 7 days.</strong></p>';
            print '<p>Try widening the date range or clearing some filters.</p>';
        }
    } else {
        print '<p>📭 No matching records were found for the selected period and filters.</p>';
        print '<p>Adjust your date range or remove some filters, then refresh the preview.</p>';
    }

    print '</div>';
} else {
    print '<div class="uat-table-wrapper">';
    print '<table class="uat-table">';
    print '<thead>';
    print '<tr>';
    print '<th>Date/Time</th>';
    print '<th>Action</th>';
    print '<th>Element</th>';
    print '<th>User</th>';
    print '<th>IP</th>';
    print '<th>Severity</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';

    foreach ($previewRows as $obj) {
        $sev = strtolower($obj->severity ?: 'info');
        if (!in_array($sev, $allowed_severity, true)) $sev = 'info';
        $sev_class = 'uat-severity-badge uat-severity-' . $sev;

        print '<tr>';
        print '<td>'.dol_print_date($db->jdate($obj->datestamp), 'dayhour').'</td>';
        print '<td>'.dol_escape_htmltag($obj->action ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->element_type ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->username ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->ip ?: 'N/A').'</td>';
        print '<td><span class="'.$sev_class.'">'.strtoupper($sev).'</span></td>';
        print '</tr>';
    }

    print '</tbody>';
    print '</table>';
    print '</div>';
}

print '</div>'; // card-body

print '<div class="uat-card-footer">';
print '<a class="uat-btn uat-btn-secondary" href="'.dol_escape_htmltag($dashboard_url).'">';
print '<i class="fas fa-arrow-left"></i><span>Back to Dashboard</span></a>';
print '</div>';

print '</div>'; // preview card

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
