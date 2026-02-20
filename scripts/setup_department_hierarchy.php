#!/usr/bin/env php
<?php
/**
 * Setup Department Hierarchy
 *
 * 1. Creates "Department of Informatics" top-level category
 * 2. Moves BCS and BIT categories (and ALL their children) under it
 * 3. Revokes system-wide manager role from hod_informatics
 * 4. Assigns hod_informatics the manager role scoped to Informatics category only
 *
 * Run: php scripts/setup_department_hierarchy.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir  . '/accesslib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Setup Department of Informatics Hierarchy           ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Create Department of Informatics category ──────────────────────────────
echo "Step 1: Create Department of Informatics category...\n";

$dept_idnum = 'DEPT_INFORMATICS';
$dept_cat   = $DB->get_record('course_categories', ['idnumber' => $dept_idnum]);

if (!$dept_cat) {
   $data                    = new stdClass();
   $data->name              = 'Department of Informatics';
   $data->idnumber          = $dept_idnum;
   $data->description       = '<p>Covers Bachelor of Computer Science (BCS) and Bachelor of Information Technology (BIT) programmes.</p>';
   $data->descriptionformat = FORMAT_HTML;
   $data->parent            = 0; // Top-level
   $data->visible           = 1;
   $dept_cat = core_course_category::create($data);
   echo "  ✓ Created: Department of Informatics (id={$dept_cat->id})\n";
} else {
   echo "  • Already exists (id={$dept_cat->id})\n";
}

// ── 2. Move BCS and BIT under Informatics ────────────────────────────────────
echo "\nStep 2: Move BCS and BIT under Department of Informatics...\n";

foreach (['BCS', 'BIT'] as $prog_idnum) {
   $cat = $DB->get_record('course_categories', ['idnumber' => $prog_idnum]);
   if (!$cat) {
      echo "  WARN : Category $prog_idnum not found, skipping.\n";
      continue;
   }

   if ((int)$cat->parent === (int)$dept_cat->id) {
      echo "  • $prog_idnum already under Informatics\n";
      continue;
   }

   // Use Moodle's category API to move (handles path/depth/context updates)
   $cat_obj = core_course_category::get($cat->id);
   $cat_obj->change_parent($dept_cat->id);
   echo "  ✓ Moved $prog_idnum (id={$cat->id}) under Informatics\n";
}

// ── 3. Fix hod_informatics role assignment ────────────────────────────────────
echo "\nStep 3: Fix hod_informatics role assignment...\n";

$hod = $DB->get_record('user', ['username' => 'hod_informatics']);
if (!$hod) {
   echo "  WARN : hod_informatics user not found.\n";
} else {
   $manager_role = $DB->get_record('role', ['shortname' => 'manager']);
   if (!$manager_role) {
      echo "  ERROR: 'manager' role not found.\n";
   } else {
      // Revoke ALL existing role assignments for this user
      $existing = $DB->get_records('role_assignments', ['userid' => $hod->id, 'roleid' => $manager_role->id]);
      foreach ($existing as $ra) {
         role_unassign($manager_role->id, $hod->id, $ra->contextid);
         echo "  ✓ Revoked manager role at contextid={$ra->contextid}\n";
      }

      // Assign manager role scoped to Department of Informatics category context
      $dept_context = context_coursecat::instance($dept_cat->id);
      role_assign($manager_role->id, $hod->id, $dept_context->id);
      echo "  ✓ Assigned manager role scoped to 'Department of Informatics' category\n";
      echo "    (contextid={$dept_context->id}, contextlevel=40)\n";
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  Done!                                                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\n  hod_informatics now manages BCS + BIT but NOT the whole site.\n\n";
