<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "Checking Data Counts per Field:\n";
echo str_repeat("-", 40) . "\n";

$fields = $DB->get_records('user_info_field', null, '', 'id, shortname');

foreach ($fields as $field) {
   $count = $DB->count_records('user_info_data', ['fieldid' => $field->id]);
   echo sprintf("%-15s (ID: %d): %d records\n", $field->shortname, $field->id, $count);
}
