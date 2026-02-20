<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

// Helper to check enrolments
function check_enrolments($userid)
{
   global $DB;
   $sql = "SELECT c.id, c.shortname 
            FROM {user_enrolments} ue 
            JOIN {enrol} e ON ue.enrolid = e.id 
            JOIN {course} c ON e.courseid = c.id 
            WHERE ue.userid = ?";
   $courses = $DB->get_records_sql($sql, [$userid]);
   return $courses;
}

echo "Testing Auto-Enrolment on Confirmation...\n";
echo str_repeat("-", 60) . "\n";

// 1. Create Unconfirmed User
$user = new stdClass();
$user->username = 'test_confirm_' . time();
$user->password = 'TestPass123!';
$user->email = $user->username . '@example.com';
$user->firstname = 'Test';
$user->lastname = 'Confirm';
$user->confirmed = 0; // NOT CONFIRMED
$user->mnethostid = $CFG->mnet_localhost_id;

$userid = user_create_user($user, false, false);
echo "Created Unconfirmed User: {$user->username} (ID: $userid)\n";

// Set Profile Data (BIT Year 1)
$progField = $DB->get_record('user_info_field', ['shortname' => 'program_study']);
$yearField = $DB->get_record('user_info_field', ['shortname' => 'year_of_study']);

$data = new stdClass();
$data->userid = $userid;
$data->fieldid = $progField->id;
$data->data = 'BIT';
$data->dataformat = 0;
$DB->insert_record('user_info_data', $data);

$data = new stdClass();
$data->userid = $userid;
$data->fieldid = $yearField->id;
$data->data = '1';
$data->dataformat = 0;
$DB->insert_record('user_info_data', $data);

echo "Set Profile: BIT, Year 1\n";

// Trigger user_created (Should NOT enrol)
\core\event\user_created::create_from_userid($userid)->trigger();
echo "Triggered user_created (confirmed=0)...\n";

$courses = check_enrolments($userid);
if (empty($courses)) {
   echo "✓ Success: User NOT enrolled (as expected).\n";
} else {
   echo "✗ Failure: User WAS enrolled prematurely.\n";
   print_r($courses);
}

// 2. Confirm User
$user = $DB->get_record('user', ['id' => $userid]);
$user->confirmed = 1;
$DB->update_record('user', $user);
echo "\nUpdated user to confirmed = 1.\n";

// Trigger user_updated
$event = \core\event\user_updated::create_from_userid($userid);
$event->trigger();
echo "Triggered user_updated (confirmed=1)...\n";

// Check Enrolments
$courses = check_enrolments($userid);
if (!empty($courses)) {
   echo "✓ Success: User Enrolled in " . count($courses) . " courses.\n";
   foreach ($courses as $c) {
      echo " - {$c->shortname}\n";
   }
} else {
   echo "✗ Failure: User NOT enrolled after confirmation.\n";
}
