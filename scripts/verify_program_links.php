#!/usr/bin/env php
<?php
/**
 * Verify Program Links
 * Periodic verification script to catch inconsistencies early
 * 
 * This script:
 * - Performs quick health check on program-course links
 * - Reports any issues found
 * - Can be run as a cron job for monitoring
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Program Links Verification                       ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Helper function to parse program info from category idnumber
function parse_category_program($idnumber)
{
   if (empty($idnumber)) {
      return null;
   }

   if (preg_match('/^(BIT|BCS)_Y(\d)_S(\d)$/', $idnumber, $matches)) {
      return [
         'program' => $matches[1],
         'year' => (int)$matches[2],
         'semester' => (int)$matches[3]
      ];
   }

   return null;
}

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

$issues_found = false;
$issue_count = 0;

// Quick check 1: Ghost records
echo "Check 1: Ghost Records... ";
$ghost_count = $DB->count_records_sql("
    SELECT COUNT(*) 
    FROM {custom_program_courses} pc
    LEFT JOIN {course} c ON pc.courseid = c.id
    WHERE c.id IS NULL OR c.id = 1
");

if ($ghost_count > 0) {
   echo "⚠ FOUND {$ghost_count}\n";
   $issues_found = true;
   $issue_count += $ghost_count;
} else {
   echo "✓ OK\n";
}

// Quick check 2: Orphaned courses
echo "Check 2: Orphaned Courses... ";

$programs = $DB->get_records('custom_programs', null, '', 'acronym, id, name');
$orphaned = 0;

$courses = $DB->get_records_select('course', 'id > 1');
foreach ($courses as $course) {
   // Skip deprecated and archived
   if (
      strpos($course->shortname, '-OLD') !== false ||
      strpos($course->fullname, '(DEPRECATED)') !== false
   ) {
      continue;
   }

   $cat_path = get_category_path($course->category);
   $in_archive = false;
   foreach ($cat_path as $cat) {
      if ($cat['idnumber'] === 'ARCHIVE' || $cat['name'] === 'Archive') {
         $in_archive = true;
         break;
      }
   }
   if ($in_archive) continue;

   // Check if has program in category
   $has_program = false;
   foreach ($cat_path as $cat) {
      $parsed = parse_category_program($cat['idnumber']);
      if ($parsed && isset($programs[$parsed['program']])) {
         $has_program = true;

         // Check if in DB
         $program_id = $programs[$parsed['program']]->id;
         $exists = $DB->record_exists('custom_program_courses', [
            'programid' => $program_id,
            'courseid' => $course->id
         ]);

         if (!$exists) {
            $orphaned++;
         }
         break;
      }
   }
}

if ($orphaned > 0) {
   echo "⚠ FOUND {$orphaned}\n";
   $issues_found = true;
   $issue_count += $orphaned;
} else {
   echo "✓ OK\n";
}

// Quick check 3: Mismatched programs
echo "Check 3: Program Mismatches... ";

$mismatched = 0;
$db_links = $DB->get_records('custom_program_courses');

foreach ($db_links as $link) {
   $course = $DB->get_record('course', ['id' => $link->courseid]);
   if (!$course) continue;

   $cat_path = get_category_path($course->category);

   foreach ($cat_path as $cat) {
      $parsed = parse_category_program($cat['idnumber']);
      if ($parsed && isset($programs[$parsed['program']])) {
         $expected_program_id = $programs[$parsed['program']]->id;

         if (
            $link->programid != $expected_program_id ||
            $link->year != $parsed['year'] ||
            $link->semester != $parsed['semester']
         ) {
            $mismatched++;
         }
         break;
      }
   }
}

if ($mismatched > 0) {
   echo "⚠ FOUND {$mismatched}\n";
   $issues_found = true;
   $issue_count += $mismatched;
} else {
   echo "✓ OK\n";
}

// Quick check 4: Deprecated courses
echo "Check 4: Deprecated Courses... ";

$deprecated = $DB->count_records_sql("
    SELECT COUNT(*) FROM {course}
    WHERE (shortname LIKE '%-OLD' OR fullname LIKE '%(DEPRECATED)%')
    AND id > 1
");

if ($deprecated > 0) {
   echo "⚠ FOUND {$deprecated}\n";
   $issues_found = true;
   $issue_count += $deprecated;
} else {
   echo "✓ OK\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";

if ($issues_found) {
   echo "║              ⚠ ISSUES DETECTED                         ║\n";
   echo "╚════════════════════════════════════════════════════════╝\n\n";

   echo "Total issues: {$issue_count}\n\n";

   echo "Recommended actions:\n";
   echo "  1. Run full audit: php scripts/audit_course_program_links.php\n";

   if ($deprecated > 0) {
      echo "  2. Archive deprecated: php scripts/archive_deprecated_courses.php\n";
   }

   if ($ghost_count > 0 || $deprecated > 0) {
      echo "  3. Cleanup database: php scripts/cleanup_program_courses_table.php\n";
   }

   if ($orphaned > 0 || $mismatched > 0) {
      echo "  4. Rebuild links: php scripts/rebuild_program_course_links.php\n";
   }

   echo "\n";
   exit(1); // Exit with error code for monitoring
} else {
   echo "║              ✓ ALL CHECKS PASSED                       ║\n";
   echo "╚════════════════════════════════════════════════════════╝\n\n";

   echo "System is healthy! No issues detected.\n\n";
   exit(0);
}
