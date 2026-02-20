#!/usr/bin/env php
<?php
/**
 * Setup Custom Profile Fields for Signup Form
 * - Program of Study (Menu: BIT, BCS, etc.)
 * - Year of Study (Menu: 1, 2, 3)
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/profile/definelib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      e-LMS Custom Profile Fields Setup                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

try {
   // 1. Ensure "Academic Info" Category Exists
   $categoryName = "Academic Info";
   $category = $DB->get_record('user_info_category', ['name' => $categoryName]);

   if (!$category) {
      $data = new stdClass();
      $data->name = $categoryName;
      $data->sortorder = 1;
      $id = $DB->insert_record('user_info_category', $data);
      $category = $DB->get_record('user_info_category', ['id' => $id]);
      echo "✓ Created Category: $categoryName\n";
   } else {
      echo "• Category '$categoryName' already exists.\n";
   }

   // 2. Prepare Program Options from Database
   $programs = $DB->get_records('custom_programs');
   $programOptions = [];
   foreach ($programs as $prog) {
      $programOptions[] = $prog->acronym; // e.g., BIT, BCS
   }
   // Fallback if table empty, though unlikely given context
   if (empty($programOptions)) {
      $programOptions = ['BIT', 'BCS'];
      echo "! Warning: No programs found in custom_programs, using defaults.\n";
   }
   $programMenuOptions = implode("\n", $programOptions);

   // 3. Create "Program of Study" Field
   create_or_update_field(
      'program_study',
      'Program of Study',
      'menu',
      $category->id,
      $programMenuOptions,
      'Select your program of study (e.g., BIT, BCS)'
   );

   // 4. Create "Year of Study" Field
   $yearMenuOptions = "1\n2\n3";
   create_or_update_field(
      'year_of_study',
      'Year of Study',
      'menu',
      $category->id,
      $yearMenuOptions,
      'Select your current year of study'
   );

   echo "\nNOTE: fields are configured to appear on the Signup page and are Required.\n";
} catch (Exception $e) {
   echo "✗ Error: " . $e->getMessage() . "\n";
   exit(1);
}

/**
 * Helper to create or update a profile field
 */
function create_or_update_field($shortname, $name, $datatype, $categoryId, $param1, $description = '')
{
   global $DB;

   $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);

   $data = new stdClass();
   $data->shortname = $shortname;
   $data->name = $name;
   $data->datatype = $datatype;
   $data->categoryid = $categoryId;
   $data->description = $description;
   $data->descriptionformat = 1; // FORMAT_HTML
   $data->required = 1;
   $data->locked = 0;
   $data->visible = 2; // Not visible on public profile, visible to user
   $data->forceunique = 0;
   $data->signup = 1; // Display on signup page
   $data->defaultdata = '';
   $data->defaultdataformat = 0;
   $data->param1 = $param1; // Menu options
   $data->param2 = '';
   $data->param3 = '';
   $data->param4 = '';
   $data->param5 = '';

   if ($field) {
      $data->id = $field->id;
      $DB->update_record('user_info_field', $data);
      echo "✓ Updated Field: $name ($shortname)\n";
   } else {
      $DB->insert_record('user_info_field', $data);
      echo "✓ Created Field: $name ($shortname)\n";
   }
}
