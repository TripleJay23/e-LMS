#!/usr/bin/env php
<?php
/**
 * Cleanup Stale Courses
 * Deletes old non-suffixed copies of shared modules that still sit under BIT/BCS
 * after centralization into the Shared Modules hierarchy.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(__DIR__ . '/module_catalog.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║       Cleanup Stale Duplicate Courses                 ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Load shared module codes from canonical list
try {
   $modules = load_module_catalog(__DIR__ . '/modules_corrected.json');
} catch (RuntimeException $e) {
   echo "ERROR: " . $e->getMessage() . "\n";
   exit(1);
}

$groups = split_modules_by_program($modules);
$shared_codes = [];
foreach ($groups['shared'] as $m) {
   if (!empty($m['code'])) {
      $shared_codes[] = $m['code'];
   }
}

echo "Shared module codes: " . count($shared_codes) . "\n\n";

$bit_cat = $DB->get_record('course_categories', ['idnumber' => 'BIT']);
$bcs_cat = $DB->get_record('course_categories', ['idnumber' => 'BCS']);

$deleted = 0;
$skipped = 0;

foreach ($shared_codes as $code) {
   $shared_course = $DB->get_record('course', ['shortname' => $code . '-SHARED']);
   if (!$shared_course) {
      echo "  ⚠ No -SHARED version for {$code}, skipping.\n";
      $skipped++;
      continue;
   }

   // Find and delete old BIT copy
   $old_bit = $DB->get_record_sql(
      "SELECT c.* FROM {course} c
         JOIN {course_categories} cc ON c.category = cc.id
         WHERE c.shortname = ? AND (cc.path LIKE ? OR cc.path LIKE ?)",
      [$code, $bit_cat->path . '/%', $bit_cat->path]
   );

   if ($old_bit) {
      echo "  ✗ Deleting BIT stale: {$code} (ID: {$old_bit->id})\n";
      $DB->delete_records('custom_program_courses', ['courseid' => $old_bit->id]);
      try {
         delete_course($old_bit->id, false);
      } catch (Throwable $e) {
         // Force-delete if normal deletion fails
         echo "    (force-cleaning residual data)\n";
         $DB->delete_records('course', ['id' => $old_bit->id]);
         $DB->delete_records('enrol', ['courseid' => $old_bit->id]);
         $context = context_course::instance($old_bit->id, IGNORE_MISSING);
         if ($context) $context->delete();
      }
      $deleted++;
   }

   // Find and delete old BCS copy
   $old_bcs = $DB->get_record_sql(
      "SELECT c.* FROM {course} c
         JOIN {course_categories} cc ON c.category = cc.id
         WHERE c.shortname = ? AND (cc.path LIKE ? OR cc.path LIKE ?)",
      [$code, $bcs_cat->path . '/%', $bcs_cat->path]
   );

   if ($old_bcs) {
      echo "  ✗ Deleting BCS stale: {$code} (ID: {$old_bcs->id})\n";
      $DB->delete_records('custom_program_courses', ['courseid' => $old_bcs->id]);
      try {
         delete_course($old_bcs->id, false);
      } catch (Throwable $e) {
         echo "    (force-cleaning residual data)\n";
         $DB->delete_records('course', ['id' => $old_bcs->id]);
         $DB->delete_records('enrol', ['courseid' => $old_bcs->id]);
         $context = context_course::instance($old_bcs->id, IGNORE_MISSING);
         if ($context) $context->delete();
      }
      $deleted++;
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║           Stale Course Cleanup Complete! ✓            ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\nDeleted: $deleted stale courses\n";
echo "Skipped: $skipped (no -SHARED version found)\n";
