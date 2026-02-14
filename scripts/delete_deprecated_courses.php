#!/usr/bin/env php
<?php
/**
 * Delete Deprecated Courses
 * Removes all course with -OLD suffix or DEPRECATED in name
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Deleting Deprecated Courses                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Find deprecated courses
$courses = $DB->get_records_sql("
    SELECT * FROM {course} 
    WHERE shortname LIKE '%-OLD' 
    OR fullname LIKE '%(DEPRECATED)%'
");

$count = count($courses);

if ($count === 0) {
   echo "No deprecated courses found.\n";
   exit(0);
}

echo "Found {$count} deprecated courses to delete.\n";

$deleted = 0;

foreach ($courses as $course) {
   echo "Deleting: {$course->shortname} - {$course->fullname}... ";

   try {
      delete_course($course, false);
      echo "✓ OK\n";
      $deleted++;
   } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
   }
}

echo "\nSummary:\n";
echo "  • Total found: $count\n";
echo "  • Total deleted: $deleted\n";
