<?php
/**
 * Setup page
 * Path: custom/useractivitytracker/admin/useractivitytracker_setup.php
 * Version: 3.1.0 — OroCommerce-style settings UI, CSRF, new config options
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

/* ---- Dolibarr libs / module classes ---- */
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

if (!$user->admin && empty($user->rights->useractivitytracker->admin)) {
    accessforbidden();
}

$langs->load('admin');
$langs->load('other');

/* --------------------------------------------------------------------------
 * Read current configuration
 * ------------------------------------------------------------------------*/
$action = GETPOST('action', 'aZ09');
$token  = newToken();

$master_enabled   = getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1);
$enable_tracking  = getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1);
$session_tracking = getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_SESSION_TRACKING', 1);
$skip_sensitive   = getDolGlobalInt('USERACTIVITYTRACKER_SKIP_SENSITIVE', 1);

$retention_days   = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
$payload_max      = getDolGlobalInt('USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE',
                    getDolGlobalInt('USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES', 65536));

$dedup_window     = getDolGlobalInt('USERACTIVITYTRACKER_DEDUP_WINDOW', 2);
$track_cli        = getDolGlobalInt('USERACTIVITYTRACKER_TRACK_CLI', 1);
$track_elapsed    = getDolGlobalInt('USERACTIVITYTRACKER_TRACK_ELAPSED', 1);

$capture_ip       = getDolGlobalInt('USERACTIVITYTRACKER_CAPTURE_IP', 1);
$capture_payload  = getDolGlobalString('USERACTIVITYTRACKER_CAPTURE_PAYLOAD', 'full');

$enable_anomaly   = getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_ANOMALY', 0);

$webhook_url      = getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL', '');
$webhook_secret   = getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_SECRET', '');

$action_whitelist  = getDolGlobalString('USERACTIVITYTRACKER_ACTION_WHITELIST', '');
$action_blacklist  = getDolGlobalString('USERACTIVITYTRACKER_ACTION_BLACKLIST', '');
$element_whitelist = getDolGlobalString('USERACTIVITYTRACKER_ELEMENT_WHITELIST', '');
$element_blacklist = getDolGlobalString('USERACTIVITYTRACKER_ELEMENT_BLACKLIST', '');

$curl_available   = function_exists('curl_init') ? 1 : 0;

/* --------------------------------------------------------------------------
 * Actions
 * ------------------------------------------------------------------------*/
