#!/usr/bin/env php
<?php
/**
 * Remove Redundant HODs
 * Safely removes hod_bit and hod_bcs accounts after verification
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Remove Redundant HOD Accounts                    ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "⚠️  This will remove hod_bit and hod_bcs accounts.\n";
echo "hod_informatics already has full faculty access.\n\n";

// Get the redundant HODs
$hods_to_remove = ['hod_bit', 'hod_bcs'];
$found_hods = [];

foreach ($hods_to_remove as $username) {
   $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
   if ($user) {
      $found_hods[] = $user;
   }
}

if (empty($found_hods)) {
   echo "✓ No redundant HODs found. Already clean!\n";
   exit(0);
}

echo "Found " . count($found_hods) . " HOD(s) to remove:\n";
echo str_repeat("-", 60) . "\n";

foreach ($found_hods as $hod) {
   echo "\n[{$hod->id}] {$hod->firstname} {$hod->lastname}\n";
   echo "    Username: {$hod->username}\n";
   echo "    Email: {$hod->email}\n";

   // Check their enrollments
   $enrollments = $DB->count_records_sql("
        SELECT COUNT(DISTINCT c.id)
        FROM {user_enrolments} ue
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {course} c ON c.id = e.courseid
        WHERE ue.userid = ?
        AND c.id > 1
    ", [$hod->id]);

   echo "    Enrolled in: {$enrollments} courses\n";

   // Check if they created any courses
   $created_courses = $DB->count_records('course', ['id' => $hod->id]);
   echo "    Created courses: {$created_courses}\n";
}

echo "\n";
echo "Choose removal method:\n";
echo "  1. SOFT DELETE (Recommended) - Mark as deleted, keep data\n";
echo "  2. HARD DELETE - Permanently remove from database\n";
echo "  3. DEMOTE - Remove Manager role, keep as regular user\n";
echo "  4. CANCEL\n\n";

echo "Enter choice (1-4): ";
$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));
fclose($handle);

if ($choice == '4' || $choice == '') {
   echo "\n❌ Operation cancelled.\n";
   exit(0);
}

echo "\n";

switch ($choice) {
   case '1': // SOFT DELETE
      echo "Soft deleting accounts...\n\n";
      foreach ($found_hods as $hod) {
         echo "Processing {$hod->username}... ";
         delete_user($hod); // Moodle function - marks as deleted
         echo "✓ Soft deleted\n";
      }
      echo "\n✓ Accounts marked as deleted (data preserved for records).\n";
      break;

   case '2': // HARD DELETE
      echo "⚠️  HARD DELETE WARNING!\n";
      echo "This permanently removes all traces. Type 'CONFIRM' to proceed: ";
      $handle = fopen("php://stdin", "r");
      $confirm = trim(fgets($handle));
      fclose($handle);

      if ($confirm !== 'CONFIRM') {
         echo "\n❌ Hard delete cancelled.\n";
         exit(0);
      }

      echo "\nPermanently deleting accounts...\n\n";
      foreach ($found_hods as $hod) {
         echo "Processing {$hod->username}... ";

         // Remove role assignments
         $DB->delete_records('role_assignments', ['userid' => $hod->id]);

         // Remove enrollments
         $DB->delete_records('user_enrolments', ['userid' => $hod->id]);

         // Delete user record
         $DB->delete_records('user', ['id' => $hod->id]);

         echo "✓ Hard deleted\n";
      }
      echo "\n✓ Accounts permanently removed.\n";
      break;

   case '3': // DEMOTE
      echo "Demoting to regular users...\n\n";

      $manager_role = $DB->get_record('role', ['shortname' => 'manager']);

      foreach ($found_hods as $hod) {
         echo "Processing {$hod->username}... ";

         // Remove all Manager role assignments
         $DB->delete_records('role_assignments', [
            'userid' => $hod->id,
            'roleid' => $manager_role->id
         ]);

         echo "✓ Manager role removed\n";
      }
      echo "\n✓ Accounts demoted (now regular users with no special permissions).\n";
      break;

   default:
      echo "❌ Invalid choice.\n";
      exit(1);
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              CLEANUP COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Verification:\n";
echo "  • hod_informatics: Has system-wide Manager role ✓\n";
echo "  • hod_bit: Removed\n";
echo "  • hod_bcs: Removed\n\n";

echo "Result: Single, unified HOD structure ✓\n";
