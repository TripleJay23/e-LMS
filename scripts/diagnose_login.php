<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

$username = 'admin';
$user = $DB->get_record('user', ['username' => $username]);

if (!$user) {
   die("User '$username' not found.\n");
}

echo "User Status for '$username':\n";
echo "--------------------------------------------------\n";
echo "ID: " . $user->id . "\n";
echo "Confirmed: " . $user->confirmed . "\n";
echo "Policy Agreed: " . $user->policyagreed . "\n";
echo "Deleted: " . $user->deleted . "\n";
echo "Suspended: " . $user->suspended . "\n";
echo "Auth: " . $user->auth . "\n";
echo "Email: " . $user->email . "\n";
echo "Firstname: " . $user->firstname . "\n";
echo "Lastname: " . $user->lastname . "\n";

// Check user preferences for any 'force' flags
$prefs = $DB->get_records('user_preferences', ['userid' => $user->id]);
echo "\nUser Preferences:\n";
foreach ($prefs as $pref) {
   if (strpos($pref->name, 'force') !== false || strpos($pref->name, 'policy') !== false || strpos($pref->name, 'redirect') !== false) {
      echo " - " . $pref->name . ": " . $pref->value . "\n";
   }
}

// Check System Config
echo "\nSystem Configuration:\n";
echo "--------------------------------------------------\n";
$configs = ['sitepolicy', 'sitepolicyguest', 'forcepasswordchange', 'forcelogin', 'forceloginforprofiles', 'allowuserthemes'];
foreach ($configs as $cfg) {
   $val = get_config('moodle', $cfg);
   echo "$cfg: " . ($val === false ? 'Not Set' : $val) . "\n";
}

// Check installed plugins to see if 'profileenrol' is actually installed and enabled
$plugin_manager = \core\plugin_manager::instance();
$plugins = $plugin_manager->get_plugins();
echo "\nLocal Plugins Status:\n";
if (isset($plugins['local'])) {
   foreach ($plugins['local'] as $name => $info) {
      echo " - $name: " . $info->versiondb . " (" . ($info->is_enabled() ? 'Enabled' : 'Disabled') . ")\n";
   }
}

// Check Required Custom Fields
echo "\nRequired Custom Profile Fields:\n";
echo "--------------------------------------------------\n";
$required_fields = $DB->get_records('user_info_field', ['required' => 1]);

if (empty($required_fields)) {
   echo "No required custom fields found.\n";
} else {
   foreach ($required_fields as $field) {
      $has_data = $DB->record_exists('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id]);
      $data_record = $has_data ? $DB->get_record('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id]) : null;
      $data_val = $data_record ? $data_record->data : '(Empty)';

      echo " - Field: " . $field->shortname . " (ID: " . $field->id . ")\n";
      echo "   Value: " . $data_val . "\n";
      echo "   Status: " . (!empty($data_val) ? "OK" : "MISSING (Blocking Login)") . "\n";
   }
}
