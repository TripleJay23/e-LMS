#!/usr/bin/env php
<?php
/**
 * System Cleanup & Optimization
 * Improves performance by purging caches, deleting temp files, and optimizing database
 * Safe to run regularly - designed for permanent deletion of test data
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      System Cleanup & Optimization                    ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$summary = [
   'cache_purged' => false,
   'cron_run' => false,
   'logs_deleted' => false,
   'temp_cleaned' => false,
   'db_vacuumed' => false,
   'lock_files_deleted' => 0
];

// STEP 1: Purge Moodle Caches
echo "Step 1: Purging Moodle Caches\n";
echo str_repeat("-", 60) . "\n";

$cache_script = __DIR__ . '/../moodle/admin/cli/purge_caches.php';
if (file_exists($cache_script)) {
   echo "Running: php admin/cli/purge_caches.php\n";
   passthru("php \"$cache_script\"", $return_code);
   $summary['cache_purged'] = ($return_code === 0);
   echo $summary['cache_purged'] ? "✓ Caches purged successfully\n" : "⚠ Cache purge had issues\n";
} else {
   echo "⚠ Cache purge script not found\n";
}

echo "\n";

// STEP 2: Run Cron
echo "Step 2: Running Moodle Cron\n";
echo str_repeat("-", 60) . "\n";

$cron_script = __DIR__ . '/../moodle/admin/cli/cron.php';
if (file_exists($cron_script)) {
   echo "Running: php admin/cli/cron.php\n";
   passthru("php \"$cron_script\"", $return_code);
   $summary['cron_run'] = ($return_code === 0);
   echo $summary['cron_run'] ? "✓ Cron completed successfully\n" : "⚠ Cron had issues\n";
} else {
   echo "⚠ Cron script not found\n";
}

echo "\n";

// STEP 3: Delete Log Files (PERMANENT)
echo "Step 3: Deleting Log Files\n";
echo str_repeat("-", 60) . "\n";

$log_dir = __DIR__ . '/../logs';
$logs_to_delete = ['access.log', 'error.log'];

foreach ($logs_to_delete as $log_file) {
   $log_path = "$log_dir/$log_file";
   if (file_exists($log_path)) {
      $size = filesize($log_path);
      if (unlink($log_path)) {
         echo "✓ Deleted: $log_file (" . number_format($size / 1024, 2) . " KB)\n";
         $summary['logs_deleted'] = true;
         // Create empty file
         touch($log_path);
      } else {
         echo "⚠ Failed to delete: $log_file\n";
      }
   } else {
      echo "• $log_file not found (already clean)\n";
   }
}

echo "\n";

// STEP 4: Clean Temp Directories
echo "Step 4: Cleaning Temporary Files\n";
echo str_repeat("-", 60) . "\n";

// Get moodledata path from config
$moodledata = $CFG->dataroot;

$dirs_to_clean = [
   "$moodledata/trashdir",
   "$moodledata/cache",
   "$moodledata/localcache",
   "$moodledata/sessions"
];

function delete_directory_contents($dir)
{
   if (!is_dir($dir)) {
      return ['files' => 0, 'size' => 0];
   }

   $files = 0;
   $size = 0;

   $items = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
   );

   foreach ($items as $item) {
      if ($item->isFile()) {
         $size += $item->getSize();
         unlink($item->getRealPath());
         $files++;
      } elseif ($item->isDir()) {
         rmdir($item->getRealPath());
      }
   }

   return ['files' => $files, 'size' => $size];
}

foreach ($dirs_to_clean as $dir) {
   if (is_dir($dir)) {
      echo "Cleaning: " . basename($dir) . "... ";
      $result = delete_directory_contents($dir);
      echo "✓ Deleted {$result['files']} files (" . number_format($result['size'] / 1024 / 1024, 2) . " MB)\n";
      $summary['temp_cleaned'] = true;
   } else {
      echo "• " . basename($dir) . " not found\n";
   }
}

echo "\n";

// STEP 5: Delete Excel Lock Files
echo "Step 5: Deleting Lock Files\n";
echo str_repeat("-", 60) . "\n";

$project_dir = __DIR__ . '/..';
$lock_files = glob("$project_dir/~$*.xlsx");

if (empty($lock_files)) {
   echo "• No lock files found\n";
} else {
   foreach ($lock_files as $lock_file) {
      if (unlink($lock_file)) {
         echo "✓ Deleted: " . basename($lock_file) . "\n";
         $summary['lock_files_deleted']++;
      }
   }
}

echo "\n";

// STEP 6: Vacuum Database
echo "Step 6: Optimizing Database\n";
echo str_repeat("-", 60) . "\n";

try {
   // Get database connection details
   $dbhost = $CFG->dbhost;
   $dbname = $CFG->dbname;
   $dbuser = $CFG->dbuser;
   $dbpass = $CFG->dbpass;
   $dbport = $CFG->dboptions['dbport'] ?? '5432';

   // Create PostgreSQL connection string
   $conn_string = "host=$dbhost port=$dbport dbname=$dbname user=$dbuser password=$dbpass";

   $conn = pg_connect($conn_string);

   if ($conn) {
      echo "Running VACUUM ANALYZE on database...\n";
      $result = pg_query($conn, "VACUUM ANALYZE");

      if ($result) {
         echo "✓ Database optimized successfully\n";
         $summary['db_vacuumed'] = true;
      } else {
         echo "⚠ VACUUM command failed: " . pg_last_error($conn) . "\n";
      }

      pg_close($conn);
   } else {
      echo "⚠ Could not connect to database\n";
   }
} catch (Exception $e) {
   echo "⚠ Database optimization error: " . $e->getMessage() . "\n";
}

echo "\n";

// FINAL SUMMARY
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              CLEANUP COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  • Moodle caches: " . ($summary['cache_purged'] ? "✓ Purged" : "⚠ Failed") . "\n";
echo "  • Cron tasks: " . ($summary['cron_run'] ? "✓ Completed" : "⚠ Failed") . "\n";
echo "  • Log files: " . ($summary['logs_deleted'] ? "✓ Deleted" : "• None found") . "\n";
echo "  • Temp files: " . ($summary['temp_cleaned'] ? "✓ Cleaned" : "• None found") . "\n";
echo "  • Lock files: " . ($summary['lock_files_deleted'] > 0 ? "✓ Deleted {$summary['lock_files_deleted']}" : "• None found") . "\n";
echo "  • Database: " . ($summary['db_vacuumed'] ? "✓ Optimized" : "⚠ Failed") . "\n\n";

echo "✓ System cleanup complete!\n";
echo "Your Moodle should feel faster now.\n\n";

echo "Optional next steps:\n";
echo "  • To delete archived courses: php scripts/delete_archived_courses.php\n";
echo "  • Set up scheduled cron: Run 'php moodle/admin/cli/cron.php' every 1 minute\n\n";
