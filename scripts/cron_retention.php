<?php
/**
 * Cron script for User Activity Tracker retention cleanup
 * Path: custom/useractivitytracker/scripts/cron_retention.php
 * Version: 2.8.0 — purge rows older than UAT_RETENTION_DAYS
 * 
 * Usage: php cron_retention.php [--dry-run]
 * 
 * Can be scheduled via:
 * - System crontab: 0 2 * * * php /path/to/htdocs/custom/useractivitytracker/scripts/cron_retention.php
 * - Dolibarr cron module
 */

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = __DIR__ . '/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
    echo "Error: You are using PHP for CGI. To execute " . $script_file . " from command line, you must use PHP for CLI mode.\n";
    exit(1);
}

// Locate main.inc.php
$main = null;
$dir = __DIR__;
for ($i = 0; $i < 10; $i++) {
    $candidate = $dir . '/main.inc.php';
    if (is_file($candidate)) {
        $main = $candidate;
        break;
    }
    $dir = dirname($dir);
}

if (!$main) {
    // Try common paths
    $paths = array(
        __DIR__ . '/../../main.inc.php',
        __DIR__ . '/../../../main.inc.php',
        __DIR__ . '/../../../../main.inc.php'
    );
    foreach ($paths as $p) {
        if (is_file($p)) {
            $main = $p;
            break;
        }
    }
}

if (!$main) {
    print "Error: Unable to locate Dolibarr main.inc.php\n";
    exit(1);
}

require $main;

// Check if module is enabled
if (empty($conf->useractivitytracker->enabled)) {
    print "Error: User Activity Tracker module is not enabled\n";
    exit(1);
}

// Check master switch
if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) {
    print "Info: Master tracking switch is OFF, retention cleanup skipped\n";
    exit(0);
}

// Parse arguments
$dry_run = false;
if (!empty($argv)) {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run' || $arg === '-d') {
            $dry_run = true;
        }
    }
}

// Get retention period
$retention_days = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
if ($retention_days < 1) {
    print "Error: Invalid retention period: $retention_days days\n";
    exit(1);
}

print "User Activity Tracker - Retention Cleanup\n";
print "==========================================\n";
print "Retention period: $retention_days days\n";
print "Mode: " . ($dry_run ? "DRY RUN (no changes)" : "LIVE") . "\n";
print "\n";

$table = $db->prefix() . 'alt_user_activity';

// Count records to delete
$sql = "SELECT COUNT(*) as total FROM " . $table . " 
        WHERE datestamp < DATE_SUB(NOW(), INTERVAL " . (int)$retention_days . " DAY)";
$res = $db->query($sql);

if (!$res) {
    print "Error: Failed to count records: " . $db->lasterror() . "\n";
    exit(1);
}

$obj = $db->fetch_object($res);
$count = (int)$obj->total;
$db->free($res);

print "Records older than $retention_days days: $count\n";

if ($count === 0) {
    print "Nothing to delete.\n";
    exit(0);
}

if ($dry_run) {
    print "\nDRY RUN: Would delete $count records\n";
    
    // Show sample of what would be deleted
    $sql = "SELECT rowid, datestamp, action, username FROM " . $table . " 
            WHERE datestamp < DATE_SUB(NOW(), INTERVAL " . (int)$retention_days . " DAY)
            ORDER BY datestamp ASC LIMIT 10";
    $res = $db->query($sql);
    
    if ($res) {
        print "\nSample of records to delete (first 10):\n";
        print "----------------------------------------\n";
        while ($obj = $db->fetch_object($res)) {
            print sprintf("ID: %d, Date: %s, Action: %s, User: %s\n",
                $obj->rowid,
                $obj->datestamp,
                $obj->action,
                $obj->username ?? '(none)'
            );
        }
        $db->free($res);
    }
    
    print "\nRun without --dry-run to actually delete these records.\n";
} else {
    // Perform deletion per entity
    $entities = array();
    
    // Get all entities with old records
    $sql = "SELECT DISTINCT entity FROM " . $table . " 
            WHERE datestamp < DATE_SUB(NOW(), INTERVAL " . (int)$retention_days . " DAY)";
    $res = $db->query($sql);
    
    if ($res) {
        while ($obj = $db->fetch_object($res)) {
            $entities[] = (int)$obj->entity;
        }
        $db->free($res);
    }
    
    print "\nDeleting records from " . count($entities) . " entities...\n";
    
    $total_deleted = 0;
    foreach ($entities as $entity) {
        $sql = "DELETE FROM " . $table . " 
                WHERE entity = " . (int)$entity . " 
                AND datestamp < DATE_SUB(NOW(), INTERVAL " . (int)$retention_days . " DAY)";
        
        $res = $db->query($sql);
        if ($res) {
            $deleted = $db->affected_rows($res);
            $total_deleted += $deleted;
            print "Entity $entity: Deleted $deleted records\n";
        } else {
            print "Entity $entity: Error - " . $db->lasterror() . "\n";
        }
    }
    
    print "\nTotal deleted: $total_deleted records\n";
    print "Cleanup completed successfully.\n";
}

exit(0);
