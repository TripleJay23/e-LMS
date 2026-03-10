#!/usr/bin/env php
<?php
/**
 * Normalize shared-course mappings and student enrolments.
 *
 * Fixes legacy state where both <CODE> and <CODE>-SHARED are linked to BIT/BCS,
 * causing duplicate enrolments for shared modules.
 *
 * What this script does for each shared module:
 * - Ensures canonical link to <CODE>-SHARED exists for BIT and BCS.
 * - Removes legacy links to <CODE> for BIT and BCS.
 * - Migrates enrolled students from legacy <CODE> course to canonical <CODE>-SHARED.
 * - Unenrols migrated students from legacy course instances.
 * - Hides legacy course copy to avoid accidental reuse.
 *
 * Run:
 *   php scripts/normalize_shared_course_links.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/module_catalog.php');

echo "Normalize Shared Course Links\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $modules = load_module_catalog(__DIR__ . '/modules_corrected.json');
} catch (RuntimeException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

$groups = split_modules_by_program($modules);
$sharedmodules = $groups['shared'];
if (empty($sharedmodules)) {
    echo "No shared modules found. Nothing to do.\n";
    exit(0);
}

$programs = [];
foreach (['BIT', 'BCS'] as $acronym) {
    $program = $DB->get_record('custom_programs', ['acronym' => $acronym], '*', IGNORE_MISSING);
    if (!$program) {
        echo "ERROR: Program {$acronym} not found in custom_programs.\n";
        exit(1);
    }
    $programs[$acronym] = $program;
}

$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', IGNORE_MISSING);
$studentroleid = $studentrole ? (int)$studentrole->id : 5;
$manualenrol = enrol_get_plugin('manual');
if (!$manualenrol) {
    echo "ERROR: manual enrol plugin is unavailable.\n";
    exit(1);
}

$prevnoemailever = $CFG->noemailever ?? false;
$CFG->noemailever = true;

$stats = [
    'modules' => 0,
    'missingcanonical' => 0,
    'linksadded' => 0,
    'legacylinksremoved' => 0,
    'studentsmigrated' => 0,
    'studentsunenrolledlegacy' => 0,
    'legacyhidden' => 0,
];

foreach ($sharedmodules as $module) {
    $code = trim((string)($module['code'] ?? ''));
    if ($code === '') {
        continue;
    }
    $stats['modules']++;

    [$year, $semester] = resolve_year_semester($module);
    $canonicalshortname = $code . '-SHARED';
    $legacyshortname = $code;

    $canonicalcourse = $DB->get_record('course', ['shortname' => $canonicalshortname], '*', IGNORE_MISSING);
    if (!$canonicalcourse) {
        $stats['missingcanonical']++;
        echo "WARN: Canonical course missing: {$canonicalshortname}\n";
        continue;
    }

    echo "Module {$code}\n";

    foreach ($programs as $acronym => $program) {
        if (!$DB->record_exists('custom_program_courses', [
            'programid' => $program->id,
            'courseid' => $canonicalcourse->id,
        ])) {
            $DB->insert_record('custom_program_courses', (object)[
                'programid' => $program->id,
                'courseid' => $canonicalcourse->id,
                'year' => $year,
                'semester' => $semester,
                'timecreated' => time(),
            ]);
            $stats['linksadded']++;
            echo "  + linked canonical to {$acronym}\n";
        }
    }

    $legacycourse = $DB->get_record('course', ['shortname' => $legacyshortname], '*', IGNORE_MISSING);
    if (!$legacycourse || (int)$legacycourse->id === (int)$canonicalcourse->id) {
        continue;
    }

    foreach ($programs as $acronym => $program) {
        $linkcount = $DB->count_records('custom_program_courses', [
            'programid' => $program->id,
            'courseid' => $legacycourse->id,
        ]);
        if ($linkcount > 0) {
            $DB->delete_records('custom_program_courses', [
                'programid' => $program->id,
                'courseid' => $legacycourse->id,
            ]);
            $stats['legacylinksremoved'] += $linkcount;
            echo "  - removed {$linkcount} legacy link(s) for {$acronym}\n";
        }
    }

    $legacycontext = context_course::instance($legacycourse->id);
    $canonicalcontext = context_course::instance($canonicalcourse->id);
    $canonicalinstance = $DB->get_record('enrol', ['courseid' => $canonicalcourse->id, 'enrol' => 'manual'], '*', IGNORE_MISSING);
    if (!$canonicalinstance) {
        $instanceid = $manualenrol->add_instance($canonicalcourse);
        $canonicalinstance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    $legacyinstances = $DB->get_records('enrol', ['courseid' => $legacycourse->id]);
    foreach ($legacyinstances as $legacyinstance) {
        $plugin = enrol_get_plugin($legacyinstance->enrol);
        if (!$plugin) {
            continue;
        }

        $userenrolments = $DB->get_records('user_enrolments', ['enrolid' => $legacyinstance->id]);
        foreach ($userenrolments as $ue) {
            $userid = (int)$ue->userid;
            $user = $DB->get_record('user', ['id' => $userid], 'id,deleted', IGNORE_MISSING);
            if (!$user || !empty($user->deleted)) {
                continue;
            }

            $isstudentinlegacy = $DB->record_exists('role_assignments', [
                'userid' => $userid,
                'contextid' => $legacycontext->id,
                'roleid' => $studentroleid,
            ]);
            if (!$isstudentinlegacy) {
                continue;
            }

            if (!is_enrolled($canonicalcontext, $userid)) {
                $manualenrol->enrol_user($canonicalinstance, $userid, $studentroleid);
                $stats['studentsmigrated']++;
            }

            try {
                $plugin->unenrol_user($legacyinstance, $userid);
                $stats['studentsunenrolledlegacy']++;
            } catch (\Throwable $e) {
                echo "  ! failed unenrol uid {$userid} from legacy {$legacyshortname}: {$e->getMessage()}\n";
            }
        }
    }

    if ((int)$legacycourse->visible !== 0) {
        $legacycourse->visible = 0;
        $DB->update_record('course', $legacycourse);
        $stats['legacyhidden']++;
        echo "  * hid legacy course {$legacyshortname}\n";
    }
}

$CFG->noemailever = $prevnoemailever;

echo "\nDone\n";
echo "  Shared modules scanned: {$stats['modules']}\n";
echo "  Missing canonical shared courses: {$stats['missingcanonical']}\n";
echo "  Canonical links added: {$stats['linksadded']}\n";
echo "  Legacy links removed: {$stats['legacylinksremoved']}\n";
echo "  Students migrated to canonical: {$stats['studentsmigrated']}\n";
echo "  Legacy student enrolments removed: {$stats['studentsunenrolledlegacy']}\n";
echo "  Legacy courses hidden: {$stats['legacyhidden']}\n";
