#!/usr/bin/env php
<?php
/**
 * Create Head of Department (HOD) User
 * Assigns Manager role scoped to specific categories
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Creating Head of Department (HOD)                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$username = 'hod_informatics';
$password = 'Head@2026';
$email = 'hod.informatics@example.com';
$firstname = 'Dr. Head';
$lastname = 'Informatics';

// 1. Create User
echo "1. Creating User Account...\n";
$user = $DB->get_record('user', ['username' => $username]);
if (!$user) {
    $user = create_user_record($username, $password, 'manual');
    $user->firstname = $firstname;
    $user->lastname = $lastname;
    $user->email = $email;
    $user->city = 'Dar es Salaam';
    $user->country = 'TZ';
    $user->confirmed = 1;
    
    $DB->update_record('user', $user);
    echo "   ✓ Created user '$username' (ID: {$user->id})\n";
} else {
    echo "   • User '$username' already exists (ID: {$user->id})\n";
}

// 2. Assign Manager Role in Categories
echo "\n2. Assigning Permissions...\n";
$manager_role = $DB->get_record('role', ['shortname' => 'manager']);
if (!$manager_role) {
    die("Error: Manager role not found\n");
}

$categories = ['BIT', 'BCS', 'COMMON'];

foreach ($categories as $idnumber) {
    $cat = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
    if ($cat) {
        $context = context_coursecat::instance($cat->id);
        
        // Check if already assigned
        if (!user_has_role_assignment($user->id, $manager_role->id, $context->id)) {
            role_assign($manager_role->id, $user->id, $context->id);
            echo "   ✓ Assigned Manager role in category: $idnumber\n";
        } else {
            echo "   • Already Manager in category: $idnumber\n";
        }
    } else {
        echo "   ✗ Category '$idnumber' not found\n";
    }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║      HOD Implementation Complete! ✓                   ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Credentials:\n";
echo "  Username: $username\n";
echo "  Password: $password\n";
echo "  Role:     Manager (Scoped to BIT, BCS, Common)\n";
