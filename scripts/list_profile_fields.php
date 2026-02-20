<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "Listing User Profile Fields:\n";
echo str_repeat("-", 60) . "\n";
echo sprintf("%-5s | %-20s | %-30s | %-10s\n", "ID", "Shortname", "Name", "Category");
echo str_repeat("-", 60) . "\n";

$fields = $DB->get_records_sql("
    SELECT f.id, f.shortname, f.name, c.name as category
    FROM {user_info_field} f
    JOIN {user_info_category} c ON f.categoryid = c.id
    ORDER BY c.sortorder, f.sortorder
");

foreach ($fields as $field) {
    echo sprintf("%-5d | %-20s | %-30s | %-10s\n", $field->id, $field->shortname, $field->name, $field->category);
}
