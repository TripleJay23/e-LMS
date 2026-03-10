#!/usr/bin/env php
<?php
/**
 * Audit Teacher Assignments
 * Verifies each course has exactly 1 editing teacher.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║         Audit Teacher Assignments                     ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);

$courses = $DB->get_records_sql('SELECT id, shortname, fullname FROM {course} WHERE id != 1 ORDER BY shortname');

$no_teacher = [];
$multi_teacher = [];
$ok = 0;

foreach ($courses as $c) {
   $ctx = context_course::instance($c->id);
   $teachers = $DB->get_records_sql(
      'SELECT u.username, u.firstname, u.lastname
         FROM {role_assignments} ra
         JOIN {user} u ON u.id = ra.userid
         WHERE ra.contextid = ? AND ra.roleid = ?',
      [$ctx->id, $teacher_role->id]
   );

   $count = count($teachers);
   if ($count === 0) {
      $no_teacher[] = $c;
   } elseif ($count > 1) {
      $names = [];
      foreach ($teachers as $t) $names[] = $t->firstname . ' ' . $t->lastname . ' (' . $t->username . ')';
      $multi_teacher[] = ['course' => $c, 'teachers' => $names];
   } else {
      $t = reset($teachers);
      echo "  ✓ {$c->shortname} → {$t->firstname} {$t->lastname}\n";
      $ok++;
   }
}

echo "\n--- Summary ---\n";
echo "OK (1 teacher): $ok\n";

if (!empty($no_teacher)) {
   echo "\n⚠ NO TEACHER (" . count($no_teacher) . "):\n";
   foreach ($no_teacher as $c) echo "  - {$c->shortname} | {$c->fullname}\n";
}

if (!empty($multi_teacher)) {
   echo "\n⚠ MULTIPLE TEACHERS (" . count($multi_teacher) . "):\n";
   foreach ($multi_teacher as $m) {
      echo "  - {$m['course']->shortname}: " . implode(', ', $m['teachers']) . "\n";
   }
}

echo "\nDone!\n";
