#!/usr/bin/env php
<?php
/**
 * Fix user triple (id=72): enrol in the 4 missing BCS Y1 courses
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$userid = 72;
$missing_course_ids = [126, 128, 132, 133]; // ITU 07103, 07107, 07212, 07213

$enrol = enrol_get_plugin('manual');
$student_role = $DB->get_record('role', ['shortname' => 'student']);
$roleid = $student_role->id;

foreach ($missing_course_ids as $cid) {
   $course = $DB->get_record('course', ['id' => $cid]);
   $context = context_course::instance($cid);

   if (is_enrolled($context, $userid)) {
      echo "Already enrolled: {$course->shortname}\n";
      continue;
   }

   $instance = $DB->get_record('enrol', ['courseid' => $cid, 'enrol' => 'manual']);
   if (!$instance) {
      $iid = $enrol->add_instance($course);
      $instance = $DB->get_record('enrol', ['id' => $iid]);
   }

   $enrol->enrol_user($instance, $userid, $roleid);
   echo "Enrolled: {$course->shortname}\n";
}

// Verify total
$total = $DB->count_records_sql(
   "SELECT COUNT(*) FROM {user_enrolments} ue JOIN {enrol} e ON e.id=ue.enrolid WHERE ue.userid=?",
   [$userid]
);
echo "\nTotal enrolled courses for triple: {$total}\n";
