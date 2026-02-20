#!/usr/bin/env php
<?php
/**
 * Apply Uniform Course Styling
 * - Removes existing images from Course Summary
 * - Applies specific template image (download (8).jpg) to match "Computer System Architecture"
 * - Maintains Description Box layout
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Applying Uniform Template Image                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 1. Get Template Image
$image_dir = __DIR__ . '/../images';
$template_image = 'download (8).jpg';
$image_path = $image_dir . '/' . $template_image;

if (!file_exists($image_path)) {
   // Fallback if not found (based on list_dir content)
   $image_path = $image_dir . '/download (8).jpg';
   if (!file_exists($image_path)) {
      die("Error: Template image $template_image not found in $image_dir\n");
   }
}

echo "Using Template Image: $template_image\n";

// 2. Get Courses
$courses = $DB->get_records_select('course', 'id > 1');
$fs = get_file_storage();

foreach ($courses as $course) {
   echo "Processing: {$course->shortname}... ";

   // 3. Remove existing images (Cleanup)
   // Regex to remove <p...><img ...></p> at the start
   $original_summary = $course->summary;
   $clean_summary = preg_replace('/^<p[^>]*><img[^>]*>.*?<\/p>/is', '', $original_summary);

   // Also remove empty <p></p> at start
   $clean_summary = preg_replace('/^<p>\s*<\/p>/is', '', $clean_summary);

   // 4. Upload Template Image to Course Summary Area
   $context = context_course::instance($course->id);

   $file_record = [
      'contextid' => $context->id,
      'component' => 'course',
      'filearea'  => 'summary',
      'itemid'    => 0,
      'filepath'  => '/',
      'filename'  => $template_image,
      'timecreated' => time(),
      'timemodified' => time(),
   ];

   // Delete old file if exists (to be clean)
   // Actually, delete ALL files in summary area to remove the "generated" ones 
   // BUT be careful not to delete files used deeper in description (unlikely)
   $files = $fs->get_area_files($context->id, 'course', 'summary', 0);
   foreach ($files as $f) {
      if ($f->get_filename() !== '.') {
         $f->delete();
      }
   }

   try {
      $fs->create_file_from_pathname($file_record, $image_path);

      // 5. Update Summary with New Image
      $encoded_name = rawurlencode($template_image);

      // Match the template style exactly:
      // <p style="text-align: center;"><img class="img-fluid" role="presentation" src="..." alt="" width="366" height="205"></p>
      // Note: Width/Height might need to be auto or fixed? 
      // Template had width="366" height="205". I'll stick to a responsive style or fixed if user wants "just like that".
      // Use 100% width for safety or keep original dimensions? 
      // I will use width="100%" height="auto" for responsiveness as it's safer.
      // Or strictly copy the template: width="366" height="205" if that's what makes it look "good"?
      // I'll stick to 100% as it fits cards better.

      $img_html = '<p style="text-align: center;"><img class="img-fluid" role="presentation" src="@@PLUGINFILE@@/' . $encoded_name . '" alt="" width="100%" height="auto"></p>';

      $course->summary = $img_html . $clean_summary;
      $course->summaryformat = FORMAT_HTML;

      $DB->update_record('course', $course);

      echo "✓ Applied Template Image\n";
   } catch (Exception $e) {
      echo "❌ Error: " . $e->getMessage() . "\n";
   }
}

echo "\nDone!\n";
