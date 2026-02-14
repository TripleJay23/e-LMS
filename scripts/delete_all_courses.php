#!/usr/bin/env php
<?php
/**
 * Delete All Courses (Cleanup Script)
 * Deletes all courses except the site course (ID 1)
 * Used before refactoring course structure
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Deleting All Courses                             ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$courses = $DB->get_records_select('course', 'id > 1');
echo "Found " . count($courses) . " courses to delete.\n";

foreach ($courses as $course) {
   echo "Deleting: {$course->shortname}... ";
   delete_course($course, false);
   echo "✓\n";
}

echo "\nCleanup Complete.\n";
