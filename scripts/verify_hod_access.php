#!/usr/bin/env php
<?php
/**
 * Verify HOD Course Access
 * Tests what courses hod_informatics can actually see
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      HOD Course Access Verification                   ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get hod_informatics user
$hod = $DB->get_record('user', ['username' => 'hod_informatics']);

if (!$hod) {
   echo "❌ hod_informatics user not found!\n";
   exit(1);
}

echo "Testing access for: {$hod->firstname} {$hod->lastname}\n";
echo "User ID: {$hod->id}\n\n";

// Get all active courses
$all_courses = $DB->get_records_sql("
    SELECT c.* 
    FROM {course} c
    WHERE c.id > 1 
    AND c.visible = 1
    AND c.shortname NOT LIKE '%-OLD'
    AND c.fullname NOT LIKE '%(DEPRECATED)%'
    ORDER BY c.shortname
");

echo "Total active courses in system: " . count($all_courses) . "\n\n";

// Check HOD's role assignments
$role_assignments = $DB->get_records_sql("
    SELECT ra.*, r.shortname as rolename, ctx.contextlevel, ctx.instanceid
    FROM {role_assignments} ra
    JOIN {role} r ON r.id = ra.roleid
    JOIN {context} ctx ON ctx.id = ra.contextid
    WHERE ra.userid = ?
", [$hod->id]);

echo "HOD's Role Assignments:\n";
echo str_repeat("-", 60) . "\n";
foreach ($role_assignments as $ra) {
   $context_type = '';
   switch ($ra->contextlevel) {
      case 10:
         $context_type = "System-wide";
         break;
      case 40:
         $context_type = "Category (ID: {$ra->instanceid})";
         break;
      case 50:
         $context_type = "Course (ID: {$ra->instanceid})";
         break;
      default:
         $context_type = "Level {$ra->contextlevel}";
   }
   echo "  • {$ra->rolename} at {$context_type}\n";
}

echo "\n";

// Test course visibility as HOD
echo "Course Access Test:\n";
echo str_repeat("-", 60) . "\n";

$accessible = 0;
$not_accessible = 0;
$bit_courses = 0;
$bcs_courses = 0;
$shared_courses = 0;

foreach ($all_courses as $course) {
   // Get course context
   $context = context_course::instance($course->id);

   // Check if HOD has capability to view this course
   $can_view = has_capability('moodle/course:view', $context, $hod->id);
   $can_update = has_capability('moodle/course:update', $context, $hod->id);

   // Determine course type
   $type = 'Unknown';
   if (strpos($course->shortname, '-BIT') !== false) {
      $type = 'BIT';
      $bit_courses++;
   } elseif (strpos($course->shortname, '-BCS') !== false) {
      $type = 'BCS';
      $bcs_courses++;
   } elseif (strpos($course->shortname, 'COMMON') !== false) {
      $type = 'COMMON';
      $shared_courses++;
   }

   if ($can_view) {
      $accessible++;
   } else {
      $not_accessible++;
      echo "  ❌ CANNOT ACCESS: [{$type}] {$course->shortname}\n";
   }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Summary                                           ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Total Courses: " . count($all_courses) . "\n";
echo "  • BIT courses: {$bit_courses}\n";
echo "  • BCS courses: {$bcs_courses}\n";
echo "  • Shared/Common: {$shared_courses}\n\n";

echo "hod_informatics Access:\n";
echo "  ✓ Can access: {$accessible} courses\n";
echo "  ❌ Cannot access: {$not_accessible} courses\n\n";

if ($not_accessible > 0) {
   echo "⚠️  PROBLEM DETECTED!\n";
   echo "hod_informatics cannot see all courses.\n\n";
   echo "Likely Cause:\n";
   echo "  • Manager role assigned at CATEGORY level (limited scope)\n";
   echo "  • Should be assigned at SYSTEM level (full access)\n\n";
   echo "Solution: Run fix_hod_permissions.php\n";
} else {
   echo "✓ ALL GOOD!\n";
   echo "hod_informatics can access all active courses.\n";
}
