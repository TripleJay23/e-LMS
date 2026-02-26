#!/usr/bin/env php
<?php
/**
 * Cleanup System Script
 * 1. Deletes BTCIT, DCS, and DIT categories
 * 2. Deletes related custom program records
 * 3. Unenrols HOD from all individual courses (Option B)
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║             e-LMS System Cleanup                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Delete Redundant Categories ───────────────────────────────────────────
echo "1. Deleting redundant categories...\n";
$to_delete = ['BTCIT', 'DCS', 'DIT'];

foreach ($to_delete as $idnumber) {
   $cat = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
   if ($cat) {
      try {
         $category = core_course_category::get($cat->id);
         $category->delete_full(false); // Move contents to default? No, delete full.
         echo "   ✓ Deleted category: $idnumber (ID: {$cat->id})\n";
      } catch (Exception $e) {
         echo "   ✗ Error deleting $idnumber: " . $e->getMessage() . "\n";
      }
   } else {
      echo "   • Category $idnumber not found, skipping.\n";
   }
}

// ── 2. Delete Custom Program Records ─────────────────────────────────────────
echo "\n2. Deleting custom program records...\n";
foreach ($to_delete as $acronym) {
   if ($DB->record_exists('custom_programs', ['acronym' => $acronym])) {
      $DB->delete_records('custom_programs', ['acronym' => $acronym]);
      echo "   ✓ Deleted program record: $acronym\n";
   } else {
      echo "   • Program record $acronym not found.\n";
   }
}

// ── 3. Unenrol HOD from all courses (Option B) ───────────────────────────────
echo "\n3. Unenrolling HOD from individual courses...\n";
$hod = $DB->get_record('user', ['username' => 'hod_informatics']);

if ($hod) {
   $enrolments = $DB->get_records_sql("
        SELECT ue.id, ue.enrolid, e.courseid, e.enrol
        FROM {user_enrolments} ue
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE ue.userid = ?
    ", [$hod->id]);

   echo "   Found " . count($enrolments) . " enrolments to remove.\n";

   foreach ($enrolments as $ue) {
      $plugin = enrol_get_plugin($ue->enrol);
      $instance = $DB->get_record('enrol', ['id' => $ue->enrolid]);
      if ($plugin && $instance) {
         $plugin->unenrol_user($instance, $hod->id);
         // echo "     - Unenrolled from course ID {$ue->courseid}\n";
      }
   }
   echo "   ✓ HOD dashboard should now be clean.\n";
} else {
   echo "   • hod_informatics user not found.\n";
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║              Cleanup Complete! ✓                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
