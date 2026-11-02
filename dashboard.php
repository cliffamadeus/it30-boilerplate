<?php
// ============================================================
// DASHBOARD – uses real data from the database
// Navbar is included from includes/navbar.php
// ============================================================

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

// ---------- 1. KPI COUNTS ----------
$studentCount  = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$bookCount     = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalBorrows  = $pdo->query("SELECT COUNT(*) FROM borrow")->fetchColumn();
$activeBorrows = $pdo->query("SELECT COUNT(*) FROM borrow WHERE borrow_return_date IS NULL")->fetchColumn();

// ---------- 2. BOOKS BY CATEGORY (for doughnut) ----------
$catStmt = $pdo->query("
    SELECT book_category, COUNT(*) AS cnt
    FROM books
    GROUP BY book_category
    ORDER BY cnt DESC
");
$categories = [];
$categoryCounts = [];
while ($row = $catStmt->fetch()) {
    $categories[]     = $row['book_category'];
    $categoryCounts[] = (int) $row['cnt'];
}

// ---------- 3. BORROW ACTIVITY LAST 7 DAYS (for bar chart) ----------
$dailyStmt = $pdo->query("
    SELECT DATE(borrow_date) AS day, COUNT(*) AS cnt
    FROM borrow
    WHERE borrow_date >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(borrow_date)
    ORDER BY day ASC
");
$borrowByDay = [];
while ($row = $dailyStmt->fetch()) {
    $borrowByDay[$row['day']] = (int) $row['cnt'];
}

$dayLabels = [];
$dailyBorrows = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayLabels[]    = date('D', strtotime($date));
    $dailyBorrows[] = $borrowByDay[$date] ?? 0;
}

// ---------- 4. RECENT BORROWS (for mini list) ----------
$recentStmt = $pdo->query("
    SELECT
        s.student_first_name,
        s.student_last_name,
        b.book_title,
        br.borrow_return_date
    FROM borrow br
    INNER JOIN students s ON br.student_id = s.student_id
    INNER JOIN books b ON br.book_id = b.book_id
    ORDER BY br.borrow_id DESC
    LIMIT 5
");
$recentBorrows = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library · Dashboard</title>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f8fafc;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: #0f172a;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .dashboard { max-width: 1300px; width: 100%; }

        /* ---- KPI CARDS ---- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.25rem;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 1.25rem;
            padding: 1.3rem 1.5rem;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
        }
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 500;
            color: #5e6f8d;
        }
        .stat-header .material-icons {
            font-size: 1.5rem;
            color: #9aaec7;
        }
        .stat-number { font-size: 2.3rem; font-weight: 450; color: #0b1e3a; line-height: 1.2; }
        .stat-sub { font-size: 0.85rem; color: #6b7b98; margin-top: 0.3rem; }

        /* ---- CHARTS ---- */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.8rem;
        }
        .chart-card {
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 1.5rem 1.5rem 1rem 1.5rem;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
        }
        .chart-title {
            font-size: 0.95rem;
            font-weight: 500;
            color: #1e2f4a;
            margin-bottom: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chart-title span {
            font-weight: 350;
            color: #6b7f9e;
            font-size: 0.8rem;
            background: #f1f5f9;
            padding: 0.15rem 0.6rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .chart-title span .material-icons {
            font-size: 0.9rem;
        }
        canvas { display: block; width: 100% !important; height: auto !important; max-height: 190px; }

        /* ---- RECENT BORROWS ---- */
        .recent-borrows {
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 1.5rem 1.8rem;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
        }
        .recent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .recent-header h3 {
            font-weight: 500;
            font-size: 1rem;
            color: #1e2f4a;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .recent-header h3 .material-icons {
            font-size: 1.2rem;
            color: #5e6f8d;
        }
        .recent-header a {
            font-size: 0.8rem;
            color: #2c3e5c;
            text-decoration: none;
            border-bottom: 1px dashed #b8c7dd;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        .recent-header a .material-icons {
            font-size: 0.9rem;
        }
        .borrow-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem 0;
            border-bottom: 1px solid #f0f4fa;
            font-size: 0.9rem;
        }
        .borrow-row:last-child { border-bottom: none; }
        .borrow-student {
            font-weight: 450;
            color: #0e1f3a;
            width: 30%;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .borrow-student .material-icons {
            font-size: 1rem;
            color: #8a9bb8;
        }
        .borrow-book {
            color: #3b4e6e;
            width: 45%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .borrow-book .material-icons {
            font-size: 1rem;
            color: #8a9bb8;
        }
        .borrow-status {
            font-size: 0.75rem;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-weight: 450;
            background: #f1f5f9;
            color: #2d405d;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .borrow-status .material-icons {
            font-size: 0.8rem;
        }
        .borrow-status.returned { background: #e6f7ec; color: #0b6b3e; }
        .borrow-status.borrowed { background: #fff6e5; color: #a15e00; }

        .footer-note {
            margin-top: 1.8rem;
            text-align: center;
            font-size: 0.78rem;
            color: #8a9bb8;
        }

        @media (max-width: 760px) {
            .charts-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .borrow-student,
            .borrow-book { width: auto; flex: 1; }
            .borrow-row { flex-wrap: wrap; gap: 0.3rem; }
        }
    </style>
</head>
<body>
<div class="dashboard">

    <!-- NAVBAR (extracted) -->
    <?php include 'includes/navbar.php'; ?>

    <!-- KPI CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Students</span>
                <span class="material-icons">school</span>
            </div>
            <div class="stat-number"><?= $studentCount ?></div>
            <div class="stat-sub">enrolled</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Books</span>
                <span class="material-icons">auto_stories</span>
            </div>
            <div class="stat-number"><?= $bookCount ?></div>
            <div class="stat-sub">in collection</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Borrow records</span>
                <span class="material-icons">history</span>
            </div>
            <div class="stat-number"><?= $totalBorrows ?></div>
            <div class="stat-sub">all time</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active loans</span>
                <span class="material-icons">pending_actions</span>
            </div>
            <div class="stat-number"><?= $activeBorrows ?></div>
            <div class="stat-sub">not returned</div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-title">
                Books by category
                <span>
                    <span class="material-icons">category</span>
                    collection
                </span>
            </div>
            <canvas id="categoryChart" width="400" height="190"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">
                Borrows · last 7 days
                <span>
                    <span class="material-icons">trending_up</span>
                    activity
                </span>
            </div>
            <canvas id="borrowChart" width="400" height="190"></canvas>
        </div>
    </div>

    <!-- RECENT BORROWS -->
    <div class="recent-borrows">
        <div class="recent-header">
            <h3>
                <span class="material-icons">receipt_long</span>
                Recent borrows
            </h3>
            <a href="#">
                view all
                <span class="material-icons">arrow_forward</span>
            </a>
        </div>
        <?php if (empty($recentBorrows)): ?>
            <div class="borrow-row" style="color:#8a9bb8; justify-content:center;">
                No borrow records yet.
            </div>
        <?php else: ?>
            <?php foreach ($recentBorrows as $row): ?>
                <div class="borrow-row">
                    <div class="borrow-student">
                        <span class="material-icons">person</span>
                        <?= htmlspecialchars($row['student_first_name'] . ' ' . $row['student_last_name']) ?>
                    </div>
                    <div class="borrow-book">
                        <span class="material-icons">book</span>
                        <?= htmlspecialchars($row['book_title']) ?>
                    </div>
                    <div class="borrow-status <?= $row['borrow_return_date'] ? 'returned' : 'borrowed' ?>">
                        <span class="material-icons">
                            <?= $row['borrow_return_date'] ? 'check_circle' : 'schedule' ?>
                        </span>
                        <?= $row['borrow_return_date'] ? 'Returned' : 'Borrowed' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="footer-note">minimalist dashboard · chart.js · material icons</div>
</div>

<script>
    (function() {
        // ---------- DOUGHNUT : BOOKS BY CATEGORY ----------
        const catCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($categories) ?>,
                datasets: [{
                    data: <?= json_encode($categoryCounts) ?>,
                    backgroundColor: [
                        '#3b5d7a', '#7a8fa5', '#b0c2d0', '#d6e0e8', '#e9eef3'
                    ],
                    borderWidth: 0,
                    borderRadius: 3,
                    spacing: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 14,
                            font: { size: 11, weight: '400' },
                            color: '#334155'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#ffffff',
                        titleColor: '#0b1b33',
                        bodyColor: '#3b4e6e',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 8,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((value / total) * 100).toFixed(0) : 0;
                                return `${label}: ${value} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });

        // ---------- BAR : BORROWS LAST 7 DAYS ----------
        const borrowCtx = document.getElementById('borrowChart').getContext('2d');
        new Chart(borrowCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($dayLabels) ?>,
                datasets: [{
                    label: 'Borrows',
                    data: <?= json_encode($dailyBorrows) ?>,
                    backgroundColor: '#3b5d7a',
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#ffffff',
                        titleColor: '#0b1b33',
                        bodyColor: '#3b4e6e',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 8,
                        cornerRadius: 8,
                        callbacks: {
                            label: (ctx) => `${ctx.raw} borrows`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef2f6', drawBorder: false },
                        ticks: { stepSize: 1, color: '#5e6f8d', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#5e6f8d', font: { size: 10 } }
                    }
                }
            }
        });
    })();
</script>
</body>
</html>