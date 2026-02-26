#!/usr/bin/env php
<?php
/**
 * Create and normalize course categories for e-LMS.
 *
 * Target hierarchy:
 * Faculty of Informatics
 *   -> Department of Informatics
 *      -> BCS
 *      -> BIT
 *      -> Shared Modules
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "e-LMS Course Categories Setup\n";
echo str_repeat("=", 60) . "\n\n";

/**
 * Ensure category exists with expected parent/name/description.
 *
 * @return core_course_category
 */
function ensure_category(string $name, string $idnumber, int $parentid = 0, string $description = ''): core_course_category
{
   global $DB;

   $existing = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
   if (!$existing) {
      $data = (object)[
         'name' => $name,
         'idnumber' => $idnumber,
         'description' => $description,
         'descriptionformat' => FORMAT_HTML,
         'parent' => $parentid,
         'visible' => 1,
      ];
      return core_course_category::create($data);
   }

   $cat = core_course_category::get($existing->id);
   $updates = (object)['id' => $existing->id];
   $hasupdates = false;

   if ((int)$existing->parent !== $parentid) {
      $cat->change_parent($parentid);
   }

   if ($existing->name !== $name) {
      $updates->name = $name;
      $hasupdates = true;
   }
   if ((string)$existing->description !== (string)$description) {
      $updates->description = $description;
      $updates->descriptionformat = FORMAT_HTML;
      $hasupdates = true;
   }

   if ($hasupdates) {
      $DB->update_record('course_categories', $updates);
   }

   return core_course_category::get($existing->id);
}

try {
   echo "Step 1: Ensuring Faculty and Department categories\n";
   echo str_repeat("-", 60) . "\n";

   $faculty = ensure_category(
      'Faculty of Informatics',
      'FACULTY_INFORMATICS',
      0,
      '<p>Top-level academic unit for Informatics programs.</p>'
   );
   echo "+ FACULTY_INFORMATICS (ID: {$faculty->id})\n";

   $department = ensure_category(
      'Department of Informatics',
      'DEPT_INFORMATICS',
      $faculty->id,
      '<p>Department offering BIT, BCS and shared Informatics courses.</p>'
   );
   echo "+ DEPT_INFORMATICS (ID: {$department->id})\n\n";

   echo "Step 2: Ensuring program categories under Department\n";
   echo str_repeat("-", 60) . "\n";

   $programs = $DB->get_records_sql(
      "SELECT p.*, d.name AS department_name
         FROM {custom_programs} p
         JOIN {custom_departments} d ON p.departmentid = d.id
        ORDER BY p.acronym"
   );

   foreach ($programs as $program) {
      $description = "<p><strong>Level:</strong> " . ucfirst((string)$program->level) . "</p>"
         . "<p><strong>Department:</strong> {$program->department_name}</p>"
         . "<p><strong>Duration:</strong> {$program->duration} semesters</p>";

      $cat = ensure_category(
         $program->name,
         (string)$program->acronym,
         $department->id,
         $description
      );

      echo "+ {$program->acronym} (ID: {$cat->id})\n";
   }

   echo "\nStep 3: Ensuring Shared Modules hierarchy\n";
   echo str_repeat("-", 60) . "\n";

   $shared = ensure_category(
      'Shared Modules',
      'COMMON',
      $department->id,
      '<p>Courses shared by BIT and BCS programs.</p>'
   );
   echo "+ COMMON (ID: {$shared->id})\n";

   for ($year = 1; $year <= 3; $year++) {
      $yearidnum = "common_y{$year}";
      $yearcat = ensure_category("Year {$year}", $yearidnum, $shared->id);
      echo "  + {$yearidnum} (ID: {$yearcat->id})\n";

      for ($sem = 1; $sem <= 2; $sem++) {
         $semidnum = "common_y{$year}_s{$sem}";
         $semcat = ensure_category("Semester {$sem}", $semidnum, $yearcat->id);
         echo "    + {$semidnum} (ID: {$semcat->id})\n";
      }
   }

   echo "\nDone.\n";
   echo "Hierarchy target is now enforced in an idempotent way.\n";
} catch (Exception $e) {
   echo "ERROR: {$e->getMessage()}\n";
   echo $e->getTraceAsString() . "\n";
   exit(1);
}

