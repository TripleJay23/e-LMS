#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

function get_enrolled_courses($DB, $userid)
{
   return $DB->get_records_sql(
      "SELECT c.id, c.shortname, c.fullname, cc.name AS catname, cc.id AS catid
         FROM {user_enrolments} ue
         JOIN {enrol} e ON e.id=ue.enrolid
         JOIN {course} c ON c.id=e.courseid
         JOIN {course_categories} cc ON cc.id=c.category
         WHERE ue.userid=?
         ORDER BY c.shortname",
      [$userid]
   );
}

echo "=== bcs_y1_student1 (baseline) ===\n";
$u1 = $DB->get_record('user', ['username' => 'bcs_y1_student1']);
$c1 = get_enrolled_courses($DB, $u1->id);
echo "Total: " . count($c1) . "\n";
$c1_ids = [];
foreach ($c1 as $c) {
   echo "  {$c->shortname} | cat={$c->catname} (id={$c->catid}) | {$c->fullname}\n";
   $c1_ids[] = $c->id;
}

echo "\n=== triple (id=72) ===\n";
$c2 = get_enrolled_courses($DB, 72);
echo "Total: " . count($c2) . "\n";
$c2_ids = [];
foreach ($c2 as $c) {
   echo "  {$c->shortname} | cat={$c->catname} (id={$c->catid}) | {$c->fullname}\n";
   $c2_ids[] = $c->id;
}

// Find missing
$missing = array_diff($c1_ids, $c2_ids);
echo "\n=== Courses in bcs_y1_student1 but NOT in triple ===\n";
if (empty($missing)) {
   echo "  NONE — they match!\n";
} else {
   foreach ($missing as $cid) {
      $c = $DB->get_record('course', ['id' => $cid]);
      $cat = $DB->get_record('course_categories', ['id' => $c->category]);
      echo "  MISSING: {$c->shortname} | cat={$cat->name} (id={$cat->id}) | {$c->fullname}\n";

      // Check if this course is in custom_program_courses for BCS Y1
      $bcs = $DB->get_record('custom_programs', ['acronym' => 'BCS']);
      $link = $DB->get_record('custom_program_courses', ['programid' => $bcs->id, 'year' => 1, 'courseid' => $cid]);
      echo "    In custom_program_courses(BCS,Y1): " . ($link ? "YES" : "NO") . "\n";
   }
}

echo "\n=== custom_program_courses for BCS Y1 (full dump) ===\n";
$bcs = $DB->get_record('custom_programs', ['acronym' => 'BCS']);
$all_links = $DB->get_records('custom_program_courses', ['programid' => $bcs->id, 'year' => 1]);
echo "Total mappings: " . count($all_links) . "\n";
foreach ($all_links as $l) {
   $c = $DB->get_record('course', ['id' => $l->courseid]);
   $in_triple = in_array($l->courseid, $c2_ids) ? 'ENROLLED' : 'MISSING';
   echo "  course_id={$l->courseid} {$c->shortname} → {$in_triple}\n";
}

echo "\nDone.\n";
