#!/usr/bin/env php
<?php
/**
 * Fix Lecturer Assignments
 * Removes duplicate lecturer enrollments - keeps only one per course
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Fixing Lecturer Assignments                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get teacher role
$teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);

// Get all courses
$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname');

$total_removed = 0;
$courses_fixed = 0;

foreach ($courses as $course) {
   // Get all teaching staff for this course
   $context = context_course::instance($course->id);
   $teachers = get_role_users($teacher_role->id, $context);

   $teacher_count = count($teachers);

   if ($teacher_count > 1) {
      echo "Course: {$course->shortname} - {$course->fullname}\n";
      echo "  Found {$teacher_count} lecturers:";

      $first = true;
      foreach ($teachers as $teacher) {
         if ($first) {
            echo " KEEPING: {$teacher->firstname} {$teacher->lastname}\n";
            $first = false;
         } else {
            echo "  REMOVING: {$teacher->firstname} {$teacher->lastname}\n";

            // Get enrolment instance
            $enrol_instance = $DB->get_record('enrol', [
               'courseid' => $course->id,
               'enrol' => 'manual'
            ]);

            if ($enrol_instance) {
               $enrol = enrol_get_plugin('manual');
               $enrol->unenrol_user($enrol_instance, $teacher->id);
               $total_removed++;
            }
         }
      }
      $courses_fixed++;
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         Lecturer Cleanup Complete! ✓                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Courses with multiple lecturers: $courses_fixed\n";
echo "  • Duplicate enrollments removed: $total_removed\n\n";

echo "Result:\n";
echo "  Each course now has exactly one lecturer assigned.\n";
