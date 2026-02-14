#!/usr/bin/env php
<?php
/**
 * Export All Users to Excel-Compatible CSV
 * This script exports all users from the system to update the Excel file
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Export Users from e-LMS Database                 ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get all users except guest and admin
$sql = "SELECT 
    u.id,
    u.username,
    u.firstname,
    u.lastname,
    u.email,
    u.institution,
    u.department,
    u.idnumber,
    u.auth,
    u.timecreated,
    string_agg(DISTINCT r.shortname, ',') as roles
FROM {user} u
LEFT JOIN {role_assignments} ra ON ra.userid = u.id
LEFT JOIN {role} r ON r.id = ra.roleid
WHERE u.id > 2 AND u.deleted = 0
GROUP BY u.id, u.username, u.firstname, u.lastname, u.email, 
         u.institution, u.department, u.idnumber, u.auth, u.timecreated
ORDER BY u.lastname, u.firstname";

$users = $DB->get_records_sql($sql);

if (empty($users)) {
   echo "No users found.\n";
   exit(0);
}

$count = count($users);
echo "Found {$count} users in the system.\n\n";

// Create CSV file
$output_file = __DIR__ . '/../e-LMS_Users_Export.csv';
$fp = fopen($output_file, 'w');

// Write header
fputcsv($fp, [
   'ID',
   'Username',
   'First Name',
   'Last Name',
   'Email',
   'Institution',
   'Department',
   'ID Number',
   'Auth Method',
   'Roles',
   'Created Date'
]);

// Write user data
foreach ($users as $user) {
   $created_date = date('Y-m-d H:i:s', $user->timecreated);

   fputcsv($fp, [
      $user->id,
      $user->username,
      $user->firstname,
      $user->lastname,
      $user->email,
      $user->institution ?? '',
      $user->department ?? '',
      $user->idnumber ?? '',
      $user->auth,
      $user->roles ?? '',
      $created_date
   ]);
}

fclose($fp);

echo "✓ Exported {$count} users to: e-LMS_Users_Export.csv\n\n";

// Display summary by role
echo "Summary by Role:\n";
echo str_repeat("-", 60) . "\n";

$role_counts = [];
foreach ($users as $user) {
   $roles = $user->roles ? explode(',', $user->roles) : ['(no role)'];
   foreach ($roles as $role) {
      $role = trim($role);
      if (!isset($role_counts[$role])) {
         $role_counts[$role] = 0;
      }
      $role_counts[$role]++;
   }
}

foreach ($role_counts as $role => $count) {
   echo str_pad($role, 30) . ": $count\n";
}

echo "\n";
echo "Next steps:\n";
echo "  1. Open e-LMS_Users_Export.csv in Excel\n";
echo "  2. Copy new users to e-LMS_Users_Reorganized_1.xlsx\n";
echo "  3. Or replace the old file with the new export\n\n";
