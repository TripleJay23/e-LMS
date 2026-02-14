#!/usr/bin/env php
<?php
/**
 * Export User Data to JSON
 * Fetches all users and their primary system roles
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

// Get all users except guest and deleted
$users = $DB->get_records_sql("
    SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.city, u.country 
    FROM {user} u 
    WHERE u.deleted = 0 AND u.username != 'guest'
    ORDER BY u.username
");

$export_data = [];

foreach ($users as $user) {
   // Get system roles
   $roles = get_user_roles(context_system::instance(), $user->id);
   $role_names = [];
   foreach ($roles as $role) {
      $role_names[] = $role->shortname;
   }

   // Heuristic for roles if system role is empty (common in Moodle)
   if (empty($role_names)) {
      if (strpos($user->username, 'student') !== false) {
         $role_names[] = 'student';
      } elseif (preg_match('/^(prof|dr|mr|ms)_/', $user->username)) {
         $role_names[] = 'editingteacher';
      } elseif (is_siteadmin($user)) {
         $role_names[] = 'manager/admin';
      } else {
         $role_names[] = 'user';
      }
   }

   // Determine password hint
   $password_hint = '';
   if (strpos($user->username, 'student') !== false) {
      $password_hint = 'Student@2026';
   } elseif (preg_match('/^(prof|dr|mr|ms)_/', $user->username)) {
      $password_hint = 'Lecturer@2026';
   } elseif ($user->username === 'test_facilitator' || $user->username === 'test_student') {
      $password_hint = 'TestUser123!';
   } else {
      $password_hint = '(Manual/Unknown)';
   }

   $export_data[] = [
      'username' => $user->username,
      'firstname' => $user->firstname,
      'lastname' => $user->lastname,
      'email' => $user->email,
      'city' => $user->city,
      'country' => $user->country,
      'roles' => implode(', ', array_unique($role_names)),
      'password_hint' => $password_hint
   ];
}

$json_file = __DIR__ . '/../users_export.json';
file_put_contents($json_file, json_encode($export_data, JSON_PRETTY_PRINT));
echo "User data exported to $json_file\n";
