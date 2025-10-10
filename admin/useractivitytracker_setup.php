<?php
/**
 * Setup page
 * Path: custom/useractivitytracker/admin/useractivitytracker_setup.php
 * Version: 2.7.0 — CSRF protection, new config options (master switch, capture toggles)
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
dol_include_once('/useractivitytracker/class/useractivity.class.php');

if (!$user->admin && empty($user->rights->useractivitytracker->admin)) {
    accessforbidden();
}

$langs->load("admin");
$langs->load("other");

$action = GETPOST('action','aZ09');

/* ------------------------- Actions ------------------------- */
if ($action === 'save') {
    // CSRF protection
    if (!isset($_SESSION['newtoken']) || !GETPOST('token', 'alpha') || $_SESSION['newtoken'] !== GETPOST('token', 'alpha')) {
        setEventMessage('Invalid security token. Please try again.', 'errors');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Master switch (v2.7)
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_MASTER_ENABLED', GETPOSTISSET('master_enabled')?'1':'0', 'chaine', 0, '', $conf->entity);
    
    // Retention & payload
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_RETENTION_DAYS', max(1,(int)GETPOST('retention','int')), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES', max(1024,(int)GETPOST('payload_max_bytes','int')), 'chaine', 0, '', $conf->entity);
    
    // Capture toggles (v2.7)
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_CAPTURE_IP', GETPOSTISSET('capture_ip')?'1':'0', 'chaine', 0, '', $conf->entity);
    $capture_payload = GETPOST('capture_payload','aZ09');
    if (!in_array($capture_payload, array('off','truncated','full'))) $capture_payload = 'full';
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_CAPTURE_PAYLOAD', $capture_payload, 'chaine', 0, '', $conf->entity);
    
    // Webhook
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_WEBHOOK_URL',    trim(GETPOST('webhook','alphanohtml')), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_WEBHOOK_SECRET', trim(GETPOST('secret','alphanohtml')),   'chaine', 0, '', $conf->entity);
    
    // Other toggles
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ENABLE_ANOMALY', GETPOSTISSET('anomaly') ? '1' : '0',     'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ENABLE_TRACKING', GETPOSTISSET('enable_tracking')?'1':'0', 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ENABLE_SESSION_TRACKING', GETPOSTISSET('session_tracking')?'1':'0', 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_SKIP_SENSITIVE_DATA',     GETPOSTISSET('skip_sensitive')?'1':'0',    'chaine', 0, '', $conf->entity);
    
    // Legacy name compatibility
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE', max(1024,(int)GETPOST('payload_max_bytes','int')), 'chaine', 0, '', $conf->entity);
    
    setEventMessage($langs->trans('SetupSaved'));
}
elseif ($action === 'testwebhook' && getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL')) {
    // CSRF protection
    if (!isset($_SESSION['newtoken']) || !GETPOST('token', 'alpha') || $_SESSION['newtoken'] !== GETPOST('token', 'alpha')) {
        setEventMessage('Invalid security token. Please try again.', 'errors');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $url  = getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL');
    $data = json_encode(array(
        'test'    => true,
        'message' => 'Test webhook from User Activity Tracker',
        'date'    => dol_print_date(dol_now(),'standard'),
        'entity'  => $conf->entity,
        'version' => '2.7.0'
    ), JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $headers = array('Content-Type: application/json');

        if (getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_SECRET')) {
            $secret    = getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_SECRET');
            $signature = hash_hmac('sha256', $data, $secret);
            $headers[] = 'X-Webhook-Secret: '.$secret;
            $headers[] = 'X-Hub-Signature-256: sha256='.$signature;
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $out = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            setEventMessage('Webhook test successful! HTTP '.$httpCode.'. Response: '.dol_trunc((string)$out, 120));
        } else {
            setEventMessage('Webhook test failed! HTTP '.$httpCode.'. Error: '.$curlError.'. Response: '.dol_trunc((string)$out, 120), 'errors');
        }
    } else {
        setEventMessage('cURL not available for webhook testing', 'warnings');
    }
}
elseif ($action === 'cleanup') {
    // CSRF protection
    if (!isset($_SESSION['newtoken']) || !GETPOST('token', 'alpha') || $_SESSION['newtoken'] !== GETPOST('token', 'alpha')) {
        setEventMessage('Invalid security token. Please try again.', 'errors');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $activity = class_exists('UserActivity') ? new UserActivity($db) : null;
    $retention_days = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
    $deleted = $activity ? (int)$activity->cleanOldActivities($retention_days, (int)$conf->entity) : 0;
    setEventMessage('Cleanup completed: '.$deleted.' records deleted');
}
elseif ($action === 'analyze_anomalies') {
    // CSRF protection
    if (!isset($_SESSION['newtoken']) || !GETPOST('token', 'alpha') || $_SESSION['newtoken'] !== GETPOST('token', 'alpha')) {
        setEventMessage('Invalid security token. Please try again.', 'errors');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if (getDolGlobalString('USERACTIVITYTRACKER_ENABLE_ANOMALY')) {
        $activity  = class_exists('UserActivity') ? new UserActivity($db) : null;
        $anomalies = $activity ? (array)$activity->detectAnomalies((int)$conf->entity) : array();
        if (empty($anomalies)) {
            setEventMessage('No anomalies detected in recent activity');
        } else {
            $message = 'Found '.count($anomalies).' potential anomalies:<br>';
            foreach ($anomalies as $anomaly) {
                $message .= '- '.dol_escape_htmltag($anomaly['description']).'<br>';
            }
            setEventMessage($message, 'warnings');
        }
    } else {
        setEventMessage('Anomaly detection is disabled. Enable it in settings first.', 'warnings');
    }
}

/* Opportunistic retention cleanup */
$days = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
$db->query("DELETE FROM ".$db->prefix()."alt_user_activity 
            WHERE entity=".(int)$conf->entity." 
              AND datestamp < DATE_SUB(NOW(), INTERVAL ".((int)$days)." DAY)");

/* ------------------------- View ------------------------- */
llxHeader('', 'User Activity Tracker — Settings');
print load_fiche_titre('User Activity Tracker — Settings', '', 'object_useractivitytracker@useractivitytracker');

print '<form method="post">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-cogs"></i> General Settings</td></tr>';
print '<tr><td class="titlefield"><i class="fas fa-toggle-on"></i> <strong>Master Tracking Switch</strong></td><td><input type="checkbox" name="master_enabled" '.(getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)?'checked':'').'> <em><strong>v2.7:</strong> Central gate - disables ALL tracking when OFF</em></td></tr>';
print '<tr><td class="titlefield"><i class="fas fa-power-off"></i> Enable user tracking</td><td><input type="checkbox" name="enable_tracking" '.(getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)?'checked':'').'> <em>Enable activity tracking for all users (disable to stop all tracking)</em></td></tr>';
print '<tr><td class="titlefield"><i class="fas fa-calendar-alt"></i> Retention (days)</td><td><input class="flat" type="number" name="retention" min="1" value="'.dol_escape_htmltag($days).'"> <em>How long to keep activity data</em></td></tr>';
print '<tr><td><i class="fas fa-user-clock"></i> Enable session tracking</td><td><input type="checkbox" name="session_tracking" '.(getDolGlobalString('USERACTIVITYTRACKER_ENABLE_SESSION_TRACKING')?'checked':'').'></td></tr>';

print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-database"></i> Data Capture Settings (v2.7)</td></tr>';
print '<tr><td><i class="fas fa-network-wired"></i> Capture IP addresses</td><td><input type="checkbox" name="capture_ip" '.(getDolGlobalInt('USERACTIVITYTRACKER_CAPTURE_IP', 1)?'checked':'').'> <em>Capture user IP addresses</em></td></tr>';
print '<tr><td><i class="fas fa-file-code"></i> Capture payload</td><td><select name="capture_payload" class="flat">';
$capture_mode = getDolGlobalString('USERACTIVITYTRACKER_CAPTURE_PAYLOAD', 'full');
print '<option value="off"'.($capture_mode==='off'?' selected':'').'>Off - No payload capture</option>';
print '<option value="truncated"'.($capture_mode==='truncated'?' selected':'').'>Truncated - Capture with size limit</option>';
print '<option value="full"'.($capture_mode==='full'?' selected':'').'>Full - Capture everything</option>';
print '</select></td></tr>';
print '<tr><td><i class="fas fa-database"></i> Max payload size (bytes)</td><td><input class="flat" type="number" name="payload_max_bytes" min="1024" value="'.dol_escape_htmltag(getDolGlobalInt('USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES', 65536)).'"> <em>Max size for JSON payloads</em></td></tr>';
print '<tr><td><i class="fas fa-user-shield"></i> Skip sensitive data</td><td><input type="checkbox" name="skip_sensitive" '.(getDolGlobalString('USERACTIVITYTRACKER_SKIP_SENSITIVE_DATA')?'checked':'').'> <em>Filter passwords/tokens from logs</em></td></tr>';

print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-webhook"></i> Webhook Settings</td></tr>';
print '<tr><td><i class="fas fa-link"></i> Webhook URL</td><td><input class="flat quatrevingtpercent" type="url" name="webhook" placeholder="https://your-webhook-endpoint.com/webhook" value="'.dol_escape_htmltag(getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL')).'"></td></tr>';
print '<tr><td><i class="fas fa-key"></i> Webhook Secret</td><td><input class="flat" type="text" name="secret" size="40" placeholder="Optional secret for HMAC signature" value="'.dol_escape_htmltag(getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_SECRET')).'"></td></tr>';

print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-shield-alt"></i> Security & Monitoring</td></tr>';
print '<tr><td><i class="fas fa-search"></i> Enable anomaly detection</td><td><input type="checkbox" name="anomaly" '.(getDolGlobalString('USERACTIVITYTRACKER_ENABLE_ANOMALY')?'checked':'').'></td></tr>';

print '</table>';

print '<div class="center" style="margin: 20px 0;">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'"><i class="fas fa-save" style="margin-left: 8px;"></i>';
print '</div>';
print '</form>';

/* Action buttons */
print '<div class="center">';
print '<form method="post" style="display:inline-block;margin:0 10px;"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="testwebhook">';
print '<input type="submit" class="button" value="Test Webhook"'.(getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL')?'':' disabled title="Configure webhook URL first"').'><i class="fas fa-plug" style="margin-left: 8px;"></i></form>';

print '<form method="post" style="display:inline-block;margin:0 10px;"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="cleanup">';
print '<input type="submit" class="button" value="Manual Cleanup" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmPurge")).'\')"'.'><i class="fas fa-broom" style="margin-left: 8px;"></i></form>';

print '<form method="post" style="display:inline-block;margin:0 10px;"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="analyze_anomalies">';
print '<input type="submit" class="button" value="Analyze Anomalies"'.(getDolGlobalString('USERACTIVITYTRACKER_ENABLE_ANOMALY')?'':' disabled title="Enable anomaly detection first"').'><i class="fas fa-search" style="margin-left: 8px;"></i></form>';
print '</div>';

/* Quick stats (if class exists) */
print '<br><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2"><i class="fas fa-info-circle"></i> System Information</td></tr>';

$tot = 0; $by_action = array(); $by_user = array();
if (class_exists('UserActivity')) {
    $activity = new UserActivity($db);
    $stats = $activity->getActivityStats(
        dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d'),
        dol_print_date(dol_now(), '%Y-%m-%d'),
        (int)$conf->entity
    );
    $tot = (int)($stats['total'] ?? 0);
    $by_action = (array)($stats['by_action'] ?? array());
    $by_user   = (array)($stats['by_user'] ?? array());
}

print '<tr><td class="titlefield"><i class="fas fa-tag"></i> Module Version</td><td><strong>2.5.0</strong></td></tr>';
print '<tr><td><i class="fas fa-chart-line"></i> Total Activities (last 30 days)</td><td><strong>'.$tot.'</strong></td></tr>';
print '<tr><td><i class="fas fa-tasks"></i> Unique Actions (last 30 days)</td><td><strong>'.count($by_action).'</strong></td></tr>';
print '<tr><td><i class="fas fa-users"></i> Active Users (last 30 days)</td><td><strong>'.count($by_user).'</strong></td></tr>';
print '<tr><td><i class="fab fa-php"></i> PHP Version</td><td>'.phpversion().'</td></tr>';
print '<tr><td><i class="fas fa-download"></i> cURL Available</td><td>'.((function_exists('curl_init'))?'<span style="color: green;"><i class="fas fa-check"></i> Yes</span>':'<span style="color: red;"><i class="fas fa-times"></i> No</span>').'</td></tr>';
print '<tr><td><i class="fas fa-calendar-check"></i> Current Retention</td><td>'.$days.' days</td></tr>';
print '</table></div>';

llxFooter();
