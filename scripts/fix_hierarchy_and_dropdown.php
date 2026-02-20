#!/usr/bin/env php
<?php
/**
 * Fix 2: Move Shared Modules under Department of Informatics
 * Fix 3: Update program dropdown to BCS and BIT only
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "=== Fix 2: Move Shared Modules under Informatics ===\n";
$dept   = $DB->get_record('course_categories', ['idnumber' => 'DEPT_INFORMATICS']);
$shared = $DB->get_record('course_categories', ['name'     => 'Shared Modules']);

if (!$dept) {
   echo "  ERROR: Department of Informatics not found\n";
} elseif (!$shared) {
   echo "  ERROR: Shared Modules category not found\n";
} elseif ((int)$shared->parent === (int)$dept->id) {
   echo "  Already under Informatics (id={$dept->id})\n";
} else {
   $obj = core_course_category::get($shared->id);
   $obj->change_parent($dept->id);
   echo "  ✓ Moved Shared Modules (id={$shared->id}) under Informatics (id={$dept->id})\n";
}

echo "\n=== Fix 3: Program dropdown -> BCS and BIT only ===\n";
$field = $DB->get_record('user_info_field', ['shortname' => 'program_study']);
if (!$field) {
   echo "  ERROR: program_study field not found\n";
} else {
   $field->param1 = "BCS\nBIT";
   $DB->update_record('user_info_field', $field);
   echo "  ✓ Updated program_study menu options to: BCS, BIT\n";
}

echo "\nDone.\n";
