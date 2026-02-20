#!/usr/bin/env php
<?php
/**
 * Apply Course Styling
 * - Uploads a random image to Course Summary file area
 * - Prepends <img> tag to Course Summary
 * - Matches the style of "Computer System Architecture"
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Applying Course Styling (Images + Layout)        ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Get Images
$image_dir = __DIR__ . '/../images';
$images = glob($image_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

if (empty($images)) {
   die("Error: No images found in $image_dir\n");
}

echo "Found " . count($images) . " images to cycle through.\n";

// 2. Get Courses
$courses = $DB->get_records_select('course', 'id > 1');
$fs = get_file_storage();

$img_index = 0;

foreach ($courses as $course) {
   // Skip if summary already has an image (simple check)
   if (strpos($course->summary, '<img') !== false) {
      echo "Skipping {$course->shortname} (Already has image)\n";
      continue;
   }

   echo "Processing: {$course->shortname}... ";

   // Pick image
   $image_path = $images[$img_index % count($images)];
   $image_name = basename($image_path);
   $img_index++;

   // 3. Upload File to Course Summary Area
   $context = context_course::instance($course->id);

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

   // Check if file exists, overwrite if needed
   $existing = $fs->get_file($context->id, 'course', 'summary', 0, '/', $image_name);
   if ($existing) {
      $existing->delete();
   }

   try {
      $file = $fs->create_file_from_pathname($file_record, $image_path);

      // 4. Update Summary
      // URL encoding for filename in src
      $encoded_name = rawurlencode($image_name);

      $img_html = '<p style="text-align: center;"><img class="img-fluid" src="@@PLUGINFILE@@/' . $encoded_name . '" alt="" width="100%" height="auto"></p>';

      $course->summary = $img_html . $course->summary;
      $course->summaryformat = FORMAT_HTML;

      $DB->update_record('course', $course);

      echo "✓ Added $image_name\n";
   } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
   }
}

echo "\nDone!\n";
