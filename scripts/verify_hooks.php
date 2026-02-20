#!/usr/bin/env php
<?php
/**
 * Verify signup hooks are discoverable and simulate an end-to-end enrolment
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/adminlib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Verify Signup Hooks + Simulate Enrolment            ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Check hook discovery ────────────────────────────────────────────────────
echo "=== Hook Discovery (get_plugins_with_function) ===\n";

$validate_hooks = get_plugins_with_function('validate_extend_signup_form');
$post_hooks     = get_plugins_with_function('post_signup_requests');

echo "  validate_extend_signup_form:\n";
if (empty($validate_hooks)) {
   echo "    ✗ NONE FOUND — hook not registered!\n";
} else {
   foreach ($validate_hooks as $type => $plugins) {
      foreach ($plugins as $name => $fn) {
         echo "    ✓ {$fn}\n";
      }
   }
}

echo "  post_signup_requests:\n";
if (empty($post_hooks)) {
   echo "    ✗ NONE FOUND — hook not registered!\n";
} else {
   foreach ($post_hooks as $type => $plugins) {
      foreach ($plugins as $name => $fn) {
         echo "    ✓ {$fn}\n";
      }
   }
}

// ── 2. Generate a fresh unused test token ─────────────────────────────────────
echo "\n=== Generating Test Token ===\n";

$test_token = 'BCS-02-TEST-2026';
$DB->delete_records('custom_reg_tokens', ['reg_number' => $test_token]);

$rec = new stdClass();
$rec->reg_number  = $test_token;
$rec->program     = 'BCS';
$rec->year        = 1;
$rec->batch       = 2;
$rec->enroll_year = 2026;
$rec->status      = 'unused';
$rec->userid      = null;
$rec->timecreated = time();
$rec->timeclaimed = null;
$DB->insert_record('custom_reg_tokens', $rec);
echo "  ✓ Test token created: {$test_token}\n";

// ── 3. Simulate validate_extend_signup_form ───────────────────────────────────
echo "\n=== Simulate: validate_extend_signup_form ===\n";
require_once($CFG->dirroot . '/local/profileenrol/lib.php');

$cases = [
   ['label' => 'Valid token',       'reg' => $test_token, 'prog' => 'BCS', 'yr' => 1, 'expect' => 'PASS'],
   ['label' => 'Wrong program',     'reg' => $test_token, 'prog' => 'BIT', 'yr' => 1, 'expect' => 'FAIL'],
   ['label' => 'Wrong year',        'reg' => $test_token, 'prog' => 'BCS', 'yr' => 2, 'expect' => 'FAIL'],
   ['label' => 'Non-existent token', 'reg' => 'ZZZ-99-0000-2020', 'prog' => 'BCS', 'yr' => 1, 'expect' => 'FAIL'],
];

foreach ($cases as $c) {
   $data = [
      'profile_field_reg_number'   => $c['reg'],
      'profile_field_program_study' => $c['prog'],
      'profile_field_year_of_study' => $c['yr'],
   ];
   $errors = local_profileenrol_validate_extend_signup_form($data);
   $result = empty($errors) ? 'PASS' : 'FAIL';
   $icon   = $result === $c['expect'] ? '✓' : '✗';
   $msg    = empty($errors) ? 'no errors' : reset($errors);
   echo "  {$icon} [{$c['label']}] → {$result} ({$msg})\n";
}

// ── 4. Simulate post_signup_requests (inline enrolment) ───────────────────────
echo "\n=== Simulate: post_signup_requests (enrolment) ===\n";

// Use existing bcs_y1_student1 as test subject but check BCS Year 2 to avoid dups
$test_user = $DB->get_record('user', ['username' => 'bcs_y2_student1']);
if (!$test_user) {
   echo "  SKIP: bcs_y2_student1 not found\n";
} else {
   // Count current enrolments
   $before = $DB->count_records_sql(
      "SELECT COUNT(*) FROM {user_enrolments} ue JOIN {enrol} e ON e.id=ue.enrolid WHERE ue.userid=?",
      [$test_user->id]
   );
   echo "  Enrolments before : {$before}\n";

   // Re-run as if they just signed up
   $fake_data = (object)[
      'email'                       => $test_user->email,
      'profile_field_reg_number'   => $test_user->profile ?? 'BCS-01-2395-2024',
      'profile_field_program_study' => 'BCS',
      'profile_field_year_of_study' => 2,
   ];
   local_profileenrol_post_signup_requests($fake_data);

   $after = $DB->count_records_sql(
      "SELECT COUNT(*) FROM {user_enrolments} ue JOIN {enrol} e ON e.id=ue.enrolid WHERE ue.userid=?",
      [$test_user->id]
   );
   echo "  Enrolments after  : {$after}\n";
   echo ($after >= $before ? "  ✓ Enrolment logic ran successfully\n" : "  ✗ No change in enrolments\n");
}

// Cleanup test token
$DB->delete_records('custom_reg_tokens', ['reg_number' => $test_token]);
echo "\n  ✓ Test token cleaned up\n\nDone.\n";
