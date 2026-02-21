#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

echo "=== TEACHER VERIFICATION ===\n\n";

$teacher_assigns = $DB->get_records_sql(
   "SELECT DISTINCT ra.userid FROM {role_assignments} ra
     JOIN {role} r ON r.id = ra.roleid JOIN {user} u ON u.id = ra.userid
     WHERE r.shortname IN ('editingteacher','teacher') AND u.deleted=0"
);

$all_ok = true;
$student_role = $DB->get_record('role', ['shortname' => 'student']);

foreach ($teacher_assigns as $ta) {
   $u = $DB->get_record('user', ['id' => $ta->userid]);
   profile_load_data($u);
   $reg = $u->profile_field_reg_number ?? '';
   $prog = $u->profile_field_program_study ?? '';
   $year = $u->profile_field_year_of_study ?? '';

   $reg_ok = (strpos($reg, 'DI-') === 0);
   $prog_ok = empty($prog);
   $year_ok = empty($year);

   // Check student role
   list($in_sql, $params) = $DB->get_in_or_equal([$ta->userid]);
   $student_count = $DB->count_records_sql(
      "SELECT COUNT(*) FROM {role_assignments} ra
         JOIN {context} ctx ON ctx.id=ra.contextid AND ctx.contextlevel=50
         WHERE ra.userid=? AND ra.roleid=?",
      [$ta->userid, $student_role->id]
   );
   $no_student = ($student_count == 0);

   $status = ($reg_ok && $prog_ok && $year_ok && $no_student) ? '✓' : '✗';
   if (!($reg_ok && $prog_ok && $year_ok && $no_student)) $all_ok = false;

   echo "{$status} {$u->firstname} {$u->lastname}\n";
   echo "    reg={$reg} " . ($reg_ok ? '✓' : '✗ NOT DI format') . "\n";
   echo "    program=" . ($prog ?: '(empty)') . " " . ($prog_ok ? '✓' : '✗') . "\n";
   echo "    year=" . ($year ?: '(empty)') . " " . ($year_ok ? '✓' : '✗') . "\n";
   echo "    student_roles={$student_count} " . ($no_student ? '✓' : '✗ HAS STUDENT ROLE') . "\n\n";
}

echo "OVERALL: " . ($all_ok ? "ALL PASS ✓" : "SOME FAILED ✗") . "\n";
