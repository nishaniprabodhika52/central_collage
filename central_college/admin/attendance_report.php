<?php
// admin/attendance_report.php - Principal Attendance Report
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['principal_id']) || empty($_SESSION['principal_id'])) {
    header("Location: ../login.php");
    exit();
}

$principal_name = $_SESSION['principal_name'] ?? 'Principal';

// Get filters
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

try {
    // Get all classes for filter
    $classes = $pdo->query("SELECT class_id, class_name, grade FROM class ORDER BY grade ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all students for filter (if class selected)
    $students = [];
    if ($selected_class > 0) {
        $stu_stmt = $pdo->prepare("SELECT student_id, name FROM student WHERE class_id = ? ORDER BY name");
        $stu_stmt->execute([$selected_class]);
        $students = $stu_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Build query for attendance report
    $sql = "SELECT 
                s.student_id,
                s.name as student_name,
                s.gender,
                c.class_name,
                c.grade,
                COUNT(a.attendance_id) as total_days,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.attendance_id)) * 100, 2) as percentage
            FROM student s
            LEFT JOIN class c ON s.class_id = c.class_id
            LEFT JOIN attendance a ON s.student_id = a.student_id 
                AND MONTH(a.date) = ? 
                AND YEAR(a.date) = ?
            WHERE 1=1";
    
    $params = [$selected_month, $selected_year];
    
    if ($selected_class > 0) {
        $sql .= " AND s.class_id = ?";
        $params[] = $selected_class;
    }
    
    if ($selected_student > 0) {
        $sql .= " AND s.student_id = ?";
        $params[] = $selected_student;
    }
    
    $sql .= " GROUP BY s.student_id ORDER BY s.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary stats
    $total_students = count($report_data);
    $overall_present = 0;
    $overall_total = 0;
    foreach ($report_data as $row) {
        $overall_present += $row['present_days'];
        $overall_total += $row['total_days'];
    }
    $overall_percentage = $overall_total > 0 ? round(($overall_present / $overall_total) * 100, 2) : 0;
    
    // Month name
    $month_names = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    $month_name = $month_names[$selected_month] ?? '';

} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Attendance Report | Principal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --primary-glow: rgba(99,102,241,0.4);
            --secondary: #8b5cf6;
            --accent: #06b6d4;
            --gradient-main: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            --gradient-primary: linear-gradient(135deg, var(--primary), var(--secondary));
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --gradient-danger: linear-gradient(135deg, #ef4444, #f87171);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --bg: #0b0f1a;
            --bg-card: rgba(17,24,39,0.85);
            --bg-card-hover: rgba(30,41,59,0.8);
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --text-light: #cbd5e1;
            --border: rgba(255,255,255,0.06);
            --border-light: rgba(255,255,255,0.1);
            --shadow: 0 8px 32px rgba(0,0,0,0.4);
            --radius: 16px;
            --radius-sm: 10px;
            --radius-full: 9999px;
            --transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
            --glass-bg: rgba(255,255,255,0.05);
            --glass-border: rgba(255,255,255,0.1);
            --backdrop-blur: blur(20px);
        }

        [data-theme="light"] {
            --bg: #f1f5f9;
            --bg-card: rgba(255,255,255,0.85);
            --bg-card-hover: rgba(241,245,249,0.9);
            --text: #0f172a;
            --text-muted: #64748b;
            --text-light: #334155;
            --border: rgba(0,0,0,0.08);
            --border-light: rgba(0,0,0,0.12);
            --shadow: 0 8px 32px rgba(0,0,0,0.08);
            --glass-bg: rgba(255,255,255,0.6);
            --glass-border: rgba(255,255,255,0.3);
            --gradient-main: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 50%, #a5b4fc 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            transition: var(--transition);
            padding: 30px;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: var(--radius-full); }

        .bg-gradient-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: var(--gradient-main); z-index: 0; opacity: 0.3;
            animation: gradientShift 15s ease-in-out infinite alternate;
        }
        @keyframes gradientShift {
            0% { opacity: 0.3; transform: scale(1) rotate(0deg); }
            50% { opacity: 0.5; transform: scale(1.1) rotate(2deg); }
            100% { opacity: 0.3; transform: scale(1) rotate(-2deg); }
        }

        .orb {
            position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.3;
            z-index: 0; pointer-events: none;
            animation: orbFloat 20s ease-in-out infinite alternate;
        }
        .orb-1 { width: 400px; height: 400px; background: var(--primary); top: -100px; right: -100px; }
        .orb-2 { width: 300px; height: 300px; background: var(--secondary); bottom: -50px; left: -50px; animation-delay: -5s; }
        .orb-3 { width: 200px; height: 200px; background: var(--accent); top: 50%; left: 50%; animation-delay: -10s; }
        @keyframes orbFloat {
            0% { transform: translate(0,0) scale(1); }
            33% { transform: translate(50px,-30px) scale(1.1); }
            66% { transform: translate(-30px,50px) scale(0.9); }
            100% { transform: translate(20px,-20px) scale(1.05); }
        }

        #particles-container {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0; pointer-events: none; overflow: hidden;
        }
        .particle {
            position: absolute; width: 3px; height: 3px;
            background: var(--primary-light); border-radius: 50%;
            animation: particleFloat linear infinite; opacity: 0.4;
        }
        @keyframes particleFloat {
            0% { transform: translateY(100vh) translateX(0) scale(0); opacity: 0; }
            10% { opacity: 0.4; }
            90% { opacity: 0.4; }
            100% { transform: translateY(-10vh) translateX(50px) scale(1); opacity: 0; }
        }

        .top-nav {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 16px 24px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }
        .top-nav .brand {
            font-size: 1.2rem; font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            display: flex; align-items: center; gap: 10px;
        }
        .top-nav .brand i { -webkit-text-fill-color: var(--primary); font-size: 1.4rem; }
        .top-nav-actions { display: flex; align-items: center; gap: 12px; }
        .nav-btn {
            width: 40px; height: 40px; border-radius: 50%;
            border: 1px solid var(--border-light);
            background: var(--glass-bg); color: var(--text);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: var(--transition);
            font-size: 1rem;
        }
        .nav-btn:hover { background: var(--primary); border-color: var(--primary); transform: scale(1.05); box-shadow: 0 0 20px var(--primary-glow); }
        .principal-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; color: white;
            cursor: pointer; border: 2px solid var(--border-light);
            transition: var(--transition); flex-shrink: 0; text-transform: uppercase;
        }
        .principal-avatar:hover { transform: scale(1.05); border-color: var(--primary); }

        .sidebar {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 20px 15px;
            margin-bottom: 28px;
            transition: var(--transition);
        }
        .menu-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; color: var(--text-muted);
            text-decoration: none; border-radius: var(--radius-sm);
            font-weight: 500; font-size: 0.9rem;
            transition: var(--transition); margin-bottom: 4px;
        }
        .menu-item i { width: 20px; text-align: center; font-size: 1rem; }
        .menu-item:hover { background: var(--bg-card); color: var(--text); transform: translateX(4px); }
        .menu-item.active {
            background: var(--gradient-primary); color: white;
            box-shadow: 0 4px 20px var(--primary-glow);
        }
        .menu-item.active i { color: white; }
        .menu-item .shortcut { margin-left: auto; font-size: 0.6rem; background: var(--glass-bg); padding: 2px 8px; border-radius: var(--radius-full); color: var(--text-muted); font-weight: 600; }
        .menu-item.active .shortcut { background: rgba(255,255,255,0.2); color: white; }
        .menu-divider { height: 1px; background: var(--border); margin: 12px 0; }
        .sidebar-footer { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); font-size: 0.7rem; color: var(--text-muted); text-align: center; }

        .main-content { position: relative; z-index: 1; }
        .page-content { animation: pageFadeIn 0.6s ease-out; }
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .card {
            background: var(--glass-bg); backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border); border-radius: var(--radius);
            padding: 24px; transition: var(--transition);
        }
        .card:hover { border-color: var(--border-light); box-shadow: var(--shadow); }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 18px;
        }
        .card-header h3 { font-size: 1.05rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: var(--primary); }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 18px; border-radius: var(--radius-full);
            border: none; font-weight: 600; font-size: 0.85rem;
            cursor: pointer; transition: var(--transition);
            text-decoration: none; background: var(--glass-bg);
            color: var(--text); border: 1px solid var(--border-light);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .btn-primary { background: var(--gradient-primary); color: white; border: none; }
        .btn-primary:hover { box-shadow: 0 4px 24px var(--primary-glow); }
        .btn-success { background: var(--gradient-success); color: white; border: none; }
        .btn-danger { background: var(--gradient-danger); color: white; border: none; }
        .btn-sm { padding: 5px 14px; font-size: 0.75rem; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table thead th { text-align: left; padding: 12px 10px; color: var(--text-muted); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
        table tbody td { padding: 12px 10px; border-bottom: 1px solid var(--border); color: var(--text-light); }
        table tbody tr:hover { background: var(--glass-bg); }

        .badge {
            padding: 3px 12px; border-radius: var(--radius-full);
            font-size: 0.7rem; font-weight: 600; display: inline-block;
        }
        .badge-success { background: rgba(16,185,129,0.15); color: #10b981; }
        .badge-danger { background: rgba(239,68,68,0.15); color: #ef4444; }
        .badge-warning { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .badge-primary { background: rgba(99,102,241,0.15); color: var(--primary-light); }

        .filter-bar {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
            margin-bottom: 20px; padding: 16px;
            background: var(--glass-bg); border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
        }
        .filter-bar select, .filter-bar input {
            padding: 8px 14px; background: var(--glass-bg);
            border: 1px solid var(--border-light); border-radius: var(--radius-sm);
            color: var(--text); font-size: 0.85rem;
        }
        .filter-bar select:focus, .filter-bar input:focus { outline: none; border-color: var(--primary); }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px; margin-bottom: 24px;
        }
        .stat-card {
            background: var(--glass-bg); backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border); border-radius: var(--radius-sm);
            padding: 16px 20px; text-align: center;
        }
        .stat-card .stat-value { font-size: 1.8rem; font-weight: 800; background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-card .stat-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        .text-muted { color: var(--text-muted); }
        .text-sm { font-size: 0.8rem; }
        .font-mono { font-family: 'Courier New', monospace; }
        .mt-20 { margin-top: 20px; }
        .mb-20 { margin-bottom: 20px; }

        .progress-bar {
            width: 100%; height: 6px; background: var(--border); border-radius: var(--radius-full); overflow: hidden;
        }
        .progress-bar .fill {
            height: 100%; border-radius: var(--radius-full);
            background: var(--gradient-success);
            transition: width 0.5s ease;
        }
        .progress-bar .fill.low { background: var(--gradient-danger); }
        .progress-bar .fill.medium { background: var(--gradient-warning); }

        @media (max-width: 768px) {
            .top-nav { flex-direction: column; gap: 12px; }
            body { padding: 16px; }
            .filter-bar { flex-direction: column; }
            .filter-bar select, .filter-bar input { width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

    <div class="bg-gradient-overlay"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div id="particles-container"></div>

    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="brand">
            <i class="fa-solid fa-crown"></i>
            Principal <span style="-webkit-text-fill-color:var(--text-muted);font-weight:400;">Report</span>
        </div>
        <div class="top-nav-actions">
            <button class="nav-btn" onclick="toggleTheme()"><i class="fa-solid fa-moon"></i></button>
            <div class="principal-avatar" onclick="window.location.href='profile.php'">
                <?= strtoupper(substr($principal_name, 0, 2)) ?>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div style="margin-bottom:16px;padding:0 10px;">
            <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Navigation</div>
        </div>
        <a href="dashboard.php" class="menu-item">
            <i class="fa-solid fa-border-all"></i> Dashboard <span class="shortcut">Alt+D</span>
        </a>
        <a href="?page=students" class="menu-item">
            <i class="fa-solid fa-users"></i> Students <span class="shortcut">Alt+S</span>
        </a>
        <a href="?page=teachers" class="menu-item">
            <i class="fa-solid fa-chalkboard-user"></i> Teachers <span class="shortcut">Alt+T</span>
        </a>
        <a href="attendance_report.php" class="menu-item active">
            <i class="fa-solid fa-chart-bar"></i> Attendance Report <span class="shortcut">Alt+R</span>
        </a>
        <a href="?page=profile" class="menu-item">
            <i class="fa-solid fa-user-gear"></i> Profile <span class="shortcut">Alt+P</span>
        </a>
        <div class="menu-divider"></div>
        <a href="../logout.php" class="menu-item" style="color:#ef4444;">
            <i class="fa-solid fa-power-off"></i> Logout <span class="shortcut">Alt+L</span>
        </a>
        <div class="sidebar-footer">v5.0 • Secure Session</div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-content">

            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-chart-bar"></i> Attendance Report</h3>
                    <span class="text-muted text-sm"><?= $month_name ?> <?= $selected_year ?></span>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <form method="GET" action="attendance_report.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;flex:1;">
                        <select name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0,0,0,$m,1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year">
                            <?php for ($y = date('Y') - 3; $y <= date('Y'); $y++): ?>
                                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="class_id" onchange="this.form.submit()">
                            <option value="0">-- All Classes --</option>
                            <?php foreach ($classes as $cl): ?>
                                <option value="<?= $cl['class_id'] ?>" <?= $selected_class == $cl['class_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cl['grade'] . ' - ' . $cl['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selected_class > 0 && count($students) > 0): ?>
                            <select name="student_id">
                                <option value="0">-- All Students --</option>
                                <?php foreach ($students as $stu): ?>
                                    <option value="<?= $stu['student_id'] ?>" <?= $selected_student == $stu['student_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($stu['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Filter</button>
                        <a href="attendance_report.php" class="btn btn-sm">Clear</a>
                    </form>
                    <button class="btn btn-success" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
                </div>

                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $total_students ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $overall_percentage ?>%</div>
                        <div class="stat-label">Overall Attendance</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $overall_present ?></div>
                        <div class="stat-label">Total Present</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $overall_total ?></div>
                        <div class="stat-label">Total Days</div>
                    </div>
                </div>

                <!-- Report Table -->
                <?php if (count($report_data) > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Gender</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Late</th>
                                    <th>Total</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($report_data as $row): 
                                    $percentage = $row['percentage'] ?? 0;
                                    $bar_class = $percentage >= 80 ? '' : ($percentage >= 60 ? 'medium' : 'low');
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                                        <td><span class="badge badge-primary"><?= htmlspecialchars($row['grade'] ?? 'N/A') . ' - ' . htmlspecialchars($row['class_name'] ?? 'N/A') ?></span></td>
                                        <td><span class="badge <?= ($row['gender'] ?? '') === 'Male' ? 'badge-primary' : 'badge-success' ?>"><?= htmlspecialchars($row['gender'] ?? 'N/A') ?></span></td>
                                        <td><span class="badge badge-success"><?= $row['present_days'] ?? 0 ?></span></td>
                                        <td><span class="badge badge-danger"><?= $row['absent_days'] ?? 0 ?></span></td>
                                        <td><span class="badge badge-warning"><?= $row['late_days'] ?? 0 ?></span></td>
                                        <td><?= $row['total_days'] ?? 0 ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <span style="font-weight:600;color:<?= $percentage >= 80 ? '#10b981' : ($percentage >= 60 ? '#f59e0b' : '#ef4444') ?>;">
                                                    <?= $percentage ?>%
                                                </span>
                                                <div class="progress-bar" style="width:60px;">
                                                    <div class="fill <?= $bar_class ?>" style="width:<?= $percentage ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align:center;padding:40px 0;color:var(--text-muted);">
                        <i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:12px;"></i>
                        No attendance records found for the selected filters.
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const target = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', target);
            localStorage.setItem('theme', target);
        }
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }

        (function createParticles() {
            const container = document.getElementById('particles-container');
            for (let i = 0; i < 30; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                p.style.left = Math.random() * 100 + '%';
                p.style.animationDuration = (Math.random() * 15 + 10) + 's';
                p.style.animationDelay = (Math.random() * 10) + 's';
                p.style.width = (Math.random() * 4 + 2) + 'px';
                p.style.height = p.style.width;
                container.appendChild(p);
            }
        })();

        console.log('📊 Attendance Report loaded');
    </script>

</body>
</html>