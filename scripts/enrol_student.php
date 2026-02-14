#!/usr/bin/env php
<?php
/**
 * Student Program Enrollment Script
 * Enrolls a student in all courses for their assigned program
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');

// Check command line arguments
if ($argc < 3) {
   echo "Usage: php enrol_student.php <username> <program_acronym>\n\n";
   echo "Example: php enrol_student.php john.doe BIT\n\n";
   echo "Available programs:\n";

   $programs = $DB->get_records('custom_programs', [], 'acronym');
   foreach ($programs as $program) {
      echo "  • {$program->acronym} - {$program->name}\n";
   }
   exit(1);
}

$username = $argv[1];
$programAcronym = $argv[2];

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║     e-LMS Student Program Enrollment                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

try {
   // Get user
   $user = $DB->get_record('user', ['username' => $username]);
   if (!$user) {
      echo "✗ Error: User '$username' not found\n";
      exit(1);
   }
   echo "Student: {$user->firstname} {$user->lastname} ({$user->email})\n";

   // Get program
   $program = $DB->get_record('custom_programs', ['acronym' => $programAcronym]);
   if (!$program) {
      echo "✗ Error: Program '$programAcronym' not found\n";
      exit(1);
   }
   echo "Program: {$program->name} ({$program->acronym})\n\n";

   // Check if already enrolled in program
   $existing = $DB->get_record('custom_student_programs', [
      'userid' => $user->id,
      'programid' => $program->id
   ]);

   if (!$existing) {
      // Create program enrollment record
      $enrollment = new stdClass();
      $enrollment->userid = $user->id;
      $enrollment->programid = $program->id;
      $enrollment->yearofstudy = 1;
      $enrollment->status = 'active';
      $enrollment->timecreated = time();
      $enrollment->timemodified = time();

      $DB->insert_record('custom_student_programs', $enrollment);
      echo "✓ Enrolled student in program\n";
   } else {
      echo "• Student already enrolled in program\n";
   }

   // Get all courses for this program
   $programCourses = $DB->get_records('custom_program_courses', ['programid' => $program->id]);

   if (empty($programCourses)) {
      echo "\n⚠ No courses linked to this program yet\n";
      echo "  Use Moodle admin interface to link courses to program\n";
      exit(0);
   }

   // Get student role
   $studentRole = $DB->get_record('role', ['shortname' => 'student']);

   echo "\nEnrolling in courses:\n";

   $enrolledCount = 0;
   foreach ($programCourses as $pc) {
      $course = $DB->get_record('course', ['id' => $pc->courseid]);
      if (!$course) continue;

      // Check if manual enrolment instance exists
      $enrolInstance = $DB->get_record('enrol', [
         'courseid' => $course->id,
         'enrol' => 'manual'
      ]);

      if (!$enrolInstance) {
         // Create manual enrolment instance
         $enrol = enrol_get_plugin('manual');
         $enrol->add_default_instance($course);
         $enrolInstance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => 'manual'
         ]);
      }

      // Check if already enrolled
      $isEnrolled = $DB->record_exists('user_enrolments', [
         'enrolid' => $enrolInstance->id,
         'userid' => $user->id
      ]);

      if (!$isEnrolled) {
         // Enrol student
         $enrol = enrol_get_plugin('manual');
         $enrol->enrol_user($enrolInstance, $user->id, $studentRole->id);

         echo "  ✓ {$course->fullname}\n";
         $enrolledCount++;
      } else {
         echo "  • {$course->fullname} (already enrolled)\n";
      }
   }

   echo "\n╔════════════════════════════════════════════════════════╗\n";
   echo "║            Enrollment Complete! ✓                      ║\n";
   echo "╚════════════════════════════════════════════════════════╝\n\n";

   echo "Summary:\n";
   echo "  • Student: {$user->username}\n";
   echo "  • Program: {$program->acronym}\n";
   echo "  • Courses enrolled: $enrolledCount\n\n";
} catch (Exception $e) {
   echo "✗ Error: " . $e->getMessage() . "\n";
   exit(1);
}
