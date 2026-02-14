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

   echo "Next steps:\n";
   echo "  1. Create courses within these categories\n";
   echo "  2. Assign facilitators to courses\n";
   echo "  3. Add course content (PDFs, videos, quizzes)\n\n";
} catch (Exception $e) {
   echo "✗ Error: " . $e->getMessage() . "\n";
   echo $e->getTraceAsString() . "\n";
   exit(1);
}
