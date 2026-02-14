#!/usr/bin/env php
<?php
/**
 * Audit Course-Program Links
 * Identifies inconsistencies between course categories and mdl_custom_program_courses table
 * 
 * This script:
 * - Analyzes all active courses and their category placement
 * - Compares with mdl_custom_program_courses records
 * - Identifies mismatches, orphaned courses, and ghost records
 * - Generates a detailed report
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║      Course-Program Link Audit                        ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Helper function to parse program info from category idnumber
function parse_category_program($idnumber) {
    if (empty($idnumber)) {
        return null;
    }
    
    // Match patterns like BIT_Y1_S1, BCS_Y2_S2
    if (preg_match('/^(BIT|BCS)_Y(\d)_S(\d)$/', $idnumber, $matches)) {
        return [
            'program' => $matches[1],
            'year' => (int)$matches[2],
            'semester' => (int)$matches[3]
        ];
    }
    
    // Match program-level categories: BIT, BCS
    if (in_array($idnumber, ['BIT', 'BCS'])) {
        return [
            'program' => $idnumber,
            'year' => null,
            'semester' => null
        ];
    }
    
    return null;
}

// Get full category path for a course
function get_category_path($category_id) {
    global $DB;
    
    $path = [];
    $current = $category_id;
    
    while ($current > 0) {
        $cat = $DB->get_record('course_categories', ['id' => $current]);
        if (!$cat) break;
        
        array_unshift($path, [
            'name' => $cat->name,
            'idnumber' => $cat->idnumber
        ]);
        
        $current = $cat->parent;
    }
    
    return $path;
}

// Initialize counters
$stats = [
    'total_courses' => 0,
    'courses_with_category_program' => 0,
    'courses_with_db_link' => 0,
    'courses_matched' => 0,
    'courses_mismatched' => 0,
    'orphaned_courses' => 0,  // In category but not in DB
    'ghost_records' => 0,      // In DB but course doesn't exist/archived
    'deprecated_courses' => 0,
    'archive_courses' => 0
];

$issues = [
    'orphaned' => [],
    'mismatched' => [],
    'ghost' => [],
    'deprecated' => [],
    'no_program' => []
];

echo "Step 1: Analyzing Active Courses\n";
echo str_repeat("-", 60) . "\n";

// Get all active courses (excluding site course)
$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname');
$stats['total_courses'] = count($courses);

echo "Total courses found: {$stats['total_courses']}\n\n";

// Get program IDs for reference
$programs = $DB->get_records('custom_programs', null, '', 'acronym, id, name');

echo "Step 2: Checking Each Course\n";
echo str_repeat("-", 60) . "\n";

foreach ($courses as $course) {
    // Check if deprecated
    if (strpos($course->shortname, '-OLD') !== false || 
        strpos($course->fullname, '(DEPRECATED)') !== false) {
        $stats['deprecated_courses']++;
        $issues['deprecated'][] = [
            'id' => $course->id,
            'shortname' => $course->shortname,
            'fullname' => $course->fullname
        ];
        continue;
    }
    
    // Get category path
    $cat_path = get_category_path($course->category);
    
    // Check if in Archive
    $in_archive = false;
    foreach ($cat_path as $cat) {
        if ($cat['idnumber'] === 'ARCHIVE' || $cat['name'] === 'Archive') {
            $in_archive = true;
            break;
        }
    }
    
    if ($in_archive) {
        $stats['archive_courses']++;
        continue;
    }
    
    // Determine program from category
    $category_program = null;
    $category_year = null;
    $category_semester = null;
    
    foreach ($cat_path as $cat) {
        $parsed = parse_category_program($cat['idnumber']);
        if ($parsed) {
            $category_program = $parsed['program'];
            $category_year = $parsed['year'];
            $category_semester = $parsed['semester'];
            break;
        }
    }
    
    if ($category_program) {
        $stats['courses_with_category_program']++;
    }
    
    // Check database link
    $db_links = $DB->get_records('custom_program_courses', ['courseid' => $course->id]);
    
    if (!empty($db_links)) {
        $stats['courses_with_db_link']++;
        
        // Check if program matches
        $db_link = reset($db_links); // Get first link
        
        if (isset($programs[$category_program])) {
            $expected_program_id = $programs[$category_program]->id;
            
            if ($db_link->programid == $expected_program_id) {
                // Check year/semester match
                if ($db_link->year == $category_year && 
                    $db_link->semester == $category_semester) {
                    $stats['courses_matched']++;
                } else {
                    $stats['courses_mismatched']++;
                    $issues['mismatched'][] = [
                        'id' => $course->id,
                        'shortname' => $course->shortname,
                        'fullname' => $course->fullname,
                        'category_program' => $category_program,
                        'category_year' => $category_year,
                        'category_semester' => $category_semester,
                        'db_program_id' => $db_link->programid,
                        'db_year' => $db_link->year,
                        'db_semester' => $db_link->semester,
                        'issue' => 'Year/Semester mismatch'
                    ];
                }
            } else {
                $stats['courses_mismatched']++;
                
                // Get actual program name from DB
                $db_program = $DB->get_record('custom_programs', ['id' => $db_link->programid]);
                
                $issues['mismatched'][] = [
                    'id' => $course->id,
                    'shortname' => $course->shortname,
                    'fullname' => $course->fullname,
                    'category_program' => $category_program,
                    'category_year' => $category_year,
                    'category_semester' => $category_semester,
                    'db_program' => $db_program ? $db_program->acronym : 'UNKNOWN',
                    'db_year' => $db_link->year,
                    'db_semester' => $db_link->semester,
                    'issue' => 'Program mismatch'
                ];
            }
        } else {
            // Category doesn't indicate a known program
            $issues['no_program'][] = [
                'id' => $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'category_path' => implode(' → ', array_column($cat_path, 'name'))
            ];
        }
    } else {
        // No database link
        if ($category_program) {
            $stats['orphaned_courses']++;
            $issues['orphaned'][] = [
                'id' => $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'category_program' => $category_program,
                'category_year' => $category_year,
                'category_semester' => $category_semester
            ];
        } else {
            $issues['no_program'][] = [
                'id' => $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'category_path' => implode(' → ', array_column($cat_path, 'name'))
            ];
        }
    }
}

echo "Analyzed {$stats['total_courses']} courses\n\n";

// Check for ghost records (DB links to non-existent courses)
echo "Step 3: Checking for Ghost Records\n";
echo str_repeat("-", 60) . "\n";

$all_db_links = $DB->get_records('custom_program_courses');
foreach ($all_db_links as $link) {
    $course = $DB->get_record('course', ['id' => $link->courseid]);
    if (!$course || $course->id == 1) {
        $stats['ghost_records']++;
        
        $program = $DB->get_record('custom_programs', ['id' => $link->programid]);
        $issues['ghost'][] = [
            'link_id' => $link->id,
            'courseid' => $link->courseid,
            'program' => $program ? $program->acronym : "ID: {$link->programid}",
            'year' => $link->year,
            'semester' => $link->semester
        ];
    }
}

echo "Found {$stats['ghost_records']} ghost records\n\n";

// Generate Report
echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              AUDIT REPORT SUMMARY                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Overall Statistics:\n";
echo "  • Total courses: {$stats['total_courses']}\n";
echo "  • Courses in Archive: {$stats['archive_courses']}\n";
echo "  • Deprecated courses: {$stats['deprecated_courses']}\n";
echo "  • Courses with category program: {$stats['courses_with_category_program']}\n";
echo "  • Courses with DB link: {$stats['courses_with_db_link']}\n\n";

echo "Health Status:\n";
echo "  ✓ Correctly linked: {$stats['courses_matched']}\n";
echo "  ⚠ Mismatched: {$stats['courses_mismatched']}\n";
echo "  ⚠ Orphaned (category but no DB): {$stats['orphaned_courses']}\n";
echo "  ⚠ Ghost records (DB but no course): {$stats['ghost_records']}\n";
echo "  • No program identified: " . count($issues['no_program']) . "\n\n";

// Detailed issues
if ($stats['orphaned_courses'] > 0) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "ORPHANED COURSES (In categories but not in database)\n";
    echo str_repeat("=", 60) . "\n";
    foreach ($issues['orphaned'] as $item) {
        echo "• [{$item['id']}] {$item['shortname']}\n";
        echo "  Name: {$item['fullname']}\n";
        echo "  Category indicates: {$item['category_program']} Y{$item['category_year']} S{$item['category_semester']}\n";
        echo "  Action needed: Add to mdl_custom_program_courses\n\n";
    }
}

if ($stats['courses_mismatched'] > 0) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "MISMATCHED COURSES (Category ≠ Database)\n";
    echo str_repeat("=", 60) . "\n";
    foreach ($issues['mismatched'] as $item) {
        echo "• [{$item['id']}] {$item['shortname']}\n";
        echo "  Name: {$item['fullname']}\n";
        echo "  Category: {$item['category_program']} Y{$item['category_year']} S{$item['category_semester']}\n";
        echo "  Database: {$item['db_program']} Y{$item['db_year']} S{$item['db_semester']}\n";
        echo "  Issue: {$item['issue']}\n";
        echo "  Action needed: Update mdl_custom_program_courses\n\n";
    }
}

if ($stats['ghost_records'] > 0) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "GHOST RECORDS (Database links to non-existent courses)\n";
    echo str_repeat("=", 60) . "\n";
    foreach ($issues['ghost'] as $item) {
        echo "• Link ID: {$item['link_id']}\n";
        echo "  Course ID: {$item['courseid']} (DOES NOT EXIST)\n";
        echo "  Program: {$item['program']} Y{$item['year']} S{$item['semester']}\n";
        echo "  Action needed: Delete from mdl_custom_program_courses\n\n";
    }
}

if ($stats['deprecated_courses'] > 0) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "DEPRECATED COURSES\n";
    echo str_repeat("=", 60) . "\n";
    foreach ($issues['deprecated'] as $item) {
        echo "• [{$item['id']}] {$item['shortname']}\n";
        echo "  Name: {$item['fullname']}\n";
        echo "  Action needed: Archive or delete\n\n";
    }
}

if (count($issues['no_program']) > 0) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "COURSES WITHOUT PROGRAM IDENTIFICATION\n";
    echo str_repeat("=", 60) . "\n";
    foreach ($issues['no_program'] as $item) {
        echo "• [{$item['id']}] {$item['shortname']}\n";
        echo "  Path: {$item['category_path']}\n";
        echo "  Action needed: Move to appropriate category or investigate\n\n";
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              RECOMMENDATIONS                           ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$total_issues = $stats['orphaned_courses'] + $stats['courses_mismatched'] + 
                $stats['ghost_records'] + $stats['deprecated_courses'];

if ($total_issues == 0) {
    echo "✓ No issues found! Your system is in good health.\n\n";
} else {
    echo "Found {$total_issues} issues that need attention:\n\n";
    
    if ($stats['deprecated_courses'] > 0) {
        echo "1. Archive deprecated courses:\n";
        echo "   php scripts/archive_deprecated_courses.php\n\n";
    }
    
    if ($stats['ghost_records'] > 0) {
        echo "2. Clean up ghost records:\n";
        echo "   php scripts/cleanup_program_courses_table.php\n\n";
    }
    
    if ($stats['orphaned_courses'] > 0 || $stats['courses_mismatched'] > 0) {
        echo "3. Rebuild program-course links:\n";
        echo "   php scripts/rebuild_program_course_links.php\n\n";
    }
    
    echo "4. Re-run this audit to verify fixes:\n";
    echo "   php scripts/audit_course_program_links.php\n\n";
}

echo "Audit complete!\n";
