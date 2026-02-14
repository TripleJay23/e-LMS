#!/usr/bin/env php
<?php
/**
 * Export Comprehensive User Data
 * Extracts user details, roles, and course enrollments
 * Outputs JSON for Python processing
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');

echo "Starting Data Export...\n";

// Get all users (excluding deleted and guest)
$users = $DB->get_records_select('user', "deleted = 0 AND username != 'guest'", null, 'username ASC');

$export_data = [];

foreach ($users as $user) {
    if ($user->id == 1) continue; // Skip guest/site user

    // Get Roles
    $roles_str = "User";
    $rolenames = [];
    $context = context_system::instance();
    $roles = get_user_roles($context, $user->id, false);
    
    // Check specific system roles
    if (is_siteadmin($user)) {
        $rolenames[] = "Administrator";
    }

    // Check course enrollments to deduce role (Student vs Teacher)
    $enrolled_courses = enrol_get_users_courses($user->id, true, 'fullname, shortname');
    
    $student_courses = [];
    $teaching_courses = [];
    $is_student = false;
    $is_teacher = false;

    foreach ($enrolled_courses as $course) {
        // Get user's role in this specific course context
        $context = context_course::instance($course->id);
        $course_roles = get_user_roles($context, $user->id);
        
        $role_names = [];
        foreach ($course_roles as $r) {
            $role_names[] = $r->shortname;
        }

        // Categorize
        if (in_array('editingteacher', $role_names) || in_array('teacher', $role_names)) {
            $teaching_courses[] = $course->fullname . " (" . $course->shortname . ")";
            $is_teacher = true;
        } elseif (in_array('student', $role_names)) {
            $student_courses[] = $course->fullname . " (" . $course->shortname . ")";
            $is_student = true;
        } else {
             // Manager or other
             if (in_array('manager', $role_names)) {
                 $rolenames[] = "Manager (Course Level)";
             }
        }
    }

    // Determine primary role label
    if ($is_teacher) $rolenames[] = "Lecturer";
    if ($is_student) $rolenames[] = "Student";
    
    // Check for HOD (Manager at category level is harder to detect quickly, but username helps)
    if (strpos($user->username, 'hod_') !== false) {
        $rolenames[] = "Head of Department";
    }
    
    // Unique roles
    $rolenames = array_unique($rolenames);
    if (!empty($rolenames)) {
        $roles_str = implode(", ", $rolenames);
    }

    // Prepare User Object
    $userData = [
        'username' => $user->username,
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
        'email' => $user->email,
        'password' => 'See Guide', // Default placeholder
        'role' => $roles_str,
        'courses_enrolled' => implode(", ", $student_courses),
        'courses_teaching' => implode(", ", $teaching_courses)
    ];

    // Attempt to guess password based on username pattern (for convenience in Excel)
    if (strpos($user->username, 'student') !== false) {
        $userData['password'] = 'Student@2026';
    } elseif (preg_match('/^(dr_|prof_|mr_|ms_|miss_)/', $user->username)) {
        $userData['password'] = 'Lecturer@2026';
    } elseif ($user->username === 'hod_informatics') {
        $userData['password'] = 'Head@2026';
    } elseif ($user->username === 'admin') {
        $userData['password'] = '(Manual)';
    }

    $export_data[] = $userData;
}

// Convert to JSON
$json = json_encode($export_data, JSON_PRETTY_PRINT);
file_put_contents(__DIR__ . '/../users_full_data.json', $json);

echo "Exported " . count($export_data) . " users to users_full_data.json\n";
