#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

echo "=== bcs_y1_student1 courses ===\n";
$u1 = $DB->get_record('user', ['username' => 'bcs_y1_student1']);
if ($u1) {
   $courses1 = $DB->get_records_sql(
      "SELECT c.id, c.shortname, c.fullname, cc.name AS catname
         FROM {user_enrolments} ue
         JOIN {enrol} e ON e.id=ue.enrolid
         JOIN {course} c ON c.id=e.courseid
         JOIN {course_categories} cc ON cc.id=c.category
         WHERE ue.userid=?
         ORDER BY c.shortname",
      [$u1->id]
   );
   echo "Total: " . count($courses1) . "\n";
   foreach ($courses1 as $c) {
      echo "  {$c->shortname} | {$c->catname} | {$c->fullname}\n";
   }
}

echo "\n=== Find 'triple' user ===\n";
$u2 = $DB->get_record_sql(
   "SELECT * FROM {user} WHERE username LIKE ? OR firstname LIKE ? OR lastname LIKE ?",
   ['%triple%', '%triple%', '%triple%']
);
if (!$u2) {
   echo "User 'triple' not found - listing 10 newest users:\n";
   $recent = $DB->get_records_sql("SELECT id, username, firstname, lastname, email, confirmed FROM {user} WHERE deleted=0 ORDER BY id DESC LIMIT 10");
   foreach ($recent as $r) {
      echo "  id={$r->id} {$r->username} ({$r->firstname} {$r->lastname}) confirmed={$r->confirmed} {$r->email}\n";
   }
} else {
   echo "Found: {$u2->username} ({$u2->firstname} {$u2->lastname}) id={$u2->id}\n";
   profile_load_data($u2);
   echo "Program: " . ($u2->profile_field_program_study ?? 'NULL') . " Year: " . ($u2->profile_field_year_of_study ?? 'NULL') .
      " Reg: " . ($u2->profile_field_reg_number ?? 'NULL') . "\n";

   $courses2 = $DB->get_records_sql(
      "SELECT c.id, c.shortname, c.fullname, cc.name AS catname
         FROM {user_enrolments} ue
         JOIN {enrol} e ON e.id=ue.enrolid
         JOIN {course} c ON c.id=e.courseid
         JOIN {course_categories} cc ON cc.id=c.category
         WHERE ue.userid=?
         ORDER BY c.shortname",
      [$u2->id]
   );
   echo "Total: " . count($courses2) . "\n";
   foreach ($courses2 as $c) {
      echo "  {$c->shortname} | {$c->catname} | {$c->fullname}\n";
   }
}

echo "\n=== custom_program_courses for BCS Y1 ===\n";
$bcs = $DB->get_record('custom_programs', ['acronym' => 'BCS']);
if ($bcs) {
   $links = $DB->get_records_sql(
      "SELECT pc.id, pc.courseid, c.shortname, c.fullname, cc.name AS catname
         FROM {custom_program_courses} pc
         JOIN {course} c ON c.id=pc.courseid
         JOIN {course_categories} cc ON cc.id=c.category
         WHERE pc.programid=? AND pc.year=?
         ORDER BY c.shortname",
      [$bcs->id, 1]
   );
   echo "Total mappings: " . count($links) . "\n";
   foreach ($links as $l) {
      echo "  {$l->shortname} | {$l->catname} | {$l->fullname}\n";
   }
}

echo "\n=== All courses under BCS Year 1 + Shared Year 1 categories ===\n";
$bcs_y1_cat = $DB->get_record('course_categories', ['idnumber' => 'BCS_Y1']);
$shared_y1_cats = $DB->get_records_sql("SELECT cc.id, cc.name FROM {course_categories} cc WHERE cc.name='Year 1' AND cc.parent IN (SELECT id FROM {course_categories} WHERE name='Shared Modules')");
echo "BCS_Y1 category: " . ($bcs_y1_cat ? "id={$bcs_y1_cat->id}" : "NOT FOUND") . "\n";

$cat_ids = [];
if ($bcs_y1_cat) $cat_ids[] = $bcs_y1_cat->id;
foreach ($shared_y1_cats as $sc) {
   echo "Shared Y1 category: id={$sc->id}\n";
   $cat_ids[] = $sc->id;
}

if (!empty($cat_ids)) {
   list($in_sql, $in_params) = $DB->get_in_or_equal($cat_ids);
   $all_courses = $DB->get_records_sql(
      "SELECT c.id, c.shortname, c.fullname, cc.name AS catname
         FROM {course} c
         JOIN {course_categories} cc ON cc.id=c.category
         WHERE c.category {$in_sql}
         ORDER BY c.shortname",
      $in_params
   );
   echo "Total courses in categories: " . count($all_courses) . "\n";
   foreach ($all_courses as $c) {
      echo "  {$c->shortname} | {$c->catname} | {$c->fullname}\n";
   }
}

echo "\nDone.\n";
