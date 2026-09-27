<?php
// admin/print_report.php - Print Report
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch all students for report
$students = $pdo->query("
    SELECT s.*, c.class_name, c.grade 
    FROM student s 
    LEFT JOIN class c ON s.class_id = c.class_id 
    ORDER BY s.student_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_students = count($students);
$male_count = $pdo->query("SELECT COUNT(*) FROM student WHERE gender = 'Male'")->fetchColumn();
$female_count = $pdo->query("SELECT COUNT(*) FROM student WHERE gender = 'Female'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Student Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: white;
            color: #0f172a;
            padding: 40px;
        }
        .report-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .report-header h1 { font-size: 1.8rem; font-weight: 800; }
        .report-header p { color: #64748b; margin-top: 4px; }
        .report-meta {
            display: flex; justify-content: center; gap: 30px;
            margin-top: 12px; font-size: 0.9rem;
        }
        .report-meta span { background: #f1f5f9; padding: 4px 16px; border-radius: 20px; }

        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table thead th {
            text-align: left; padding: 10px 12px;
            background: #f1f5f9; font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        table tbody td {
            padding: 10px 12px; border-bottom: 1px solid #e2e8f0;
        }
        table tbody tr:hover { background: #f8fafc; }

        .badge {
            padding: 2px 10px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600;
            display: inline-block;
        }
        .badge-primary { background: #e0e7ff; color: #4f46e5; }
        .badge-success { background: #d1fae5; color: #065f46; }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.75rem;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }

        .print-btn {
            display: inline-block; padding: 10px 30px;
            background: #6366f1; color: white;
            border: none; border-radius: 8px;
            font-weight: 600; cursor: pointer;
            margin-bottom: 20px;
        }
        .print-btn:hover { background: #4f46e5; }

        @media print {
            .print-btn { display: none; }
            body { padding: 20px; }
            table thead th { background: #e2e8f0; }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save PDF</button>

    <div class="report-header">
        <h1>📊 Student Report</h1>
        <p>Central College - Complete Student List</p>
        <div class="report-meta">
            <span>👨 Male: <?= $male_count ?></span>
            <span>👩 Female: <?= $female_count ?></span>
            <span>🎓 Total: <?= $total_students ?></span>
            <span>📅 <?= date('F j, Y') ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Gender</th>
                <th>Class</th>
                <th>Contact</th>
                <th>Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $row): ?>
                    <tr>
                        <td class="font-mono">STU-00<?= $row['student_id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><span class="badge <?= $row['gender'] === 'Male' ? 'badge-primary' : 'badge-success' ?>"><?= htmlspecialchars($row['gender']) ?></span></td>
                        <td><?= !empty($row['grade']) ? htmlspecialchars($row['grade'] . ' - ' . $row['class_name']) : 'N/A' ?></td>
                        <td><?= htmlspecialchars($row['contact'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['address'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;">No students found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Generated on <?= date('F j, Y h:i A') ?> • Powered by Central College System
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); };
        console.log('📊 Student Report loaded');
    </script>
</body>
</html>