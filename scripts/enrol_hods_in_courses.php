#!/usr/bin/env php
<?php
/**
 * Enrol HODs in All Program Courses
 * Ensures courses appear in "My Courses" dashboard
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Enrolling HODs in Program Courses                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Configurations
$configs = [
   [
      'username' => 'hod_bit',
      'cat_id' => 3, // BIT
      'role' => 'manager' // Enrol as Manager? Or Teacher?
      // Manager enrollment allows them to see it in dashboard
   ],
   [
      'username' => 'hod_bcs',
      'cat_id' => 2, // BCS
      'role' => 'manager'
   ]
];

$manager_role = $DB->get_record('role', ['shortname' => 'manager']);
$manual_enrol = enrol_get_plugin('manual');

foreach ($configs as $conf) {
   $user = $DB->get_record('user', ['username' => $conf['username']]);
   if (!$user) {
      echo "User {$conf['username']} not found. Skipping.\n";
      continue;
   }

   echo "Processing {$user->username} (Category ID: {$conf['cat_id']})...\n";

   // Find all courses in this category and subcategories
   // We use the category path to find all children
   $category = $DB->get_record('course_categories', ['id' => $conf['cat_id']]);
   if (!$category) {
      echo "  Category not found.\n";
      continue;
   }

   // Get all courses where category path starts with this category path
   $sql = "SELECT c.id, c.shortname, c.fullname 
            FROM {course} c 
            JOIN {course_categories} cc ON c.category = cc.id 
            WHERE cc.path LIKE ? OR cc.path LIKE ?";

   // Path can be /ID or /.../ID/...
   // But simplified: path LIKE '/ID/%' OR path = '/ID'
   $path_search = $category->path . '/%';
   $exact_path = $category->path;

   $courses = $DB->get_records_sql($sql, [$path_search, $exact_path]);

   echo "  Found " . count($courses) . " courses in hierarchy.\n";
   $enrolled_count = 0;

   foreach ($courses as $course) {
      // Check if manual enrolment exists
      $enrol_instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
      if (!$enrol_instance) {
         $enrol_instance = $manual_enrol->add_default_instance($course);
         $enrol_instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
      }

      // Check availability
      if (!$manual_enrol->allow_enrol($enrol_instance)) {
         echo "  ⚠ Cannot enrol in {$course->shortname}\n";
         continue;
      }

      // Enrol user
      if (!$DB->record_exists('user_enrolments', ['enrolid' => $enrol_instance->id, 'userid' => $user->id])) {
         $manual_enrol->enrol_user($enrol_instance, $user->id, $manager_role->id);
         // echo "    + Enrolled in {$course->shortname}\n"; // Verbose
         $enrolled_count++;
      }
   }

   echo "  ✓ Enrolled {$user->username} in {$enrolled_count} new courses.\n\n";
}

echo "Done!\n";
