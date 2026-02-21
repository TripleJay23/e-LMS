#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Teacher Diagnosis: Reg Numbers + Role Assignments    ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. All users with teacher roles ──────────────────────────────────────────
echo "=== USERS WITH TEACHER ROLES ===\n";
$teacher_assigns = $DB->get_records_sql(
   "SELECT DISTINCT ra.userid, r.shortname AS rolename
     FROM {role_assignments} ra
     JOIN {role} r ON r.id = ra.roleid
     JOIN {user} u ON u.id = ra.userid
     WHERE r.shortname IN ('editingteacher', 'teacher') AND u.deleted = 0"
);

$teacher_ids = [];
foreach ($teacher_assigns as $ta) {
   $teacher_ids[$ta->userid] = $ta->rolename;
}

foreach (array_keys($teacher_ids) as $tid) {
   $u = $DB->get_record('user', ['id' => $tid]);
   if (!$u) continue;
   profile_load_data($u);
   $reg = $u->profile_field_reg_number ?? '(none)';
   $prog = $u->profile_field_program_study ?? '(none)';
   $year = $u->profile_field_year_of_study ?? '(none)';
   $role = $teacher_ids[$tid];
   echo "  id={$u->id} {$u->firstname} {$u->lastname} | role={$role} | email={$u->email}\n";
   echo "    reg_number={$reg} | program={$prog} | year={$year}\n";
}

// ── 2. Teachers who ALSO have student role in courses ────────────────────────
echo "\n=== TEACHERS WITH STUDENT ROLE IN COURSES ===\n";
$tids = array_keys($teacher_ids);
if (!empty($tids)) {
   list($in_sql, $in_params) = $DB->get_in_or_equal($tids);
   $student_assigns = $DB->get_records_sql(
      "SELECT ra.userid, u.firstname, u.lastname, r.shortname AS rolename,
                c.shortname AS cshort, c.fullname AS cfull
         FROM {role_assignments} ra
         JOIN {user} u ON u.id = ra.userid
         JOIN {role} r ON r.id = ra.roleid
         JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
         JOIN {course} c ON c.id = ctx.instanceid
         WHERE ra.userid {$in_sql} AND r.shortname = 'student'
         ORDER BY u.lastname, c.shortname",
      $in_params
   );

   if (empty($student_assigns)) {
      echo "  NONE ✓\n";
   } else {
      echo "  Found " . count($student_assigns) . " cases:\n";
      foreach ($student_assigns as $sa) {
         echo "  ✗ {$sa->firstname} {$sa->lastname} (id={$sa->userid}) → student in {$sa->cshort} ({$sa->cfull})\n";
      }
   }
}

// ── 3. All reg numbers in the system ─────────────────────────────────────────
echo "\n=== ALL REG NUMBERS ===\n";
$reg_fid = $DB->get_field('user_info_field', 'id', ['shortname' => 'reg_number']);
if ($reg_fid) {
   $all_regs = $DB->get_records_sql(
      "SELECT uid.userid, uid.data, u.firstname, u.lastname
         FROM {user_info_data} uid
         JOIN {user} u ON u.id = uid.userid
         WHERE uid.fieldid = ? AND uid.data != '' AND u.deleted = 0
         ORDER BY uid.data",
      [$reg_fid]
   );
   foreach ($all_regs as $r) {
      $tag = isset($teacher_ids[$r->userid]) ? ' [TEACHER]' : ' [STUDENT]';
      echo "  {$r->data} | {$r->firstname} {$r->lastname}{$tag}\n";
   }
}

// ── 4. Reg tokens sample ─────────────────────────────────────────────────────
echo "\n=== REG TOKENS (all) ===\n";
$tokens = $DB->get_records_sql("SELECT * FROM {custom_reg_tokens} ORDER BY reg_number");
foreach ($tokens as $t) {
   echo "  {$t->reg_number} | prog={$t->program} | yr={$t->year} | batch={$t->batch} | status={$t->status}\n";
}

echo "\nDone.\n";
