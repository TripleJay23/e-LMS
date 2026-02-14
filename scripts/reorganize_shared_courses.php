#!/usr/bin/env php
<?php
/**
 * Reorganize Shared Courses Using JSON Metadata
 * Moves courses from top-level Shared Modules into proper Year/Semester subcategories
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Reorganize Shared Modules Courses               ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Load JSON metadata
$json_file = __DIR__ . '/../modules_categorized.json';
if (!file_exists($json_file)) {
   echo "❌ modules_categorized.json not found!\n";
   exit(1);
}

$json_data = json_decode(file_get_contents($json_file), true);
if (!$json_data || !isset($json_data['shared'])) {
   echo "❌ Invalid JSON data!\n";
   exit(1);
}

// Build mapping: course_code => [year, semester]
$course_map = [];
foreach ($json_data['shared'] as $module) {
   $code = $module['code'];
   $semester_str = $module['semester']; // e.g., "Semester I", "Semester III"

   // Parse semester number (I, II, III, IV, V, VI -> 1, 2, 3, 4, 5, 6)
   $roman = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6];
   $sem_num = null;
   foreach ($roman as $r => $n) {
      if (strpos($semester_str, $r) !== false) {
         $sem_num = $n;
         break;
      }
   }

   if (!$sem_num) {
      echo "⚠️  Cannot parse semester for {$code}: {$semester_str}\n";
      continue;
   }

   // Calculate year and semester within year
   // Sem 1, 2 = Year 1; Sem 3, 4 = Year 2; Sem 5, 6 = Year 3
   $year = ceil($sem_num / 2);
   $semester = ($sem_num % 2 == 0) ? 2 : 1;

   $course_map[$code] = ['year' => $year, 'semester' => $semester];
}

echo "Loaded " . count($course_map) . " course mappings from JSON.\n\n";

// Get Shared Modules category
$shared_cat = $DB->get_record('course_categories', ['name' => 'Shared Modules']);

if (!$shared_cat) {
   echo "❌ Shared Modules category not found!\n";
   exit(1);
}

// Get all courses directly in Shared Modules (top-level)
$courses = $DB->get_records('course', ['category' => $shared_cat->id]);

// Filter out site course
$courses = array_filter($courses, function ($c) {
   return $c->id > 1;
});

$total = count($courses);
echo "Found {$total} courses at top-level Shared Modules.\n\n";

if ($total == 0) {
   echo "✓ No courses to reorganize. Already organized!\n";
   exit(0);
}

// Get all Year/Semester subcategories
echo "Verifying category structure...\n";
echo str_repeat("-", 60) . "\n";

$moves = [];
$no_data = [];

foreach ($courses as $course) {
   // Extract course code from shortname
   $shortname = $course->shortname;

   // Look up in JSON mapping
   if (!isset($course_map[$shortname])) {
      $no_data[] = $course;
      echo "⚠️  {$shortname}: Not in JSON metadata\n";
      continue;
   }

   $year = $course_map[$shortname]['year'];
   $semester = $course_map[$shortname]['semester'];

   // Find target category
   $year_cat_name = "Year {$year}";
   $sem_cat_name = "Semester {$semester}";

   $year_cat = $DB->get_record('course_categories', [
      'parent' => $shared_cat->id,
      'name' => $year_cat_name
   ]);

   if (!$year_cat) {
      echo "⚠️  {$shortname}: Year category '{$year_cat_name}' not found\n";
      continue;
   }

   $sem_cat = $DB->get_record('course_categories', [
      'parent' => $year_cat->id,
      'name' => $sem_cat_name
   ]);

   if (!$sem_cat) {
      echo "⚠️  {$shortname}: Semester category '{$sem_cat_name}' not found\n";
      continue;
   }

   $moves[] = [
      'course' => $course,
      'target' => $sem_cat,
      'year' => $year,
      'semester' => $semester
   ];

   echo "✓ {$shortname} → Year {$year} / Semester {$semester}\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Moving Courses                                    ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

if (empty($moves)) {
   echo "⚠️  No courses to move.\n";

   if (!empty($no_data)) {
      echo "\nCourses without metadata:\n";
      foreach ($no_data as $c) {
         echo "  • {$c->shortname} - {$c->fullname}\n";
      }
   }

   exit(0);
}

echo "Ready to move " . count($moves) . " courses.\n";
if (!empty($no_data)) {
   echo "Courses without data: " . count($no_data) . "\n";
}
echo "\n";

$moved = 0;
$errors = 0;

foreach ($moves as $move) {
   $course = $move['course'];
   $target = $move['target'];

   echo "Moving: {$course->shortname}... ";

   try {
      // Use Moodle's safe move function
      move_courses([$course->id], $target->id);
      echo "✓ Moved to {$target->name}\n";
      $moved++;
   } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
      $errors++;
   }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              REORGANIZATION COMPLETE                   ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Total courses processed: {$total}\n";
echo "  • Successfully moved: {$moved}\n";
echo "  • Errors: {$errors}\n";
echo "  • No data (skipped): " . count($no_data) . "\n\n";

if ($moved > 0) {
   echo "✓ Shared Modules courses are now organized by Year/Semester!\n\n";
   echo "Structure:\n";
   for ($y = 1; $y <= 3; $y++) {
      for ($s = 1; $s <= 2; $s++) {
         $count = 0;
         foreach ($moves as $move) {
            if ($move['year'] == $y && $move['semester'] == $s) {
               $count++;
            }
         }
         if ($count > 0) {
            echo "  • Year {$y} → Semester {$s}: {$count} courses\n";
         }
      }
   }

   echo "\nNext steps:\n";
   echo "  1. Login as HOD and navigate: Shared Modules → Year X → Semester Y\n";
   echo "  2. Verify courses appear in the correct categories\n\n";
}

if (!empty($no_data)) {
   echo "\n⚠️  Courses without metadata:\n";
   foreach ($no_data as $c) {
      echo "  • {$c->shortname} - {$c->fullname}\n";
   }
   echo "\nThese courses remain at top-level and may need manual placement.\n";
}
