#!/usr/bin/env php
<?php
/**
 * Grant HOD Access to Shared Modules
 * Assigns Manager role and enrolls HODs in Shared Modules category
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Granting Access to Shared Modules                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Setup
$manager_role = $DB->get_record('role', ['shortname' => 'manager']);
$shared_cat = $DB->get_record('course_categories', ['id' => 26]); // Shared Modules

if (!$shared_cat) {
   die("Error: Shared Modules category (ID 26) not found\n");
}

$hods = ['hod_bit', 'hod_bcs'];
$manual_enrol = enrol_get_plugin('manual');

foreach ($hods as $username) {
   $user = $DB->get_record('user', ['username' => $username]);
   if (!$user) continue;

   echo "Processing {$username}...\n";

   // 2. Assign Manager Role to Category
   $context = context_coursecat::instance($shared_cat->id);
   if (!user_has_role_assignment($user->id, $manager_role->id, $context->id)) {
      role_assign($manager_role->id, $user->id, $context->id);
      echo "  ✓ Assigned Manager role in 'Shared Modules'\n";
   } else {
      echo "  • Already Manager in 'Shared Modules'\n";
   }

   // 3. Enrol in All Shared Courses
   $courses = $DB->get_records('course', ['category' => $shared_cat->id]);
   $enrolled_count = 0;

   foreach ($courses as $course) {
      $enrol_instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
      if (!$enrol_instance) {
         $enrol_instance = $manual_enrol->add_default_instance($course);
         $enrol_instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
      }

      if (!$DB->record_exists('user_enrolments', ['enrolid' => $enrol_instance->id, 'userid' => $user->id])) {
         $manual_enrol->enrol_user($enrol_instance, $user->id, $manager_role->id);
         $enrolled_count++;
      }
   }
   echo "  ✓ Enrolled in {$enrolled_count} shared courses\n\n";
}

echo "Done! HODs should now see Shared Modules.\n";
