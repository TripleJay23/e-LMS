<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

// --- CONFIGURATION ---
$imagesDir = 'c:\Users\jtrip\Desktop\Group 07\e-LMS\images';

// Scan for images dynamically
$imageFiles = [];
$patterns = ['*.jpg', '*.jpeg', '*.png', '*.gif'];
$excludedImages = ['210051270_10847226.png'];

foreach ($patterns as $pattern) {
    foreach (glob($imagesDir . DIRECTORY_SEPARATOR . $pattern) as $filename) {
        $basename = basename($filename);
        if (!in_array($basename, $excludedImages)) {
            $imageFiles[] = $basename;
        }
    }
}

if (empty($imageFiles)) {
    die("Error: No images found in " . $imagesDir . "\n");
}

echo "Found " . count($imageFiles) . " images to cycle through.\n";

// --- MAIN LOOP ---

$courses = $DB->get_records('course', [], 'sortorder ASC');
$fs = get_file_storage();
$imageIndex = 0;
$updatedCount = 0;

echo "\nStarting Course Description Update...\n";
echo str_repeat('-', 60) . "\n";

foreach ($courses as $course) {
    if ($course->id == 1) continue; // Skip site course

    // Derive Metadata from Category Structure
    $meta = [
        'program' => 'Informatics',
        'year' => 'N/A',
        'semester' => 'N/A',
        'code' => $course->shortname
    ];

    $category = $DB->get_record('course_categories', ['id' => $course->category]);
    if ($category) {
        // Traverse up
        $path = $category->path; // e.g., /1/2/3
        $catIds = explode('/', trim($path, '/'));

        foreach ($catIds as $catId) {
            $cat = $DB->get_record('course_categories', ['id' => $catId]);
            if (!$cat) continue;

            $name = $cat->name;
            if (stripos($name, 'BIT') !== false) $meta['program'] = 'BIT (Bachelor in Information Technology)';
            if (stripos($name, 'BCS') !== false) $meta['program'] = 'BCS (Bachelor in Computer Science)';
            if (stripos($name, 'Shared') !== false) $meta['program'] = 'Shared Module (BIT & BCS)';

            if (stripos($name, 'Year 1') !== false) $meta['year'] = 'Year 1';
            if (stripos($name, 'Year 2') !== false) $meta['year'] = 'Year 2';
            if (stripos($name, 'Year 3') !== false) $meta['year'] = 'Year 3';

            if (stripos($name, 'Semester 1') !== false || stripos($name, 'Semester I') !== false) $meta['semester'] = 'Semester I';
            if (stripos($name, 'Semester 2') !== false || stripos($name, 'Semester II') !== false) $meta['semester'] = 'Semester II';
        }
    }

    // Select Image (Round Robin)
    $sourceImageName = $imageFiles[$imageIndex % count($imageFiles)];
    $sourceImagePath = $imagesDir . DIRECTORY_SEPARATOR . $sourceImageName;
    $imageIndex++;

    // Context
    $context = context_course::instance($course->id);

    // 1. Delete existing summary files to keep it clean (removed to prevent deleting existing valid images if run multiple times, 
    // actually, let's keep it to ensure we replace the old one if we are re-running for update)
    // $fs->delete_area_files($context->id, 'course', 'summary'); 

    // 2. Upload New Image
    $fileRecord = [
        'contextid' => $context->id,
        'component' => 'course',
        'filearea'  => 'summary',
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => $sourceImageName,
    ];

    // Check if exact file already exists
    $existingFile = $fs->get_file($context->id, 'course', 'summary', 0, '/', $sourceImageName);
    if ($existingFile) {
        $existingFile->delete();
    }

    $fs->create_file_from_pathname($fileRecord, $sourceImagePath);

    // 3. Generate HTML Template
    // The @@PLUGINFILE@@ token is replaced by Moodle at runtime
    $imageUrl = "@@PLUGINFILE@@/" . $sourceImageName;

    $descriptionHtml = '
<div class="course-description-container" style="font-family: sans-serif; color: #333;">
    <div class="course-image-wrapper" style="text-align: center; margin-bottom: 20px;">
        <img src="' . $imageUrl . '" alt="' . htmlspecialchars($course->fullname) . '" class="img-fluid" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    </div>

    <div class="course-metadata" style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 5px solid #007bff; margin-bottom: 20px;">
        <div style="margin-bottom: 5px;"><strong>Program:</strong> ' . htmlspecialchars($meta['program']) . '</div>
        <div style="margin-bottom: 5px;"><strong>Year:</strong> ' . htmlspecialchars($meta['year']) . '</div>
        <div style="margin-bottom: 5px;"><strong>Semester:</strong> ' . htmlspecialchars($meta['semester']) . '</div>
        <div><strong>Course Code:</strong> ' . htmlspecialchars($meta['code']) . '</div>
    </div>

    <div class="course-summary-text">
        <p>' . htmlspecialchars($course->fullname) . ' provides comprehensive coverage of key concepts. Students will engage with theoretical foundations and practical applications designed to build competency in this subject area.</p>
    </div>
</div>';

    // 4. Update Course Record
    $course->summary = $descriptionHtml;
    $course->summaryformat = FORMAT_HTML;
    $DB->update_record('course', $course);

    echo "Updated: [{$course->shortname}] {$course->fullname} | {$meta['program']} - {$meta['year']}\n";
    $updatedCount++;
}

echo str_repeat('-', 60) . "\n";
echo "Completed! Updated $updatedCount courses.\n";
echo "IMPORTANT: Run 'php moodle/admin/cli/purge_caches.php' to see changes.\n";
