#!/usr/bin/env php
<?php
/**
 * Force Run Question Bank Migration Tasks
 * Manually triggers the question bank transfer tasks
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/clilib.php');

echo "Forcing question bank migration tasks...\n\n";

// Get all courses
$courses = $DB->get_records('course', ['id' => 2]); // Course ID 2 is our test course

foreach ($courses as $course) {
   $context = context_course::instance($course->id);

   // Queue the transfer tasks for this course
   echo "Processing course: {$course->fullname} (ID: {$course->id})\n";

   // Check if question categories exist
   $categories = $DB->get_records('question_categories', ['contextid' => $context->id]);

   if (empty($categories)) {
      echo "  No question categories found. Creating default category...\n";

      // Create default question category
      $defaultcat = new stdClass();
      $defaultcat->name = 'Default for ' . $course->fullname;
      $defaultcat->contextid = $context->id;
      $defaultcat->info = 'Default category';
      $defaultcat->infoformat = FORMAT_PLAIN;
      $defaultcat->stamp = make_unique_id_code();
      $defaultcat->parent = 0;
      $defaultcat->sortorder = 999;
      $defaultcat->idnumber = null;

      $DB->insert_record('question_categories', $defaultcat);
      echo "  ✓ Created default question category\n";
   } else {
      echo "  Found " . count($categories) . " question categories\n";
   }
}

echo "\n✓ Question bank setup complete!\n";
echo "\nNow try creating questions again in the quiz.\n";
