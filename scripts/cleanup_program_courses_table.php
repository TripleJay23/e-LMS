#!/usr/bin/env php
<?php
/**
 * Cleanup Program Courses Table
 * Removes orphaned and invalid records from mdl_custom_program_courses
 * 
 * This script:
 * - Removes links to non-existent courses (ghost records)
 * - Removes links to archived courses
 * - Removes links to deprecated courses
 * - Removes duplicate records
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Cleanup Program Courses Table                    ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Safety check
echo "⚠️  WARNING: This script will delete records from mdl_custom_program_courses\n";
echo "Make sure you have a database backup before proceeding!\n\n";
echo "Press Enter to continue or Ctrl+C to cancel...\n";
if (!defined('CLI_SCRIPT')) {
   fgets(STDIN);
}

$stats = [
   'ghost_records' => 0,
   'archived_courses' => 0,
   'deprecated_courses' => 0,
   'duplicates' => 0,
   'total_deleted' => 0
];

echo "Step 1: Removing Ghost Records (links to non-existent courses)\n";
echo str_repeat("-", 60) . "\n";

// Get all program_courses records
$all_links = $DB->get_records('custom_program_courses');

foreach ($all_links as $link) {
   $course = $DB->get_record('course', ['id' => $link->courseid]);

   if (!$course || $course->id == 1) {
      // Course doesn't exist or is site course
      echo "• Removing link to course ID {$link->courseid} (doesn't exist)\n";
      $DB->delete_records('custom_program_courses', ['id' => $link->id]);
      $stats['ghost_records']++;
      $stats['total_deleted']++;
   }
}

echo "Removed {$stats['ghost_records']} ghost records\n\n";

echo "Step 2: Removing Links to Archived Courses\n";
echo str_repeat("-", 60) . "\n";

// Get Archive category
$archive_cat = $DB->get_record('course_categories', ['idnumber' => 'ARCHIVE']);

if ($archive_cat) {
   // Get all courses in Archive (including subcategories)
   $archive_courses = $DB->get_records_sql("
        SELECT c.id 
        FROM {course} c
        JOIN {course_categories} cc ON c.category = cc.id
        WHERE cc.path LIKE ?
    ", ['%/' . $archive_cat->id . '/%']);

   $archive_course_ids = array_keys($archive_courses);

   if (!empty($archive_course_ids)) {
      list($in_sql, $params) = $DB->get_in_or_equal($archive_course_ids);
      $deleted = $DB->delete_records_select(
         'custom_program_courses',
         "courseid $in_sql",
         $params
      );

      echo "Removed {$deleted} links to archived courses\n";
      $stats['archived_courses'] = $deleted;
      $stats['total_deleted'] += $deleted;
   } else {
      echo "No courses found in Archive\n";
   }
} else {
   echo "Archive category not found (skipping)\n";
}

echo "\n";

echo "Step 3: Removing Links to Deprecated Courses\n";
echo str_repeat("-", 60) . "\n";

// Get deprecated courses
$deprecated_courses = $DB->get_records_sql("
    SELECT id FROM {course}
    WHERE shortname LIKE '%-OLD'
    OR fullname LIKE '%(DEPRECATED)%'
");

$deprecated_ids = array_keys($deprecated_courses);

if (!empty($deprecated_ids)) {
   list($in_sql, $params) = $DB->get_in_or_equal($deprecated_ids);
   $deleted = $DB->delete_records_select(
      'custom_program_courses',
      "courseid $in_sql",
      $params
   );

   echo "Removed {$deleted} links to deprecated courses\n";
   $stats['deprecated_courses'] = $deleted;
   $stats['total_deleted'] += $deleted;
} else {
   echo "No deprecated courses found\n";
}

echo "\n";

echo "Step 4: Removing Duplicate Records\n";
echo str_repeat("-", 60) . "\n";

// Find duplicates (same programid + courseid)
$duplicates = $DB->get_records_sql("
    SELECT programid, courseid, COUNT(*) as count
    FROM {custom_program_courses}
    GROUP BY programid, courseid
    HAVING COUNT(*) > 1
");

foreach ($duplicates as $dup) {
   // Get all records for this combination
   $records = $DB->get_records('custom_program_courses', [
      'programid' => $dup->programid,
      'courseid' => $dup->courseid
   ], 'id ASC');

   // Keep the first one, delete the rest
   $first = true;
   foreach ($records as $record) {
      if ($first) {
         $first = false;
         continue;
      }

      echo "• Removing duplicate: Program {$dup->programid}, Course {$dup->courseid}\n";
      $DB->delete_records('custom_program_courses', ['id' => $record->id]);
      $stats['duplicates']++;
      $stats['total_deleted']++;
   }
}

echo "Removed {$stats['duplicates']} duplicate records\n\n";

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              CLEANUP COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Ghost records removed: {$stats['ghost_records']}\n";
echo "  • Archived course links removed: {$stats['archived_courses']}\n";
echo "  • Deprecated course links removed: {$stats['deprecated_courses']}\n";
echo "  • Duplicate records removed: {$stats['duplicates']}\n";
echo "  ─────────────────────────────────────\n";
echo "  • Total records deleted: {$stats['total_deleted']}\n\n";

if ($stats['total_deleted'] > 0) {
   echo "✓ Database cleaned successfully!\n";
   echo "\nNext steps:\n";
   echo "  1. Run rebuild script: php scripts/rebuild_program_course_links.php\n";
   echo "  2. Re-run audit: php scripts/audit_course_program_links.php\n\n";
} else {
   echo "✓ No cleanup needed - database is clean!\n\n";
}
