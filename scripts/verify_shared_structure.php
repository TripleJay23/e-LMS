#!/usr/bin/env php
<?php
/**
 * Verify Shared Modules Structure 
 * Shows detailed course distribution across Year/Semester categories
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Shared Modules Structure Verification             ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get Shared Modules category
$shared_cat = $DB->get_record('course_categories', ['name' => 'Shared Modules']);

if (!$shared_cat) {
   echo "❌ Shared Modules category not found!\n";
   exit(1);
}

// Check top-level courses
$top_level_courses = $DB->count_records('course', ['category' => $shared_cat->id]);
// Subtract 1 for site course if it exists
if ($top_level_courses > 0) {
   $courses = $DB->get_records('course', ['category' => $shared_cat->id]);
   $top_level_courses = count(array_filter($courses, function ($c) {
      return $c->id > 1;
   }));
}

echo "Top-level Shared Modules: {$top_level_courses} courses\n\n";

if ($top_level_courses > 0) {
   echo "⚠️  WARNING: Courses should not be at top-level!\n\n";
}

// Get Year categories
$year_cats = $DB->get_records('course_categories', ['parent' => $shared_cat->id], 'name');

if (empty($year_cats)) {
   echo "❌ No Year subcategories found!\n";
   exit(1);
}

echo "Course Distribution:\n";
echo str_repeat("=", 60) . "\n\n";

$total_organized = 0;

foreach ($year_cats as $ycat) {
   echo "📁 {$ycat->name}\n";
   echo str_repeat("-", 60) . "\n";

   $sem_cats = $DB->get_records('course_categories', ['parent' => $ycat->id], 'name');

   if (empty($sem_cats)) {
      echo "  ⚠️  No semester subcategories!\n\n";
      continue;
   }

   foreach ($sem_cats as $scat) {
      $courses = $DB->get_records('course', ['category' => $scat->id]);
      // Filter out site course
      $courses = array_filter($courses, function ($c) {
         return $c->id > 1;
      });
      $count = count($courses);

      echo "  └─ {$scat->name}: {$count} courses\n";

      if ($count > 0) {
         foreach ($courses as $course) {
            echo "      • {$course->shortname}\n";
         }
         $total_organized += $count;
      }
   }

   echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "TOTAL ORGANIZED: {$total_organized} courses\n";
echo "TOP-LEVEL (unorganized): {$top_level_courses} courses\n\n";

if ($top_level_courses == 0 && $total_organized > 0) {
   echo "✅ SUCCESS!\n";
   echo "All shared courses are properly organized into Year/Semester structure.\n";
} else if ($top_level_courses > 0) {
   echo "⚠️  INCOMPLETE!\n";
   echo "Some courses are still at top-level and need to be moved.\n";
} else {
   echo "⚠️  NO COURSES FOUND!\n";
   echo "Shared modules appear to be empty.\n";
}
