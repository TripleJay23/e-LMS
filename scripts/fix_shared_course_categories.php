#!/usr/bin/env php
<?php
/**
 * Fix Shared Course Category Placement
 *
 * Problem:
 *   Shared standalone courses (no -BIT/-BCS suffix) were placed in BIT's
 *   category tree. A BCS Year 2 student sees them under "Semester 1" because
 *   the Moodle category is BIT > Year N > Semester 1.
 *
 * Fix:
 *   For each standalone course, keep it in the BIT category (since it IS a
 *   BIT course too), but also ensure the correct label is shown in the template.
 *   The template already uses data from custom_program_courses, so the real fix
 *   is ensuring the course summary reflects the correct year/semester per-program.
 *
 *   However, if the course only belongs to ONE program's category tree, we move
 *   it to the SHARED category (idnumber = SHARED_Y{year}_S{sem}) or fall back
 *   to BIT's correct year/semester category (which was already assigned).
 *
 *   For courses in BOTH programs, we leave them in BIT's category (they appear
 *   correct for BIT students) and rely on the summary HTML for correct labelling
 *   (the template already handles this from custom_program_courses).
 *
 * NOTE: The course card summary (image + description box) is student-agnostic —
 *       it shows one fixed Year/Semester value per course. For truly shared courses
 *       the value shown is based on the FIRST matched program entry in CPC.
 *       The most important fix is ensuring the summary is labelled with the
 *       course's actual curriculum position (which apply_full_course_template handles).
 *
 * This script moves courses to the CORRECT year/semester within their current
 * program category tree, so the Moodle category name is at least accurate to
 * the BIT curriculum (since standalone courses belong to BIT + BCS both).
 *
 * Run: php scripts/fix_shared_course_categories.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Fix Shared Course Category Placement                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Load module metadata ───────────────────────────────────────────────────
$modules = json_decode(file_get_contents(__DIR__ . '/modules_corrected.json'), true);
$module_map = [];
foreach ($modules as $m) {
   $module_map[$m['code']] = $m;
}

// ── 2. Read the BIT/BCS category trees into a lookup ─────────────────────────
// Format: BIT_Y1_S1, BCS_Y2_S2, etc.
function get_category_id($DB, $idnumber)
{
   $cat = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
   return $cat ? $cat->id : null;
}

$moved   = 0;
$correct = 0;
$failed  = 0;

// ── 3. Process all courses ────────────────────────────────────────────────────
$courses = $DB->get_records_select('course', 'id > 1');

foreach ($courses as $course) {
   // Only target standalone shared courses (no -BIT/-BCS suffix)
   if (preg_match('/-(?:BIT|BCS)$/i', $course->shortname)) {
      // These are already in the right program-specific category
      continue;
   }

   $code   = trim($course->shortname);
   $module = $module_map[$code] ?? null;
   if (!$module) {
      continue;
   }

   $year = (int) $module['year'];
   $sem  = (int) $module['semester_num'];

   // Determine correct primary program tree: BIT first (most shared courses started there)
   $programs_in = $module['programs'] ?? [$module['program']];
   $primary     = in_array('BIT', $programs_in) ? 'BIT' : $programs_in[0];

   $target_idnum = "{$primary}_Y{$year}_S{$sem}";
   $target_cat   = get_category_id($DB, $target_idnum);

   if (!$target_cat) {
      echo "  WARN : No category found for $target_idnum ($code)\n";
      $failed++;
      continue;
   }

   if ((int)$course->category === (int)$target_cat) {
      $correct++;
      continue;
   }

   // Move the course
   $update          = new stdClass();
   $update->id      = $course->id;
   $update->category = $target_cat;

   $DB->update_record('course', $update);

   // Fix course context path (required after category change)
   $context = context_course::instance($course->id);
   $context->update_moved($context);

   echo "  MOVED : {$code} → {$target_idnum}\n";
   $moved++;
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  Done!                                                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\n  • Moved    : $moved\n";
echo "  • Correct  : $correct\n";
echo "  • Failed   : $failed\n\n";
