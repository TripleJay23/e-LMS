#!/usr/bin/env php
<?php
/**
 * Fix Course Summaries
 * Moves rich HTML summary from Course Settings to General Section (Section 0)
 * Replaces Course Summary with short text for clean Dashboard Cards
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Fixing Course Summaries for Dashboard Cards      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Get all courses except site
$courses = $DB->get_records_select('course', 'id > 1');

echo "Found " . count($courses) . " courses to process.\n";

foreach ($courses as $course) {
   echo "Processing: {$course->shortname}... ";

   // 1. Get current summary
   $current_summary = $course->summary;

   // Check if summary is already simple (don't move if it's short)
   if (strlen($current_summary) < 200 && strpos($current_summary, '<table') === false) {
      echo "• Skipped (Summary already short/simple)\n";
      continue;
   }

   // 2. Move to Section 0
   // Get section 0
   $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);

   if ($section0) {
      // Append to existing section summary if needed, or replace if empty
      // We'll prepend the rich summary to ensure it's at the top
      // But let's check if we already moved it to avoid duplication?
      // Simple check: if section0 already contains "Course Code:", maybe skip?
      // Let's just overwrite for consistency because we want the NEWEST description

      $new_section_summary = $current_summary;
      if (!empty($section0->summary)) {
         // If section 0 has content, we might be overwriting specific instructions. 
         // But usually it's empty. safely, let's prepend.
         if (strpos($section0->summary, '<table') === false) {
            $new_section_summary = $current_summary . "<br><hr><br>" . $section0->summary;
         } else {
            // Already likely has it, let's just update it to be sure it's the latest
            $new_section_summary = $current_summary;
         }
      }

      $section0->summary = $new_section_summary;
      $DB->update_record('course_sections', $section0);

      // 3. Update Course Summary (The Card View)
      $course->summary = "<p><strong>{$course->fullname}</strong><br>Click to view full course details.</p>";
      $course->summaryformat = FORMAT_HTML;
      $DB->update_record('course', $course);

      echo "✓ Moved to Section 0 & Cleaned Card\n";
   } else {
      echo "❌ Error: Section 0 not found\n";
   }
}

echo "\nDone!\n";
