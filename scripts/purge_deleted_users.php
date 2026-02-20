#!/usr/bin/env php
<?php
/**
 * Purge Deleted Users
 * Permanently deletes users who are currently soft-deleted (deleted=1)
 * WARNING: This is destructive and irreversible.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Purging Deleted Users (Hard Delete)              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get count before
$count_before = $DB->count_records('user', ['deleted' => 1]);
echo "Found {$count_before} soft-deleted users.\n";

if ($count_before === 0) {
   echo "No users to purge.\n";
   exit(0);
}

// Get IDs of deleted users
$deleted_users = $DB->get_records('user', ['deleted' => 1], '', 'id, username');
$deleted_ids = array_keys($deleted_users);

echo "Purging...\n";

// Transaction
$transaction = $DB->start_delegated_transaction();

try {
   // 1. Delete from {user} table
   $DB->delete_records_list('user', 'id', $deleted_ids);

   // 2. Cleanup orphans (Basic) - Moodle usually does this on soft-delete, but safe to check
   $DB->delete_records_list('role_assignments', 'userid', $deleted_ids);
   $DB->delete_records_list('user_enrolments', 'userid', $deleted_ids);
   $DB->delete_records_list('user_lastaccess', 'userid', $deleted_ids);
   $DB->delete_records_list('user_preferences', 'userid', $deleted_ids);

   // Commit
   $transaction->allow_commit();
   echo "✓ Successfully purged {$count_before} users from the database.\n";
} catch (Exception $e) {
   $transaction->rollback($e);
   echo "❌ Error during purge: " . $e->getMessage() . "\n";
   exit(1);
}

// Verify
$count_after = $DB->count_records('user', ['deleted' => 1]);
if ($count_after === 0) {
   echo "\nVerification: 0 soft-deleted users remaining.\n";
} else {
   echo "\nWarning: {$count_after} users still remain.\n";
}

echo "\nDone!\n";
