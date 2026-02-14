#!/usr/bin/env php
<?php
/**
 * Analyze HOD Structure
 * Shows current HODs, their assigned categories, and permissions
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      HOD Structure Analysis                           ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get all users with manager role
$sql = "SELECT DISTINCT 
    u.id,
    u.username,
    u.firstname,
    u.lastname,
    u.email
FROM {user} u
JOIN {role_assignments} ra ON ra.userid = u.id
JOIN {role} r ON r.id = ra.roleid
WHERE r.shortname = 'manager'
AND u.deleted = 0
ORDER BY u.username";

$managers = $DB->get_records_sql($sql);

echo "Found " . count($managers) . " HODs (Managers):\n";
echo str_repeat("-", 60) . "\n";

foreach ($managers as $manager) {
   echo "\n[{$manager->id}] {$manager->firstname} {$manager->lastname}\n";
   echo "    Username: {$manager->username}\n";
   echo "    Email: {$manager->email}\n";

   // Get their assigned categories
   $category_sql = "SELECT DISTINCT
        c.id,
        c.name,
        c.idnumber,
        ctx.id as contextid
    FROM {role_assignments} ra
    JOIN {context} ctx ON ctx.id = ra.contextid
    JOIN {course_categories} c ON c.id = ctx.instanceid
    WHERE ra.userid = ?
    AND ctx.contextlevel = 40
    ORDER BY c.name";

   $categories = $DB->get_records_sql($category_sql, [$manager->id]);

   if (!empty($categories)) {
      echo "    Assigned Categories:\n";
      foreach ($categories as $cat) {
         $idnumber = $cat->idnumber ? "({$cat->idnumber})" : "";
         echo "      • {$cat->name} {$idnumber}\n";
      }
   } else {
      echo "    No specific category assignments (site-wide access)\n";
   }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Recommendation                                    ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Current Structure: " . count($managers) . " HODs\n\n";

if (count($managers) > 1) {
   echo "Options:\n";
   echo "  1. Keep Multiple HODs (Department-specific)\n";
   echo "     Pros: Distributed responsibility, program-specific control\n";
   echo "     Cons: More complex management, potential overlap\n\n";
   echo "  2. Consolidate to 1 HOD (Faculty-wide)\n";
   echo "     Pros: Simpler management, clear authority, easier UX\n";
   echo "     Cons: Single point of responsibility\n\n";
   echo "Recommendation: Consolidate to 1 HOD for Faculty of Informatics\n";
   echo "  • One HOD can manage both BIT and BCS programs\n";
   echo "  • Simpler permission structure\n";
   echo "  • Better user experience\n\n";
}
