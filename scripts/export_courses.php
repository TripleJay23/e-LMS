#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname');
$course_data = [];

foreach ($courses as $c) {
   $course_data[] = [
      'shortname' => $c->shortname,
      'fullname' => $c->fullname
   ];
}

file_put_contents(__DIR__ . '/../courses_list.json', json_encode($course_data, JSON_PRETTY_PRINT));
echo "Exported " . count($course_data) . " courses to courses_list.json\n";
