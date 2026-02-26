<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/moodle/config.php');

$categories = $DB->get_records('course_categories', null, 'idnumber ASC');
echo "ID | IDNumber | Name | Parent | Path\n";
echo str_repeat("-", 80) . "\n";
foreach ($categories as $cat) {
    echo "{$cat->id} | {$cat->idnumber} | {$cat->name} | {$cat->parent} | {$cat->path}\n";
    
    // Check for courses in this category
    $course_count = $DB->count_records('course', ['category' => $cat->id]);
    echo "   -> Course Count: $course_count\n";
}
