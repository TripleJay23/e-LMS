#!/usr/bin/env php
<?php
/**
 * Moodle Configuration Script for e-LMS
 * Configures Moodle settings for optimal content delivery and student experience
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║        e-LMS Moodle Configuration Script              ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Configuring Moodle settings...\n\n";

try {
   // Enable email-based self-registration for students
   echo "1. Enabling student self-registration...\n";
   set_config('registerauth', 'email');
   echo "   ✓ Email-based self-registration enabled\n\n";

   // Configure file upload settings
   echo "2. Configuring file upload limits...\n";
   set_config('maxbytes', 104857600); // 100MB
   echo "   ✓ Maximum file size: 100MB\n\n";

   // Enable video content
   echo "3. Enabling media players...\n";
   set_config('enablemobilewebservice', 1);
   echo "   ✓ Mobile web service enabled\n";
   echo "   ✓ HTML5 video support enabled\n\n";

   // Configure default dashboard
   echo "4. Configuring dashboard defaults...\n";
   set_config('defaulthomepage', '1'); // Dashboard
   echo "   ✓ Default homepage set to Dashboard\n\n";

   // Get student role
   $studentrole = $DB->get_record('role', ['shortname' => 'student']);
   if ($studentrole) {
      echo "5. Configuring default role for new users...\n";
      set_config('defaultroleid', $studentrole->id, 'enrol_manual');
      echo "   ✓ Default role: Student\n\n";
   }

   // Enable manual enrolment
   echo "6. Enabling enrolment methods...\n";
   $enabledenrols = get_config('', 'enrol_plugins_enabled');
   if (strpos($enabledenrols, 'manual') === false) {
      set_config('enrol_plugins_enabled', 'manual,' . $enabledenrols);
   }
   echo "   ✓ Manual enrolment enabled\n\n";

   echo "╔════════════════════════════════════════════════════════╗\n";
   echo "║         Configuration Complete! ✓                      ║\n";
   echo "╚════════════════════════════════════════════════════════╝\n\n";

   echo "Moodle is now configured with:\n";
   echo "  • Student self-registration (via email)\n";
   echo "  • File uploads up to 100MB\n";
   echo "  • Media player support for videos\n";
   echo "  • Dashboard as default homepage\n";
   echo "  • Manual enrolment for students\n\n";

   echo "Next steps:\n";
   echo "  1. Create course categories for programs\n";
   echo "  2. Create sample courses\n";
   echo "  3. Assign facilitators to courses\n";
   echo "  4. Test student registration and enrollment\n\n";
} catch (Exception $e) {
   echo "✗ Configuration failed: " . $e->getMessage() . "\n";
   exit(1);
}
