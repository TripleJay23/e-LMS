#!/usr/bin/env php
<?php
/**
 * Create Sample Course and Link to Program
 * Creates a "Introduction to Programming" course for BIT program
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║     e-LMS Sample Course Creation                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

try {
   // 1. Get BIT program
   $program = $DB->get_record('custom_programs', ['acronym' => 'BIT']);
   if (!$program) {
      die("Error: BIT program not found\n");
   }
   echo "Program: {$program->name} ({$program->acronym})\n";

   // 2. Get BIT category
   $category = $DB->get_record('course_categories', ['idnumber' => 'BIT']);
   if (!$category) {
      die("Error: BIT course category not found\n");
   }
   echo "Category: {$category->name} (ID: {$category->id})\n";

   // 3. Create Course
   $courseData = new stdClass();
   $courseData->fullname = 'Introduction to Programming';
   $courseData->shortname = 'BIT 101';
   $courseData->category = $category->id;
   $courseData->summary = 'Fundamentals of programming using Python.';
   $courseData->format = 'topics';
   $courseData->numsections = 10;
   $courseData->startdate = time();
   $courseData->visible = 1;

   $existingCourse = $DB->get_record('course', ['shortname' => 'BIT 101']);
   if ($existingCourse) {
      $course = $existingCourse;
      echo "• Course 'BIT 101' already exists (ID: {$course->id})\n";
   } else {
      $course = create_course($courseData);
      echo "✓ Created course: {$course->fullname} (ID: {$course->id})\n";
   }

   // 4. Link Course to Program
   $existingLink = $DB->get_record('custom_program_courses', [
      'programid' => $program->id,
      'courseid' => $course->id
   ]);

   if (!$existingLink) {
      $link = new stdClass();
      $link->programid = $program->id;
      $link->courseid = $course->id;
      $link->year = 1;
      $link->semester = 1;
      $link->timecreated = time();

      $DB->insert_record('custom_program_courses', $link);
      echo "✓ Linked course to BIT program (Year 1, Sem 1)\n";
   } else {
      echo "• Course already linked to program\n";
   }

   // 5. Enroll Facilitator
   $facilitator = $DB->get_record('user', ['username' => 'test_facilitator']);
   $role = $DB->get_record('role', ['shortname' => 'editingteacher']);

   if ($facilitator && $role) {
      $enrol = enrol_get_plugin('manual');
      $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);

      if ($instance) {
         $enrol->enrol_user($instance, $facilitator->id, $role->id);
         echo "✓ Enrolled 'test_facilitator' as teacher\n";
      }
   }

   echo "\nSuccess! Sample course ready.\n";
} catch (Exception $e) {
   echo "Error: " . $e->getMessage() . "\n";
   exit(1);
}
