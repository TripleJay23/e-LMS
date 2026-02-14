#!/usr/bin/env php
<?php
/**
 * Unenrol Users from Deprecated Courses
 * Removes all user enrollments from courses marked as OLD or DEPRECATED
 * This removes them from users' "My Courses" dashboard
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Unenrol Users from Deprecated Courses            ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Find Deprecated Courses
echo "Finding deprecated courses...\n";
$courses = $DB->get_records_sql("
    SELECT * FROM {course} 
    WHERE shortname LIKE '%-OLD' 
    OR fullname LIKE '%(DEPRECATED)%'
    OR category = (SELECT id FROM {course_categories} WHERE idnumber = 'ARCHIVE')
");

$count = count($courses);

if ($count === 0) {
   echo "No deprecated courses found.\n";
   exit(0);
}

echo "Found {$count} deprecated courses.\n\n";

$total_unenrolled = 0;

foreach ($courses as $course) {
   echo "Processing: {$course->shortname}... ";

   // Get all enrollments
   $enrols = enrol_get_instances($course->id, true);

   if (empty($enrols)) {
      echo "No enrollment methods found.\n";
      continue;
   }

   $course_unenrolled = 0;

   foreach ($enrols as $instance) {
      // Get plugin
      $plugin = enrol_get_plugin($instance->enrol);
      if (!$plugin) continue;

      // Get all enrolled users
      $users = $DB->get_records_sql("
            SELECT u.id, u.username, u.firstname, u.lastname
            FROM {user_enrolments} ue
            JOIN {user} u ON ue.userid = u.id
            WHERE ue.enrolid = ?
        ", [$instance->id]);

      foreach ($users as $user) {
         $plugin->unenrol_user($instance, $user->id);
         $course_unenrolled++;
      }
   }

   if ($course_unenrolled > 0) {
      echo "Removed {$course_unenrolled} enrollments ✓\n";
      $total_unenrolled += $course_unenrolled;
   } else {
      echo "No active enrollments.\n";
   }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              CLEANUP COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Deprecated courses scanned: $count\n";
echo "  • Total users unenrolled: $total_unenrolled\n\n";
echo "These courses should now be gone from 'My Courses' dashboard.\n";
