#!/usr/bin/env php
<?php
/**
 * Apply summary-only course template with optimized static images.
 *
 * What this script does:
 * - Removes all course overview images (course/overviewfiles).
 * - Generates lightweight optimized images in moodle/public/local/courseimages.
 * - Writes the requested image + description box HTML into course summary.
 *
 * Run:
 *   php scripts/apply_full_course_template.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../moodle/config.php');
require_once($CFG->libdir . '/filelib.php');

const TARGET_IMAGE_WIDTH = 300;
const TARGET_IMAGE_HEIGHT = 168;
const TARGET_IMAGE_QUALITY = 72;

/**
 * Build module lookup by code.
 *
 * @return array<string, array>
 */
function load_module_map(string $path): array
{
   if (!file_exists($path)) {
      throw new RuntimeException("modules_corrected.json not found at {$path}");
   }

   $raw = json_decode(file_get_contents($path), true);
   if (!is_array($raw)) {
      throw new RuntimeException("Invalid JSON in {$path}");
   }

   $map = [];
   foreach ($raw as $module) {
      if (!isset($module['code'])) {
         continue;
      }
      $map[(string)$module['code']] = $module;
   }
   return $map;
}

/**
 * Normalize course shortname to module code key.
 */
function normalize_course_code(string $shortname): string
{
   return trim((string)preg_replace('/-(?:BIT|BCS|SHARED)$/i', '', $shortname));
}

/**
 * Resolve metadata for a course from module map or category fallback.
 *
 * @return array{year_label:string, semester_label:string, credits:string, course_type:string, fallback:bool}
 */
function resolve_course_metadata(stdClass $course, array $modulemap, moodle_database $DB): array
{
   $semroman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI'];

   $rawcode = normalize_course_code((string)$course->shortname);
   $module = $modulemap[$rawcode] ?? null;

   if ($module === null) {
      foreach ($modulemap as $code => $candidate) {
         if (strcasecmp($code, $rawcode) === 0) {
            $module = $candidate;
            break;
         }
      }
   }

   if ($module !== null) {
      $yearnum = (int)($module['year'] ?? 1);
      $semnum = (int)($module['semester_num'] ?? 1);
      $credits = (string)($module['credits'] ?? 'N/A');
      $coursetype = (string)($module['type'] ?? 'N/A');
      return [
         'year_label' => "Year {$yearnum}",
         'semester_label' => "Semester " . ($semroman[$semnum] ?? (string)$semnum),
         'credits' => $credits,
         'course_type' => $coursetype,
         'fallback' => false,
      ];
   }

   $yearnum = 1;
   $semnum = 1;
   $credits = 'N/A';
   $coursetype = 'N/A';

   $cat = $DB->get_record('course_categories', ['id' => $course->category]);
   if ($cat) {
      if (preg_match('/Year\s*(\d)/i', (string)$cat->name, $myear)) {
         $yearnum = (int)$myear[1];
      }
      if (preg_match('/Semester\s*(\d)/i', (string)$cat->name, $msem)) {
         $semnum = (int)$msem[1];
      }
      if (!empty($cat->parent)) {
         $parent = $DB->get_record('course_categories', ['id' => $cat->parent]);
         if ($parent && preg_match('/Year\s*(\d)/i', (string)$parent->name, $myearp)) {
            $yearnum = (int)$myearp[1];
         }
      }
   }

   if (preg_match('/Credit(?:\s+Hours?)?:\s*([^<\n]+)/i', (string)$course->summary, $mcredit)) {
      $credits = trim((string)$mcredit[1]);
   }
   if (preg_match('/Type:\s*([^<\n]+)/i', (string)$course->summary, $mtype)) {
      $coursetype = trim((string)$mtype[1]);
   }

   return [
      'year_label' => "Year {$yearnum}",
      'semester_label' => "Semester " . ($semroman[$semnum] ?? (string)$semnum),
      'credits' => $credits,
      'course_type' => $coursetype,
      'fallback' => true,
   ];
}

/**
 * Create a center-cropped optimized image and return relative URL.
 */
function ensure_optimized_image(string $sourcepath, string $publicdir): string
{
   $sourcebase = pathinfo($sourcepath, PATHINFO_FILENAME);
   $targetsafe = preg_replace('/[^a-zA-Z0-9_-]/', '-', $sourcebase) ?: 'course-image';

   $usewebp = function_exists('imagewebp');
   $ext = $usewebp ? 'webp' : 'jpg';
   $targetfilename = "{$targetsafe}_" . TARGET_IMAGE_WIDTH . "x" . TARGET_IMAGE_HEIGHT . ".{$ext}";
   $targetpath = $publicdir . DIRECTORY_SEPARATOR . $targetfilename;

   if (file_exists($targetpath) && filemtime($targetpath) >= filemtime($sourcepath)) {
      return '/local/courseimages/' . rawurlencode($targetfilename);
   }

   $binary = file_get_contents($sourcepath);
   if ($binary === false) {
      throw new RuntimeException("Cannot read source image: {$sourcepath}");
   }

   $src = @imagecreatefromstring($binary);
   if (!$src) {
      // Fallback: copy original as-is.
      copy($sourcepath, $targetpath);
      return '/local/courseimages/' . rawurlencode($targetfilename);
   }

   $srcw = imagesx($src);
   $srch = imagesy($src);
   $targetratio = TARGET_IMAGE_WIDTH / TARGET_IMAGE_HEIGHT;
   $srcratio = $srcw / max(1, $srch);

   if ($srcratio > $targetratio) {
      $croph = $srch;
      $cropw = (int)round($srch * $targetratio);
      $cropx = (int)round(($srcw - $cropw) / 2);
      $cropy = 0;
   } else {
      $cropw = $srcw;
      $croph = (int)round($srcw / $targetratio);
      $cropx = 0;
      $cropy = (int)round(($srch - $croph) / 2);
   }

   $dst = imagecreatetruecolor(TARGET_IMAGE_WIDTH, TARGET_IMAGE_HEIGHT);
   imagealphablending($dst, false);
   imagesavealpha($dst, true);
   $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
   imagefill($dst, 0, 0, $transparent);

   imagecopyresampled(
      $dst,
      $src,
      0,
      0,
      $cropx,
      $cropy,
      TARGET_IMAGE_WIDTH,
      TARGET_IMAGE_HEIGHT,
      $cropw,
      $croph
   );

   if ($usewebp) {
      imagewebp($dst, $targetpath, TARGET_IMAGE_QUALITY);
   } else {
      imagejpeg($dst, $targetpath, TARGET_IMAGE_QUALITY);
   }

   imagedestroy($src);
   imagedestroy($dst);

   return '/local/courseimages/' . rawurlencode($targetfilename);
}

