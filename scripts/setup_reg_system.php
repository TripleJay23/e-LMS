#!/usr/bin/env php
<?php
/**
 * Setup reg_tokens table AND reg_number profile field
 *
 * Run: php scripts/setup_reg_system.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Setup Registration Number System                    ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Create mdl_custom_reg_tokens table ─────────────────────────────────────
echo "Step 1: Create custom_reg_tokens table...\n";

$dbman = $DB->get_manager();
$table = new xmldb_table('custom_reg_tokens');

if (!$dbman->table_exists($table)) {
   $table->add_field('id',           XMLDB_TYPE_INTEGER, '10',    XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
   $table->add_field('reg_number',   XMLDB_TYPE_CHAR,    '30',    null, XMLDB_NOTNULL);
   $table->add_field('program',      XMLDB_TYPE_CHAR,    '10',    null, XMLDB_NOTNULL);
   $table->add_field('year',         XMLDB_TYPE_INTEGER, '2',     null, XMLDB_NOTNULL, null, '1');
   $table->add_field('batch',        XMLDB_TYPE_INTEGER, '4',     null, XMLDB_NOTNULL, null, '1');
   $table->add_field('enroll_year',  XMLDB_TYPE_INTEGER, '4',     null, XMLDB_NOTNULL);
   $table->add_field('status',       XMLDB_TYPE_CHAR,    '10',    null, XMLDB_NOTNULL, null, 'unused');
   $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10',    null, null,         null, null);
   $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
   $table->add_field('timeclaimed',  XMLDB_TYPE_INTEGER, '10',    null, null,         null, null);

   $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
   $table->add_index('reg_number_unique', XMLDB_INDEX_UNIQUE, ['reg_number']);
   $table->add_index('status_idx',        XMLDB_INDEX_NOTUNIQUE, ['status']);

   $dbman->create_table($table);
   echo "  ✓ Table custom_reg_tokens created\n";
} else {
   echo "  • Table already exists\n";
}

// ── 2. Create reg_number profile field ───────────────────────────────────────
echo "\nStep 2: Create reg_number profile field...\n";

// Ensure Academic Info category exists
$cat_name = 'Academic Info';
$pf_cat   = $DB->get_record('user_info_category', ['name' => $cat_name]);
if (!$pf_cat) {
   $c           = new stdClass();
   $c->name      = $cat_name;
   $c->sortorder = 1;
   $id           = $DB->insert_record('user_info_category', $c);
   $pf_cat       = $DB->get_record('user_info_category', ['id' => $id]);
   echo "  ✓ Created profile category: $cat_name\n";
}

$field = $DB->get_record('user_info_field', ['shortname' => 'reg_number']);
$data  = new stdClass();
$data->shortname          = 'reg_number';
$data->name               = 'Registration Number';
$data->datatype           = 'text';
$data->categoryid         = $pf_cat->id;
$data->description        = 'Your official student registration number (e.g. BCS-02-4821-2026).';
$data->descriptionformat  = FORMAT_HTML;
$data->required           = 1;    // Must be filled at signup
$data->locked             = 1;    // Student cannot change after set
$data->visible            = 2;    // Visible to student, not public
$data->forceunique        = 1;    // Must be unique across all users
$data->signup             = 1;    // Show on signup form
$data->defaultdata        = '';
$data->defaultdataformat  = 0;
$data->param1             = '30'; // Max length
$data->param2             = '30';
$data->param3             = '0';  // Not multiline
$data->param4             = '';
$data->param5             = '';

if ($field) {
   $data->id = $field->id;
   $DB->update_record('user_info_field', $data);
   echo "  ✓ Updated reg_number profile field\n";
} else {
   $DB->insert_record('user_info_field', $data);
   echo "  ✓ Created reg_number profile field\n";
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  Done! Run generate_reg_tokens.php next.              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";
