#!/usr/bin/env php
<?php
/**
 * Deprecated helper.
 *
 * This project now uses summary-only images from /local/courseimages.
 * Re-adding course overviewfiles will bring back the separate "Course image"
 * section, which is intentionally disabled.
 *
 * Run this script only to clear any existing overviewfiles.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

$fs = get_file_storage();
$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname ASC', 'id,shortname');
$cleared = 0;

echo "Deprecated: fix_frontpage_images.php\n";
echo "Clearing course overviewfiles only...\n\n";

foreach ($courses as $course) {
   $context = context_course::instance($course->id);
   $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id', false);
   if (empty($files)) {
      continue;
   }
   $fs->delete_area_files($context->id, 'course', 'overviewfiles');
   $cleared++;
   echo "+ Cleared overviewfiles for {$course->shortname}\n";
}

echo "\nDone. Cleared {$cleared} course(s).\n";
echo "Use scripts/apply_full_course_template.php for summary image layout.\n";

