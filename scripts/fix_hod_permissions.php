#!/usr/bin/env php
<?php
/**
 * Fix HOD Permissions
 * Grants hod_informatics system-wide Manager role for full course access
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Fix HOD Permissions                              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get hod_informatics user
$hod = $DB->get_record('user', ['username' => 'hod_informatics']);

if (!$hod) {
   echo "❌ hod_informatics user not found!\n";
   exit(1);
}

echo "Fixing permissions for: {$hod->firstname} {$hod->lastname}\n";
echo "User ID: {$hod->id}\n\n";

// Get Manager role
$manager_role = $DB->get_record('role', ['shortname' => 'manager']);

if (!$manager_role) {
   echo "❌ Manager role not found!\n";
   exit(1);
}

echo "Manager role ID: {$manager_role->id}\n\n";

// STEP 1: Show current assignments
echo "Step 1: Current Role Assignments\n";
echo str_repeat("-", 60) . "\n";

$current_assignments = $DB->get_records_sql("
    SELECT ra.*, ctx.contextlevel, ctx.instanceid
    FROM {role_assignments} ra
    JOIN {context} ctx ON ctx.id = ra.contextid
    WHERE ra.userid = ?
    AND ra.roleid = ?
", [$hod->id, $manager_role->id]);

foreach ($current_assignments as $ra) {
   $context_type = '';
   switch ($ra->contextlevel) {
      case 10:
         $context_type = "System-wide";
         break;
      case 40:
         $cat = $DB->get_record('course_categories', ['id' => $ra->instanceid]);
         $context_type = "Category: " . ($cat ? $cat->name : "ID {$ra->instanceid}");
         break;
      case 50:
         $context_type = "Course ID: {$ra->instanceid}";
         break;
      default:
         $context_type = "Level {$ra->contextlevel}";
   }
   echo "  • Manager at {$context_type}\n";
}

echo "\n";

// STEP 2: Remove category-level assignments
echo "Step 2: Removing Category-Level Manager Assignments\n";
echo str_repeat("-", 60) . "\n";

$removed = 0;
foreach ($current_assignments as $ra) {
   if ($ra->contextlevel == 40) { // Category level
      $cat = $DB->get_record('course_categories', ['id' => $ra->instanceid]);
      $cat_name = $cat ? $cat->name : "ID {$ra->instanceid}";

      $DB->delete_records('role_assignments', ['id' => $ra->id]);
      echo "  ✓ Removed Manager from: {$cat_name}\n";
      $removed++;
   }
}

if ($removed > 0) {
   echo "\nRemoved {$removed} category-level assignments.\n";
} else {
   echo "  • No category-level assignments to remove.\n";
}

echo "\n";

// STEP 3: Grant system-wide Manager role
echo "Step 3: Granting System-Wide Manager Role\n";
echo str_repeat("-", 60) . "\n";

// Get system context
$system_context = context_system::instance();

// Check if already has system-wide manager
$existing_system = $DB->get_record('role_assignments', [
   'userid' => $hod->id,
   'roleid' => $manager_role->id,
   'contextid' => $system_context->id
]);

if ($existing_system) {
   echo "  • Already has system-wide Manager role.\n";
} else {
   // Assign system-wide Manager role
   $assignment = new stdClass();
   $assignment->roleid = $manager_role->id;
   $assignment->contextid = $system_context->id;
   $assignment->userid = $hod->id;
   $assignment->timemodified = time();
   $assignment->modifierid = 2; // Admin

   $DB->insert_record('role_assignments', $assignment);
   echo "  ✓ Granted system-wide Manager role!\n";
}

echo "\n";

// STEP 4: Verify fix
echo "Step 4: Verification\n";
echo str_repeat("-", 60) . "\n";

// Count accessible courses
$all_courses = $DB->get_records_sql("
    SELECT c.* 
    FROM {course} c
    WHERE c.id > 1 
    AND c.visible = 1
    AND c.shortname NOT LIKE '%-OLD'
    AND c.fullname NOT LIKE '%(DEPRECATED)%'
");

$total_courses = count($all_courses);
$accessible = 0;

foreach ($all_courses as $course) {
   $context = context_course::instance($course->id);
   if (has_capability('moodle/course:view', $context, $hod->id)) {
      $accessible++;
   }
}

echo "  • Total active courses: {$total_courses}\n";
echo "  • HOD can access: {$accessible} courses\n\n";

if ($accessible == $total_courses) {
   echo "✓ SUCCESS! HOD can now access ALL courses!\n";
} else {
   $missing = $total_courses - $accessible;
   echo "⚠️  Still missing access to {$missing} courses.\n";
   echo "You may need to purge caches: php moodle/admin/cli/purge_caches.php\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              FIX COMPLETE                              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Next Steps:\n";
echo "  1. Purge caches: php moodle/admin/cli/purge_caches.php\n";
echo "  2. Verify: php scripts/verify_hod_access.php\n";
echo "  3. Login as hod_informatics and test manually\n\n";
