#!/usr/bin/env php
<?php
/**
 * Rebuild Program-Course Links
 * Synchronizes mdl_custom_program_courses with actual category placement
 * 
 * This script:
 * - Scans all active courses
 * - Determines their program from category path
 * - Creates/updates records in mdl_custom_program_courses
 * - Ensures database matches the category organization
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Rebuild Program-Course Links                     ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Helper function to parse program info from category idnumber
function parse_category_program($idnumber)
{
   if (empty($idnumber)) {
      return null;
   }

   // Match patterns like BIT_Y1_S1, BCS_Y2_S2
   if (preg_match('/^(BIT|BCS)_Y(\d)_S(\d)$/', $idnumber, $matches)) {
      return [
         'program' => $matches[1],
         'year' => (int)$matches[2],
         'semester' => (int)$matches[3]
      ];
   }

   return null;
}

// Get full category path for a course
function get_category_path($category_id)
{
   global $DB;

   $path = [];
   $current = $category_id;

   while ($current > 0) {
      $cat = $DB->get_record('course_categories', ['id' => $current]);
      if (!$cat) break;

      array_unshift($path, [
         'name' => $cat->name,
         'idnumber' => $cat->idnumber
      ]);

      $current = $cat->parent;
   }

   return $path;
}

echo "Step 1: Loading Programs\n";
echo str_repeat("-", 60) . "\n";

// Get program IDs
$programs = $DB->get_records('custom_programs', null, '', 'acronym, id, name');

if (empty($programs)) {
   echo "✗ Error: No programs found in mdl_custom_programs\n";
   echo "Please run database setup first.\n";
   exit(1);
}

echo "Found programs:\n";
foreach ($programs as $program) {
   echo "  • {$program->acronym}: {$program->name} (ID: {$program->id})\n";
}
echo "\n";

echo "Step 2: Processing Courses\n";
echo str_repeat("-", 60) . "\n";

$stats = [
   'total_processed' => 0,
   'skipped_archive' => 0,
   'skipped_deprecated' => 0,
   'skipped_no_program' => 0,
   'created' => 0,
   'updated' => 0,
   'unchanged' => 0
];

// Get all active courses
$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname');

foreach ($courses as $course) {
   $stats['total_processed']++;

   // Skip deprecated courses
   if (
      strpos($course->shortname, '-OLD') !== false ||
      strpos($course->fullname, '(DEPRECATED)') !== false
   ) {
      $stats['skipped_deprecated']++;
      continue;
   }

   // Get category path
   $cat_path = get_category_path($course->category);

   // Check if in Archive
   $in_archive = false;
   foreach ($cat_path as $cat) {
      if ($cat['idnumber'] === 'ARCHIVE' || $cat['name'] === 'Archive') {
         $in_archive = true;
         break;
      }
   }

   if ($in_archive) {
      $stats['skipped_archive']++;
      continue;
   }

   // Determine program from category
   $category_program = null;
   $category_year = null;
   $category_semester = null;

   foreach ($cat_path as $cat) {
      $parsed = parse_category_program($cat['idnumber']);
      if ($parsed) {
         $category_program = $parsed['program'];
         $category_year = $parsed['year'];
         $category_semester = $parsed['semester'];
         break;
      }
   }

   // Skip if no program identified
   if (!$category_program || !isset($programs[$category_program])) {
      $stats['skipped_no_program']++;
      echo "• [{$course->id}] {$course->shortname}: No program identified\n";
      continue;
   }

   $program_id = $programs[$category_program]->id;

   // Check if link exists
   $existing = $DB->get_record('custom_program_courses', [
      'programid' => $program_id,
      'courseid' => $course->id
   ]);

   if ($existing) {
      // Check if needs update
      if (
         $existing->year != $category_year ||
         $existing->semester != $category_semester
      ) {
         // Update
         $existing->year = $category_year;
         $existing->semester = $category_semester;
         $existing->timecreated = $existing->timecreated ?: time();

         $DB->update_record('custom_program_courses', $existing);

         echo "↻ [{$course->id}] {$course->shortname}: Updated → {$category_program} Y{$category_year} S{$category_semester}\n";
         $stats['updated']++;
      } else {
         $stats['unchanged']++;
      }
   } else {
      // Check if there's a link with different program (mismatch)
      $wrong_link = $DB->get_record('custom_program_courses', ['courseid' => $course->id]);

      if ($wrong_link) {
         // Delete wrong link and create correct one
         $DB->delete_records('custom_program_courses', ['id' => $wrong_link->id]);
         echo "✗ [{$course->id}] {$course->shortname}: Removed incorrect link\n";
      }

      // Create new link
      $link = new stdClass();
      $link->programid = $program_id;
      $link->courseid = $course->id;
      $link->year = $category_year;
      $link->semester = $category_semester;
      $link->timecreated = time();

      $DB->insert_record('custom_program_courses', $link);

      echo "✓ [{$course->id}] {$course->shortname}: Linked → {$category_program} Y{$category_year} S{$category_semester}\n";
      $stats['created']++;
   }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              REBUILD COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Total courses processed: {$stats['total_processed']}\n";
echo "  • Skipped (archived): {$stats['skipped_archive']}\n";
echo "  • Skipped (deprecated): {$stats['skipped_deprecated']}\n";
echo "  • Skipped (no program): {$stats['skipped_no_program']}\n";
echo "  ─────────────────────────────────────\n";
echo "  • Links created: {$stats['created']}\n";
echo "  • Links updated: {$stats['updated']}\n";
echo "  • Links unchanged: {$stats['unchanged']}\n\n";

$total_linked = $stats['created'] + $stats['updated'] + $stats['unchanged'];
echo "✓ Total courses linked to programs: {$total_linked}\n\n";

echo "Next step:\n";
echo "  Run audit to verify: php scripts/audit_course_program_links.php\n\n";
