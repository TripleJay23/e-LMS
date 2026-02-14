#!/usr/bin/env php
<?php
/**
 * Cross-Enroll Students in Shared Courses
 * Enrolls BIT students in BCS versions and vice versa
 * Solves the duplicate upload problem
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Cross-Enrollment for Shared Courses              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Read shared modules
$json = file_get_contents(__DIR__ . '/../modules_categorized.json');
$data = json_decode($json, true);
$shared_modules = $data['shared'];

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
