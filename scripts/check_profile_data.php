#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "=== Raw profile data for user id=66 ===\n";
$recs = $DB->get_records('user_info_data', ['userid' => 66]);
if (empty($recs)) {
   echo "  NO DATA in user_info_data for this user!\n";
} else {
   foreach ($recs as $r) {
      $fn = $DB->get_field('user_info_field', 'shortname', ['id' => $r->fieldid]);
      echo "  field={$fn} value='{$r->data}'\n";
   }
}

echo "\n=== User record for id=66 ===\n";
$u = $DB->get_record('user', ['id' => 66]);
echo "  username : {$u->username}\n";
echo "  email    : {$u->email}\n";
echo "  confirmed: {$u->confirmed}\n";
echo "  auth     : {$u->auth}\n";
echo "  timecreated: " . date('Y-m-d H:i:s', $u->timecreated) . "\n";

echo "\n=== All users created in last 24h ===\n";
$since = time() - 86400;
$recent = $DB->get_records_sql(
   "SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.confirmed, u.auth
     FROM {user} u WHERE u.timecreated > ? AND u.deleted = 0 ORDER BY u.id DESC",
   [$since]
);
foreach ($recent as $r) {
   echo "  id={$r->id} {$r->username} ({$r->firstname} {$r->lastname}) confirmed={$r->confirmed} auth={$r->auth}\n";
   $pd = $DB->get_records('user_info_data', ['userid' => $r->id]);
   foreach ($pd as $p) {
      $fn = $DB->get_field('user_info_field', 'shortname', ['id' => $p->fieldid]);
      echo "    {$fn} = '{$p->data}'\n";
   }
}

echo "\nDone.\n";
