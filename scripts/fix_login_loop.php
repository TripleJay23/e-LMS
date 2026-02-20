<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "Fixing Login Loop Issues...\n";
echo "--------------------------------------------------\n";

// 1. Identify Required Custom Fields
$required_fields = $DB->get_records('user_info_field', ['required' => 1]);

if (empty($required_fields)) {
   echo "No required custom fields found. That wasn't the issue.\n";
} else {
   echo "Found " . count($required_fields) . " required custom fields. Disabling 'required' status...\n";
   foreach ($required_fields as $field) {
      $field->required = 0;
      $DB->update_record('user_info_field', $field);
      echo " - Updated field '{$field->shortname}' (ID: {$field->id}) -> Not Required\n";
   }
   echo "✓ Custom fields are no longer mandatory.\n";
}

// 2. Clear Caches
echo "\nPurging Caches...\n";
require_once($CFG->libdir . '/adminlib.php');
purge_all_caches();
echo "✓ Caches purged.\n";

echo "\n--------------------------------------------------\n";
echo "Fix Complete. Please try logging in again.\n";
