#!/usr/bin/env php
<?php
/**
 * Generate User CSV Export
 * Converts JSON user data to CSV format
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

$json_file = __DIR__ . '/../users_full_data.json';
$csv_file = __DIR__ . '/../e-LMS_Users_Export.csv';

if (!file_exists($json_file)) {
    die("Error: $json_file not found\n");
}

$users = json_decode(file_get_contents($json_file), true);

$fp = fopen($csv_file, 'w');

// Add BOM for Excel compatibility
fputs($fp, "\xEF\xBB\xBF");

// Headers
fputcsv($fp, [
    "Username", 
    "First Name", 
    "Last Name", 
    "Email", 
    "Role", 
    "Password", 
    "Courses Teaching", 
    "Courses Enrolled"
]);

foreach ($users as $user) {
    // Format courses as newline-separated strings for Excel cell
    $teaching = str_replace([", ", ","], "\n", $user['courses_teaching'] ?? '');
    $enrolled = str_replace([", ", ","], "\n", $user['courses_enrolled'] ?? '');
    
    fputcsv($fp, [
        $user['username'],
        $user['firstname'],
        $user['lastname'],
        $user['email'],
        $user['role'],
        $user['password'],
        $teaching,
        $enrolled
    ]);
}

fclose($fp);

echo "Successfully exported " . count($users) . " users to:\n$csv_file\n";
