#!/usr/bin/env php
<?php
/**
 * PERMANENTLY Delete Archived Courses
 * This script completely removes archived/deprecated courses from the database.
 * 
 * WARNING: This is PERMANENT and CANNOT be undone!
 * Use only if you're sure the data is not needed (e.g., test data)
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      PERMANENTLY Delete Archived Courses              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "⚠️  WARNING: This will PERMANENTLY DELETE courses!\n";
echo "This action CANNOT be undone.\n\n";

// Find courses to delete
$sql = "SELECT * FROM {course} 
        WHERE id > 1 
        AND (
            shortname LIKE '%-OLD' 
            OR fullname LIKE '%(DEPRECATED)%'
            OR category = (SELECT id FROM {course_categories} WHERE idnumber = 'ARCHIVE')
        )";

$courses = $DB->get_records_sql($sql);
$count = count($courses);

if ($count === 0) {
   echo "✓ No archived courses found. System is clean!\n";
   exit(0);
}

echo "Found {$count} courses to delete:\n\n";

foreach ($courses as $course) {
   echo "  • [{$course->id}] {$course->shortname} - {$course->fullname}\n";
}

echo "\n";
echo "Are you ABSOLUTELY SURE you want to delete these {$count} courses?\n";
echo "Type 'DELETE' to confirm (or Ctrl+C to cancel): ";

$handle = fopen("php://stdin", "r");
$confirm = trim(fgets($handle));
fclose($handle);

if ($confirm !== 'DELETE') {
   echo "\n❌ Deletion cancelled.\n";
   exit(0);
}

echo "\nProceeding with deletion...\n\n";

$deleted = 0;
$errors = 0;

foreach ($courses as $course) {
   echo "Deleting: {$course->shortname}... ";

   try {
      // delete_course() removes everything: enrollments, grades, activities, etc.
      delete_course($course, false); // false = don't show output
      echo "✓ DELETED\n";
      $deleted++;
   } catch (Exception $e) {
      echo "❌ ERROR: " . $e->getMessage() . "\n";
      $errors++;
   }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              DELETION COMPLETE                         ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Courses found: {$count}\n";
echo "  • Successfully deleted: {$deleted}\n";
echo "  • Errors: {$errors}\n\n";

if ($deleted > 0) {
   echo "✓ Deleted {$deleted} courses permanently.\n";
   echo "\nNext steps:\n";
   echo "  1. Clean database links: php scripts/cleanup_program_courses_table.php\n";
   echo "  2. Vacuum database: php scripts/system_cleanup.php\n\n";
}
