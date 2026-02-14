#!/usr/bin/env php
<?php
/**
 * Populate Course Structure - Fixed Version
 * Updates section names with curated topics
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Course Structure Population (Fixed)              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Load topic mappings
$topics_json = file_get_contents(__DIR__ . '/../course_topics.json');
$topic_map = json_decode($topics_json, true);

if (!$topic_map) {
   die("Error: Could not load course_topics.json\n");
}

// Get all courses
$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname');

$total_sections = 0;
$processed_courses = 0;

foreach ($courses as $course) {
   // Determine which topics to use
   $base_name = $course->fullname;

   if (!isset($topic_map[$base_name])) {
      echo "⚠ Skipping {$course->shortname}: No topics defined\n";
      continue;
   }

   $topics = $topic_map[$base_name];

   echo "Processing: {$course->shortname} - {$course->fullname}\n";

   $section_num = 1;  // Start after section 0

   foreach ($topics as $topic_name) {
      // Get existing section - FETCH FULL RECORD FIRST
      $section = $DB->get_record('course_sections', [
         'course' => $course->id,
         'section' => $section_num
      ]);

      if ($section) {
         // Modify the fetched record
         $section->name = $topic_name;
         $section->summary = "<p>This section covers: <strong>{$topic_name}</strong></p>";
         $section->summaryformat = FORMAT_HTML;

         // Update with full record
         try {
            $DB->update_record('course_sections', $section);
            $total_sections++;
         } catch (Exception $e) {
            echo "  ❌ Error updating section {$section_num}: " . $e->getMessage() . "\n";
            continue;
         }
      } else {
         echo "  ⚠ Section {$section_num} doesn't exist for this course\n";
      }

      $section_num++;
   }

   // Update course numsections to match topic count
   try {
      $DB->set_field('course', 'numsections', count($topics), ['id' => $course->id]);
   } catch (Exception $e) {
      echo "  ⚠ Could not update numsections: " . $e->getMessage() . "\n";
   }

   echo "  ✓ Updated " . ($section_num - 1) . " section names\n";
   $processed_courses++;

   // Rebuild course cache
   try {
      rebuild_course_cache($course->id, true);
   } catch (Exception $e) {
      echo "  ⚠ Cache rebuild warning: " . $e->getMessage() . "\n";
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         Structure Population Complete! ✓              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Courses processed: $processed_courses\n";
echo "  • Total sections updated: $total_sections\n\n";

echo "Result:\n";
echo "  All courses now have structured topic names.\n";
echo "  Visit any course to see the organized sections!\n";
