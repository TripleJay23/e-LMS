#!/usr/bin/env php
<?php
/**
 * Create Course Categories for e-LMS Programs
 * Creates Moodle course categories for each program (BIT, BCS, DCS, DIT, BTCIT)
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║        e-LMS Course Categories Setup                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Creating course categories for programs...\n\n";

try {
   // Get all programs from database
   $programs = $DB->get_records_sql("
        SELECT p.*, d.name as department_name
        FROM {custom_programs} p
        JOIN {custom_departments} d ON p.departmentid = d.id
        ORDER BY p.acronym
    ");

   $categoryCount = 0;

   foreach ($programs as $program) {
      // Check if category already exists
      $existing = $DB->get_record('course_categories', ['idnumber' => $program->acronym]);

      if ($existing) {
         echo "• {$program->acronym} - Already exists (ID: {$existing->id})\n";
         continue;
      }

      // Create new course category
      $categoryData = new stdClass();
      $categoryData->name = $program->name;
      $categoryData->idnumber = $program->acronym;
      $categoryData->description = "<p><strong>Level:</strong> " . ucfirst($program->level) . "</p>" .
         "<p><strong>Department:</strong> {$program->department_name}</p>" .
         "<p><strong>Duration:</strong> {$program->duration} semesters</p>";
      $categoryData->descriptionformat = FORMAT_HTML;
      $categoryData->parent = 0; // Top-level category
      $categoryData->visible = 1;

      $category = core_course_category::create($categoryData);
      $categoryCount++;

      echo "✓ Created: {$program->acronym} - {$program->name} (ID: {$category->id})\n";
   }

   echo "\n╔════════════════════════════════════════════════════════╗\n";
   echo "║         Categories Created! ✓                          ║\n";
   echo "╚════════════════════════════════════════════════════════╝\n\n";

   echo "Summary:\n";
   echo "  • Total programs: " . count($programs) . "\n";
   echo "  • New categories created: $categoryCount\n\n";


   // ── Create Shared Modules Hierarchy ────────────────────────────────────────
   echo "\nCreating Shared Modules hierarchy...\n";
   $shared_idnum = 'COMMON'; // As used in create_hod.php
   $shared_cat_record = $DB->get_record('course_categories', ['idnumber' => $shared_idnum]);

   if (!$shared_cat_record) {
      $sharedData = new stdClass();
      $sharedData->name = 'Shared Modules';
      $sharedData->idnumber = $shared_idnum;
      $sharedData->description = 'Courses shared across multiple programs (BIT, BCS, etc.)';
      $sharedData->parent = 0;
      $sharedData->visible = 1;
      $shared_cat = core_course_category::create($sharedData);
      echo "✓ Created: Shared Modules (ID: {$shared_cat->id})\n";
   } else {
      $shared_cat = core_course_category::get($shared_cat_record->id);
      echo "• Shared Modules already exists (ID: {$shared_cat->id})\n";
   }

   // Create Year/Semester subcategories for Shared Modules
   for ($year = 1; $year <= 3; $year++) {
      $year_idnum = "common_y{$year}";
      $year_cat_record = $DB->get_record('course_categories', ['idnumber' => $year_idnum]);

      if (!$year_cat_record) {
         $year_cat = core_course_category::create([
            'name' => "Year $year",
            'idnumber' => $year_idnum,
            'parent' => $shared_cat->id,
            'visible' => 1
         ]);
         echo "  ✓ Shared Year $year (ID: {$year_cat->id})\n";
      } else {
         $year_cat = core_course_category::get($year_cat_record->id);
         echo "  • Shared Year $year exists (ID: {$year_cat->id})\n";
      }

      for ($sem = 1; $sem <= 2; $sem++) {
         $sem_idnum = "common_y{$year}_s{$sem}";
         if (!$DB->record_exists('course_categories', ['idnumber' => $sem_idnum])) {
            $sem_cat = core_course_category::create([
               'name' => "Semester $sem",
               'idnumber' => $sem_idnum,
               'parent' => $year_cat->id,
               'visible' => 1
            ]);
            echo "    ✓ Shared Semester $sem (ID: {$sem_cat->id})\n";
         } else {
            echo "    • Shared Semester $sem exists\n";
         }
      }
   }

   echo "\nNext steps:\n";
   echo "  1. Create courses within these categories\n";
   echo "  2. Assign facilitators to courses\n";
   echo "  3. Add course content (PDFs, videos, quizzes)\n\n";
} catch (Exception $e) {
   echo "✗ Error: " . $e->getMessage() . "\n";
   echo $e->getTraceAsString() . "\n";
   exit(1);
}
