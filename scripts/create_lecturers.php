#!/usr/bin/env php
<?php
/**
 * Generate lecturers and assign exactly one lecturer per course.
 *
 * Idempotent behavior:
 * - Creates missing lecturer users.
 * - Distributes courses in a deterministic round-robin order.
 * - Ensures one editingteacher role per course (target lecturer).
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/accesslib.php');

echo "Lecturer Generation and Assignment\n";
echo str_repeat("=", 60) . "\n\n";

$lecturers = [
   ['username' => 'dr_mwangi',    'firstname' => 'Dr. James',  'lastname' => 'Mwangi',   'email' => 'james.mwangi@example.com'],
   ['username' => 'prof_njoroge', 'firstname' => 'Prof. Grace','lastname' => 'Njoroge',  'email' => 'grace.njoroge@example.com'],
   ['username' => 'dr_kamau',     'firstname' => 'Dr. Peter',  'lastname' => 'Kamau',    'email' => 'peter.kamau@example.com'],
   ['username' => 'ms_otieno',    'firstname' => 'Ms. Sarah',  'lastname' => 'Otieno',   'email' => 'sarah.otieno@example.com'],
   ['username' => 'mr_odhiambo',  'firstname' => 'Mr. David',  'lastname' => 'Odhiambo', 'email' => 'david.odhiambo@example.com'],
   ['username' => 'dr_wanjiru',   'firstname' => 'Dr. Mary',   'lastname' => 'Wanjiru',  'email' => 'mary.wanjiru@example.com'],
   ['username' => 'prof_kimani',  'firstname' => 'Prof. John', 'lastname' => 'Kimani',   'email' => 'john.kimani@example.com'],
   ['username' => 'dr_achieng',   'firstname' => 'Dr. Jane',   'lastname' => 'Achieng',  'email' => 'jane.achieng@example.com'],
];

$password = 'Lecturer@2026';
$created = 0;

echo "Step 1: Creating lecturer accounts\n";
echo str_repeat("-", 60) . "\n";

foreach ($lecturers as $lec) {
   $existing = $DB->get_record('user', ['username' => $lec['username']]);
   if ($existing) {
      echo "- {$lec['username']} (exists)\n";
      continue;
   }

   $user = create_user_record($lec['username'], $password, 'manual');
   $user->firstname = $lec['firstname'];
   $user->lastname = $lec['lastname'];
   $user->email = $lec['email'];
   $user->city = 'Dar es Salaam';
   $user->country = 'TZ';
   $user->confirmed = 1;
   $user->mnethostid = $CFG->mnet_localhost_id;
   $DB->update_record('user', $user);

   echo "+ {$lec['username']} created\n";
   $created++;
}

echo "\nCreated {$created} new lecturer(s)\n";
echo "Default lecturer password: {$password}\n\n";

echo "Step 2: Assigning one lecturer per course\n";
echo str_repeat("-", 60) . "\n";

$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$manualenrol = enrol_get_plugin('manual');
if (!$manualenrol) {
   echo "ERROR: manual enrolment plugin is not available.\n";
   exit(1);
}

$usernames = array_map(static function ($l) {
   return $l['username'];
}, $lecturers);

$lecturerusers = $DB->get_records_list('user', 'username', $usernames, 'username ASC');
$lecturerarray = array_values($lecturerusers);
$lecturercount = count($lecturerarray);

if ($lecturercount === 0) {
   echo "ERROR: no managed lecturers found.\n";
   exit(1);
}

$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname ASC', 'id,shortname,fullname');
$courseindex = 0;
$newassignments = 0;
$removedroles = 0;
$unenrolments = 0;

foreach ($courses as $course) {
   $target = $lecturerarray[$courseindex % $lecturercount];
   $coursecontext = context_course::instance($course->id);

   $manualinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
   if (!$manualinstance) {
      $manualenrol->add_default_instance($course);
      $manualinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
   }

   $assignments = $DB->get_records_sql(
      "SELECT ra.id, ra.userid
         FROM {role_assignments} ra
         JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE ra.roleid = ?
          AND ctx.contextlevel = ?
          AND ctx.instanceid = ?",
      [$teacherrole->id, CONTEXT_COURSE, $course->id]
   );

   foreach ($assignments as $assignment) {
      if ((int)$assignment->userid === (int)$target->id) {
         continue;
      }

      role_unassign($teacherrole->id, $assignment->userid, $coursecontext->id);
      $removedroles++;

      $stillhasroles = $DB->count_records_sql(
         "SELECT COUNT(1)
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
           WHERE ra.userid = ?
             AND ctx.contextlevel = ?
             AND ctx.instanceid = ?",
         [$assignment->userid, CONTEXT_COURSE, $course->id]
      );

      if ($stillhasroles > 0) {
         continue;
      }

      $instances = enrol_get_instances($course->id, true);
      foreach ($instances as $instance) {
         if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $assignment->userid])) {
            continue;
         }

         $plugin = enrol_get_plugin($instance->enrol);
         if ($plugin) {
            $plugin->unenrol_user($instance, $assignment->userid);
            $unenrolments++;
         }
      }
   }

   $hastargetassignment = user_has_role_assignment($target->id, $teacherrole->id, $coursecontext->id);
   if (!$hastargetassignment) {
      $manualenrol->enrol_user($manualinstance, $target->id, $teacherrole->id);
      $newassignments++;
   } elseif (!$DB->record_exists('user_enrolments', ['enrolid' => $manualinstance->id, 'userid' => $target->id])) {
      $manualenrol->enrol_user($manualinstance, $target->id, $teacherrole->id);
      $newassignments++;
   }

   echo "+ {$course->shortname} -> {$target->username}\n";
   $courseindex++;
}

echo "\nSummary\n";
echo "  Lecturers created: {$created}\n";
echo "  New teacher assignments: {$newassignments}\n";
echo "  Extra teacher roles removed: {$removedroles}\n";
echo "  Extra enrolments removed: {$unenrolments}\n";
echo "  Courses processed: " . count($courses) . "\n";
echo "  Target courses per lecturer: ~" . round(count($courses) / $lecturercount, 1) . "\n\n";

echo "Lecturer credentials\n";
foreach ($lecturerarray as $lecturer) {
   echo "  - {$lecturer->username} / {$password}\n";
}

