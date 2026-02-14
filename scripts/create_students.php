#!/usr/bin/env php
<?php
/**
 * Generate Students and Enroll in Programs
 * Creates student accounts for each program and year
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Student Generation and Enrollment                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$password = 'Student@2026';
$programs = ['BIT', 'BCS'];
$students_per_year = 5;

$created_students = 0;
$enrollments = 0;

// Step 1: Create students
echo "Step 1: Creating Student Accounts\n";
echo str_repeat("-", 60) . "\n";

foreach ($programs as $program) {
   echo "\n{$program} Program:\n";

   for ($year = 1; $year <= 3; $year++) {
      for ($i = 1; $i <= $students_per_year; $i++) {
         $username = strtolower("{$program}_y{$year}_student{$i}");

         // Check existence
         $existing = $DB->get_record('user', ['username' => $username]);
         if ($existing) {
            echo "  • Year $year Student $i (exists)\n";
         } else {
            // Create user
            $user = create_user_record($username, $password, 'manual');
            $user->firstname = ucfirst($program) . " Y{$year}";
            $user->lastname = "Student {$i}";
            $user->email = "{$username}@student.example.com";
            $user->city = 'Dar es Salaam';
            $user->country = 'TZ';
            $user->confirmed = 1;
            $user->mnethostid = $CFG->mnet_localhost_id; // Local Moodle

            $DB->update_record('user', $user);
            echo "  ✓ Year $year Student $i ({$username})\n";
            $created_students++;
         }
      }
   }
}

echo "\nCreated $created_students new students\n";
echo "Password for all students: $password\n\n";

// Step 2: Enroll students in courses
echo "Step 2: Enrolling Students in Courses\n";
echo str_repeat("-", 60) . "\n";

$student_role = $DB->get_record('role', ['shortname' => 'student']);

foreach ($programs as $program) {
   echo "\n{$program} Program Enrollments:\n";

   for ($year = 1; $year <= 3; $year++) {
      // Get students for this year
      $pattern = strtolower("{$program}_y{$year}_%");
      $year_students = $DB->get_records_sql(
         "SELECT * FROM {user} WHERE username LIKE ?",
         [$pattern]
      );

      if (empty($year_students)) {
         echo "  No students found for Year $year\n";
         continue;
      }

      // Get courses for this year and program
      $courses = [];

      // For each semester in this year
      for ($sem = 1; $sem <= 2; $sem++) {
         // Get category
         $cat_idnum = "{$program}_Y{$year}_S{$sem}";
         $category = $DB->get_record('course_categories', ['idnumber' => $cat_idnum]);

         if ($category) {
            $sem_courses = $DB->get_records('course', ['category' => $category->id]);
            $courses = array_merge($courses, $sem_courses);
         }
      }

      echo "  Year $year: " . count($year_students) . " students → " . count($courses) . " courses\n";

      foreach ($year_students as $student) {
         foreach ($courses as $course) {
            // Get enrol instance
            $enrol_instance = $DB->get_record('enrol', [
               'courseid' => $course->id,
               'enrol' => 'manual'
            ]);

            if (!$enrol_instance) {
               $enrol = enrol_get_plugin('manual');
               $enrol->add_default_instance($course);
               $enrol_instance = $DB->get_record('enrol', [
                  'courseid' => $course->id,
                  'enrol' => 'manual'
               ]);
            }

            // Check if already enrolled
            $already_enrolled = $DB->record_exists('user_enrolments', [
               'enrolid' => $enrol_instance->id,
               'userid' => $student->id
            ]);

            if (!$already_enrolled) {
               $enrol = enrol_get_plugin('manual');
               $enrol->enrol_user($enrol_instance, $student->id, $student_role->id);
               $enrollments++;
            }
         }
      }
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         Student Enrollment Complete! ✓                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Students created: $created_students\n";
echo "  • Course enrollments: $enrollments\n\n";
