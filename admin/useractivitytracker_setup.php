
<?php
/**
 * Setup page
 * Path: custom/useractivitytracker/admin/useractivitytracker_setup.php
 * Version: 2025-09-27.beta-1
 */
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

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
    setEventMessage('Saved');
}
elseif ($action=='testwebhook' && ! empty($conf->global->USERACTIVITYTRACKER_WEBHOOK_URL))
{
    $url = $conf->global->USERACTIVITYTRACKER_WEBHOOK_URL;
    $data = json_encode(array('test'=>true,'date'=>dol_print_date(dol_now(),'standard'),'entity'=>$conf->entity));
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $out = curl_exec($ch);
        curl_close($ch);
        setEventMessage('Webhook sent. Response (truncated): '.dol_trunc($out, 120));
    } else {
        setEventMessage('cURL not available', 'warnings');
    }
}

// Opportunistic retention cleanup
$days = (int)($conf->global->USERACTIVITYTRACKER_RETENTION_DAYS ?: 365);
$db->query("DELETE FROM ".$db->prefix()."alt_user_activity WHERE datestamp < DATE_SUB(NOW(), INTERVAL ".((int)$days)." DAY) AND entity=".(int)$conf->entity);

llxHeader('', 'User Activity Tracker — Setup');
print load_fiche_titre('User Activity Tracker — Setup');

print '<form method="post">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><td>Setting</td><td>Value</td></tr>';
print '<tr><td>Retention (days)</td><td><input type="number" name="retention" value="'.dol_escape_htmltag($days).'"></td></tr>';
print '<tr><td>Webhook URL</td><td><input type="text" name="webhook" size="80" value="'.dol_escape_htmltag($conf->global->USERACTIVITYTRACKER_WEBHOOK_URL).'"></td></tr>';
print '<tr><td>Webhook Secret</td><td><input type="text" name="secret" size="40" value="'.dol_escape_htmltag($conf->global->USERACTIVITYTRACKER_WEBHOOK_SECRET).'"></td></tr>';
print '<tr><td>Enable anomaly heuristics</td><td><input type="checkbox" name="anomaly" '.(!empty($conf->global->USERACTIVITYTRACKER_ENABLE_ANOMALY)?'checked':'').'></td></tr>';
print '</table>';
print '<div class="center">';
print '<input type="submit" class="button button-save" value="Save">';
print '</div>';
print '</form>';

print '<form method="post" style="margin-top: 1em;"><input type="hidden" name="action" value="testwebhook">';
print '<div class="center"><input type="submit" class="button" value="Test Webhook"></div>';
print '</form>';

llxFooter();
