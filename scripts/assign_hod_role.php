#!/usr/bin/env php
<?php
/**
 * Create and Assign HOD Roles
 * Creates hod_bit and hod_bcs users if missing
 * Grants Manager access to HODs at the Program Category level
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Creating and Assigning HODs                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get Roles
$manager_role = $DB->get_record('role', ['shortname' => 'manager']);
if (!$manager_role) {
   die("Error: Manager role not found\n");
}

// Get Categories (Confirmed IDs: 2=BCS, 3=BIT)
$cat_bcs = $DB->get_record('course_categories', ['id' => 2]);
$cat_bit = $DB->get_record('course_categories', ['id' => 3]);

if (!$cat_bit || !$cat_bcs) {
   die("Error: Program categories not found (Expected ID 2 and 3)\n");
}

// Function to create or get user
function ensure_user_exists($username, $firstname, $lastname, $email, $password)
{
   global $DB;
   $user = $DB->get_record('user', ['username' => $username]);
   if (!$user) {
      $user = create_user_record($username, $password, 'manual');
      $user->firstname = $firstname;
      $user->lastname = $lastname;
      $user->email = $email;
      $user->city = 'Dar es Salaam';
      $user->country = 'TZ';
      $user->confirmed = 1;
      $DB->update_record('user', $user);
      echo "✓ Created user '$username' (ID: {$user->id})\n";
   } else {
      echo "• User '$username' already exists (ID: {$user->id})\n";
   }
   return $user;
}

// 1. Create HOD Users
echo "1. Ensuring HOD Accounts Exist...\n";
$password = 'Head@2026';

$hod_bit = ensure_user_exists(
   'hod_bit',
   'Dr. Head',
   'BIT',
   'hod.bit@example.com',
   $password
);

$hod_bcs = ensure_user_exists(
   'hod_bcs',
   'Dr. Head',
   'BCS',
   'hod.bcs@example.com',
   $password
);

echo "\n2. Assigning Permissions...\n";

// 2. Assign HOD BIT to Category 3
$context_bit = context_coursecat::instance($cat_bit->id);
if (!user_has_role_assignment($hod_bit->id, $manager_role->id, $context_bit->id)) {
   role_assign($manager_role->id, $hod_bit->id, $context_bit->id);
   echo "✓ Assigned 'hod_bit' as Manager to '{$cat_bit->name}'\n";
} else {
   echo "• 'hod_bit' already Manager of '{$cat_bit->name}'\n";
}

// 3. Assign HOD BCS to Category 2
$context_bcs = context_coursecat::instance($cat_bcs->id);
if (!user_has_role_assignment($hod_bcs->id, $manager_role->id, $context_bcs->id)) {
   role_assign($manager_role->id, $hod_bcs->id, $context_bcs->id);
   echo "✓ Assigned 'hod_bcs' as Manager to '{$cat_bcs->name}'\n";
} else {
   echo "• 'hod_bcs' already Manager of '{$cat_bcs->name}'\n";
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         HOD Implementation Complete! ✓                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Credentials Updated:\n";
echo "  • BIT Head: hod_bit / $password\n";
echo "  • BCS Head: hod_bcs / $password\n";
