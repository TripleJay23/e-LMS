#!/usr/bin/env php
<?php
/**
 * Create Test Users for e-LMS Verification
 * Creates a test facilitator and test student with known passwords
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/user/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║        e-LMS Test User Creation Script                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$users = [
   [
      'username' => 'test_facilitator',
      'password' => 'TestUser123!',
      'firstname' => 'Test',
      'lastname' => 'Facilitator',
      'email' => 'facilitator@example.com',
      'role' => 'editingteacher' // Moodle role for facilitator
   ],
   [
      'username' => 'test_student',
      'password' => 'TestUser123!',
      'firstname' => 'Test',
      'lastname' => 'Student',
      'email' => 'student@example.com',
      'role' => 'student'
   ]
];

foreach ($users as $userData) {
   // Check if user exists
   $existingUser = $DB->get_record('user', ['username' => $userData['username']]);

   if ($existingUser) {
      echo "• User '{$userData['username']}' already exists. Resetting password...\n";
      $user = $existingUser;
      // Update password
      $user->password = hash_internal_user_password($userData['password']);
      $DB->update_record('user', $user);
      echo "  ✓ Password reset to: {$userData['password']}\n";
   } else {
      // Create new user
      $user = create_user_record($userData['username'], $userData['password'], 'manual');

      // Update profile
      $user->firstname = $userData['firstname'];
      $user->lastname = $userData['lastname'];
      $user->email = $userData['email'];
      $user->city = 'Dar es Salaam';
      $user->country = 'TZ';
      $user->confirmed = 1;
      $user->mnethostid = $CFG->mnet_localhost_id;

      $DB->update_record('user', $user);
      echo "✓ Created user: {$userData['username']}\n";
   }

   echo "  Role: {$userData['role']}\n";
   echo "  Password: {$userData['password']}\n\n";
}

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║         Test Users Ready! ✓                            ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";
echo "You can now log in with:\n";
echo "1. Facilitator: test_facilitator / TestUser123!\n";
echo "2. Student: test_student / TestUser123!\n\n";

if (file_exists(__DIR__ . '/enrol_student.php')) {
   echo "Auto-enrolling test student in BIT program...\n";
   passthru('php ' . __DIR__ . '/enrol_student.php test_student BIT');
}
