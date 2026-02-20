<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "Checking Program-Course Links:\n";
echo str_repeat("-", 60) . "\n";

$programs = $DB->get_records('custom_programs');

foreach ($programs as $prog) {
   echo "Program: {$prog->acronym}\n";
   for ($year = 1; $year <= 3; $year++) {
      $count = $DB->count_records('custom_program_courses', ['programid' => $prog->id, 'year' => $year]);
      echo "  Year $year: $count courses\n";
   }
}
