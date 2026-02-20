<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Profile Field Cleanup & Data Migration           ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Delete Unused Fields
echo "Step 1: Deleting unused duplicate fields...\n";
$unused_fields = ['program', 'year']; // Shortnames
foreach ($unused_fields as $shortname) {
   if ($field = $DB->get_record('user_info_field', ['shortname' => $shortname])) {
      // Delete data associated (should be 0 based on check)
      $DB->delete_records('user_info_data', ['fieldid' => $field->id]);
      $DB->delete_records('user_info_field', ['id' => $field->id]);
      echo "✓ Deleted field: $shortname (ID: $field->id)\n";
   } else {
      echo "• Field '$shortname' not found (already deleted?)\n";
   }
}
echo "\n";

// 2. Data Migration
echo "Step 2: Migrating User Data from Enrolments...\n";

// Get target fields
$progField = $DB->get_record('user_info_field', ['shortname' => 'program_study']);
$yearField = $DB->get_record('user_info_field', ['shortname' => 'year_of_study']);

if (!$progField || !$yearField) {
   echo "✗ Error: Target fields 'program_study' or 'year_of_study' not found.\n";
   exit(1);
}

// Get all users (excluding guest, deleted, admin)
$users = $DB->get_records_select('user', "deleted = 0 AND id > 2 AND username != 'guest'");
$count = 0;
$updated = 0;

foreach ($users as $user) {
   // Check if data already exists
   $hasProg = $DB->record_exists('user_info_data', ['userid' => $user->id, 'fieldid' => $progField->id]);
   $hasYear = $DB->record_exists('user_info_data', ['userid' => $user->id, 'fieldid' => $yearField->id]);

   if ($hasProg && $hasYear) {
      continue; // Skip if already has data
   }

   // Get enrolled courses
   $courses = enrol_get_users_courses($user->id);
   if (empty($courses)) {
      continue;
   }

   // Infer Program and Year
   $programCounts = [];
   $yearCounts = [];

   foreach ($courses as $course) {
      // Check custom_program_courses link
      $link = $DB->get_record('custom_program_courses', ['courseid' => $course->id]);
      if ($link) {
         $prog = $DB->get_record('custom_programs', ['id' => $link->programid]);
         if ($prog) {
            $programCounts[$prog->acronym] = ($programCounts[$prog->acronym] ?? 0) + 1;
            $yearCounts[$link->year] = ($yearCounts[$link->year] ?? 0) + 1;
         }
      } else {
         // Fallback: Check shortname pattern (e.g. "ITU 07106-BIT")
         if (preg_match('/-(BIT|BCS)$/', $course->shortname, $matches)) {
            $p = $matches[1];
            $programCounts[$p] = ($programCounts[$p] ?? 0) + 1;
            // Year inference from shortname difficult without mapping, rely on custom_program_courses mainly
         }
      }
   }

   if (empty($programCounts)) {
      continue;
   }

   // Get dominant program/year
   arsort($programCounts);
   $bestProgram = key($programCounts);

   arsort($yearCounts);
   $bestYear = key($yearCounts);

   if ($bestProgram) {
      // Update Program
      if (!$hasProg) {
         $data = new stdClass();
         $data->userid = $user->id;
         $data->fieldid = $progField->id;
         $data->data = $bestProgram;
         $data->dataformat = 0;
         $DB->insert_record('user_info_data', $data);
         echo "• User {$user->username}: Set Program = $bestProgram\n";
      }

      // Update Year (default to 1 if not found but program found? Or leave empty?)
      if (!$hasYear && $bestYear) {
         $data = new stdClass();
         $data->userid = $user->id;
         $data->fieldid = $yearField->id;
         $data->data = $bestYear;
         $data->dataformat = 0;
         $DB->insert_record('user_info_data', $data);
         echo "• User {$user->username}: Set Year = $bestYear\n";
      }
      $updated++;
   }
}

echo "\nMigration Complete.\n";
echo "Total users processed: " . count($users) . "\n";
echo "Users updated: $updated\n";
