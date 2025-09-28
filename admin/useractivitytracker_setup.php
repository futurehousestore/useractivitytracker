
<?php
/**
 * Setup page
 * Path: custom/useractivitytracker/admin/useractivitytracker_setup.php
 * Version: 1.0.0
 */
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../class/useractivity.class.php';

if (! $user->admin && empty($user->rights->useractivitytracker->admin)) accessforbidden();

$langs->load("admin");
$langs->load("other");

$action = GETPOST('action','alpha');

if ($action=='save')
{
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_RETENTION_DAYS', max(1,(int)GETPOST('retention','int')), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_WEBHOOK_URL', trim(GETPOST('webhook','alpha')), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_WEBHOOK_SECRET', trim(GETPOST('secret','alpha')), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ENABLE_ANOMALY', GETPOST('anomaly','alpha')?'1':'0', 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_ENABLE_SESSION_TRACKING', GETPOST('session_tracking','alpha')?'1':'0', 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_SKIP_SENSITIVE_DATA', GETPOST('skip_sensitive','alpha')?'1':'0', 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE', max(1024,(int)GETPOST('max_payload_size','int')), 'chaine', 0, '', $conf->entity);
    setEventMessage('Settings saved successfully');
}
elseif ($action=='testwebhook' && ! empty($conf->global->USERACTIVITYTRACKER_WEBHOOK_URL))
{
    $url = $conf->global->USERACTIVITYTRACKER_WEBHOOK_URL;
    $data = json_encode(array(
        'test' => true,
        'message' => 'Test webhook from User Activity Tracker',
        'date' => dol_print_date(dol_now(),'standard'),
        'entity' => $conf->entity,
        'version' => '1.0.0'
    ));
    
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $headers = array('Content-Type: application/json');
        
        if (!empty($conf->global->USERACTIVITYTRACKER_WEBHOOK_SECRET)) {
            $signature = hash_hmac('sha256', $data, $conf->global->USERACTIVITYTRACKER_WEBHOOK_SECRET);
            $headers[] = 'X-Webhook-Secret: '.$conf->global->USERACTIVITYTRACKER_WEBHOOK_SECRET;
            $headers[] = 'X-Hub-Signature-256: sha256='.$signature;
        }
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $out = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            setEventMessage('Webhook test successful! HTTP '.$httpCode.'. Response: '.dol_trunc($out, 120));
        } else {
            setEventMessage('Webhook test failed! HTTP '.$httpCode.'. Error: '.$curlError.'. Response: '.dol_trunc($out, 120), 'errors');
        }
    } else {
        setEventMessage('cURL not available for webhook testing', 'warnings');
    }
}
elseif ($action=='cleanup')
{
    $activity = new UserActivity($db);
    $retention_days = (int)($conf->global->USERACTIVITYTRACKER_RETENTION_DAYS ?: 365);
    $deleted = $activity->cleanOldActivities($retention_days, $conf->entity);
    setEventMessage('Cleanup completed: '.$deleted.' records deleted');
}
elseif ($action=='analyze_anomalies')
{
    if (!empty($conf->global->USERACTIVITYTRACKER_ENABLE_ANOMALY)) {
        $activity = new UserActivity($db);
        $anomalies = $activity->detectAnomalies($conf->entity);
        
        if (empty($anomalies)) {
            setEventMessage('No anomalies detected in recent activity');
        } else {
            $message = 'Found '.count($anomalies).' potential anomalies:<br>';
            foreach ($anomalies as $anomaly) {
                $message .= '- '.$anomaly['description'].'<br>';
            }
            setEventMessage($message, 'warnings');
        }
    } else {
        setEventMessage('Anomaly detection is disabled. Enable it in settings first.', 'warnings');
    }
}

// Opportunistic retention cleanup
$days = (int)($conf->global->USERACTIVITYTRACKER_RETENTION_DAYS ?: 365);
$db->query("DELETE FROM ".$db->prefix()."alt_user_activity WHERE datestamp < DATE_SUB(NOW(), INTERVAL ".((int)$days)." DAY) AND entity=".(int)$conf->entity);

llxHeader('', 'User Activity Tracker — Settings');
print load_fiche_titre('User Activity Tracker — Settings', '', 'object_useractivitytracker@useractivitytracker');

