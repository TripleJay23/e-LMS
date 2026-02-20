<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "Unblocking login by making profile fields optional...\n";

try {
   $DB->execute("UPDATE {user_info_field} SET required = 0 WHERE shortname IN ('program_study', 'year_of_study')");
   echo "✓ Fields 'program_study' and 'year_of_study' are now OPTIONAL.\n";
   echo "Admin/HOD should be able to login now.\n";
} catch (Exception $e) {
   echo "Error: " . $e->getMessage() . "\n";
}