/**
 * Build the exact summary layout requested by user.
 */
function build_summary_html(string $imageurl, array $meta): string
{
   return '<p style="text-align: center">' . "\n"
      . '  <img class="img-fluid" role="presentation" src="' . htmlspecialchars($imageurl, ENT_QUOTES) . '" alt="" width="300" height="168" loading="lazy" decoding="async" fetchpriority="low" />' . "\n"
      . '</p>' . "\n"
      . '<div class="course-description-box" style="background: #ffffff; border-left: 5px solid #0f6cbf; padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 0.95rem; color: #222222; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">' . "\n"
      . '  <strong>Academic Year: </strong>' . htmlspecialchars($meta['year_label'], ENT_QUOTES) . '<br>' . "\n"
      . '  <strong>Semester: </strong>' . htmlspecialchars($meta['semester_label'], ENT_QUOTES) . '<br>' . "\n"
      . '  <strong>Credit: </strong>' . htmlspecialchars((string)$meta['credits'], ENT_QUOTES) . '<br>' . "\n"
      . '  <strong>Course Type:</strong> ' . htmlspecialchars((string)$meta['course_type'], ENT_QUOTES) . "\n"
      . '</div>';
}

echo "Apply Full Course Card Template\n";
echo str_repeat("=", 60) . "\n\n";

try {
   $modulemap = load_module_map(__DIR__ . '/modules_corrected.json');
   echo "Loaded " . count($modulemap) . " module records.\n";
} catch (Exception $e) {
   echo "ERROR: {$e->getMessage()}\n";
   exit(1);
}

$imagedir = __DIR__ . '/../images';
$images = glob($imagedir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
if (!$images) {
   echo "ERROR: no source images found in {$imagedir}\n";
   exit(1);
}
sort($images);
echo "Found " . count($images) . " source images.\n";

$publicimagedir = __DIR__ . '/../moodle/public/local/courseimages';
if (!is_dir($publicimagedir) && !mkdir($publicimagedir, 0777, true) && !is_dir($publicimagedir)) {
   echo "ERROR: cannot create {$publicimagedir}\n";
   exit(1);
}

$courses = $DB->get_records_select('course', 'id > 1', null, 'shortname ASC');
$fs = get_file_storage();

$processed = 0;
$errors = 0;
$fallbacks = 0;
$overviewclears = 0;
$summaryfileclears = 0;
$imageindex = 0;

foreach ($courses as $course) {
   echo "Processing {$course->shortname}... ";

   try {
      $context = context_course::instance($course->id);
      $meta = resolve_course_metadata($course, $modulemap, $DB);
      if ($meta['fallback']) {
         $fallbacks++;
      }

      // Remove overview files so "course image" section is empty.
      $overviewfiles = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id', false);
      if (!empty($overviewfiles)) {
         $fs->delete_area_files($context->id, 'course', 'overviewfiles');
         $overviewclears++;
      }

      // Remove embedded summary files that are not needed anymore.
      $summaryfiles = $fs->get_area_files($context->id, 'course', 'summary', 0, 'id', false);
      if (!empty($summaryfiles)) {
         $fs->delete_area_files($context->id, 'course', 'summary');
         $summaryfileclears++;
      }

      $source = $images[$imageindex % count($images)];
      $imageindex++;
      $imageurl = ensure_optimized_image($source, $publicimagedir);

      $course->summary = build_summary_html($imageurl, $meta);
      $course->summaryformat = FORMAT_HTML;
      $DB->update_record('course', $course);

      echo "OK ({$meta['year_label']}, {$meta['semester_label']}, img=" . basename((string)$imageurl) . ")\n";
      $processed++;
   } catch (Exception $e) {
      echo "ERROR: {$e->getMessage()}\n";
      $errors++;
   }
}

echo "\nDone\n";
echo "  Processed courses: {$processed}\n";
echo "  Errors: {$errors}\n";
echo "  Metadata fallbacks: {$fallbacks}\n";
echo "  Cleared overviewfiles areas: {$overviewclears}\n";
echo "  Cleared summary file areas: {$summaryfileclears}\n";
echo "  Optimized image target: " . TARGET_IMAGE_WIDTH . "x" . TARGET_IMAGE_HEIGHT . "\n";

