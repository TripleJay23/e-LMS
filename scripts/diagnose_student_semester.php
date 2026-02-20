#!/usr/bin/env php
<?php
/**
 * Diagnose Student Semester Assignment
 * Reports what courses a student is enrolled in and their actual year/semester metadata.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

$target_username = 'bcs_y2_student1';

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Diagnosing Student Semester Assignments           ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Find the student ───────────────────────────────────────────────────────
$user = $DB->get_record('user', ['username' => $target_username]);
if (!$user) {
   die("Error: User '$target_username' not found.\n");
}
echo "Student : {$user->firstname} {$user->lastname} (username={$user->username}, id={$user->id})\n\n";

// ── 2. Get all enrolled courses with category and program_courses metadata ─────
$sql = "SELECT c.id, c.fullname, c.shortname, c.category,
               cc.name        AS catname,
               cc.idnumber    AS catidnum,
               cc.parent      AS catparent,
               pc.name        AS parent_catname,
               pc.idnumber    AS parent_catidnum,
               cpc.year       AS prog_year,
               cpc.semester   AS prog_sem
        FROM {user_enrolments} ue
        JOIN {enrol}               e   ON e.id       = ue.enrolid
        JOIN {course}              c   ON c.id       = e.courseid
        JOIN {course_categories}   cc  ON cc.id      = c.category
        LEFT JOIN {course_categories} pc ON pc.id    = cc.parent
        LEFT JOIN {custom_program_courses} cpc ON cpc.courseid = c.id
        WHERE ue.userid = :uid
        ORDER BY cpc.year, cpc.semester, c.shortname";

$records = $DB->get_records_sql($sql, ['uid' => $user->id]);

echo "Enrolled in " . count($records) . " course(s):\n";
echo str_repeat('─', 110) . "\n";
printf("%-35s | %-25s | CPC Y/S | Template Displayed\n", "Shortname", "Category");
echo str_repeat('─', 110) . "\n";

$sem_counts = [];
foreach ($records as $r) {
   $cpc_year = $r->prog_year ?: '?';
   $cpc_sem  = $r->prog_sem  ?: '?';

   // Extract what was actually written to the summary (what the student sees)
   $displayed_sem = 'NOT SET';
   if (preg_match('/<strong>Semester:<\/strong>\s*Semester\s*([IVX]+)/i', $r->summary ?? '', $m)) {
      $displayed_sem = 'Semester ' . $m[1];
   }

   // Tally
   $key = "Y{$cpc_year}_S{$cpc_sem}";
   $sem_counts[$key] = ($sem_counts[$key] ?? 0) + 1;

   printf(
      "%-35s | %-25s | Y%s  S%s  | %s\n",
      substr($r->shortname, 0, 35),
      substr($r->catname, 0, 25),
      $cpc_year,
      $cpc_sem,
      $displayed_sem
   );
}

echo str_repeat('─', 110) . "\n\n";

echo "Summary by Year/Semester (from custom_program_courses):\n";
ksort($sem_counts);
foreach ($sem_counts as $k => $cnt) {
   echo "  $k : $cnt courses\n";
}

echo "\n";

// ── 3. Cross-check: What should BCS Year 2 students have? ──────────────────
echo "Expected BCS Year 2 course codes (from custom_program_courses for BCS program, year=2):\n";
$bcs_prog = $DB->get_record('custom_programs', ['acronym' => 'BCS']);
if ($bcs_prog) {
   $expected = $DB->get_records_sql(
      "SELECT cpc.*, c.shortname, c.fullname
         FROM {custom_program_courses} cpc
         JOIN {course} c ON c.id = cpc.courseid
         WHERE cpc.programid = :pid AND cpc.year = 2
         ORDER BY cpc.semester, c.shortname",
      ['pid' => $bcs_prog->id]
   );
   echo "  Found " . count($expected) . " Year-2 courses linked to BCS program:\n";
   foreach ($expected as $e) {
      echo "    Y{$e->year} S{$e->semester} | {$e->shortname}\n";
   }
} else {
   echo "  BCS program not found in custom_programs table.\n";
}
echo "\nDone.\n";
