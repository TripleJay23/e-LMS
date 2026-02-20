#!/usr/bin/env php
<?php
/**
 * Apply Diverse Course Styling
 * - Removes images from 'Course image' (overviewfiles)
 * - Inserts DIFFERENT images from 'images/' directory into 'Course summary'
 * - Matches format of "Computer System Architecture"
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Applying Diverse Course Images (Summary Only)    ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Get Images
$image_dir = __DIR__ . '/../images';
$images = glob($image_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

if (empty($images)) {
   die("Error: No images found in $image_dir\n");
}

echo "Found " . count($images) . " images to distribute.\n";

// 2. Get Courses
$courses = $DB->get_records_select('course', 'id > 1');
$fs = get_file_storage();

$img_index = 0;

foreach ($courses as $course) {
   echo "Processing: {$course->shortname}... ";

   // 3. Clear 'Course image' (overviewfiles)
   $context = context_course::instance($course->id);
   $fs->delete_area_files($context->id, 'course', 'overviewfiles');
   // echo "  - Cleared overviewfiles\n";

   // 4. Prepare Summary
   // Pick image (Round Robin)
   $image_path = $images[$img_index % count($images)];
   $image_name = basename($image_path);
   $img_index++;

   // Clean existing images from summary (Resetting to clean slate)
   $original_summary = $course->summary;
   // Remove existing <p><img...></p> at start
   $clean_summary = preg_replace('/^<p[^>]*><img[^>]*>.*?<\/p>/is', '', $original_summary);
   $clean_summary = preg_replace('/^<p>\s*<\/p>/is', '', $clean_summary); // Remove empty p

   // 5. Upload New Image to 'Summary' area
   $file_record = [
      'contextid' => $context->id,
      'component' => 'course',
      'filearea'  => 'summary',
      'itemid'    => 0,
      'filepath'  => '/',
      'filename'  => $image_name,
      'timecreated' => time(),
      'timemodified' => time(),
   ];

   // Delete old summary files (to avoid clutter)
   // Careful: Only delete if filename is an image? 
   // Or just clear the area and re-add. 
   // User wants "Different images". Let's clear to be safe.
   $fs->delete_area_files($context->id, 'course', 'summary', 0);

   try {
      $fs->create_file_from_pathname($file_record, $image_path);

      // 6. Update Summary HTML
      $encoded_name = rawurlencode($image_name);

      // Exact template style
      $img_html = '<p style="text-align: center;"><img class="img-fluid" role="presentation" src="@@PLUGINFILE@@/' . $encoded_name . '" alt="" width="100%" height="auto"></p>';

      $course->summary = $img_html . $clean_summary;
      $course->summaryformat = FORMAT_HTML;

      $DB->update_record('course', $course);

      echo "✓ Added $image_name to Summary\n";
   } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
   }
}

echo "\nDone!\n";
