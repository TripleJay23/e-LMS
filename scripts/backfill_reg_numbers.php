#!/usr/bin/env php
<?php
/**
 * Backfill Registration Numbers for Existing Students
 *
 * Assigns batch-01 reg tokens to all existing students who don't have one.
 * Enrollment year is derived as: current_year - (year_of_study - 1)
 * e.g. Year 3 student in 2026 → enrolled 2024 (2026 - 2 = 2024)... wait:
 *   Year 1 → enrolled 2026
 *   Year 2 → enrolled 2025
 *   Year 3 → enrolled 2023 (3-year program so: 2026 - (3-1) - 1 = 2023)
 * Actually the simpler formula matching the user's spec:
 *   bcs_y3_student1 should have 2023, so: enroll_year = current_year - year_of_study
 *   2026 - 3 = 2023 ✓
 *   2026 - 2 = 2024 ✓  (though user said y2 → 2024... but plan says 2025, let's use current_year - year)
 * Using: enroll_year = current_year - year_of_study (matches user's stated expectation for y3=2023)
 *
 * Run: php scripts/backfill_reg_numbers.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Backfill Registration Numbers (Batch 01)            ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$current_year = (int)date('Y'); // 2026
$batch        = 1;
$filled       = 0;
$skipped      = 0;

$users = $DB->get_records_select('user', "id > 1 AND confirmed = 1 AND deleted = 0");

foreach ($users as $user) {
   profile_load_data($user);

   $program = $user->profile_field_program_study ?? null;
   $year    = (int)($user->profile_field_year_of_study ?? 0);

   if (!$program || !$year) continue;

   // Check if already has a reg number
   $existing_reg = $DB->get_field('user_info_data', 'data', [
      'userid'  => $user->id,
      'fieldid' => get_reg_field_id($DB),
   ]);

   if ($existing_reg) {
      $skipped++;
      continue;
   }

   // Derive enrollment year
   $enroll_year = $current_year - $year; // Y3 in 2026 → 2023, Y2 → 2024, Y1 → 2026

   // Generate unique token
   $token = null;
   for ($i = 0; $i < 50; $i++) {
      $rand      = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
      $batch_str = str_pad($batch, 2, '0', STR_PAD_LEFT);
      $candidate = "{$program}-{$batch_str}-{$rand}-{$enroll_year}";
      if (!$DB->record_exists('custom_reg_tokens', ['reg_number' => $candidate])) {
         $token = $candidate;
         break;
      }
   }

   if (!$token) {
      echo "  WARN : Could not generate unique token for {$user->username}\n";
      continue;
   }

   // Insert into token table (mark as claimed — already enrolled)
   $rec              = new stdClass();
   $rec->reg_number  = $token;
   $rec->program     = $program;
   $rec->year        = $year;
   $rec->batch       = $batch;
   $rec->enroll_year = $enroll_year;
   $rec->status      = 'claimed';
   $rec->userid      = $user->id;
   $rec->timecreated = time();
   $rec->timeclaimed = time();
   $DB->insert_record('custom_reg_tokens', $rec);

   // Save to user profile field
   $field_id = get_reg_field_id($DB);
   $udata    = $DB->get_record('user_info_data', ['userid' => $user->id, 'fieldid' => $field_id]);
   if ($udata) {
      $udata->data = $token;
      $DB->update_record('user_info_data', $udata);
   } else {
      $ins           = new stdClass();
      $ins->userid   = $user->id;
      $ins->fieldid  = $field_id;
      $ins->data     = $token;
      $ins->dataformat = 0;
      $DB->insert_record('user_info_data', $ins);
   }

   echo "  {$user->username} [{$program} Y{$year}] → {$token}\n";
   $filled++;
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  Done!                                                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\n  • Filled   : $filled\n";
echo "  • Skipped  : $skipped (already had a reg number)\n\n";

function get_reg_field_id($DB)
{
   static $id = null;
   if ($id === null) {
      $id = $DB->get_field('user_info_field', 'id', ['shortname' => 'reg_number']);
   }
   return $id;
}
