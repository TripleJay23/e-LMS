#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

// The 4 missing course IDs: 133 (ITU07213), 132 (ITU07212), 128 (ITU07107), 126 (ITU07103)
$missing_ids = [133, 132, 128, 126];
// The 8 working course IDs
$working_ids = [113, 114, 124, 127, 125, 129, 130, 131];

echo "=== Enrol instances on MISSING courses ===\n";
foreach ($missing_ids as $cid) {
   $c = $DB->get_record('course', ['id' => $cid]);
   echo "\n  Course: {$c->shortname} (id={$cid})\n";
   $instances = $DB->get_records('enrol', ['courseid' => $cid]);
   if (empty($instances)) {
      echo "    NO enrol instances at all!\n";
   } else {
      foreach ($instances as $inst) {
         echo "    enrol_id={$inst->id} type={$inst->enrol} status={$inst->status}\n";
      }
   }
}

echo "\n=== Enrol instances on WORKING courses (sample of 3) ===\n";
foreach (array_slice($working_ids, 0, 3) as $cid) {
   $c = $DB->get_record('course', ['id' => $cid]);
   echo "\n  Course: {$c->shortname} (id={$cid})\n";
   $instances = $DB->get_records('enrol', ['courseid' => $cid]);
   foreach ($instances as $inst) {
      echo "    enrol_id={$inst->id} type={$inst->enrol} status={$inst->status}\n";
   }
}

// Also test: can we actually enrol user 72 in missing course 126 right now?
echo "\n=== Test enrolment: user 72 → course 126 ===\n";
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => 126, 'enrol' => 'manual']);
if (!$instance) {
   echo "  No manual instance — creating one...\n";
   $course = $DB->get_record('course', ['id' => 126]);
   $iid = $enrol->add_instance($course);
   $instance = $DB->get_record('enrol', ['id' => $iid]);
   echo "  Created enrol instance id={$instance->id}\n";
}

$student_role = $DB->get_record('role', ['shortname' => 'student']);
$context = context_course::instance(126);
$already = is_enrolled($context, 72);
echo "  Already enrolled: " . ($already ? 'YES' : 'NO') . "\n";

if (!$already) {
   try {
      $enrol->enrol_user($instance, 72, $student_role->id);
      echo "  Enrolment: SUCCESS\n";
   } catch (Throwable $e) {
      echo "  Enrolment FAILED: " . $e->getMessage() . "\n";
   }
}

echo "\nDone.\n";
