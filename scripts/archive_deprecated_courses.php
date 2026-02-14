#!/usr/bin/env php
<?php
/**
 * Archive Deprecated Courses
 * Moves deprecated courses to a hidden Archive category
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Archiving Deprecated Courses                     ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Create/Get Archive Category
echo "1. Ensuring Archive Category exists...\n";
$archive_cat = $DB->get_record('course_categories', ['name' => 'Archive']);

if (!$archive_cat) {
   $new_cat = new stdClass();
   $new_cat->name = 'Archive';
   $new_cat->parent = 0; // Top level
   $new_cat->visible = 0; // Hidden
   $new_cat->description = 'Storage for deprecated courses';
   $new_cat->idnumber = 'ARCHIVE';

   $cat_id = $DB->insert_record('course_categories', $new_cat);
   $archive_cat = $DB->get_record('course_categories', ['id' => $cat_id]);

   // Fix path
   $archive_cat->path = '/' . $cat_id;
   $archive_cat->depth = 1;
   $DB->update_record('course_categories', $archive_cat);

   echo "   ✓ Created 'Archive' category (Hidden)\n";
} else {
   // Ensure it's hidden
   if ($archive_cat->visible == 1) {
      $archive_cat->visible = 0;
      $DB->update_record('course_categories', $archive_cat);
      echo "   ✓ Hidden existing 'Archive' category\n";
   } else {
      echo "   • 'Archive' category exists and is hidden\n";
   }
}

// 2. Find Deprecated Courses
echo "\n2. Finding deprecated courses...\n";
$courses = $DB->get_records_sql("
    SELECT * FROM {course} 
    WHERE (shortname LIKE '%-OLD' OR fullname LIKE '%(DEPRECATED)%')
    AND category != ?
", [$archive_cat->id]);

$count = count($courses);

if ($count === 0) {
   echo "   No deprecated courses found outside Archive.\n";
   echo "\nAll clean! ✓\n";
   exit(0);
}

echo "   Found {$count} courses to archive.\n";

// 3. Move Courses
echo "\n3. Moving courses...\n";
$moved = 0;

foreach ($courses as $course) {
   echo "Archiving: {$course->shortname}... ";

   try {
      // Move to archive category
      move_courses([$course->id], $archive_cat->id);

      // Hide course
      $update = new stdClass();
      $update->id = $course->id;
      $update->visible = 0; // Ensure course itself is hidden too
      $DB->update_record('course', $update);

      echo "✓ Moved & Hidden\n";
      $moved++;
   } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
   }
}

echo "\nSummary:\n";
echo "  • Total found: $count\n";
echo "  • Total archived: $moved\n";
