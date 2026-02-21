#!/usr/bin/env php
<?php
/**
 * Fix teachers:
 *   1. Generate new DI-01-XXXX-2026 reg numbers
 *   2. Clear program_study and year_of_study fields
 *   3. Release old student-format tokens
 *   4. Remove student role assignments from courses
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Fix Teachers: Reg Numbers + Student Roles            ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Identify all teachers
$teacher_assigns = $DB->get_records_sql(
   "SELECT DISTINCT ra.userid
     FROM {role_assignments} ra
     JOIN {role} r ON r.id = ra.roleid
     JOIN {user} u ON u.id = ra.userid
     WHERE r.shortname IN ('editingteacher', 'teacher') AND u.deleted = 0"
);
$teacher_ids = array_keys($teacher_assigns);

// Get profile field IDs
$reg_fid  = $DB->get_field('user_info_field', 'id', ['shortname' => 'reg_number']);
$prog_fid = $DB->get_field('user_info_field', 'id', ['shortname' => 'program_study']);
$year_fid = $DB->get_field('user_info_field', 'id', ['shortname' => 'year_of_study']);

$student_role = $DB->get_record('role', ['shortname' => 'student']);

// ── 1. Generate new reg numbers and update profiles ──────────────────────────
echo "=== STEP 1: Assign DI-format reg numbers ===\n";
$batch = 1;
$enroll_year = 2026;
$used_numbers = [];

foreach ($teacher_ids as $tid) {
   $u = $DB->get_record('user', ['id' => $tid]);
   if (!$u) continue;

   // Generate unique random 4-digit number
   do {
      $rand = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
   } while (in_array($rand, $used_numbers));
   $used_numbers[] = $rand;

   $new_reg = sprintf('DI-%02d-%s-%d', $batch, $rand, $enroll_year);

   // Get old reg number
   $old_reg = $DB->get_field('user_info_data', 'data', ['userid' => $tid, 'fieldid' => $reg_fid]);

   // Update or insert reg_number
   $existing = $DB->get_record('user_info_data', ['userid' => $tid, 'fieldid' => $reg_fid]);
   if ($existing) {
      $existing->data = $new_reg;
      $DB->update_record('user_info_data', $existing);
   } else {
      $DB->insert_record('user_info_data', (object)[
         'userid' => $tid,
         'fieldid' => $reg_fid,
         'data' => $new_reg,
         'dataformat' => 0
      ]);
   }

   // Clear program_study
   $prog_rec = $DB->get_record('user_info_data', ['userid' => $tid, 'fieldid' => $prog_fid]);
   if ($prog_rec) {
      $prog_rec->data = '';
      $DB->update_record('user_info_data', $prog_rec);
   }

   // Clear year_of_study
   $year_rec = $DB->get_record('user_info_data', ['userid' => $tid, 'fieldid' => $year_fid]);
   if ($year_rec) {
      $year_rec->data = '';
      $DB->update_record('user_info_data', $year_rec);
   }

   // Release old token if it was a student-format token
   if ($old_reg && preg_match('/^(BCS|BIT)-/', $old_reg)) {
      $old_token = $DB->get_record('custom_reg_tokens', ['reg_number' => $old_reg]);
      if ($old_token) {
         $DB->delete_records('custom_reg_tokens', ['id' => $old_token->id]);
         echo "  ✓ {$u->firstname} {$u->lastname}: {$old_reg} → {$new_reg}  (old token deleted)\n";
      } else {
         echo "  ✓ {$u->firstname} {$u->lastname}: {$old_reg} → {$new_reg}  (no old token)\n";
      }
   } else {
      echo "  ✓ {$u->firstname} {$u->lastname}: (none) → {$new_reg}\n";
   }
}

// ── 2. Remove student role assignments from courses ──────────────────────────
echo "\n=== STEP 2: Remove student roles from teachers ===\n";
if (!empty($teacher_ids)) {
   list($in_sql, $in_params) = $DB->get_in_or_equal($teacher_ids);
   $student_assigns = $DB->get_records_sql(
      "SELECT ra.id AS raid, ra.userid, ra.contextid, ra.roleid,
                c.shortname AS cshort, c.fullname AS cfull, u.firstname, u.lastname
         FROM {role_assignments} ra
         JOIN {user} u ON u.id = ra.userid
         JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
         JOIN {course} c ON c.id = ctx.instanceid
         WHERE ra.userid {$in_sql} AND ra.roleid = ?
         ORDER BY u.lastname, c.shortname",
      array_merge($in_params, [$student_role->id])
   );

   if (empty($student_assigns)) {
      echo "  No student role assignments found ✓\n";
   } else {
      foreach ($student_assigns as $sa) {
         // Remove role assignment
         $DB->delete_records('role_assignments', ['id' => $sa->raid]);

         // Also remove the user_enrolment if enrolled via manual as student
         $context = context_course::instance($DB->get_field('context', 'instanceid', ['id' => $sa->contextid]));
         $course_id = $context->instanceid;

         // Check if they have another role (editingteacher) in this course
         $other_roles = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {role_assignments} WHERE userid = ? AND contextid = ?",
            [$sa->userid, $sa->contextid]
         );

         if ($other_roles == 0) {
            // No other roles — also remove enrolment
            $enrolments = $DB->get_records_sql(
               "SELECT ue.id FROM {user_enrolments} ue
                     JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.userid = ? AND e.courseid = ?",
               [$sa->userid, $course_id]
            );
            foreach ($enrolments as $ue) {
               $DB->delete_records('user_enrolments', ['id' => $ue->id]);
            }
            echo "  ✓ {$sa->firstname} {$sa->lastname}: removed student role + enrolment from {$sa->cshort}\n";
         } else {
            echo "  ✓ {$sa->firstname} {$sa->lastname}: removed student role from {$sa->cshort} (kept enrolment — has teacher role)\n";
         }
      }
   }
}

echo "\n=== DONE ===\n";
echo "Run 'php scripts/diagnose_teachers.php' to verify.\n";
