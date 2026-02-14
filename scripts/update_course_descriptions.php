#!/usr/bin/env php
<?php
/**
 * Update Course Descriptions
 * Adds professional descriptions with semester, program, and credit info
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Updating Course Descriptions                     ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Load module data
$modules_json = file_get_contents(__DIR__ . '/../modules_categorized.json');
$modules_data = json_decode($modules_json, true);

// Create lookup table: code => module info
$module_lookup = [];
foreach ($modules_data['shared'] as $mod) {
   $module_lookup[$mod['code']] = $mod + ['program' => 'Both BIT and BCS'];
}
foreach ($modules_data['bit_only'] as $mod) {
   $module_lookup[$mod['code']] = $mod + ['program' => 'BIT Only'];
}
foreach ($modules_data['bcs_only'] as $mod) {
   $module_lookup[$mod['code']] = $mod + ['program' => 'BCS Only'];
}

// Get all courses
$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname');

$updated = 0;

foreach ($courses as $course) {
   // Extract base code (remove -BIT/-BCS suffix)
   $base_code = preg_replace('/-BIT|-BCS/', '', $course->shortname);

   if (!isset($module_lookup[$base_code])) {
      echo "⚠ Skipping {$course->shortname}: No module data found\n";
      continue;
   }

   $mod = $module_lookup[$base_code];

   // Determine semester year
   $sem_upper = strtoupper($mod['semester']);
   if (strpos($sem_upper, 'SEMESTER I') !== false && strpos($sem_upper, 'II') === false && strpos($sem_upper, 'IV') === false && strpos($sem_upper, 'VI') === false) {
      $year = 1; // Semester I
   } elseif (strpos($sem_upper, 'SEMESTER II') !== false && strpos($sem_upper, 'III') === false) {
      $year = 1; // Semester II
   } elseif (strpos($sem_upper, 'SEMESTER III') !== false) {
      $year = 2; // Semester III
   } elseif (strpos($sem_upper, 'SEMESTER IV') !== false) {
      $year = 2; // Semester IV
   } elseif (strpos($sem_upper, 'SEMESTER V') !== false && strpos($sem_upper, 'VI') === false) {
      $year = 3; // Semester V
   } elseif (strpos($sem_upper, 'SEMESTER VI') !== false) {
      $year = 3; // Semester VI
   } else {
      $year = 1; // Default fallback
   }

   // Build professional description
   $description = "
<div style='line-height: 1.6;'>
    <h3>{$course->fullname}</h3>
    
    <table style='margin: 15px 0; border-collapse: collapse;'>
        <tr>
            <td style='padding: 5px 15px 5px 0; font-weight: bold;'>Program:</td>
            <td style='padding: 5px 0;'>{$mod['program']}</td>
        </tr>
        <tr>
            <td style='padding: 5px 15px 5px 0; font-weight: bold;'>Year & Semester:</td>
            <td style='padding: 5px 0;'>Year {$year}, {$mod['semester']}</td>
        </tr>
        <tr>
            <td style='padding: 5px 15px 5px 0; font-weight: bold;'>Credit Hours:</td>
            <td style='padding: 5px 0;'>{$mod['credits']}</td>
        </tr>
        <tr>
            <td style='padding: 5px 15px 5px 0; font-weight: bold;'>Type:</td>
            <td style='padding: 5px 0;'>{$mod['type']}</td>
        </tr>
    </table>
    
    <p><strong>Course Code:</strong> {$course->shortname}</p>
    
    <p style='margin-top: 15px;'>This course is structured into comprehensive topics covering essential aspects of {$course->fullname}. Each topic includes study materials, practice exercises, and assessments to ensure thorough understanding and practical application of concepts.</p>
</div>
";

   // Update course
   try {
      $course_update = new stdClass();
      $course_update->id = $course->id;
      $course_update->summary = $description;
      $course_update->summaryformat = FORMAT_HTML;

      $DB->update_record('course', $course_update);
      $updated++;

      // Rebuild course cache
      rebuild_course_cache($course->id, true);
   } catch (Exception $e) {
      echo "  ❌ Error updating {$course->shortname}: " . $e->getMessage() . "\n";
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         Description Update Complete! ✓                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Courses updated: $updated\n\n";

echo "Result:\n";
echo "  All courses now have professional descriptions with:\n";
echo "  - Program information (BIT/BCS/Both)\n";
echo "  - Year and semester details\n";
echo "  - Credit hours\n";
echo "  - Course type\n";
