#!/usr/bin/env php
<?php
/**
 * E2E test: simulate the Option B email confirmation flow.
 *
 *   Step 1: user_signup_with_confirmation() creates user (confirmed=0), saves profile, fires user_created
 *           → observer should NOT auto-confirm → user stays confirmed=0
 *   Step 2: user_confirm() sets confirmed=1 (mimics clicking email link)
 *   Step 3: complete_user_login() fires user_loggedin
 *           → observer should enrol + claim token (with emails suppressed)
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir  . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║  E2E Test: Email Confirmation → Auto-Enrol (Option B) ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── Setup: create unused token ────────────────────────────────────────────────
$test_reg = 'BCS-99-8888-2026';
$DB->delete_records('custom_reg_tokens', ['reg_number' => $test_reg]);
$DB->delete_records('user', ['username' => 'e2e_confirm_test']);

$rec = new stdClass();
$rec->reg_number  = $test_reg;
$rec->program     = 'BCS';
$rec->year        = 1;
$rec->batch       = 99;
$rec->enroll_year = 2026;
$rec->status      = 'unused';
$rec->userid      = null;
$rec->timecreated = time();
$rec->timeclaimed = null;
$DB->insert_record('custom_reg_tokens', $rec);
echo "✓ Token created: {$test_reg}\n\n";

// ══════════════════════════════════════════════════════════════════════════════
// STEP 1: Simulate auth_email->user_signup_with_confirmation()
// ══════════════════════════════════════════════════════════════════════════════
echo "═══ STEP 1: Signup (user_create_user + profile_save + user_created event) ═══\n";

$newuser = new stdClass();
$newuser->username   = 'e2e_confirm_test';
$newuser->email      = 'e2e_confirm@example.com';
$newuser->password   = hash_internal_user_password('TestPass1!');
$newuser->firstname  = 'E2E';
$newuser->lastname   = 'ConfirmTest';
$newuser->confirmed  = 0;
$newuser->mnethostid = $CFG->mnet_localhost_id;
$newuser->auth       = 'email';
$newuser->lang       = 'en';
$newuser->country    = 'TZ';
$newuser->profile_field_program_study = 'BCS';
$newuser->profile_field_year_of_study = '1';
$newuser->profile_field_reg_number    = $test_reg;

$newuser->id = user_create_user($newuser, false, false);
profile_save_data($newuser);

// Fire user_created (like auth_email does)
\core\event\user_created::create_from_userid($newuser->id)->trigger();

// Check: should still be confirmed=0, no enrolments, token unused
$u = $DB->get_record('user', ['id' => $newuser->id]);
$enrol_count = $DB->count_records_sql(
   "SELECT COUNT(*) FROM {user_enrolments} ue JOIN {enrol} e ON e.id=ue.enrolid WHERE ue.userid=?",
   [$newuser->id]
);
$tkn = $DB->get_record('custom_reg_tokens', ['reg_number' => $test_reg]);

echo "  confirmed  : {$u->confirmed}  " . ($u->confirmed == 0 ? '✓ (still 0, good!)' : '✗ SHOULD BE 0') . "\n";
echo "  enrolments : {$enrol_count}  " . ($enrol_count == 0 ? '✓ (none yet, good!)' : '✗ SHOULD BE 0') . "\n";
echo "  token status: {$tkn->status}  " . ($tkn->status === 'unused' ? '✓ (still unused, good!)' : '✗ SHOULD BE unused') . "\n";

$step1_ok = ($u->confirmed == 0 && $enrol_count == 0 && $tkn->status === 'unused');
echo "  STEP 1: " . ($step1_ok ? "PASS ✓" : "FAIL ✗") . "\n\n";

// ══════════════════════════════════════════════════════════════════════════════
// STEP 2: Simulate clicking confirmation link (auth_email->user_confirm)
// ══════════════════════════════════════════════════════════════════════════════
echo "═══ STEP 2: Email Confirmation (set confirmed=1, no event) ═══\n";

$DB->set_field('user', 'confirmed', 1, ['id' => $newuser->id]);
echo "  confirmed=1 set in DB\n";

// Check: confirmed=1 but still no enrolments (no event was fired)
$u = $DB->get_record('user', ['id' => $newuser->id]);
$enrol_count = $DB->count_records_sql(
   "SELECT COUNT(*) FROM {user_enrolments} ue JOIN {enrol} e ON e.id=ue.enrolid WHERE ue.userid=?",
   [$newuser->id]
);
$tkn = $DB->get_record('custom_reg_tokens', ['reg_number' => $test_reg]);

echo "  confirmed  : {$u->confirmed}  " . ($u->confirmed == 1 ? '✓' : '✗') . "\n";
echo "  enrolments : {$enrol_count}  " . ($enrol_count == 0 ? '✓ (none yet — waiting for login)' : '✗ SHOULD BE 0') . "\n";
echo "  token status: {$tkn->status}  " . ($tkn->status === 'unused' ? '✓ (still unused)' : '✗ SHOULD BE unused') . "\n";

$step2_ok = ($u->confirmed == 1 && $enrol_count == 0 && $tkn->status === 'unused');
echo "  STEP 2: " . ($step2_ok ? "PASS ✓" : "FAIL ✗") . "\n\n";

// ══════════════════════════════════════════════════════════════════════════════
// STEP 3: Simulate complete_user_login → fires user_loggedin
// ══════════════════════════════════════════════════════════════════════════════
echo "═══ STEP 3: First Login (user_loggedin event → observer enrols) ═══\n";

// Fire user_loggedin event (as complete_user_login does)
$event = \core\event\user_loggedin::create([
   'userid'   => $newuser->id,
   'objectid' => $newuser->id,
   'other'    => ['username' => $newuser->username],
]);
$event->trigger();

echo "  user_loggedin event fired\n";

// Check results
$u = $DB->get_record('user', ['id' => $newuser->id]);
$enrol_count = $DB->count_records_sql(
   "SELECT COUNT(*) FROM {user_enrolments} ue JOIN {enrol} e ON e.id=ue.enrolid WHERE ue.userid=?",
   [$newuser->id]
);
$tkn = $DB->get_record('custom_reg_tokens', ['reg_number' => $test_reg]);

echo "  confirmed  : {$u->confirmed}  " . ($u->confirmed == 1 ? '✓' : '✗') . "\n";
echo "  enrolments : {$enrol_count}  " . ($enrol_count == 12 ? '✓ (all 12!)' : ($enrol_count > 0 ? "⚠ ({$enrol_count} of 12)" : '✗ NONE')) . "\n";
echo "  token status: {$tkn->status}  " . ($tkn->status === 'claimed' ? '✓ (claimed!)' : '✗ SHOULD BE claimed') . "\n";
echo "  token userid: " . ($tkn->userid ?? 'null') . "\n";

$courses = $DB->get_records_sql(
   "SELECT c.shortname FROM {user_enrolments} ue
     JOIN {enrol} e ON e.id = ue.enrolid
     JOIN {course} c ON c.id = e.courseid
     WHERE ue.userid = ? ORDER BY c.shortname",
   [$newuser->id]
);
echo "\n  Courses enrolled:\n";
foreach ($courses as $c) echo "    · {$c->shortname}\n";

$step3_ok = ($enrol_count >= 12 && $tkn->status === 'claimed');
echo "\n  STEP 3: " . ($step3_ok ? "PASS ✓" : "FAIL ✗") . "\n";

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n══════════════════════════════════════════════════════════\n";
echo "OVERALL: " . ($step1_ok && $step2_ok && $step3_ok ? "ALL PASS ✓" : "SOME FAILED ✗") . "\n";
echo "══════════════════════════════════════════════════════════\n";

// ── Cleanup ──────────────────────────────────────────────────────────────────
$DB->delete_records('user_enrolments', ['userid' => $newuser->id]);
$DB->delete_records('role_assignments', ['userid' => $newuser->id]);
$DB->delete_records_select('user_info_data', 'userid = ?', [$newuser->id]);
$DB->delete_records('user', ['id' => $newuser->id]);
$DB->delete_records('custom_reg_tokens', ['reg_number' => $test_reg]);
echo "\n✓ Cleaned up test data.\nDone.\n";