print '<form method="post">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><td colspan="2">General Settings</td></tr>';
print '<tr><td width="300">Retention (days)</td><td><input type="number" name="retention" min="1" value="'.dol_escape_htmltag($days).'"> <em>How long to keep activity data</em></td></tr>';
print '<tr><td>Enable session tracking</td><td><input type="checkbox" name="session_tracking" '.(!empty($conf->global->USERACTIVITYTRACKER_ENABLE_SESSION_TRACKING)?'checked':'').'> <em>Track user sessions and enhanced context</em></td></tr>';
print '<tr><td>Skip sensitive data</td><td><input type="checkbox" name="skip_sensitive" '.(!empty($conf->global->USERACTIVITYTRACKER_SKIP_SENSITIVE_DATA)?'checked':'').'> <em>Filter passwords and tokens from payload</em></td></tr>';
print '<tr><td>Max payload size (bytes)</td><td><input type="number" name="max_payload_size" min="1024" value="'.dol_escape_htmltag($conf->global->USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE ?: 65536).'"> <em>Maximum size of JSON payload</em></td></tr>';

print '<tr class="liste_titre"><td colspan="2">Webhook Settings</td></tr>';
print '<tr><td>Webhook URL</td><td><input type="url" name="webhook" size="80" placeholder="https://your-webhook-endpoint.com/webhook" value="'.dol_escape_htmltag($conf->global->USERACTIVITYTRACKER_WEBHOOK_URL).'"></td></tr>';
print '<tr><td>Webhook Secret</td><td><input type="text" name="secret" size="40" placeholder="Optional secret for HMAC signature" value="'.dol_escape_htmltag($conf->global->USERACTIVITYTRACKER_WEBHOOK_SECRET).'"></td></tr>';

print '<tr class="liste_titre"><td colspan="2">Security & Monitoring</td></tr>';
print '<tr><td>Enable anomaly detection</td><td><input type="checkbox" name="anomaly" '.(!empty($conf->global->USERACTIVITYTRACKER_ENABLE_ANOMALY)?'checked':'').'> <em>Detect suspicious activity patterns</em></td></tr>';

print '</table>';
print '<div class="center" style="margin: 20px 0;">';
print '<input type="submit" class="button button-save" value="Save Settings">';
print '</div>';
print '</form>';

// Action buttons
print '<div class="center">';
print '<form method="post" style="display: inline-block; margin: 0 10px;"><input type="hidden" name="action" value="testwebhook">';
print '<input type="submit" class="button" value="Test Webhook"'.(!empty($conf->global->USERACTIVITYTRACKER_WEBHOOK_URL)?'':' disabled title="Configure webhook URL first"').'>';
print '</form>';

print '<form method="post" style="display: inline-block; margin: 0 10px;"><input type="hidden" name="action" value="cleanup">';
print '<input type="submit" class="button" value="Manual Cleanup" onclick="return confirm(\'This will permanently delete old activity records. Continue?\')">';
print '</form>';

print '<form method="post" style="display: inline-block; margin: 0 10px;"><input type="hidden" name="action" value="analyze_anomalies">';
print '<input type="submit" class="button" value="Analyze Anomalies"'.(!empty($conf->global->USERACTIVITYTRACKER_ENABLE_ANOMALY)?'':' disabled title="Enable anomaly detection first"').'>';
print '</form>';
print '</div>';

// System information
print '<br><div class="div-table-responsive-no-min">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><td colspan="2">System Information</td></tr>';

// Get database stats
$activity = new UserActivity($db);
$stats = $activity->getActivityStats(
    dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d'),
    dol_print_date(dol_now(), '%Y-%m-%d'),
    $conf->entity
);

print '<tr><td width="300">Module Version</td><td>1.0.0</td></tr>';
print '<tr><td>Total Activities (last 30 days)</td><td>'.$stats['total'].'</td></tr>';
print '<tr><td>Unique Actions (last 30 days)</td><td>'.count($stats['by_action']).'</td></tr>';
print '<tr><td>Active Users (last 30 days)</td><td>'.count($stats['by_user']).'</td></tr>';
print '<tr><td>PHP Version</td><td>'.phpversion().'</td></tr>';
print '<tr><td>cURL Available</td><td>'.(function_exists('curl_init')?'Yes':'No').'</td></tr>';
print '<tr><td>Current Retention</td><td>'.$days.' days</td></tr>';
print '</table>';
print '</div>';

llxFooter();
