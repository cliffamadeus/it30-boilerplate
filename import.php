<?php

// ============================================================
// DATABASE CONNECTION
// ============================================================
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = 'localhost';
    $db      = 'it30_lab_db';
    $user    = 'root';
    $pass    = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}

// HELPERS
function normalizeHeader(string $header): string
{
    return strtolower(trim(str_replace([' ', '-'], '_', $header)));
}

function parseCsv(string $path): array
{
    $rows = [];

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return $rows;
    }

    // Strip UTF-8 BOM if present
    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    while (($row = fgetcsv($handle)) !== false) {
        // Skip fully blank lines
        if (count($row) === 1 && trim($row[0]) === '') {
            continue;
        }
        $rows[] = $row;
    }

    fclose($handle);
    return $rows;
}

function validateCsv(array $rows, array $required): array
{
    $result = [
        'ok'     => false,
        'header' => [],
        'col'    => [],
        'rows'   => [],
        'errors' => [],
    ];

    if (empty($rows)) {
        $result['errors'][] = 'The CSV file is empty.';
        return $result;
    }

    $header = array_map('normalizeHeader', $rows[0]);
    $result['header'] = $header;

    $missing = array_diff($required, $header);
    if (!empty($missing)) {
        $result['errors'][] = 'Missing column(s): ' . implode(', ', $missing);
        return $result;
    }

    $result['col']  = array_flip($header);
    $result['rows'] = array_slice($rows, 1);
    $result['ok']   = true;

    return $result;
}

function extractStudent(array $row, array $col): array
{
    $firstName = trim($row[$col['student_first_name']] ?? '');
    $lastName  = trim($row[$col['student_last_name']] ?? '');
    $course    = trim($row[$col['student_course']] ?? '');

    $missing = [];
    if ($firstName === '') $missing[] = 'first name';
    if ($lastName  === '') $missing[] = 'last name';
    if ($course    === '') $missing[] = 'course';

    return [
        'valid'      => empty($missing),
        'errors'     => $missing,
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'course'     => $course,
    ];
}

// ============================================================
// HANDLERS
// ============================================================

/**
 * Handle STEP 1 (upload).
 * Validates the uploaded CSV and returns a preview payload.
 *
 * @return array { fatal?: string, preview?: array }
 */
function handleUpload(): array
{
    $file = $_FILES['csv_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['fatal' => 'File upload failed.'];
    }

    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        return ['fatal' => 'Only .csv files are allowed.'];
    }

    $required = ['student_first_name', 'student_last_name', 'student_course'];

    $rows = parseCsv($file['tmp_name']);
    $v    = validateCsv($rows, $required);

    if (!$v['ok']) {
        return ['fatal' => implode(' ', $v['errors'])];
    }

    $previewRows  = [];
    $validCount   = 0;
    $invalidCount = 0;

    $line = 1;

    foreach ($v['rows'] as $row) {
        $line++;
        $s = extractStudent($row, $v['col']);

        if ($s['valid']) {
            $validCount++;
        } else {
            $invalidCount++;
        }

        $previewRows[] = [
            'line'       => $line,
            'valid'      => $s['valid'],
            'errors'     => $s['errors'],
            'first_name' => $s['first_name'],
            'last_name'  => $s['last_name'],
            'course'     => $s['course'],
        ];
    }

    // Stash for the confirmation step
    $_SESSION['import_preview'] = $previewRows;

    return [
        'preview' => [
            'filename'      => $file['name'],
            'rows'          => $previewRows,
            'valid_count'   => $validCount,
            'invalid_count' => $invalidCount,
        ],
    ];
}

/**
 * Handle STEP 2 (confirm).
 * Inserts valid rows from the session preview.
 *
 * @return array { fatal?: string, results?: array }
 */
function handleConfirm(): array
{
    $rows = $_SESSION['import_preview'] ?? null;

    if (!$rows) {
        return ['fatal' => 'No preview data found. Please upload the CSV again.'];
    }

    $pdo = db();

    $results = ['success' => 0, 'failed' => 0, 'errors' => []];

    $stmt = $pdo->prepare("
        INSERT INTO students (
            student_first_name,
            student_last_name,
            student_course
        ) VALUES (?, ?, ?)
    ");

    foreach ($rows as $row) {

        if (!$row['valid']) {
            $results['failed']++;
            $results['errors'][] =
                "Line {$row['line']}: Missing " . implode(', ', $row['errors']) . '.';
            continue;
        }

        try {
            $stmt->execute([
                $row['first_name'],
                $row['last_name'],
                $row['course'],
            ]);
            $results['success']++;
        } catch (PDOException $e) {
            $results['failed']++;
            $results['errors'][] = "Line {$row['line']}: " . $e->getMessage();
        }
    }

    unset($_SESSION['import_preview']);

    return ['results' => $results];
}

/**
 * Handle STEP 3 (cancel).
 * Clears the session preview and redirects back to the upload form.
 */
function handleCancel(): void
{
    unset($_SESSION['import_preview']);
    header('Location: import.php');
    exit;
}

/**
 * Main controller: decides which handler to run and returns
 * the state (stage / preview / results / fatal) for the view.
 *
 * @return array
 */
function handleRequest(): array
{
    session_start();

    $stage   = 'upload';
    $preview = null;
    $results = null;
    $fatal   = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $step = $_POST['step'] ?? '';

        switch ($step) {

            case 'upload':
                $out = handleUpload();
                if (isset($out['fatal'])) {
                    $fatal = $out['fatal'];
                } else {
                    $preview = $out['preview'];
                    $stage   = 'preview';
                }
                break;

            case 'confirm':
                $out = handleConfirm();
                if (isset($out['fatal'])) {
                    $fatal = $out['fatal'];
                } else {
                    $results = $out['results'];
                    $stage   = 'done';
                }
                break;

            case 'cancel':
                handleCancel(); // exits
                break;
        }
    }

    return compact('stage', 'preview', 'results', 'fatal');
}

