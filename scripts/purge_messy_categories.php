#!/usr/bin/env php
<?php
/**
 * Purge Messy Categories
 * Deletes empty categories with IDs 27-35 which are duplicates.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║          Purging Messy Categories                     ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$messy_ids = [27, 28, 29, 30, 31, 32, 33, 34, 35];
$deleted_count = 0;

foreach ($messy_ids as $id) {
   $cat = $DB->get_record('course_categories', ['id' => $id]);
   if ($cat) {
      $count = $DB->count_records('course', ['category' => $id]);
      if ($count === 0) {
         try {
            $category = core_course_category::get($id);
            $category->delete_full(false);
            echo "   ✓ Deleted messy category: {$cat->name} (ID: $id)\n";
            $deleted_count++;
         } catch (Exception $e) {
            echo "   ✗ Error deleting ID $id: " . $e->getMessage() . "\n";
         }
      } else {
         echo "   • Skipping ID $id: Contains $count courses.\n";
      }
   } else {
      echo "   • ID $id not found.\n";
   }
}

echo "\nSummary: Deleted $deleted_count messy categories.\n";
echo "Done!\n";
