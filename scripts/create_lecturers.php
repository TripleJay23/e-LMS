#!/usr/bin/env php
<?php
/**
 * Generate Lecturers and Assign to Courses
 * Creates lecturer accounts and enrolls them in courses
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Lecturer Generation and Assignment               ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Generate lecturer data
$lecturers = [
   ['username' => 'dr_mwangi', 'firstname' => 'Dr. James', 'lastname' => 'Mwangi', 'email' => 'james.mwangi@example.com'],
   ['username' => 'prof_njoroge', 'firstname' => 'Prof. Grace', 'lastname' => 'Njoroge', 'email' => 'grace.njoroge@example.com'],
   ['username' => 'dr_kamau', 'firstname' => 'Dr. Peter', 'lastname' => 'Kamau', 'email' => 'peter.kamau@example.com'],
   ['username' => 'ms_otieno', 'firstname' => 'Ms. Sarah', 'lastname' => 'Otieno', 'email' => 'sarah.otieno@example.com'],
   ['username' => 'mr_odhiambo', 'firstname' => 'Mr. David', 'lastname' => 'Odhiambo', 'email' => 'david.odhiambo@example.com'],
   ['username' => 'dr_wanjiru', 'firstname' => 'Dr. Mary', 'lastname' => 'Wanjiru', 'email' => 'mary.wanjiru@example.com'],
   ['username' => 'prof_kimani', 'firstname' => 'Prof. John', 'lastname' => 'Kimani', 'email' => 'john.kimani@example.com'],
   ['username' => 'dr_achieng', 'firstname' => 'Dr. Jane', 'lastname' => 'Achieng', 'email' => 'jane.achieng@example.com'],
];

$password = 'Lecturer@2026';
$created = 0;

echo "Step 1: Creating Lecturer Accounts\n";
echo str_repeat("-", 60) . "\n";

foreach ($lecturers as $lec) {
   $existing = $DB->get_record('user', ['username' => $lec['username']]);

   if ($existing) {
      echo "• {$lec['username']}: {$lec['firstname']} {$lec['lastname']} (exists)\n";
      continue;
   }

   // Create user
   $user = create_user_record($lec['username'], $password, 'manual');
   $user->firstname = $lec['firstname'];
   $user->lastname = $lec['lastname'];
   $user->email = $lec['email'];
   $user->city = 'Dar es Salaam';
   $user->country = 'TZ';
   $user->confirmed = 1;
   $user->mnethostid = $CFG->mnet_localhost_id;

   $DB->update_record('user', $user);
   echo "✓ {$lec['username']}: {$lec['firstname']} {$lec['lastname']}\n";
   $created++;
}

echo "\nCreated $created new lecturers\n";
echo "Password for all lecturers: $password\n\n";

// Step 2: Assign lecturers to courses
echo "Step 2: Assigning Lecturers to Courses\n";
echo str_repeat("-", 60) . "\n";

// Get all courses (excluding site course)
$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname');
$teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);

// Distribute courses among lecturers
$lecturer_users = $DB->get_records_sql("SELECT * FROM {user} WHERE username LIKE 'dr_%' OR username LIKE 'prof_%' OR username LIKE 'ms_%' OR username LIKE 'mr_%'");
$lecturer_array = array_values($lecturer_users);
$lecturer_count = count($lecturer_array);

if ($lecturer_count == 0) {
   echo "Error: No lecturers found\n";
   exit(1);
}

$course_index = 0;
$enrollments = 0;

foreach ($courses as $course) {
   // Assign lecturer (round-robin)
   $lecturer = $lecturer_array[$course_index % $lecturer_count];

   // Check if manual enrolment exists
   $enrol_instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
   if (!$enrol_instance) {
      $enrol = enrol_get_plugin('manual');
      $enrol->add_default_instance($course);
      $enrol_instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
   }

   // Check if already enrolled
   $already_enrolled = $DB->record_exists('user_enrolments', [
      'enrolid' => $enrol_instance->id,
      'userid' => $lecturer->id
   ]);

   if (!$already_enrolled) {
      $enrol = enrol_get_plugin('manual');
      $enrol->enrol_user($enrol_instance, $lecturer->id, $teacher_role->id);
      echo "✓ {$course->shortname}: {$lecturer->firstname} {$lecturer->lastname}\n";
      $enrollments++;
   }

   $course_index++;
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         Lecturer Assignment Complete! ✓               ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Lecturers created: $created\n";
echo "  • Course enrollments: $enrollments\n";
echo "  • Courses per lecturer: ~" . round(count($courses) / $lecturer_count, 1) . "\n\n";

echo "Lecturer credentials:\n";
foreach ($lecturer_users as $lec) {
   echo "  - {$lec->username} / $password\n";
}
