#!/usr/bin/env php
<?php
/**
 * Rebuild Shared BCS (and BIT) custom_program_courses Entries
 *
 * Problem:
 *   Shared modules that exist as standalone courses (no -BIT/-BCS suffix)
 *   were never registered in custom_program_courses for the BCS program.
 *   This causes the enrolment observer to have no year/semester info for them,
 *   and the course card template shows wrong semester.
 *
 * Fix:
 *   For every course whose shortname matches a code in modules_corrected.json,
 *   ensure there is a row in custom_program_courses for EACH program listed in
 *   the module's "programs" array, with the correct year and semester.
 *
 * Run: php scripts/rebuild_shared_bcs_cpc.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Rebuild custom_program_courses (Shared Courses)     ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ── 1. Load module metadata ───────────────────────────────────────────────────
$json_path = __DIR__ . '/modules_corrected.json';
$modules   = json_decode(file_get_contents($json_path), true);

// Build lookup: code => module
$module_map = [];
foreach ($modules as $m) {
   $module_map[$m['code']] = $m;
}

// ── 2. Load all programs ──────────────────────────────────────────────────────
$programs_db = $DB->get_records('custom_programs');
$program_by_acronym = [];
foreach ($programs_db as $p) {
   $program_by_acronym[$p->acronym] = $p;
}

echo "Programs available: " . implode(', ', array_keys($program_by_acronym)) . "\n\n";

// ── 3. Process all courses ────────────────────────────────────────────────────
$courses = $DB->get_records_select('course', 'id > 1');

$inserted = 0;
$skipped  = 0;
$no_match = 0;

foreach ($courses as $course) {
   // Strip -BIT / -BCS suffix to get the raw module code
   $code = preg_replace('/-(?:BIT|BCS)$/i', '', trim($course->shortname));

   $module = $module_map[$code] ?? null;
   if (!$module) {
      // Not in our JSON — skip
      $no_match++;
      continue;
   }

   $year        = (int) $module['year'];
   $semester    = (int) $module['semester_num'];
   $prog_codes  = $module['programs'] ?? [$module['program']];

   foreach ($prog_codes as $prog_acronym) {
      $program = $program_by_acronym[$prog_acronym] ?? null;
      if (!$program) {
         continue;
      }

      // Check if an entry already exists
      $existing = $DB->get_record('custom_program_courses', [
         'programid' => $program->id,
         'courseid'  => $course->id,
      ]);

      if ($existing) {
         // Correct it if wrong
         if ((int)$existing->year !== $year || (int)$existing->semester !== $semester) {
            $existing->year     = $year;
            $existing->semester = $semester;
            $DB->update_record('custom_program_courses', $existing);
            echo "  UPDATED [{$prog_acronym}] {$course->shortname} → Y{$year} S{$semester}\n";
            $inserted++;
         } else {
            $skipped++;
         }
         continue;
      }

      // Insert new entry
      $link              = new stdClass();
      $link->programid   = $program->id;
      $link->courseid    = $course->id;
      $link->year        = $year;
      $link->semester    = $semester;
      $link->timecreated = time();

      $DB->insert_record('custom_program_courses', $link);
      echo "  INSERTED [{$prog_acronym}] {$course->shortname} → Y{$year} S{$semester}\n";
      $inserted++;
   }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  Done!                                                ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\n  • Inserted/Updated : $inserted\n";
echo "  • Already correct  : $skipped\n";
echo "  • No JSON match    : $no_match\n\n";
