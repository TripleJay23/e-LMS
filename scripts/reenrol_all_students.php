#!/usr/bin/env php
<?php
/**
 * Re-enrol All Students Based on custom_program_courses
 *
 * Triggers the same logic as the observer.php enrolment handler for every
 * student, so they are enrolled in ALL courses for their program+year —
 * including the newly registered shared courses added by rebuild_shared_bcs_cpc.php.
 *
 * Safe to re-run: already-enrolled students are skipped.
 *
 * Run: php scripts/reenrol_all_students.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Re-enrol All Students                               ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── Get manual enrol plugin ───────────────────────────────────────────────────
$enrol_plugin = enrol_get_plugin('manual');
if (!$enrol_plugin) {
   die("Error: Manual enrolment plugin not available.\n");
}

$student_role = $DB->get_record('role', ['shortname' => 'student']);
$roleid       = $student_role ? $student_role->id : 5;

// ── Get all confirmed, non-admin student-ish users ────────────────────────────
// We select users who have a program profile field set (i.e. actual students)
$users = $DB->get_records_select('user', "id > 1 AND confirmed = 1 AND deleted = 0");

$enrolled_total = 0;
$skipped_total  = 0;
$user_count     = 0;

foreach ($users as $user) {
   // Load custom profile fields
   profile_load_data($user);

   $program_acronym = $user->profile_field_program_study  ?? null;
   $year            = $user->profile_field_year_of_study  ?? null;

   if (!$program_acronym || !$year) {
      continue; // Not a student with program info
   }

   // Get program
   $program = $DB->get_record('custom_programs', ['acronym' => $program_acronym]);
   if (!$program) {
      continue;
   }

   // Get all courses for this program + year
   $links = $DB->get_records('custom_program_courses', [
      'programid' => $program->id,
      'year'      => $year,
   ]);

   if (!$links) {
      continue;
   }

   $user_count++;
   $user_enrolled = 0;

   foreach ($links as $link) {
      $course = $DB->get_record('course', ['id' => $link->courseid]);
      if (!$course) {
         continue;
      }

      $context = context_course::instance($course->id);

      if (is_enrolled($context, $user->id)) {
         $skipped_total++;
         continue;
      }

      // Get or create enrol instance
      $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
      if (!$instance) {
         $instanceid = $enrol_plugin->add_instance($course);
         $instance   = $DB->get_record('enrol', ['id' => $instanceid]);
      }

      $enrol_plugin->enrol_user($instance, $user->id, $roleid);
      $user_enrolled++;
      $enrolled_total++;
   }

   if ($user_enrolled > 0) {
      echo "  {$user->username} [{$program_acronym} Y{$year}]: +{$user_enrolled} new enrolment(s)\n";
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  Done!                                                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\n  • Students processed : $user_count\n";
echo "  • New enrolments     : $enrolled_total\n";
echo "  • Already enrolled   : $skipped_total\n\n";
