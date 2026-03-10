#!/usr/bin/env php
<?php
/**
 * Export users_full_data.json from the current Moodle database.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/accesslib.php');

/**
 * Check whether a user has any assignment for a role.
 */
function user_has_role_anywhere(int $userid, ?int $roleid, moodle_database $DB): bool {
    if (empty($roleid)) {
        return false;
    }
    return $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $roleid]);
}

/**
 * Fetch courses where a user has a specific role assignment.
 *
 * @return string[]
 */
function get_courses_for_role(int $userid, ?int $roleid, moodle_database $DB): array {
    if (empty($roleid)) {
        return [];
    }

    $records = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.shortname
           FROM {role_assignments} ra
           JOIN {context} ctx ON ctx.id = ra.contextid
           JOIN {course} c ON c.id = ctx.instanceid
          WHERE ra.userid = ?
            AND ra.roleid = ?
            AND ctx.contextlevel = ?
       ORDER BY c.shortname ASC",
        [$userid, $roleid, CONTEXT_COURSE]
    );

    $courses = [];
    foreach ($records as $course) {
        $courses[] = "{$course->fullname} ({$course->shortname})";
    }
    return $courses;
}

$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', IGNORE_MISSING);
$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', IGNORE_MISSING);
$managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', IGNORE_MISSING);

$users = $DB->get_records_sql(
    "SELECT id, username, firstname, lastname, email
       FROM {user}
      WHERE deleted = 0
        AND username <> 'guest'
   ORDER BY username ASC"
);

$export = [];
foreach ($users as $user) {
    $role = 'User';
    if (is_siteadmin($user)) {
        $role = 'Administrator';
    } elseif (user_has_role_anywhere($user->id, $managerrole ? (int)$managerrole->id : null, $DB)) {
        $role = 'Head of Department';
    } elseif (user_has_role_anywhere($user->id, $teacherrole ? (int)$teacherrole->id : null, $DB)) {
        $role = 'Lecturer';
    } elseif (user_has_role_anywhere($user->id, $studentrole ? (int)$studentrole->id : null, $DB)) {
        $role = 'Student';
    }

    $coursesEnrolled = get_courses_for_role($user->id, $studentrole ? (int)$studentrole->id : null, $DB);
    $coursesTeaching = get_courses_for_role($user->id, $teacherrole ? (int)$teacherrole->id : null, $DB);

    $export[] = [
        'username' => $user->username,
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
        'email' => $user->email,
        'password' => '(not exported)',
        'role' => $role,
        'courses_enrolled' => implode(', ', $coursesEnrolled),
        'courses_teaching' => implode(', ', $coursesTeaching),
    ];
}

// Sort by role priority, then username.
$roleOrder = [
    'Administrator' => 1,
    'Head of Department' => 2,
    'Lecturer' => 3,
    'Student' => 4,
    'User' => 5,
];

usort($export, static function (array $a, array $b) use ($roleOrder): int {
    $ra = $roleOrder[$a['role']] ?? 99;
    $rb = $roleOrder[$b['role']] ?? 99;
    if ($ra !== $rb) {
        return $ra <=> $rb;
    }
    return strcmp($a['username'], $b['username']);
});

$outpath = __DIR__ . '/../users_full_data.json';
file_put_contents($outpath, json_encode($export, JSON_PRETTY_PRINT));
echo "Exported " . count($export) . " users to users_full_data.json\n";
