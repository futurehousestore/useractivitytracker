<?php
/**
 * Constants for User Activity Tracker
 * Path: custom/useractivitytracker/class/constants.php
 * Version: 2.8.0 — centralized action and severity constants
 */

// Action type constants
define('UAT_ACTION_LOGIN', 'USER_LOGIN');
define('UAT_ACTION_LOGOUT', 'USER_LOGOUT');
define('UAT_ACTION_LOGIN_FAILED', 'USER_LOGIN_FAILED');
define('UAT_ACTION_PAGE_VIEW', 'PAGE_VIEW');
define('UAT_ACTION_PAGE_TIME', 'PAGE_TIME');
define('UAT_ACTION_CREATE', 'CREATE');
define('UAT_ACTION_MODIFY', 'MODIFY');
define('UAT_ACTION_DELETE', 'DELETE');
define('UAT_ACTION_VALIDATE', 'VALIDATE');
define('UAT_ACTION_CANCEL', 'CANCEL');

// Severity constants
define('UAT_SEVERITY_INFO', 'info');
define('UAT_SEVERITY_NOTICE', 'notice');
define('UAT_SEVERITY_WARNING', 'warning');
define('UAT_SEVERITY_ERROR', 'error');

// Allowed severity values (for validation)
$UAT_ALLOWED_SEVERITY = array(
    UAT_SEVERITY_INFO,
    UAT_SEVERITY_NOTICE,
    UAT_SEVERITY_WARNING,
    UAT_SEVERITY_ERROR
);

// Capture modes
define('UAT_CAPTURE_OFF', 'off');
define('UAT_CAPTURE_TRUNCATED', 'truncated');
define('UAT_CAPTURE_FULL', 'full');
