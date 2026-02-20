#!/usr/bin/env php
<?php
/**
 * Test Auto-Enrolment Logic
 * Simulates user creation and triggers the event to test observer.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php'); // Required for user_create_user

echo "Testing Auto-Enrolment Logic...\n";

// 1. Create a Test User
$user = new stdClass();
$user->username = 'test_student_' . time();
$user->password = 'TestPass123!';
$user->firstname = 'Test';
$user->lastname = 'Student';
$user->email = $user->username . '@example.com';
$user->confirmed = 1;
$user->mnethostid = $CFG->mnet_localhost_id;

$userid = user_create_user($user);
echo "Created Test User: $user->username (ID: $userid)\n";

// 2. Set Profile Data
// We need to set the profile data in user_info_data table manually or via API
// The profile fields shortnames are 'program_study' and 'year_of_study'

// Get field IDs
$progField = $DB->get_record('user_info_field', ['shortname' => 'program_study']);
$yearField = $DB->get_record('user_info_field', ['shortname' => 'year_of_study']);

if ($progField && $yearField) {
   // Set Program = BIT
   $data1 = new stdClass();
   $data1->userid = $userid;
   $data1->fieldid = $progField->id;
   $data1->data = 'BIT'; // Value
   $data1->dataformat = 0;
   $DB->insert_record('user_info_data', $data1);

   // Set Year = 1
   $data2 = new stdClass();
   $data2->userid = $userid;
   $data2->fieldid = $yearField->id;
   $data2->data = '1';
   $data2->dataformat = 0;
   $DB->insert_record('user_info_data', $data2);

   echo "Set Profile Data: BIT, Year 1\n";
} else {
   echo "Error: Profile fields not found.\n";
   exit;
}

// 3. Trigger user_created Event manually
// (Note: user_create_user triggers it, but we set profile data AFTER. 
//  The real signup process sets profile data BEFORE triggering the event usually,
//  or the event handler loads it. 
//  In `user_create_user`, the event is triggered at the end.
//  However, `user_create_user` doesn't handle custom profile fields arguments directly in std API unless passed in extra access?
//  Actually, Moodle's signup form processes profile data and saves it. 
//  Let's manually trigger the event again to be sure our observer runs with data present.)

$event = \core\event\user_created::create_from_userid($userid);
$event->trigger();

echo "Triggered user_created event.\n";

// 4. Check Enrolments
$courses = enrol_get_users_courses($userid);
echo "Enrolled Courses:\n";
foreach ($courses as $c) {
   echo " - $c->fullname ($c->shortname)\n";
}

if (count($courses) > 0) {
   echo "SUCCESS: User enrolled in " . count($courses) . " courses.\n";
} else {
   echo "FAILURE: No courses found. Check observer logic or custom_program_courses table.\n";
}
