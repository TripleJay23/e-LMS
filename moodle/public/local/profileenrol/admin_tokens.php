<?php
/**
 * Admin page: Generate Registration Tokens.
 * Accessible via Site Administration -> Local Plugins -> Profile Enrol.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_profileenrol_tokens');

$PAGE->set_title('Generate Registration Tokens');
$PAGE->set_heading('Generate Registration Tokens');

$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'export') {
    require_sesskey();

    $dlprogram = strtoupper(optional_param('ep', '', PARAM_ALPHA));
    $dlyear = optional_param('ey', 0, PARAM_INT);
    $dlbatch = optional_param('eb', 0, PARAM_INT);

    $where = '1=1';
    $params = [];
    if ($dlprogram !== '') {
        $where .= ' AND program = :program';
        $params['program'] = $dlprogram;
    }
    if ($dlyear > 0) {
        $where .= ' AND year = :year';
        $params['year'] = $dlyear;
    }
    if ($dlbatch > 0) {
        $where .= ' AND batch = :batch';
        $params['batch'] = $dlbatch;
    }

    $records = $DB->get_records_select('custom_reg_tokens', $where, $params, 'reg_number ASC');
    $filename = 'tokens_' . strtolower($dlprogram ?: 'all') . '_y' . $dlyear . '_b' .
        str_pad((string)$dlbatch, 2, '0', STR_PAD_LEFT) . '_' . date('Ymd') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Registration Number', 'Program', 'Year', 'Batch', 'Enroll Year', 'Status', 'Claimed By (userid)']);
    foreach ($records as $record) {
        fputcsv($out, [
            $record->reg_number,
            $record->program,
            $record->year,
            $record->batch,
            $record->enroll_year,
            $record->status,
            $record->userid ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$generatedtokens = [];
$errors = [];
$genprogram = '';
$genyear = 0;
$genbatch = 2;
$gencount = 10;

if ($action === 'generate') {
    require_sesskey();

    $genprogram = strtoupper(optional_param('program', '', PARAM_ALPHA));
    $genyear = optional_param('year', 0, PARAM_INT);
    $genbatch = optional_param('batch', 1, PARAM_INT);
    $gencount = optional_param('count', 10, PARAM_INT);

    if (!in_array($genprogram, ['BCS', 'BIT'], true)) {
        $errors[] = 'Select a valid program.';
    }
    if ($genyear < 1 || $genyear > 3) {
        $errors[] = 'Select a valid year (1-3).';
    }
    if ($genbatch < 1 || $genbatch > 99) {
        $errors[] = 'Batch must be 1-99.';
    }
    if ($gencount < 1 || $gencount > 200) {
        $errors[] = 'Count must be 1-200.';
    }

    if (empty($errors)) {
        $currentyear = (int)date('Y');
        $enrollyear = $currentyear - ($genyear - 1);
        $batchstr = str_pad((string)$genbatch, 2, '0', STR_PAD_LEFT);
        $attempts = 0;
        $maxattempts = $gencount * 20;

        while (count($generatedtokens) < $gencount && $attempts < $maxattempts) {
            $attempts++;
            $rand = str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = "{$genprogram}-{$batchstr}-{$rand}-{$enrollyear}";

            if (isset($generatedtokens[$candidate])) {
                continue;
            }
            if ($DB->record_exists('custom_reg_tokens', ['reg_number' => $candidate])) {
                continue;
            }

            $record = (object)[
                'reg_number' => $candidate,
                'program' => $genprogram,
                'year' => $genyear,
                'batch' => $genbatch,
                'enroll_year' => $enrollyear,
                'status' => 'unused',
                'userid' => null,
                'timecreated' => time(),
                'timeclaimed' => null,
            ];
            $DB->insert_record('custom_reg_tokens', $record);
            $generatedtokens[$candidate] = $enrollyear;
        }
    }
}

$summary = [];
$summaryrs = $DB->get_recordset_sql(
    "SELECT program,
            year,
            batch,
            enroll_year,
            COUNT(*) AS total,
            SUM(CASE WHEN LOWER(status) = 'unused'  THEN 1 ELSE 0 END) AS unused,
            SUM(CASE WHEN LOWER(status) = 'claimed' THEN 1 ELSE 0 END) AS claimed,
            SUM(CASE WHEN LOWER(status) = 'used'    THEN 1 ELSE 0 END) AS used
       FROM {custom_reg_tokens}
   GROUP BY program, year, batch, enroll_year
   ORDER BY program, year, batch"
);
foreach ($summaryrs as $row) {
    $summary[] = $row;
}
$summaryrs->close();

echo $OUTPUT->header();
echo $OUTPUT->heading('Generate Student Registration Tokens', 2);

foreach ($errors as $error) {
    echo $OUTPUT->notification($error, 'error');
}
if (!empty($generatedtokens)) {
    echo $OUTPUT->notification(
        count($generatedtokens) . ' tokens generated for ' . $genprogram .
        ' | Year ' . $genyear . ' | Batch ' . str_pad((string)$genbatch, 2, '0', STR_PAD_LEFT),
        'success'
    );
}
?>

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
                        <option value="">-- select --</option>
                        <option value="BCS" <?php if ($genprogram === 'BCS') { echo 'selected'; } ?>>BCS - Computer Science</option>
                        <option value="BIT" <?php if ($genprogram === 'BIT') { echo 'selected'; } ?>>BIT - Information Technology</option>
                    </select>
                </div>
            </div>

            <div class="mb-3 row">
                <label class="col-sm-4 col-form-label">Year of Study</label>
                <div class="col-sm-8">
                    <select name="year" class="form-select" required>
                        <option value="">-- select --</option>
                        <?php for ($year = 1; $year <= 3; $year++): ?>
                            <option value="<?php echo $year; ?>" <?php if ($genyear === $year) { echo 'selected'; } ?>>
                                Year <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3 row">
                <label class="col-sm-4 col-form-label">Batch No.</label>
                <div class="col-sm-8">
                    <input type="number" name="batch" class="form-control" value="<?php echo $genbatch ?: 2; ?>" min="1" max="99" required>
                    <div class="form-text">e.g. 2 = second intake cohort</div>
                </div>
            </div>

            <div class="mb-3 row">
                <label class="col-sm-4 col-form-label">Count</label>
                <div class="col-sm-8">
                    <input type="number" name="count" class="form-control" value="<?php echo $gencount; ?>" min="1" max="200" required>
                    <div class="form-text">Max 200 per batch</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Generate Tokens</button>
        </form>
    </div>
</div>

<?php if (!empty($generatedtokens)): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">Newly Generated</span>
            <a href="?action=export&sesskey=<?php echo sesskey(); ?>&ep=<?php echo urlencode($genprogram); ?>&ey=<?php echo $genyear; ?>&eb=<?php echo $genbatch; ?>"
               class="btn btn-sm btn-success">Download CSV</a>
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
                    <?php $index = 1; ?>
                    <?php foreach ($generatedtokens as $token => $enrollyear): ?>
                        <tr>
                            <td><?php echo $index++; ?></td>
                            <td><?php echo htmlspecialchars($token); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

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
                    <th><span class="text-success">Unused</span></th>
                    <th><span class="text-secondary">Claimed</span></th>
                    <th><span class="text-dark">Used</span></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">No tokens generated yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($summary as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item->program); ?></td>
                            <td>Year <?php echo (int)$item->year; ?></td>
                            <td>Batch <?php echo str_pad((string)$item->batch, 2, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo (int)$item->enroll_year; ?></td>
                            <td><?php echo (int)$item->total; ?></td>
                            <td><span class="badge bg-success"><?php echo (int)$item->unused; ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo (int)$item->claimed; ?></span></td>
                            <td><span class="badge bg-dark"><?php echo (int)$item->used; ?></span></td>
                            <td>
                                <a href="?action=export&sesskey=<?php echo sesskey(); ?>&ep=<?php echo urlencode($item->program); ?>&ey=<?php echo (int)$item->year; ?>&eb=<?php echo (int)$item->batch; ?>"
                                   class="btn btn-sm btn-outline-secondary">CSV</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>
