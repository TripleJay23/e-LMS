#!/usr/bin/env php
<?php
/**
 * Analyze Shared Modules Category Structure
 * Diagnoses why courses aren't properly organized by year/semester
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Shared Modules Category Analysis                 ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get all top-level categories
$top_categories = $DB->get_records('course_categories', ['parent' => 0], 'sortorder');

echo "Top-Level Categories:\n";
echo str_repeat("-", 60) . "\n";

foreach ($top_categories as $cat) {
   echo "[{$cat->id}] {$cat->name} (IDNumber: {$cat->idnumber})\n";

   // Get subcategories
   $subcats = $DB->get_records('course_categories', ['parent' => $cat->id], 'sortorder');

   if (!empty($subcats)) {
      foreach ($subcats as $subcat) {
         echo "  └─ [{$subcat->id}] {$subcat->name} (IDNumber: {$subcat->idnumber})\n";

         // Check for year/semester subcategories
         $year_cats = $DB->get_records('course_categories', ['parent' => $subcat->id], 'sortorder');
         if (!empty($year_cats)) {
            foreach ($year_cats as $ycat) {
               echo "      └─ [{$ycat->id}] {$ycat->name} (IDNumber: {$ycat->idnumber})\n";
            }
         }
      }
   }

   // Count courses directly in this category
   $direct_courses = $DB->count_records('course', ['category' => $cat->id]);
   if ($direct_courses > 0) {
      echo "  ⚠️  {$direct_courses} courses placed directly in this category\n";
   }

   echo "\n";
}

echo "\n";
echo "Shared/Common Modules Analysis:\n";
echo str_repeat("-", 60) . "\n";

// Find Common/Shared categories
$shared_categories = $DB->get_records_sql("
    SELECT * FROM {course_categories}
    WHERE name LIKE '%Common%' 
    OR name LIKE '%Shared%'
    OR idnumber LIKE '%COMMON%'
    OR idnumber LIKE '%SHARED%'
    ORDER BY id
");

foreach ($shared_categories as $cat) {
   echo "\n[{$cat->id}] {$cat->name}\n";
   echo "  IDNumber: {$cat->idnumber}\n";
   echo "  Parent: {$cat->parent}\n";

   // Count courses in this category
   $courses = $DB->get_records('course', ['category' => $cat->id]);
   echo "  Courses directly in this category: " . count($courses) . "\n";

   if (!empty($courses)) {
      echo "  Course List:\n";
      foreach ($courses as $course) {
         if ($course->id > 1) {
            echo "    • {$course->shortname} - {$course->fullname}\n";
         }
      }
   }

   // Check for subcategories
   $subcats = $DB->get_records('course_categories', ['parent' => $cat->id], 'sortorder');
   if (!empty($subcats)) {
      echo "  Subcategories:\n";
      foreach ($subcats as $subcat) {
         $subcat_courses = $DB->count_records('course', ['category' => $subcat->id]);
         echo "    • [{$subcat->id}] {$subcat->name} ({$subcat_courses} courses)\n";
      }
   } else {
      echo "  ⚠️  NO SUBCATEGORIES (should have Year/Semester structure!)\n";
   }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Problem Diagnosis                                 ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Check if shared courses need reorganization
$needs_fix = false;
foreach ($shared_categories as $cat) {
   $direct_count = $DB->count_records('course', ['category' => $cat->id]);
   $has_subcats = $DB->record_exists('course_categories', ['parent' => $cat->id]);

   if ($direct_count > 1 && !$has_subcats) { // > 1 to exclude site course
      $needs_fix = true;
      echo "⚠️  ISSUE FOUND:\n";
      echo "Category: {$cat->name}\n";
      echo "  • Has {$direct_count} courses placed directly\n";
      echo "  • Missing Year/Semester subcategory structure\n";
      echo "  • Courses should be organized like BIT/BCS categories\n\n";
   }
}

if ($needs_fix) {
   echo "Recommended Fix:\n";
   echo "  1. Create Year/Semester subcategories for Shared Modules\n";
   echo "  2. Move courses from top-level into appropriate Year/Semester\n";
   echo "  3. Match the structure used by BIT and BCS programs\n\n";
} else {
   echo "✓ Structure looks correct!\n";
}
