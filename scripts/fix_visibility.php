#!/usr/bin/env php
<?php
/**
 * Fix Course Visibility
 * Makes all courses visible to enrolled students.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║          Fix Course Visibility                        ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$hidden = $DB->get_records_sql('SELECT id, shortname, fullname FROM {course} WHERE visible = 0 AND id != 1');
echo "Found " . count($hidden) . " hidden courses.\n\n";

$fixed = 0;
foreach ($hidden as $c) {
   $DB->set_field('course', 'visible', 1, ['id' => $c->id]);
   $DB->set_field('course', 'visibleold', 1, ['id' => $c->id]);
   echo "  ✓ Made visible: {$c->shortname} | {$c->fullname}\n";
   $fixed++;
}

echo "\nFixed: $fixed courses.\nDone!\n";
