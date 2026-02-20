#!/usr/bin/env php
<?php
/**
 * Generate Registration Tokens (Admin Tool)
 *
 * Bulk-generates pre-allocated reg tokens for a given program, year, and batch.
 * Tokens are stored in custom_reg_tokens and can be exported as CSV.
 *
 * Usage:
 *   php scripts/generate_reg_tokens.php --program=BCS --year=1 --batch=2 --count=50
 *   php scripts/generate_reg_tokens.php --program=BIT --year=2 --batch=2 --count=30 --export=tokens.csv
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

// ── Parse CLI args ───────────────────────────────────────────────────────────
$opts = getopt('', ['program:', 'year:', 'batch:', 'count:', 'export::']);

$program     = strtoupper($opts['program']   ?? '');
$year        = (int)($opts['year']            ?? 0);
$batch       = (int)($opts['batch']           ?? 0);
$count       = (int)($opts['count']           ?? 0);
$export_file = $opts['export']               ?? null;

if (!$program || !$year || !$batch || !$count) {
   echo "Usage: php scripts/generate_reg_tokens.php --program=BCS --year=1 --batch=2 --count=50 [--export=tokens.csv]\n";
   exit(1);
}

// Derive enrollment year: assumes Year 1 started current year
// Year 2 students started (current_year - 1), Year 3 started (current_year - 2)
$current_year  = (int)date('Y');
$enroll_year   = $current_year - ($year - 1);

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║   Generate Registration Tokens                        ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "  Program      : $program\n";
echo "  Year         : $year\n";
echo "  Batch        : " . str_pad($batch, 2, '0', STR_PAD_LEFT) . "\n";
echo "  Enroll Year  : $enroll_year\n";
echo "  Count        : $count\n\n";

// ── Generate tokens ──────────────────────────────────────────────────────────
$generated = [];
$attempts  = 0;
$max_tries = $count * 10;

while (count($generated) < $count && $attempts < $max_tries) {
   $attempts++;
   $rand    = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
   $batch_s = str_pad($batch, 2, '0', STR_PAD_LEFT);
   $token   = "{$program}-{$batch_s}-{$rand}-{$enroll_year}";

   // Ensure uniqueness in DB and in current batch
   if (isset($generated[$token])) continue;
   if ($DB->record_exists('custom_reg_tokens', ['reg_number' => $token])) continue;

   // Insert into DB
   $rec              = new stdClass();
   $rec->reg_number  = $token;
   $rec->program     = $program;
   $rec->year        = $year;
   $rec->batch       = $batch;
   $rec->enroll_year = $enroll_year;
   $rec->status      = 'unused';
   $rec->userid      = null;
   $rec->timecreated = time();
   $rec->timeclaimed = null;

   $DB->insert_record('custom_reg_tokens', $rec);
   $generated[$token] = true;
   echo "  + $token\n";
}

echo "\n  Generated: " . count($generated) . " tokens\n";

// ── Export CSV ───────────────────────────────────────────────────────────────
if ($export_file) {
   $path = __DIR__ . '/' . $export_file;
   $fp   = fopen($path, 'w');
   fputcsv($fp, ['Registration Number', 'Program', 'Year', 'Batch', 'Enroll Year', 'Status']);
   foreach (array_keys($generated) as $t) {
      fputcsv($fp, [$t, $program, $year, $batch, $enroll_year, 'unused']);
   }
   fclose($fp);
   echo "\n  ✓ Exported to: $path\n";
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  Done! Distribute tokens to admitted students.        ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";
