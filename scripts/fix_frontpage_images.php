<?php

/**
 * Fix Frontpage / Dashboard Course Images
 * 
 * This script uploads an image from the `images/` directory to the 
 * Moodle native `overviewfiles` file area for each course. This is 
 * required because Moodle's native course cards (including the modern_blue theme)
 * look for images here, not in the direct URLs embedded in the course summary.
 *
 * Run: php scripts/fix_frontpage_images.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Fixing Missing Home/Dashboard Course Images      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Get Images
$image_dir = __DIR__ . '/../images';
$images = glob($image_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

if (empty($images)) {
   die("Error: No images found in $image_dir\n");
}

echo "Found " . count($images) . " images to cycle through.\n\n";

// 2. Get Courses
$courses = $DB->get_records_select('course', 'id > 1');
$fs = get_file_storage();

$img_index = 0;
$processed = 0;
$errors = 0;

foreach ($courses as $course) {
   echo "Processing: {$course->shortname}... ";

   // Pick image (Round Robin)
   $image_path = $images[$img_index % count($images)];
   $image_name = basename($image_path);
   $img_index++;

   $context = context_course::instance($course->id);

   // Clear existing overview files to prevent duplicates
   $fs->delete_area_files($context->id, 'course', 'overviewfiles');

   // Upload New Image to 'overviewfiles' area
   $file_record = [
      'contextid'   => $context->id,
      'component'   => 'course',
      'filearea'    => 'overviewfiles',
      'itemid'      => 0,
      'filepath'    => '/',
      'filename'    => $image_name,
      'timecreated' => time(),
      'timemodified' => time(),
   ];

   try {
      $fs->create_file_from_pathname($file_record, $image_path);
      echo "✓ Added $image_name to overviewfiles\n";
      $processed++;
   } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
      $errors++;
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         Done!                                         ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\n  • Processed  : $processed\n";
echo "  • Errors     : $errors\n\n";
