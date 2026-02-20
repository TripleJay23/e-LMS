#!/usr/bin/env php
<?php
/**
 * Revert Course Summaries
 * Moves summary BACK from Section 0 to Course Settings
 * Undoes the "Fix" from the previous step.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Reverting Course Summaries                       ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$courses = $DB->get_records_select('course', 'id > 1');

echo "Found " . count($courses) . " courses to process.\n";

foreach ($courses as $course) {
   // Get section 0
   $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);

   if ($section0 && !empty($section0->summary)) {
      // We assume the content in Section 0 IS the summary we moved
      // Logic: If Section 0 has the HTML table, move it back.

      if (strpos($section0->summary, 'course-description-box') !== false) {
         echo "Reverting: {$course->shortname}... ";

         // Move back to course summary
         $course->summary = $section0->summary;
         $course->summaryformat = FORMAT_HTML;
         $DB->update_record('course', $course);

         // Clear Section 0 summary (or clean it up)
         // We'll clear it because we likely populated it entirely
         $section0->summary = '';
         $DB->update_record('course_sections', $section0);

         echo "✓ Restored to Course Summary\n";
      } else {
         echo "Skipping {$course->shortname} (No rich summary found in Section 0)\n";
      }
   }
}

echo "\nDone!\n";