if ($action === 'save') {
    // CSRF protection
    if (!isset($_SESSION['newtoken']) || !GETPOST('token', 'alpha') || $_SESSION['newtoken'] !== GETPOST('token', 'alpha')) {
        setEventMessage('Invalid security token. Please try again.', 'errors');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Master + tracking switches
    $master_enabled   = GETPOSTISSET('master_enabled')   ? 1 : 0;
    $enable_tracking  = GETPOSTISSET('enable_tracking')  ? 1 : 0;
    $session_tracking = GETPOSTISSET('session_tracking') ? 1 : 0;
    $skip_sensitive   = GETPOSTISSET('skip_sensitive')   ? 1 : 0;

    dolibarr_set_const($db, 'USERACTIVITYTRACKER_MASTER_ENABLED',          (string)$master_enabled,  'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ENABLE_TRACKING',         (string)$enable_tracking, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ENABLE_SESSION_TRACKING', (string)$session_tracking,'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_SKIP_SENSITIVE',          (string)$skip_sensitive,  'chaine', 0, '', $conf->entity);

    // Retention & payload
    $retention_days = max(1, (int)GETPOST('retention', 'int'));
    $payload_max    = max(1024, (int)GETPOST('payload_max_bytes', 'int'));

    dolibarr_set_const($db, 'USERACTIVITYTRACKER_RETENTION_DAYS',   (string)$retention_days, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE', (string)$payload_max,    'chaine', 0, '', $conf->entity);
    // Legacy alias
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES',(string)$payload_max,    'chaine', 0, '', $conf->entity);

    // Dedup & elapsed / CLI tracking
    $dedup_window  = max(0, (int)GETPOST('dedup_window', 'int'));
    $track_cli     = GETPOSTISSET('track_cli')     ? 1 : 0;
    $track_elapsed = GETPOSTISSET('track_elapsed') ? 1 : 0;

    dolibarr_set_const($db, 'USERACTIVITYTRACKER_DEDUP_WINDOW',  (string)$dedup_window,  'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_TRACK_CLI',     (string)$track_cli,     'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_TRACK_ELAPSED', (string)$track_elapsed, 'chaine', 0, '', $conf->entity);

    // Capture settings
    $capture_ip      = GETPOSTISSET('capture_ip') ? 1 : 0;
    $capture_payload = GETPOST('capture_payload', 'aZ09');
    if (!in_array($capture_payload, array('off','truncated','full'), true)) {
        $capture_payload = 'full';
    }

    dolibarr_set_const($db, 'USERACTIVITYTRACKER_CAPTURE_IP',      (string)$capture_ip,      'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_CAPTURE_PAYLOAD', (string)$capture_payload, 'chaine', 0, '', $conf->entity);

    // Webhook
    $webhook_url    = trim(GETPOST('webhook', 'alphanohtml'));
    $webhook_secret = trim(GETPOST('secret',  'alphanohtml'));

    dolibarr_set_const($db, 'USERACTIVITYTRACKER_WEBHOOK_URL',    $webhook_url,    'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_WEBHOOK_SECRET', $webhook_secret, 'chaine', 0, '', $conf->entity);

    // Other toggles
    $enable_anomaly = GETPOSTISSET('anomaly') ? 1 : 0;
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ENABLE_ANOMALY', (string)$enable_anomaly, 'chaine', 0, '', $conf->entity);

    // Filters
    $action_whitelist  = trim(GETPOST('action_whitelist',  'restricthtml'));
    $action_blacklist  = trim(GETPOST('action_blacklist',  'restricthtml'));
    $element_whitelist = trim(GETPOST('element_whitelist', 'restricthtml'));
    $element_blacklist = trim(GETPOST('element_blacklist', 'restricthtml'));

    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ACTION_WHITELIST',  $action_whitelist,  'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ACTION_BLACKLIST',  $action_blacklist,  'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ELEMENT_WHITELIST', $element_whitelist, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ELEMENT_BLACKLIST', $element_blacklist, 'chaine', 0, '', $conf->entity);

    setEventMessage($langs->trans('SetupSaved'));
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* --------------------------------------------------------------------------
 * Helper for filter pattern counts
 * ------------------------------------------------------------------------*/
function uat_count_patterns($str) {
    $str = trim((string)$str);
    if ($str === '') return 0;
    $parts = preg_split('/[,\n;]+/', $str);
    $n = 0;
    foreach ($parts as $p) if (trim($p) !== '') $n++;
    return $n;
}

/* --------------------------------------------------------------------------
 * Derived values for UI
 * ------------------------------------------------------------------------*/
$analysis_url  = dol_buildpath('/useractivitytracker/admin/useractivitytracker_analysis.php', 1);
$dashboard_url = dol_buildpath('/useractivitytracker/admin/useractivitytracker_dashboard.php', 1);
$setup_url     = $_SERVER['PHP_SELF'];

$tracking_on   = ($master_enabled && $enable_tracking) ? 1 : 0;
$webhook_set   = !empty($webhook_url);

$actionWhitelistCount  = uat_count_patterns($action_whitelist);
$actionBlacklistCount  = uat_count_patterns($action_blacklist);
$elementWhitelistCount = uat_count_patterns($element_whitelist);
$elementBlacklistCount = uat_count_patterns($element_blacklist);

if ($actionWhitelistCount > 0) {
    $actionFilterSummary = 'Whitelist ('.$actionWhitelistCount.' pattern'.($actionWhitelistCount>1?'s':'').')';
} elseif ($actionBlacklistCount > 0) {
    $actionFilterSummary = 'Blacklist ('.$actionBlacklistCount.' pattern'.($actionBlacklistCount>1?'s':'').')';
} else {
    $actionFilterSummary = 'All actions tracked';
}

if ($elementWhitelistCount > 0) {
    $elementFilterSummary = 'Whitelist ('.$elementWhitelistCount.' pattern'.($elementWhitelistCount>1?'s':'').')';
} elseif ($elementBlacklistCount > 0) {
    $elementFilterSummary = 'Blacklist ('.$elementBlacklistCount.' pattern'.($elementBlacklistCount>1?'s':'').')';
} else {
    $elementFilterSummary = 'All elements tracked';
}

/* --------------------------------------------------------------------------
 * View
 * ------------------------------------------------------------------------*/
llxHeader('', 'User Activity — Setup');

/* ----------------- OroCommerce-Inspired UI CSS (shared with analysis/dashboard) ----------------- */
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

/* Stats Cards */
.uat-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.uat-stat-card {
    background: var(--uat-surface);
    border: 1px solid var(--uat-border);
    border-radius: 8px;
    padding: 1.25rem 1.5rem;
    box-shadow: var(--uat-shadow);
    position: relative;
    overflow: hidden;
}

.uat-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--card-color, var(--uat-primary)) 0%, var(--card-color-light, var(--uat-primary)) 100%);
}

