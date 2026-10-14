<?php

// DATABASE CONNECTION
$host = 'localhost';
$db   = 'it30_lab_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

session_start();

// IMPORT HANDLER
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {

    $results = ['success' => 0, 'failed' => 0, 'errors' => []];

    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $results['errors'][] = 'File upload failed.';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $results['errors'][] = 'Only .csv files are allowed.';
    } else {

        $handle = fopen($file['tmp_name'], 'r');

        if ($handle === false) {
            $results['errors'][] = 'Could not open the uploaded file.';
        } else {

            // Strip UTF-8 BOM if present
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            // Read header row
            $header = fgetcsv($handle);
            $header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

            $required = ['student_first_name', 'student_last_name', 'student_course'];
            $missing  = array_diff($required, $header);

            if (!empty($missing)) {
                $results['errors'][] = 'Missing column(s): ' . implode(', ', $missing);
            } else {

                $col = array_flip($header);

                $stmt = $pdo->prepare("
                    INSERT INTO students (
                        student_first_name,
                        student_last_name,
                        student_course
                    ) VALUES (?, ?, ?)
                ");

                $line = 1;

                while (($row = fgetcsv($handle)) !== false) {

                    $line++;

                    // Skip blank lines
                    if (count($row) === 1 && trim($row[0]) === '') {
                        continue;
                    }

                    $firstName = trim($row[$col['student_first_name']] ?? '');
                    $lastName  = trim($row[$col['student_last_name']] ?? '');
                    $course    = trim($row[$col['student_course']] ?? '');

                    if ($firstName === '' || $lastName === '' || $course === '') {
                        $results['failed']++;
                        $results['errors'][] = "Line $line: Missing required field(s).";
                        continue;
                    }

                    try {
                        $stmt->execute([$firstName, $lastName, $course]);
                        $results['success']++;
                    } catch (PDOException $e) {
                        $results['failed']++;
                        $results['errors'][] = "Line $line: " . $e->getMessage();
                    }
                }
            }

            fclose($handle);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Students from CSV</title>
</head>

<body>

<h1>Import Students from CSV</h1>

<nav>
    <a href="index.php?section=students">Students</a> |
    <strong>Import Students</strong>
</nav>

<hr>

<?php if ($results !== null): ?>

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

    <hr>

<?php endif; ?>

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

    <p>
        <label>CSV File:</label><br>
        <input type="file" name="csv_file" accept=".csv" required>
    </p>

    <button type="submit">Import Students</button>

</form>

</body>
</html>