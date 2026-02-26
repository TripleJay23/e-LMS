<?php
define('CLI_SCRIPT', true);
require('moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

$fs = get_file_storage();
$courses = $DB->get_records_select('course', 'id > 1');
$courses_with_images = 0;
$courses_without_images = 0;

foreach ($courses as $c) {
    $ctx = context_course::instance($c->id);
    $files = $fs->get_area_files($ctx->id, 'course', 'overviewfiles', 0, 'id', false);
    
    // filter out directories
    $image_count = 0;
    foreach ($files as $f) {
        if ($f->get_filename() !== '.') {
            $image_count++;
        }
    }
    
    if ($image_count > 0) {
        $courses_with_images++;
    } else {
        $courses_without_images++;
        // echo "No image: " . $c->shortname . "\n";
    }
}
echo "Courses with overviewfiles: $courses_with_images\n";
echo "Courses without overviewfiles: $courses_without_images\n";
