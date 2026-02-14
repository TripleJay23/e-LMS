#!/usr/bin/env php
<?php
/**
 * Create Complete Course Structure for BIT and BCS
 * - Creates category hierarchy (Year → Semester)
 * - Creates shared courses as DUPLICATE instances in each program
 * - Creates program-specific courses
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      BIT & BCS Course Structure Creation (Refactored) ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Read categorized modules
$json = file_get_contents(__DIR__ . '/../modules_categorized.json');
$data = json_decode($json, true);

$shared_modules = $data['shared'];
$bit_only = $data['bit_only'];
$bcs_only = $data['bcs_only'];

echo "Modules to create:\n";
echo "  • Shared: " . count($shared_modules) . " (Will be duplicated for BIT & BCS)\n";
echo "  • BIT-only: " . count($bit_only) . "\n";
echo "  • BCS-only: " . count($bcs_only) . "\n\n";

// Helper function to map semester to year/sem
function get_year_semester($semester_name)
{
   $map = [
      'Semester I' => ['year' => 1, 'sem' => 1],
      'Semester II' => ['year' => 1, 'sem' => 2],
      'Semester III' => ['year' => 2, 'sem' => 1],
      'Semester IV' => ['year' => 2, 'sem' => 2],
      'Semester V' => ['year' => 3, 'sem' => 1],
      'Semester VI' => ['year' => 3, 'sem' => 2],
   ];
   return $map[$semester_name] ?? ['year' => 1, 'sem' => 1];
}

// STEP 1: Create Category Hierarchy
echo "Step 1: Creating Category Hierarchy\n";
echo str_repeat("-", 60) . "\n";

$programs = ['BIT', 'BCS'];
$categories = [];

foreach ($programs as $program) {
   $program_cat = $DB->get_record('course_categories', ['idnumber' => $program]);
   if (!$program_cat) {
      echo "Error: $program category not found\n";
      continue;
   }

   echo "\n{$program} Category Structure:\n";

   for ($year = 1; $year <= 3; $year++) {
      // Create Year category
      $year_idnum = "{$program}_Y{$year}";
      $year_cat = $DB->get_record('course_categories', ['idnumber' => $year_idnum]);

      if (!$year_cat) {
         $year_cat = core_course_category::create([
            'name' => "Year $year",
            'idnumber' => $year_idnum,
            'parent' => $program_cat->id,
            'visible' => 1
         ]);
         echo "  ✓ Year $year (ID: {$year_cat->id})\n";
      } else {
         echo "  • Year $year exists (ID: {$year_cat->id})\n";
      }

      // Create Semester categories
      for ($sem = 1; $sem <= 2; $sem++) {
         $sem_idnum = "{$program}_Y{$year}_S{$sem}";
         $sem_cat = $DB->get_record('course_categories', ['idnumber' => $sem_idnum]);

         if (!$sem_cat) {
            $sem_cat = core_course_category::create([
               'name' => "Semester $sem",
               'idnumber' => $sem_idnum,
               'parent' => $year_cat->id,
               'visible' => 1
            ]);
            echo "    ✓ Semester $sem (ID: {$sem_cat->id})\n";
         } else {
            echo "    • Semester $sem exists (ID: {$sem_cat->id})\n";
         }

         $categories["{$program}_Y{$year}_S{$sem}"] = $sem_cat->id;
      }
   }
}

echo "\n\n";

// STEP 2: Create Shared Courses (Split between BIT and BCS)
echo "Step 2: Creating Shared Courses (Splitting)\n";
echo str_repeat("-", 60) . "\n";

$created_shared_bit = 0;
$created_shared_bcs = 0;

foreach ($shared_modules as $module) {
   // 1. Create for BIT
   $ys = get_year_semester($module['semester']);
   $cat_key = "BIT_Y{$ys['year']}_S{$ys['sem']}";
   $category_id = $categories[$cat_key] ?? $programs[0]; // Fallback

   $shortname_bit = $module['code'] . "-BIT";

   if (!$DB->record_exists('course', ['shortname' => $shortname_bit])) {
      $courseData = new stdClass();
      $courseData->fullname = $module['name'];
      $courseData->shortname = $shortname_bit;
      $courseData->category = $category_id;
      $courseData->summary = "Credit Hours: {$module['credits']}<br>Type: {$module['type']}<br>Program: BIT (Shared Module)";
      $courseData->summaryformat = FORMAT_HTML;
      $courseData->format = 'topics';
      $courseData->numsections = 12;
      $courseData->startdate = time();
      $courseData->visible = 1;

      $course = create_course($courseData);

      // AUTO-LINK: Add to mdl_custom_program_courses
      $bit_program = $DB->get_record('custom_programs', ['acronym' => 'BIT']);
      if ($bit_program) {
         $link = new stdClass();
         $link->programid = $bit_program->id;
         $link->courseid = $course->id;
         $link->year = $ys['year'];
         $link->semester = $ys['sem'];
         $link->timecreated = time();
         $DB->insert_record('custom_program_courses', $link);
      }

      echo "✓ BIT: {$module['name']} ({$shortname_bit}) [ID: {$course->id}]\n";
      $created_shared_bit++;
   }

   // 2. Create for BCS
   $ys = get_year_semester($module['semester']);
   $cat_key = "BCS_Y{$ys['year']}_S{$ys['sem']}";
   $category_id = $categories[$cat_key] ?? $programs[1];

   $shortname_bcs = $module['code'] . "-BCS";

   if (!$DB->record_exists('course', ['shortname' => $shortname_bcs])) {
      $courseData = new stdClass();
      $courseData->fullname = $module['name'];
      $courseData->shortname = $shortname_bcs;
      $courseData->category = $category_id;
      $courseData->summary = "Credit Hours: {$module['credits']}<br>Type: {$module['type']}<br>Program: BCS (Shared Module)";
      $courseData->summaryformat = FORMAT_HTML;
      $courseData->format = 'topics';
      $courseData->numsections = 12;
      $courseData->startdate = time();
      $courseData->visible = 1;

      $course = create_course($courseData);

      // AUTO-LINK: Add to mdl_custom_program_courses
      $bcs_program = $DB->get_record('custom_programs', ['acronym' => 'BCS']);
      if ($bcs_program) {
         $link = new stdClass();
         $link->programid = $bcs_program->id;
         $link->courseid = $course->id;
         $link->year = $ys['year'];
         $link->semester = $ys['sem'];
         $link->timecreated = time();
         $DB->insert_record('custom_program_courses', $link);
      }

      echo "✓ BCS: {$module['name']} ({$shortname_bcs}) [ID: {$course->id}]\n";
      $created_shared_bcs++;
   }
}

echo "\nCreated $created_shared_bit BIT shared instances\n";
echo "Created $created_shared_bcs BCS shared instances\n\n";

// STEP 3: Create BIT-only Courses
echo "Step 3: Creating BIT-only Courses\n";
echo str_repeat("-", 60) . "\n";

$created_bit = 0;
foreach ($bit_only as $module) {
   $shortname = $module['code']; // Keep original shortname for unique ones

   if (!$DB->record_exists('course', ['shortname' => $shortname])) {
      $ys = get_year_semester($module['semester']);
      $cat_key = "BIT_Y{$ys['year']}_S{$ys['sem']}";
      $category_id = $categories[$cat_key];

      $courseData = new stdClass();
      $courseData->fullname = $module['name'];
      $courseData->shortname = $shortname;
      $courseData->category = $category_id;
      $courseData->summary = "Credit Hours: {$module['credits']}<br>Type: {$module['type']}<br>BIT Program";
      $courseData->summaryformat = FORMAT_HTML;
      $courseData->format = 'topics';
      $courseData->numsections = 12;
      $courseData->startdate = time();
      $courseData->visible = 1;

      $course = create_course($courseData);

      // AUTO-LINK: Add to mdl_custom_program_courses
      $bit_program = $DB->get_record('custom_programs', ['acronym' => 'BIT']);
      if ($bit_program) {
         $link = new stdClass();
         $link->programid = $bit_program->id;
         $link->courseid = $course->id;
         $link->year = $ys['year'];
         $link->semester = $ys['sem'];
         $link->timecreated = time();
         $DB->insert_record('custom_program_courses', $link);
      }

      echo "✓ {$shortname}: {$module['name']} [ID: {$course->id}]\n";
      $created_bit++;
   }
}

echo "\nCreated $created_bit new BIT-only courses\n\n";

// STEP 4: Create BCS-only Courses
echo "Step 4: Creating BCS-only Courses\n";
echo str_repeat("-", 60) . "\n";

$created_bcs = 0;
foreach ($bcs_only as $module) {
   $shortname = $module['code'];

   if (!$DB->record_exists('course', ['shortname' => $shortname])) {
      $ys = get_year_semester($module['semester']);
      $cat_key = "BCS_Y{$ys['year']}_S{$ys['sem']}";
      $category_id = $categories[$cat_key];

      $courseData = new stdClass();
      $courseData->fullname = $module['name'];
      $courseData->shortname = $shortname;
      $courseData->category = $category_id;
      $courseData->summary = "Credit Hours: {$module['credits']}<br>Type: {$module['type']}<br>BCS Program";
      $courseData->summaryformat = FORMAT_HTML;
      $courseData->format = 'topics';
      $courseData->numsections = 12;
      $courseData->startdate = time();
      $courseData->visible = 1;

      $course = create_course($courseData);

      // AUTO-LINK: Add to mdl_custom_program_courses
      $bcs_program = $DB->get_record('custom_programs', ['acronym' => 'BCS']);
      if ($bcs_program) {
         $link = new stdClass();
         $link->programid = $bcs_program->id;
         $link->courseid = $course->id;
         $link->year = $ys['year'];
         $link->semester = $ys['sem'];
         $link->timecreated = time();
         $DB->insert_record('custom_program_courses', $link);
      }

      echo "✓ {$shortname}: {$module['name']} [ID: {$course->id}]\n";
      $created_bcs++;
   }
}

echo "\nCreated $created_bcs new BCS-only courses\n\n";

// Summary
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║         Course Structure Complete! ✓                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Total new courses: " . ($created_shared_bit + $created_shared_bcs + $created_bit + $created_bcs) . "\n\n";
