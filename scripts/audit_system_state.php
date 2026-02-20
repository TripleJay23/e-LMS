#!/usr/bin/env php
<?php
/**
 * System State Audit
 * Reports: roles, category tree, custom profile fields, custom_programs table
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   System State Audit                                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Roles ──────────────────────────────────────────────────────────────────
echo "=== ROLES ===\n";
$roles = $DB->get_records('role', [], 'id ASC', 'id,shortname,name,archetype');
foreach ($roles as $r) {
   printf("  id=%-3d  %-25s  %-30s  [%s]\n", $r->id, $r->shortname, $r->name, $r->archetype);
}
echo "\n";

// ── 2. Category tree (top 3 levels) ──────────────────────────────────────────
echo "=== COURSE CATEGORIES (top levels) ===\n";
$cats = $DB->get_records('course_categories', [], 'sortorder ASC', 'id,name,idnumber,parent,depth');
foreach ($cats as $c) {
   if ($c->depth > 3) continue;
   $indent = str_repeat('  ', $c->depth - 1);
   printf("%s[id=%-3d  %-10s]  %s\n", $indent, $c->id, $c->idnumber ?: '(none)', $c->name);
}
echo "\n";

// ── 3. Custom programs table ──────────────────────────────────────────────────
echo "=== CUSTOM PROGRAMS ===\n";
$progs = $DB->get_records('custom_programs', [], 'id ASC');
foreach ($progs as $p) {
   echo "  id={$p->id}  acronym={$p->acronym}  name={$p->name}\n";
}
echo "\n";

// ── 4. Custom profile fields ──────────────────────────────────────────────────
echo "=== CUSTOM PROFILE FIELDS ===\n";
$fields = $DB->get_records('user_info_field', [], 'id ASC', 'id,shortname,name,datatype,categoryid');
foreach ($fields as $f) {
   printf("  id=%-3d  %-30s  %-25s  [%s]\n", $f->id, $f->shortname, $f->name, $f->datatype);
}
echo "\n";

// ── 5. hod_informatics user info ──────────────────────────────────────────────
echo "=== HOD_INFORMATICS USER ===\n";
$hod = $DB->get_record('user', ['username' => 'hod_informatics']);
if ($hod) {
   echo "  Found: id={$hod->id}, name={$hod->firstname} {$hod->lastname}\n";
   // Get their role assignments
   $assignments = $DB->get_records_sql(
      "SELECT ra.id, r.shortname, r.name, ctx.contextlevel, ctx.instanceid
         FROM {role_assignments} ra
         JOIN {role} r ON r.id = ra.roleid
         JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE ra.userid = ?",
      [$hod->id]
   );
   foreach ($assignments as $a) {
      echo "    Role: {$a->shortname} | contextlevel={$a->contextlevel} | instanceid={$a->instanceid}\n";
   }
} else {
   echo "  NOT FOUND\n";
}
echo "\n";

// ── 6. Sample student profile data ───────────────────────────────────────────
echo "=== SAMPLE STUDENT PROFILE DATA (bcs_y3_student1, bcs_y2_student1) ===\n";
foreach (['bcs_y3_student1', 'bcs_y2_student1', 'bit_y1_student1'] as $uname) {
   $u = $DB->get_record('user', ['username' => $uname]);
   if (!$u) {
      echo "  $uname: NOT FOUND\n";
      continue;
   }
   require_once($CFG->dirroot . '/user/profile/lib.php');
   profile_load_data($u);
   echo "  {$uname} (id={$u->id}):\n";
   $pfields = get_object_vars($u);
   foreach ($pfields as $k => $v) {
      if (strpos($k, 'profile_field_') === 0 && $v !== null) {
         echo "    {$k} = {$v}\n";
      }
   }
}
echo "\nDone.\n";
