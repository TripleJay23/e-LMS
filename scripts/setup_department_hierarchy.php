#!/usr/bin/env php
<?php
/**
 * Setup and normalize hierarchy to:
 * Faculty of Informatics -> Department of Informatics -> BCS, BIT, COMMON
 *
 * Also aligns custom tables:
 * - mdl_custom_faculties
 * - mdl_custom_departments
 * - mdl_custom_programs (BCS/BIT -> Department of Informatics)
 *
 * Run:
 *   php scripts/setup_department_hierarchy.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/accesslib.php');

echo "Setup Department Hierarchy\n";
echo str_repeat("=", 60) . "\n\n";

/**
 * Ensure a course category exists and has the expected parent.
 *
 * @return core_course_category
 */
function ensure_course_category(string $name, string $idnumber, int $parentid = 0, string $description = ''): core_course_category
{
   global $DB;

   $existing = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
   if (!$existing) {
      return core_course_category::create((object)[
         'name' => $name,
         'idnumber' => $idnumber,
         'description' => $description,
         'descriptionformat' => FORMAT_HTML,
         'parent' => $parentid,
         'visible' => 1,
      ]);
   }

   $cat = core_course_category::get($existing->id);
   if ((int)$existing->parent !== $parentid) {
      $cat->change_parent($parentid);
   }

   $updates = (object)['id' => $existing->id];
   $hasupdates = false;
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

echo "Step 1: Ensure Moodle category hierarchy\n";
echo str_repeat("-", 60) . "\n";

$facultycat = ensure_course_category(
   'Faculty of Informatics',
   'FACULTY_INFORMATICS',
   0,
   '<p>Top-level faculty category for Informatics.</p>'
);
echo "+ FACULTY_INFORMATICS (ID: {$facultycat->id})\n";

$deptcat = ensure_course_category(
   'Department of Informatics',
   'DEPT_INFORMATICS',
   $facultycat->id,
   '<p>Department category containing BIT, BCS and Shared modules.</p>'
);
echo "+ DEPT_INFORMATICS (ID: {$deptcat->id})\n";

foreach (['BCS', 'BIT', 'COMMON'] as $idnumber) {
   $category = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
   if (!$category) {
      echo "- {$idnumber} not found, skipping move\n";
      continue;
   }

   if ((int)$category->parent !== (int)$deptcat->id) {
      core_course_category::get($category->id)->change_parent($deptcat->id);
   }
   echo "+ {$idnumber} is under DEPT_INFORMATICS\n";
}

echo "\nStep 2: Align custom faculty/department/program tables\n";
echo str_repeat("-", 60) . "\n";

$now = time();
$faculty = $DB->get_record('custom_faculties', ['code' => 'FI']);
if (!$faculty) {
   $facultyid = $DB->insert_record('custom_faculties', (object)[
      'name' => 'Faculty of Informatics',
      'code' => 'FI',
      'timecreated' => $now,
      'timemodified' => $now,
   ]);
   $faculty = $DB->get_record('custom_faculties', ['id' => $facultyid], '*', MUST_EXIST);
}
if ($faculty->name !== 'Faculty of Informatics') {
   $faculty->name = 'Faculty of Informatics';
   $faculty->timemodified = $now;
   $DB->update_record('custom_faculties', $faculty);
}
echo "+ Custom faculty FI ready (ID: {$faculty->id})\n";

$customdept = $DB->get_record_sql(
   "SELECT *
      FROM {custom_departments}
     WHERE code = ? OR name = ?
  ORDER BY id ASC",
   ['INF', 'Department of Informatics'],
   IGNORE_MULTIPLE
);

if (!$customdept) {
   $deptid = $DB->insert_record('custom_departments', (object)[
      'facultyid' => $faculty->id,
      'name' => 'Department of Informatics',
      'code' => 'INF',
      'timecreated' => $now,
      'timemodified' => $now,
   ]);
   $customdept = $DB->get_record('custom_departments', ['id' => $deptid], '*', MUST_EXIST);
}

$deptupdates = false;
if ((int)$customdept->facultyid !== (int)$faculty->id) {
   $customdept->facultyid = $faculty->id;
   $deptupdates = true;
}
if ($customdept->name !== 'Department of Informatics') {
   $customdept->name = 'Department of Informatics';
   $deptupdates = true;
}
if ((string)$customdept->code !== 'INF') {
   $customdept->code = 'INF';
   $deptupdates = true;
}
if ($deptupdates) {
   $customdept->timemodified = $now;
   $DB->update_record('custom_departments', $customdept);
}
echo "+ Custom department INF ready (ID: {$customdept->id})\n";

$programs = $DB->get_records_list('custom_programs', 'acronym', ['BCS', 'BIT']);
foreach ($programs as $program) {
   if ((int)$program->departmentid !== (int)$customdept->id) {
      $program->departmentid = $customdept->id;
      $program->timemodified = $now;
      $DB->update_record('custom_programs', $program);
   }
   echo "+ Program {$program->acronym} linked to INF department\n";
}

echo "\nStep 3: Scope hod_informatics manager role to DEPT_INFORMATICS\n";
echo str_repeat("-", 60) . "\n";

$hod = $DB->get_record('user', ['username' => 'hod_informatics']);
$managerrole = $DB->get_record('role', ['shortname' => 'manager']);

if (!$hod) {
   echo "- User hod_informatics not found, skipping role scope update\n";
} elseif (!$managerrole) {
   echo "- Manager role not found, skipping role scope update\n";
} else {
   $existingassignments = $DB->get_records('role_assignments', [
      'userid' => $hod->id,
      'roleid' => $managerrole->id,
   ]);
   foreach ($existingassignments as $ra) {
      role_unassign($managerrole->id, $hod->id, $ra->contextid);
   }

   $deptcontext = context_coursecat::instance($deptcat->id);
   role_assign($managerrole->id, $hod->id, $deptcontext->id);
   echo "+ hod_informatics manager role assigned at context {$deptcontext->id}\n";
}

echo "\nDone.\n";

