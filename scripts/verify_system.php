#!/usr/bin/env php
<?php
/**
 * System Verification Script
 * Validates course count, user count, and enrollment integrity
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      e-LMS System Verification Report                 ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Verify Counts
echo "1. System Statistics:\n";
$course_count = $DB->count_records('course') - 1; // Exclude site course
$user_count = $DB->count_records_select('user', "deleted = 0 AND username != 'guest'");
$enrol_count = $DB->count_records('user_enrolments');

echo "   Courses:    " . str_pad($course_count, 5) . " (Expected: ~50)\n";
echo "   Users:      " . str_pad($user_count, 5) . " (Expected: ~40)\n";
echo "   Enrollments:" . str_pad($enrol_count, 5) . " (Expected: >800)\n\n";

// 2. Verify Programs
echo "2. Program Categories:\n";
$programs = ['BIT', 'BCS'];
foreach ($programs as $prog) {
   $cat = $DB->get_record('course_categories', ['idnumber' => $prog]);
   echo "   $prog: " . ($cat ? "✓ Found" : "✗ Missing") . "\n";

   if ($cat) {
      $years = $DB->get_records('course_categories', ['parent' => $cat->id]);
      echo "     Years found: " . count($years) . "/3\n";
   }
}

// 3. Verify Shared Courses & Groups
echo "\n3. Shared Modules Configuration:\n";
$common_cat = $DB->get_record('course_categories', ['idnumber' => 'COMMON']);
if ($common_cat) {
   $shared_courses = $DB->get_records('course', ['category' => $common_cat->id]);
   echo "   Shared Courses: " . count($shared_courses) . "\n";

   $sample = reset($shared_courses);
   if ($sample) {
      echo "   Sample Check ({$sample->shortname}):\n";
      $groups = $DB->get_records('groups', ['courseid' => $sample->id]);
      echo "     Groups: " . count($groups) . " (Expected: 2 - BIT & BCS)\n";
      foreach ($groups as $g) {
         echo "       - {$g->name}\n";
      }
   }
} else {
   echo "   ✗ Common Modules category missing\n";
}

// 4. Verify Students (Random Check)
echo "\n4. Student Enrollment Check:\n";
$random_student = $DB->get_record('user', ['username' => 'bit_y1_student1']);
if ($random_student) {
   echo "   Checking 'bit_y1_student1':\n";
   $courses = enrol_get_users_courses($random_student->id);
   echo "     Enrolled in: " . count($courses) . " courses\n";

   $has_shared = false;
   foreach ($courses as $c) {
      if ($c->category == $common_cat->id) {
         $has_shared = true;
         break;
      }
   }
   echo "     Has shared courses: " . ($has_shared ? "Yes" : "No") . "\n";
} else {
   echo "   ✗ Sample student 'bit_y1_student1' not found\n";
}

// 5. Verify Lecturers
echo "\n5. Lecturer Assignment Check:\n";
$random_lec = $DB->get_record('user', ['username' => 'prof_kimani']);
if ($random_lec) {
   echo "   Checking 'prof_kimani':\n";
   $courses = enrol_get_users_courses($random_lec->id);
   echo "     Teaching: " . count($courses) . " courses\n";
} else {
   echo "   ✗ Lecturer 'prof_kimani' not found\n";
}

echo "\nVerification Complete.\n";
