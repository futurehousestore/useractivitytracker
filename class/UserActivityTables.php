<?php
class UserActivityTables
{
    /** ex: llx_useractivitytracker_activity */
    public static function activity(\DoliDB $db): string { return $db->prefix().'useractivitytracker_activity'; }
    /** ex: llx_useractivitytracker_log */
    public static function log(\DoliDB $db): string { return $db->prefix().'useractivitytracker_log'; }
}
