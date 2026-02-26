#!/usr/bin/env php
<?php
/**
 * Apply Full Course Template
 * - Uploads a unique image from the images/ directory per course
 * - Generates the full styled summary HTML (image + description box)
 *   matching the "Computer System Architecture" card template
 * - Looks up Academic Year, Semester, Credit, and Course Type from modules_corrected.json
 *
 * Run: php scripts/apply_full_course_template.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Applying Full Course Card Template               ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ─── 1. Load Module Metadata ──────────────────────────────────────────────────
$json_path = __DIR__ . '/modules_corrected.json';
if (!file_exists($json_path)) {
    die("Error: modules_corrected.json not found at $json_path\n");
}
$modules_raw = json_decode(file_get_contents($json_path), true);

// Build a lookup map: course_code => module data
// Codes look like "ITU 07101". Course shortnames are "ITU 07104-BCS", "ITU 07101-BIT", or just "ITU 07101".
// We'll strip the -BIT / -BCS suffix before matching.
$module_map = [];
foreach ($modules_raw as $m) {
    $module_map[$m['code']] = $m;
}
echo "Loaded " . count($module_map) . " modules from modules_corrected.json\n\n";

// ─── 2. Collect Images ────────────────────────────────────────────────────────
$image_dir = __DIR__ . '/../images';
$images = glob($image_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

if (empty($images)) {
    die("Error: No images found in $image_dir\n");
}

echo "Found " . count($images) . " images to cycle through.\n\n";

// ─── 3. Process All Courses ───────────────────────────────────────────────────
$courses = $DB->get_records_select('course', 'id > 1');
$fs = get_file_storage();

$img_index = 0;
$processed = 0;
$skipped   = 0;
$errors    = 0;

// Semester number -> Roman numeral
$sem_roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI'];

foreach ($courses as $course) {
    echo "Processing: {$course->shortname}... ";

    // ── 3a. Lookup module metadata ────────────────────────────────────────────
    // Shortname format: "ITU 07101-BIT" or "ITU 07101" or "ITU07101-BCS"
    // Normalise: strip trailing -BIT/-BCS to get the code
    $raw_code = preg_replace('/-(?:BIT|BCS)$/i', '', $course->shortname);
    $raw_code = trim($raw_code);

    $module = $module_map[$raw_code] ?? null;

    // Try a case-insensitive fallback
    if (!$module) {
        foreach ($module_map as $code => $m) {
            if (strcasecmp($code, $raw_code) === 0) {
                $module = $m;
                break;
            }
        }
    }

    // Determine year / semester from module or existing category hierarchy
    if ($module) {
        $year_num    = (int)$module['year'];
        $sem_num     = (int)$module['semester_num'];
        $credits     = $module['credits'];
        $course_type = $module['type'];
    } else {
        // Fallback: derive from category name chain (e.g., "BIT > Year 1 > Semester 1")
        $year_num    = 1;
        $sem_num     = 1;
        $credits     = 'N/A';
        $course_type = 'N/A';

        $cat = $DB->get_record('course_categories', ['id' => $course->category]);
        if ($cat) {
            if (preg_match('/Year\s*(\d)/i', $cat->name, $m_year)) {
                $year_num = (int)$m_year[1];
            } elseif (preg_match('/Semester\s*(\d)/i', $cat->name, $m_sem)) {
                $sem_num = (int)$m_sem[1];
            }
            // Also check parent
            if ($cat->parent) {
                $parent_cat = $DB->get_record('course_categories', ['id' => $cat->parent]);
                if ($parent_cat) {
                    if (preg_match('/Year\s*(\d)/i', $parent_cat->name, $m_year)) {
                        $year_num = (int)$m_year[1];
                    }
                }
            }
        }

        // Try to extract from existing summary text
        if (preg_match('/Credit Hours?:\s*(\S+)/i', $course->summary, $m_cr)) {
            $credits = $m_cr[1];
        }
        if (preg_match('/Type:\s*([^<\n]+)/i', $course->summary, $m_ty)) {
            $course_type = trim($m_ty[1]);
        }

        echo "[fallback] ";
    }

    $year_label = "Year $year_num";
    $sem_label  = "Semester " . ($sem_roman[$sem_num] ?? $sem_num);

    // ── 3b. Direct Image URL (Nginx) ──────────────────────────────────────────
    $image_path = $images[$img_index % count($images)];
    $image_name = basename($image_path);
    $img_index++;

    // Ensure the image exists in the public directory
    $public_image_dir = __DIR__ . '/../moodle/public/local/courseimages';
    if (!is_dir($public_image_dir)) {
        mkdir($public_image_dir, 0777, true);
    }
    $public_image_path = $public_image_dir . '/' . $image_name;
    if (!file_exists($public_image_path)) {
        copy($image_path, $public_image_path);
    }

    $context     = context_course::instance($course->id);
    $encoded_name = rawurlencode($image_name);

    // Build the direct URL (no pluginfile.php involvement)
    $direct_url = '/local/courseimages/' . $encoded_name;

    // Optional: Cleanup old summary files from internal storage to free up space
    $existing_files = $fs->get_area_files($context->id, 'course', 'summary', 0);
    foreach ($existing_files as $f) {
        if ($f->get_filename() !== '.') {
            $f->delete();
        }
    }

    // ── 3c. Build the full summary HTML ───────────────────────────────────────
    $img_html = '<p style="text-align: center;"><img class="img-fluid" role="presentation"'
        . ' src="' . $direct_url . '"'
        . ' alt="" width="300" height="168" loading="lazy"></p>';

    $box_html = '<div class="course-description-box"'
        . ' style="background: #ffffff; border-left: 5px solid #0f6cbf;'
        . ' padding: 15px; margin-bottom: 20px; border-radius: 4px;'
        . ' font-size: 0.95rem; color: #222222; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">'
        . '<strong>Academic Year: </strong>' . htmlspecialchars($year_label)
        . ' <br><strong>Semester: </strong>' . htmlspecialchars($sem_label)
        . ' <br><strong>Credit: </strong>' . htmlspecialchars($credits)
        . ' <br><strong>Course Type:</strong> ' . htmlspecialchars($course_type)
        . '</div>';

    $new_summary = $img_html . "\n" . $box_html;

    // ── 3d. Save to DB ────────────────────────────────────────────────────────
    $course->summary       = $new_summary;
    $course->summaryformat = FORMAT_HTML;

    try {
        $DB->update_record('course', $course);
        echo "✓ {$year_label}, {$sem_label}, {$credits} credits, {$course_type} — image: {$image_name}\n";
        $processed++;
    } catch (Exception $e) {
        echo "❌ DB error: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║         Done!                                         ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\n  • Processed  : $processed\n";
echo "  • Skipped    : $skipped\n";
echo "  • Errors     : $errors\n\n";