// BOOTSTRAP
$state = handleRequest();

// Expose variables to the view
$stage   = $state['stage'];
$preview = $state['preview'];
$results = $state['results'];
$fatal   = $state['fatal'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Students from CSV</title>

    <style>
        body { font-family: sans-serif; }
        table { border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 6px 10px; text-align: left; }
        th { background: #f0f0f0; }
        .row-invalid { background: #ffe6e6; }
        .row-valid   { background: #eaffea; }
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
        .badge-ok  { background: #c9f0c9; }
        .badge-bad { background: #f7c9c9; }
        .summary { margin: 12px 0; padding: 10px; border: 1px solid #ccc; background: #fafafa; }
    </style>
</head>

<body>

<h1>Import Students from CSV</h1>

<nav>
    <a href="index.php?section=students">Students</a> |
    <strong>Import Students</strong>
</nav>

<hr>

<?php if ($fatal !== null): ?>

    <h3>Error</h3>
    <p style="color:#b00;"><?= htmlspecialchars($fatal) ?></p>
    <p><a href="import.php">Try again</a></p>

<?php endif; ?>


<?php if ($stage === 'upload' && $fatal === null): ?>

    <h3>Upload Students CSV</h3>

    <p>
        The CSV must include a header row with the columns:
        <code>student_first_name, student_last_name, student_course</code>
    </p>

    <p><strong>Example:</strong></p>

    <pre>student_first_name,student_last_name,student_course
CLIFF AMADEUS,EVANGELIO,BSIT
JAN XAVIER,EVANGELIO,BSA-AGRONOMY
RON EDMUND,EVANGELIO,BSEE</pre>

    <form method="POST" enctype="multipart/form-data">

        <input type="hidden" name="step" value="upload">

        <p>
            <label>CSV File:</label><br>
            <input type="file" name="csv_file" accept=".csv" required>
        </p>

        <button type="submit">Preview</button>

    </form>

<?php endif; ?>


<?php if ($stage === 'preview' && $preview !== null): ?>

    <h3>Preview: <?= htmlspecialchars($preview['filename']) ?></h3>

    <div class="summary">
        <strong>Total rows:</strong> <?= count($preview['rows']) ?><br>
        <strong>Valid:</strong> <?= (int) $preview['valid_count'] ?><br>
        <strong>Invalid:</strong> <?= (int) $preview['invalid_count'] ?>
    </div>

    <?php if ($preview['valid_count'] === 0): ?>

        <p style="color:#b00;">No valid rows to import.</p>

    <?php else: ?>

        <p>
            Review the rows below. Only <strong>valid</strong> rows will be inserted.
            Invalid rows will be skipped.
        </p>

        <table>

            <thead>
                <tr>
                    <th>Line</th>
                    <th>Status</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Course</th>
                    <th>Notes</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($preview['rows'] as $row): ?>

                    <tr class="<?= $row['valid'] ? 'row-valid' : 'row-invalid' ?>">

                        <td><?= (int) $row['line'] ?></td>

                        <td>
                            <?php if ($row['valid']): ?>
                                <span class="badge badge-ok">OK</span>
                            <?php else: ?>
                                <span class="badge badge-bad">SKIP</span>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($row['first_name']) ?></td>
                        <td><?= htmlspecialchars($row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['course']) ?></td>

                        <td>
                            <?= $row['valid']
                                ? ''
                                : 'Missing ' . htmlspecialchars(implode(', ', $row['errors'])) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

    <hr>

    <form method="POST" style="display:inline;">

        <input type="hidden" name="step" value="confirm">

        <button type="submit"
            <?= $preview['valid_count'] === 0 ? 'disabled' : '' ?>
            onclick="return confirm('Import <?= (int) $preview['valid_count'] ?> valid student(s)?');"
        >
            Confirm Import
        </button>

    </form>

    <form method="POST" style="display:inline;">

        <input type="hidden" name="step" value="cancel">

        <button type="submit">Cancel</button>

    </form>

<?php endif; ?>


<?php if ($stage === 'done' && $results !== null): ?>

    <h3>Import Results</h3>

    <ul>
        <li><strong>Successful inserts:</strong> <?= (int) $results['success'] ?></li>
        <li><strong>Failed rows:</strong> <?= (int) $results['failed'] ?></li>
    </ul>

    <?php if (!empty($results['errors'])): ?>
        <h4>Details</h4>
        <ul>
            <?php foreach ($results['errors'] as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p>
        <a href="import.php">Import another file</a> |
        <a href="index.php?section=students">Back to Students</a>
    </p>

<?php endif; ?>

</body>
</html>