.uat-stat-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.uat-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, var(--card-color, var(--uat-primary)) 0%, var(--card-color-dark, var(--uat-primary-dark)) 100%);
    color: white;
    box-shadow: var(--uat-shadow-md);
}

.uat-stat-label {
    font-size: 0.875rem;
    color: var(--uat-text-secondary);
    margin-bottom: 0.125rem;
    font-weight: 500;
}

.uat-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--uat-text);
}

.uat-stat-sub {
    font-size: 0.813rem;
    color: var(--uat-text-secondary);
}

/* Form elements */
.uat-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
.uat-form-select,
.uat-form-text {
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
.uat-form-select:focus,
.uat-form-text:focus {
    outline: none;
    border-color: var(--uat-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.uat-form-text {
    min-height: 90px;
    resize: vertical;
}

.uat-form-help {
    font-size: 0.75rem;
    color: var(--uat-text-secondary);
}

.uat-toggle-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

/* Layout grids */
.uat-grid-2 {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1.5fr);
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .uat-grid-2 {
        grid-template-columns: 1fr;
    }
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

    .uat-stats-grid {
        grid-template-columns: 1fr;
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
print '<div class="uat-header-icon"><i class="fas fa-cogs"></i></div>';
print '<div>';
print '<h1>User Activity — Settings</h1>';
print '<div style="font-size:0.875rem;opacity:0.9;margin-top:0.25rem;">Control tracking, retention, filters and integrations</div>';
print '</div>';
print '</div>'; // title

print '<div class="uat-header-actions">';
print '<a href="'.dol_escape_htmltag($dashboard_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>';
print '<a href="'.dol_escape_htmltag($analysis_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-chart-area"></i><span>Analysis</span></a>';
print '<button type="button" id="themeToggle" class="uat-btn uat-btn-ghost uat-btn-icon"><i class="fas fa-moon"></i></button>';
print '</div>'; // actions
print '</div>'; // header-content

// Header meta
print '<div class="uat-header-meta">';
print '<div class="uat-meta-item"><i class="fas fa-toggle-on"></i><span>Tracking: '.($tracking_on ? 'Enabled' : 'Disabled').'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-database"></i><span>Retention: '.$retention_days.' days</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-stopwatch"></i><span>Elapsed (kpi1): '.($track_elapsed ? 'On' : 'Off').'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-layer-group"></i><span>Dedup window: '.$dedup_window.'s</span></div>';
print '</div>'; // header-meta

print '</div>'; // header

/* Stats cards */
print '<div class="uat-stats-grid">';

// Tracking status
print '<div class="uat-stat-card" style="--card-color:#10b981; --card-color-dark:#059669; --card-color-light:#34d399;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-power-off"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Tracking Status</div>';
print '<div class="uat-stat-value">'.($tracking_on ? 'Enabled' : 'Disabled').'</div>';
print '<div class="uat-stat-sub">Master + per-entity tracking switch</div>';
print '</div>';
print '</div>';
print '</div>';

// Retention & payload
print '<div class="uat-stat-card" style="--card-color:#2563eb; --card-color-dark:#1d4ed8; --card-color-light:#3b82f6;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-archive"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Data Retention</div>';
print '<div class="uat-stat-value">'.$retention_days.' days</div>';
print '<div class="uat-stat-sub">Payload max '.number_format($payload_max).' bytes</div>';
print '</div>';
print '</div>';
print '</div>';

// Dedup / CLI / elapsed
print '<div class="uat-stat-card" style="--card-color:#f59e0b; --card-color-dark:#d97706; --card-color-light:#fbbf24;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-sliders-h"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Event Processing</div>';
print '<div class="uat-stat-value">'.$dedup_window.'s</div>';
print '<div class="uat-stat-sub">Dedup window · CLI: '.($track_cli?'on':'off').' · Elapsed: '.($track_elapsed?'on':'off').'</div>';
print '</div>';
print '</div>';
print '</div>';

// Webhook / anomaly / filters
$wb_badge  = $webhook_set ? 'uat-badge-success' : 'uat-badge-warning';
$wb_label  = $webhook_set ? 'Webhook configured' : 'No webhook URL';
$an_badge  = $enable_anomaly ? 'uat-badge-info' : 'uat-badge-warning';
$an_label  = $enable_anomaly ? 'Anomaly detection on' : 'Anomaly detection off';

print '<div class="uat-stat-card" style="--card-color:#8b5cf6; --card-color-dark:#7c3aed; --card-color-light:#a78bfa;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-bell"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Integrations & Filters</div>';
print '<div class="uat-stat-value">'.($actionWhitelistCount+$actionBlacklistCount+$elementWhitelistCount+$elementBlacklistCount).' patterns</div>';
print '<div class="uat-stat-sub">';
print '<span class="uat-badge '.$wb_badge.'" style="margin-right:0.35rem;">'.$wb_label.'</span>';
print '<span class="uat-badge '.$an_badge.'">'.$an_label.'</span>';
print '</div>';
print '</div>';
print '</div>';
print '</div>';

print '</div>'; // stats-grid

/* Main form */
print '<form method="post" action="'.dol_escape_htmltag($setup_url).'" class="uat-card">';
print '<input type="hidden" name="token" value="'.dol_escape_htmltag($token).'">';
print '<input type="hidden" name="action" value="save">';

print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-sliders-h"></i> Core Tracking Settings</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-form-grid">';

// Master switch
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-toggle-on"></i> Master switch</label>';
print '<div class="uat-toggle-row">';
print '<input type="checkbox" name="master_enabled" id="master_enabled" '.($master_enabled?'checked':'').'>';
print '<label for="master_enabled" class="uat-form-help" style="margin:0;">Enable or disable all activity logging for this entity.</label>';
print '</div>';
print '</div>';

// Enable tracking
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-user-secret"></i> Per-entity tracking</label>';
print '<div class="uat-toggle-row">';
print '<input type="checkbox" name="enable_tracking" id="enable_tracking" '.($enable_tracking?'checked':'').'>';
print '<label for="enable_tracking" class="uat-form-help" style="margin:0;">Turn tracking on/off for the current entity only.</label>';
print '</div>';
print '</div>';

// Session tracking
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-stream"></i> Session tracking</label>';
print '<div class="uat-toggle-row">';
print '<input type="checkbox" name="session_tracking" id="session_tracking" '.($session_tracking?'checked':'').'>';
print '<label for="session_tracking" class="uat-form-help" style="margin:0;">Track session-related events (login/logout, session id, etc.).</label>';
print '</div>';
print '</div>';

// Skip sensitive
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-user-shield"></i> Skip sensitive data</label>';
print '<div class="uat-toggle-row">';
print '<input type="checkbox" name="skip_sensitive" id="skip_sensitive" '.($skip_sensitive?'checked':'').'>';
print '<label for="skip_sensitive" class="uat-form-help" style="margin:0;">Avoid logging sensitive payloads (password resets, tokens, etc.).</label>';
print '</div>';
print '</div>';

// Retention
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-calendar-alt"></i> Retention (days)</label>';
print '<input type="number" name="retention" class="uat-form-input" min="1" value="'.dol_escape_htmltag($retention_days).'">';
print '<div class="uat-form-help">Older records are opportunistically cleaned in the dashboard using this retention horizon.</div>';
print '</div>';

// Payload max
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-file-alt"></i> Max payload size (bytes)</label>';
print '<input type="number" name="payload_max_bytes" class="uat-form-input" min="1024" step="1024" value="'.dol_escape_htmltag($payload_max).'">';
print '<div class="uat-form-help">Protects your database by truncating very large JSON payloads.</div>';
print '</div>';

// Dedup window
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-clone"></i> Deduplication window (seconds)</label>';
print '<input type="number" name="dedup_window" class="uat-form-input" min="0" value="'.dol_escape_htmltag($dedup_window).'">';
print '<div class="uat-form-help">Events with same action/user/element/id inside this window are de-duplicated.</div>';
print '</div>';

// CLI / elapsed
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-terminal"></i> CLI & elapsed tracking</label>';
print '<div class="uat-toggle-row" style="flex-direction:column;align-items:flex-start;">';
print '<label style="font-size:0.875rem;"><input type="checkbox" name="track_cli" '.($track_cli?'checked':'').'> Track CLI/cron events</label>';
print '<label style="font-size:0.875rem;"><input type="checkbox" name="track_elapsed" '.($track_elapsed?'checked':'').'> Record elapsed time in <code>kpi1</code> for supported actions</label>';
print '<div class="uat-form-help">Elapsed seconds feed the dashboard time-on-page / per-user seconds KPIs.</div>';
print '</div>';
print '</div>';

print '</div>'; // form-grid
print '</div>'; // card-body

/* Capture & privacy card */
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-user-lock"></i> Capture & Privacy</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-form-grid">';

// Capture IP
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-network-wired"></i> Capture IP address</label>';
print '<div class="uat-toggle-row">';
print '<input type="checkbox" name="capture_ip" id="capture_ip" '.($capture_ip?'checked':'').'>';
print '<label for="capture_ip" class="uat-form-help" style="margin:0;">Store client IP (or proxy forwarded IP) for each event.</label>';
print '</div>';
print '</div>';

// Capture payload mode
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-align-left"></i> Payload capture mode</label>';
print '<select name="capture_payload" class="uat-form-select">';
$payload_opts = array(
    'off'       => 'Off — do not store extended payloads',
    'truncated' => 'Truncated — store limited JSON',
    'full'      => 'Full — store full JSON payload (recommended with max size)'
);
foreach ($payload_opts as $k => $lbl) {
    print '<option value="'.dol_escape_htmltag($k).'"'.($capture_payload===$k?' selected':'').'>'.dol_escape_htmltag($lbl).'</option>';
}
print '</select>';
print '<div class="uat-form-help">Payload field typically contains JSON with request metadata and context (see <code>payload</code> column).</div>';
print '</div>';

// Anomaly detection
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-shield-alt"></i> Anomaly detection</label>';
print '<div class="uat-toggle-row">';
print '<input type="checkbox" name="anomaly" id="anomaly" '.($enable_anomaly?'checked':'').'>';
print '<label for="anomaly" class="uat-form-help" style="margin:0;">Enable anomaly detection used by the <strong>Analysis</strong> page (suspicious logins, bursts, etc.).</label>';
print '</div>';
print '</div>';

print '</div>'; // form-grid
print '</div>'; // card-body
print '</div>'; // card

/* Filters card */
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-filter"></i> Action & Element Filters</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-grid-2">';

// Left column: actions
print '<div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-list-ul"></i> Action whitelist</label>';
print '<textarea name="action_whitelist" class="uat-form-text" placeholder="Example: COMPANY_CREATE, USER_LOGIN">'.dol_escape_htmltag($action_whitelist).'</textarea>';
print '<div class="uat-form-help">If non-empty, only events whose <code>action</code> matches one of the patterns are logged. Comma, semicolon or newline separated.</div>';
print '</div>';

print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-ban"></i> Action blacklist</label>';
print '<textarea name="action_blacklist" class="uat-form-text" placeholder="Example: LOGIN_FAILED, PASSWORD_FORGOT">'.dol_escape_htmltag($action_blacklist).'</textarea>';
print '<div class="uat-form-help">If whitelist is empty, blacklist can suppress noisy actions. Use the same pattern format.</div>';
print '</div>';
print '</div>';

// Right column: elements
print '<div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-cubes"></i> Element whitelist</label>';
print '<textarea name="element_whitelist" class="uat-form-text" placeholder="Example: societe, facture, commande">'.dol_escape_htmltag($element_whitelist).'</textarea>';
print '<div class="uat-form-help">Filter on <code>element_type</code> (e.g. <code>societe</code>, <code>facture</code>, <code>commande</code>).</div>';
print '</div>';

print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-ban"></i> Element blacklist</label>';
print '<textarea name="element_blacklist" class="uat-form-text">'.dol_escape_htmltag($element_blacklist).'</textarea>';
print '<div class="uat-form-help">Use blacklist to hide low-value elements when whitelist is not set.</div>';
print '</div>';
print '</div>';

print '</div>'; // grid-2

print '<div class="uat-info-box" style="margin-top:1rem;">';
print '<h3><span>💡</span> Filter behaviour</h3>';
print '<ul style="margin:0;padding-left:1.25rem;">';
print '<li>If an action whitelist is defined, ONLY matching actions are tracked.</li>';
print '<li>If no whitelist but an action blacklist is set, those actions are excluded.</li>';
print '<li>The same logic applies to elements based on <code>element_type</code>.</li>';
print '</ul>';
print '</div>';

print '</div>'; // card-body
print '</div>'; // card

/* Webhook & integration card */
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-plug"></i> Webhook & Integrations</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-form-grid">';

// Webhook URL
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-link"></i> Webhook URL</label>';
print '<input type="text" name="webhook" class="uat-form-input" value="'.dol_escape_htmltag($webhook_url).'" placeholder="https://example.com/uat/webhook">';
print '<div class="uat-form-help">When set, critical events may be pushed to this endpoint by the module (depending on your implementation).</div>';
print '</div>';

// Webhook secret
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-key"></i> Webhook secret</label>';
print '<input type="text" name="secret" class="uat-form-input" value="'.dol_escape_htmltag($webhook_secret).'">';
print '<div class="uat-form-help">Optional secret/token your receiver can use to validate the source.</div>';
print '</div>';

// cURL available
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-info-circle"></i> System capabilities</label>';
print '<div class="uat-form-help">cURL support: '.($curl_available
    ? '<span class="uat-badge uat-badge-success"><i class="fas fa-check"></i> Available</span>'
    : '<span class="uat-badge uat-badge-danger"><i class="fas fa-times"></i> Missing</span>').'</div>';
print '<div class="uat-form-help">If cURL is not available, outbound HTTP calls for webhooks will be limited.</div>';
print '</div>';

print '</div>'; // form-grid
print '</div>'; // card-body

print '<div class="uat-card-footer">';
print '<button type="submit" class="uat-btn uat-btn-primary"><i class="fas fa-save"></i><span>Save settings</span></button>';
print '<a href="'.dol_escape_htmltag($dashboard_url).'" class="uat-btn uat-btn-secondary"><i class="fas fa-tachometer-alt"></i><span>Back to dashboard</span></a>';
print '</div>';

print '</form>'; // main form

print '</div>'; // container

/* Theme toggle JS (same behaviour as analysis) */
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
