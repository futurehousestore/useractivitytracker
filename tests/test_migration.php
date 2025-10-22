<?php
// Test for migration logic in InterfaceUserActivityTrackerTrigger
// To run: php tests/test_migration.php

// Mock Dolibarr environment
define('DOL_DOCUMENT_ROOT', __DIR__ . '/..');

class DoliDB
{
    public $database_specific_columns;
    public $last_query;

    public function __construct() {}

    public function DDLDescTable($tableName)
    {
        if ($tableName === 'llx_alt_user_activity') {
            $this->database_specific_columns = [
                'rowid' => [],
                'datestamp' => [],
                'userid' => [],
                'username' => [],
                'action' => [],
                'object_id' => [],
                'payload' => [],
            ];
            return 1;
        } elseif ($tableName === 'llx_useractivitytracker_activity') {
            $this->database_specific_columns = [
                'rowid' => [],
                'datestamp' => [],
                'fk_user' => [],
                'username' => [],
                'action' => [],
                'element_id' => [],
                'ua' => [],
            ];
            return 1;
        }
        return -1;
    }

    public function prefix()
    {
        return 'llx_';
    }

    public function query($sql) {
        $this->last_query = $sql;
    }
}


// Include the trigger class
require_once DOL_DOCUMENT_ROOT . '/core/triggers/interface_99_modUserActivityTracker_Trigger.class.php';

// Test case
$db = new DoliDB();

// Create a reflection of the migrateLegacy method to test it
$reflectionMethod = new ReflectionMethod('InterfaceUserActivityTrackerTrigger', 'migrateLegacy');
$reflectionMethod->setAccessible(true);

// Call the method with the mock DB object
$reflectionMethod->invoke(null, $db);

// Assertion
$expected_query = "INSERT IGNORE INTO llx_useractivitytracker_activity (`datestamp`,`username`,`action`) SELECT `datestamp`,`username`,`action` FROM llx_alt_user_activity";
if (str_replace(' ', '', $db->last_query) !== str_replace(' ', '', $expected_query)) {
    echo "Assertion failed!\n";
    echo "Expected: " . $expected_query . "\n";
    echo "Got: " . $db->last_query . "\n";
    exit(1);
}

echo "Test completed successfully!\n";
