#!/usr/bin/env php
<?php
/**
 * Cross-Enroll Students in Shared Courses (Legacy)
 * Enrolls BIT students in BCS versions and vice versa when duplicate -BIT/-BCS
 * course copies exist. Prefer normalize_shared_course_links.php for -SHARED.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/module_catalog.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Cross-Enrollment for Shared Courses              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Read shared modules from canonical list.
try {
   $modules = load_module_catalog(__DIR__ . '/modules_corrected.json');
} catch (RuntimeException $e) {
   echo "ERROR: " . $e->getMessage() . "\n";
   exit(1);
}

$groups = split_modules_by_program($modules);
$shared_modules = $groups['shared'];

echo "Found " . count($shared_modules) . " shared modules to process.\n\n";

$student_role = $DB->get_record('role', ['shortname' => 'student']);
$total_enrollments = 0;
$skipped = 0;

foreach ($shared_modules as $module) {
   $code = $module['code'];
   $name = $module['name'];

   // Find BIT and BCS versions
   $bit_course = $DB->get_record('course', ['shortname' => $code . '-BIT']);
   $bcs_course = $DB->get_record('course', ['shortname' => $code . '-BCS']);

   if (!$bit_course || !$bcs_course) {
      echo "⚠ Skipping {$code}: Missing course version\n";
      continue;
   }

   echo "Processing: {$name} ({$code})\n";

   // Get enrol instances
   $bit_enrol = $DB->get_record('enrol', ['courseid' => $bit_course->id, 'enrol' => 'manual']);
   $bcs_enrol = $DB->get_record('enrol', ['courseid' => $bcs_course->id, 'enrol' => 'manual']);

   if (!$bit_enrol || !$bcs_enrol) {
      echo "  ⚠ Missing enrol instance\n";
      continue;
   }

   $enrol_plugin = enrol_get_plugin('manual');

   // 1. Get BIT students → Enroll in BCS
   $bit_enrollments = $DB->get_records_sql(
      "SELECT ue.userid 
         FROM {user_enrolments} ue
         JOIN {enrol} e ON e.id = ue.enrolid
         JOIN {role_assignments} ra ON ra.userid = ue.userid
         JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE e.courseid = ? 
         AND ctx.contextlevel = 50 
         AND ctx.instanceid = ?
         AND ra.roleid = ?",
      [$bit_course->id, $bit_course->id, $student_role->id]
   );

   $enrolled_to_bcs = 0;
   foreach ($bit_enrollments as $enrollment) {
      // Check if already enrolled in BCS
      $exists = $DB->record_exists('user_enrolments', [
         'enrolid' => $bcs_enrol->id,
         'userid' => $enrollment->userid
      ]);

      if (!$exists) {
         $enrol_plugin->enrol_user($bcs_enrol, $enrollment->userid, $student_role->id);
         $enrolled_to_bcs++;
         $total_enrollments++;
      } else {
         $skipped++;
      }
   }

   // 2. Get BCS students → Enroll in BIT
   $bcs_enrollments = $DB->get_records_sql(
      "SELECT ue.userid 
         FROM {user_enrolments} ue
         JOIN {enrol} e ON e.id = ue.enrolid
         JOIN {role_assignments} ra ON ra.userid = ue.userid
         JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE e.courseid = ? 
         AND ctx.contextlevel = 50 
         AND ctx.instanceid = ?
         AND ra.roleid = ?",
      [$bcs_course->id, $bcs_course->id, $student_role->id]
   );

   $enrolled_to_bit = 0;
   foreach ($bcs_enrollments as $enrollment) {
      // Check if already enrolled in BIT
      $exists = $DB->record_exists('user_enrolments', [
         'enrolid' => $bit_enrol->id,
         'userid' => $enrollment->userid
      ]);

      if (!$exists) {
         $enrol_plugin->enrol_user($bit_enrol, $enrollment->userid, $student_role->id);
         $enrolled_to_bit++;
         $total_enrollments++;
      } else {
         $skipped++;
      }
   }

   echo "  ✓ BIT→BCS: {$enrolled_to_bcs} students | BCS→BIT: {$enrolled_to_bit} students\n";
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         Cross-Enrollment Complete! ✓                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • New enrollments: $total_enrollments\n";
echo "  • Already enrolled (skipped): $skipped\n\n";

echo "Result:\n";
echo "  Lecturers can now upload materials to EITHER -BIT or -BCS version.\n";
echo "  All students will see the content!\n";
