#!/usr/bin/env php
<?php
/**
 * Diagnose: Enrolment + Auth + Token system state
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Full System Diagnosis                               ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Auth method ────────────────────────────────────────────────────────────
echo "=== AUTH CONFIGURATION ===\n";
$registerauth = get_config('', 'registerauth');
$authmethod   = get_config('', 'auth');
echo "  Auth plugin(s)     : {$authmethod}\n";
echo "  Self-registration  : {$registerauth}\n";
$notifyconfirm = get_config('', 'notifyloginfailures');
echo "\n";

// ── 2. Plugin event registration ─────────────────────────────────────────────
echo "=== EVENT OBSERVERS (local_profileenrol) ===\n";
$observers = $DB->get_records_sql(
   "SELECT * FROM {events_handlers} WHERE component = 'local_profileenrol'
     UNION
     SELECT * FROM {events_handlers} WHERE 1=0"  // fallback - table may not exist
);
// Try newer cache table
$obs2 = $DB->get_records_sql(
   "SELECT eventname, component, callback
     FROM {events_observers}
     WHERE component LIKE '%profileenrol%'
     LIMIT 10"
);
if ($obs2) {
   foreach ($obs2 as $o) {
      echo "  Event: {$o->eventname}\n  Callback: {$o->callback}\n";
   }
} else {
   // Read db/events.php directly
   $events_file = $CFG->dirroot . '/local/profileenrol/db/events.php';
   if (file_exists($events_file)) {
      include $events_file;
      if (isset($observers)) {
         foreach ($observers as $o) {
            echo "  Registered: {$o['eventname']} → {$o['callback']}\n";
         }
      }
   }
}
echo "\n";

// ── 3. Check local_profileenrol lib.php hooks ─────────────────────────────────
echo "=== LIB.PHP SIGNUP HOOKS ===\n";
$lib = $CFG->dirroot . '/local/profileenrol/lib.php';
echo "  lib.php exists: " . (file_exists($lib) ? "YES" : "NO") . "\n";
if (file_exists($lib)) {
   $content = file_get_contents($lib);
   $hooks = ['local_profileenrol_signup_form_validation', 'local_profileenrol_extend_signup_form', 'local_profileenrol_post_signup_requests'];
   foreach ($hooks as $h) {
      echo "  function $h: " . (strpos($content, "function $h") !== false ? "DEFINED" : "MISSING") . "\n";
   }
}
echo "\n";

// ── 4. Check if Moodle calls signup_form_validation ──────────────────────────
echo "=== MOODLE SIGNUP FORM (lib lookup) ===\n";
$signup_form = $CFG->dirroot . '/login/signup_form.php';
if (file_exists($signup_form)) {
   $content = file_get_contents($signup_form);
   echo "  signup_form_validation called: " . (strpos($content, 'signup_form_validation') !== false ? "YES" : "NO") . "\n";
   echo "  extend_signup_form called    : " . (strpos($content, 'extend_signup_form') !== false ? "YES" : "NO") . "\n";
   echo "  post_signup_requests called  : " . (strpos($content, 'post_signup_requests') !== false ? "YES" : "NO") . "\n";
}
echo "\n";

// ── 5. Token table state ──────────────────────────────────────────────────────
echo "=== CUSTOM_REG_TOKENS TABLE ===\n";
try {
   $total   = $DB->count_records('custom_reg_tokens');
   $unused  = $DB->count_records('custom_reg_tokens', ['status' => 'unused']);
   $claimed = $DB->count_records('custom_reg_tokens', ['status' => 'claimed']);
   echo "  Total: $total | Unused: $unused | Claimed: $claimed\n";
   $samples = $DB->get_records_sql("SELECT reg_number, program, year, batch, status, userid FROM {custom_reg_tokens} LIMIT 5");
   foreach ($samples as $s) {
      echo "  {$s->reg_number} [{$s->program} Y{$s->year}] status={$s->status} userid={$s->userid}\n";
   }
} catch (Exception $e) {
   echo "  ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ── 6. Check student enrolment state ─────────────────────────────────────────
echo "=== STUDENT ENROLMENTS ===\n";
foreach (['bcs_y2_student1', 'bit_y1_student1'] as $uname) {
   $u = $DB->get_record('user', ['username' => $uname]);
   if (!$u) {
      echo "  $uname: NOT FOUND\n";
      continue;
   }
   $count = $DB->count_records_sql(
      "SELECT COUNT(*) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = ?",
      [$u->id]
   );
   echo "  {$uname} (confirmed={$u->confirmed}): enrolled in {$count} courses\n";
}
echo "\n";

// ── 7. Check custom_program_courses count ─────────────────────────────────────
echo "=== CUSTOM_PROGRAM_COURSES ===\n";
$cpc_count = $DB->count_records('custom_program_courses');
echo "  Total rows: $cpc_count\n";
$bcs = $DB->get_record('custom_programs', ['acronym' => 'BCS']);
if ($bcs) {
   $y2 = $DB->count_records('custom_program_courses', ['programid' => $bcs->id, 'year' => 2]);
   echo "  BCS Year 2 courses: $y2\n";
}
echo "\nDone.\n";
