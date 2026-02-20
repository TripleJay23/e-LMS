<?php

/**
 * Admin page: Generate Registration Tokens
 * Accessible: Site Administration → Local Plugins → Profile Enrol → Generate Tokens
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_profileenrol_tokens');

$PAGE->set_title('Generate Registration Tokens');
$PAGE->set_heading('Generate Registration Tokens');

// ── Handle CSV export (before any output) ────────────────────────────────────
$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'export') {
   require_sesskey();
   $dl_program  = optional_param('ep', '', PARAM_ALPHA);
   $dl_year     = optional_param('ey', 0,  PARAM_INT);
   $dl_batch    = optional_param('eb', 0,  PARAM_INT);

   $where  = '1=1';
   $params = [];
   if ($dl_program) {
      $where .= ' AND program = :prog';
      $params['prog'] = strtoupper($dl_program);
   }
   if ($dl_year) {
      $where .= ' AND year = :yr';
      $params['yr']   = $dl_year;
   }
   if ($dl_batch) {
      $where .= ' AND batch = :bt';
      $params['bt']   = $dl_batch;
   }

   $records = $DB->get_records_select('custom_reg_tokens', $where, $params, 'reg_number ASC');

   $filename = 'tokens_' . strtolower($dl_program ?: 'all') . '_y' . $dl_year . '_b' . str_pad($dl_batch, 2, '0', STR_PAD_LEFT) . '_' . date('Ymd') . '.csv';
   header('Content-Type: text/csv');
   header('Content-Disposition: attachment; filename="' . $filename . '"');
   $out = fopen('php://output', 'w');
   fputcsv($out, ['Registration Number', 'Program', 'Year', 'Batch', 'Enroll Year', 'Status', 'Claimed By (userid)']);
   foreach ($records as $r) {
      fputcsv($out, [$r->reg_number, $r->program, $r->year, $r->batch, $r->enroll_year, $r->status, $r->userid ?? '']);
   }
   fclose($out);
   exit;
}

// ── Handle token generation ───────────────────────────────────────────────────
$generated_tokens = [];
$errors           = [];
$gen_program      = '';
$gen_year         = 0;
$gen_batch        = 2;
$gen_count        = 10;

if ($action === 'generate') {
   require_sesskey();
   $gen_program = strtoupper(optional_param('program', '', PARAM_ALPHA));
   $gen_year    = optional_param('year',  0,  PARAM_INT);
   $gen_batch   = optional_param('batch', 1,  PARAM_INT);
   $gen_count   = optional_param('count', 10, PARAM_INT);

   if (!in_array($gen_program, ['BCS', 'BIT']))       $errors[] = 'Select a valid program.';
   if ($gen_year < 1 || $gen_year > 3)                $errors[] = 'Select a valid year (1–3).';
   if ($gen_batch < 1 || $gen_batch > 99)             $errors[] = 'Batch must be 1–99.';
   if ($gen_count < 1 || $gen_count > 200)            $errors[] = 'Count must be 1–200.';

   if (empty($errors)) {
      $current_year = (int)date('Y');
      $enroll_year  = $current_year - ($gen_year - 1);
      $batch_str    = str_pad($gen_batch, 2, '0', STR_PAD_LEFT);
      $attempts     = 0;

      while (count($generated_tokens) < $gen_count && $attempts < $gen_count * 20) {
         $attempts++;
         $rand      = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
         $candidate = "{$gen_program}-{$batch_str}-{$rand}-{$enroll_year}";

         if (array_key_exists($candidate, $generated_tokens)) continue;
         if ($DB->record_exists('custom_reg_tokens', ['reg_number' => $candidate])) continue;

         $rec              = new stdClass();
         $rec->reg_number  = $candidate;
         $rec->program     = $gen_program;
         $rec->year        = $gen_year;
         $rec->batch       = $gen_batch;
         $rec->enroll_year = $enroll_year;
         $rec->status      = 'unused';
         $rec->userid      = null;
         $rec->timecreated = time();
         $rec->timeclaimed = null;
         $DB->insert_record('custom_reg_tokens', $rec);

         $generated_tokens[$candidate] = $enroll_year;
      }
   }
}

// ── Load summary data ─────────────────────────────────────────────────────────
$summary = $DB->get_records_sql(
   "SELECT program || '_' || year || '_' || batch AS rowkey,
            program, year, batch, enroll_year,
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'unused'  THEN 1 ELSE 0 END) AS unused,
            SUM(CASE WHEN status = 'claimed' THEN 1 ELSE 0 END) AS claimed
     FROM {custom_reg_tokens}
     GROUP BY program, year, batch, enroll_year
     ORDER BY program, year, batch"
);

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading('Generate Student Registration Tokens', 2);

foreach ($errors as $e) {
   echo $OUTPUT->notification($e, 'error');
}
if (!empty($generated_tokens)) {
   echo $OUTPUT->notification(count($generated_tokens) . ' tokens generated for ' . $gen_program . ' · Year ' . $gen_year . ' · Batch ' . str_pad($gen_batch, 2, '0', STR_PAD_LEFT), 'success');
}
?>

<!-- Generation Form -->
<div class="card mb-4" style="max-width:520px;">
   <div class="card-header fw-bold">New Token Batch</div>
   <div class="card-body">
      <form method="post">
         <input type="hidden" name="action" value="generate">
         <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

         <div class="mb-3 row">
            <label class="col-sm-4 col-form-label">Program</label>
            <div class="col-sm-8">
               <select name="program" class="form-select" required>
                  <option value="">— select —</option>
                  <option value="BCS" <?php if ($gen_program === 'BCS') echo 'selected'; ?>>BCS – Computer Science</option>
                  <option value="BIT" <?php if ($gen_program === 'BIT') echo 'selected'; ?>>BIT – Information Technology</option>
               </select>
            </div>
         </div>

         <div class="mb-3 row">
            <label class="col-sm-4 col-form-label">Year of Study</label>
            <div class="col-sm-8">
               <select name="year" class="form-select" required>
                  <option value="">— select —</option>
                  <?php for ($y = 1; $y <= 3; $y++): ?>
                     <option value="<?php echo $y; ?>" <?php if ($gen_year === $y) echo 'selected'; ?>>Year <?php echo $y; ?></option>
                  <?php endfor; ?>
               </select>
            </div>
         </div>

         <div class="mb-3 row">
            <label class="col-sm-4 col-form-label">Batch No.</label>
            <div class="col-sm-8">
               <input type="number" name="batch" class="form-control" value="<?php echo $gen_batch ?: 2; ?>" min="1" max="99" required>
               <div class="form-text">e.g. 2 = 2nd intake cohort</div>
            </div>
         </div>

         <div class="mb-3 row">
            <label class="col-sm-4 col-form-label">Count</label>
            <div class="col-sm-8">
               <input type="number" name="count" class="form-control" value="<?php echo $gen_count; ?>" min="1" max="200" required>
               <div class="form-text">Max 200 per batch</div>
            </div>
         </div>

         <button type="submit" class="btn btn-primary">Generate Tokens</button>
      </form>
   </div>
</div>

<?php if (!empty($generated_tokens)): ?>
   <!-- Newly generated tokens -->
   <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
         <span class="fw-bold">Newly Generated</span>
         <a href="?action=export&sesskey=<?php echo sesskey(); ?>&ep=<?php echo urlencode($gen_program); ?>&ey=<?php echo $gen_year; ?>&eb=<?php echo $gen_batch; ?>"
            class="btn btn-sm btn-success">⬇ Export CSV</a>
      </div>
      <div class="card-body p-0">
         <table class="table table-sm table-striped mb-0" style="font-family:monospace;">
            <thead>
               <tr>
                  <th>#</th>
                  <th>Registration Number</th>
               </tr>
            </thead>
            <tbody>
               <?php $i = 1;
               foreach ($generated_tokens as $t => $ey): ?>
                  <tr>
                     <td><?php echo $i++; ?></td>
                     <td><?php echo htmlspecialchars($t); ?></td>
                  </tr>
               <?php endforeach; ?>
            </tbody>
         </table>
      </div>
   </div>
<?php endif; ?>

<!-- All batches summary -->
<div class="card">
   <div class="card-header fw-bold">All Token Batches</div>
   <div class="card-body p-0">
      <table class="table table-sm mb-0">
         <thead class="table-light">
            <tr>
               <th>Program</th>
               <th>Year</th>
               <th>Batch</th>
               <th>Enroll Year</th>
               <th>Total</th>
               <th><span class="text-success">✓ Unused</span></th>
               <th><span class="text-secondary">✗ Claimed</span></th>
               <th></th>
            </tr>
         </thead>
         <tbody>
            <?php if (empty($summary)): ?>
               <tr>
                  <td colspan="8" class="text-center text-muted py-3">No tokens generated yet.</td>
               </tr>
               <?php else:
               foreach ($summary as $s): ?>
                  <tr>
                     <td><?php echo htmlspecialchars($s->program); ?></td>
                     <td>Year <?php echo $s->year; ?></td>
                     <td>Batch <?php echo str_pad($s->batch, 2, '0', STR_PAD_LEFT); ?></td>
                     <td><?php echo $s->enroll_year; ?></td>
                     <td><?php echo $s->total; ?></td>
                     <td><span class="badge bg-success"><?php echo $s->unused; ?></span></td>
                     <td><span class="badge bg-secondary"><?php echo $s->claimed; ?></span></td>
                     <td>
                        <a href="?action=export&sesskey=<?php echo sesskey();
                                                         ?>&ep=<?php echo urlencode($s->program);
                     ?>&ey=<?php echo $s->year;
                     ?>&eb=<?php echo $s->batch; ?>"
                           class="btn btn-sm btn-outline-secondary">⬇ CSV</a>
                     </td>
                  </tr>
            <?php endforeach;
            endif; ?>
         </tbody>
      </table>
   </div>
</div>

<?php echo $OUTPUT->footer(); ?>