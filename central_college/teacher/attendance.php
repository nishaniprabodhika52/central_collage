<?php
// teacher/attendance.php - Attendance Management Page (FULLY FIXED)
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || empty($_SESSION['teacher_id'])) {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$toast_msg = '';
$toast_type = '';

try {
    $teacher_stmt = $pdo->prepare("SELECT * FROM teacher WHERE teacher_id = ?");
    $teacher_stmt->execute([$teacher_id]);
    $teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    $class_id = $teacher['class_id'] ?? 0;

    // Today info
    $today = date('Y-m-d');
    $today_day_name = date('l', strtotime($today));
    $is_working_day = isWorkingDay($today, $pdo);

    // Fetch students
    $students = [];
    $total_students = 0;
    if ($class_id > 0) {
        $students_stmt = $pdo->prepare("SELECT * FROM student WHERE class_id = ? ORDER BY name ASC");
        $students_stmt->execute([$class_id]);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_students = count($students);
    }

    // Reusable attendance fetcher
    $fetchAttendance = function() use ($pdo, $class_id) {
        if ($class_id <= 0) return [];
        $stmt = $pdo->prepare("
            SELECT a.*, s.name as student_name 
            FROM attendance a
            JOIN student s ON a.student_id = s.student_id
            WHERE s.class_id = ?
            ORDER BY a.date DESC LIMIT 50
        ");
        $stmt->execute([$class_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $attendance_records = $fetchAttendance();
    $total_attendance = count($attendance_records);
    $present_days = 0;
    foreach ($attendance_records as $r) {
        if (strtolower($r['status']) === 'present') $present_days++;
    }
    $attendance_percentage = $total_attendance > 0 ? round(($present_days / $total_attendance) * 100) : 0;

    // ============================================================
    // MARK INDIVIDUAL ATTENDANCE (working day check FIRST)
    // ============================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            die("CSRF Validation Failed.");
        }

        if (!isWorkingDay($today, $pdo)) {
            $toast_msg = "❌ Today ($today_day_name) is not a working day! Attendance cannot be marked.";
            $toast_type = "error";
        } else {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $status = $_POST['attendance_status'] ?? 'present';

            if ($student_id > 0 && $class_id > 0) {
                $check_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
                $check_stmt->execute([$student_id, $today]);
                $existing = $check_stmt->fetch();

                if ($existing) {
                    $upd_stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND date = ?");
                    $upd_stmt->execute([$status, $student_id, $today]);
                } else {
                    $ins_stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, class_id) VALUES (?, ?, ?, ?)");
                    $ins_stmt->execute([$student_id, $today, $status, $class_id]);
                }

                $toast_msg = "✅ Attendance marked successfully!";
                $toast_type = "success";

                $attendance_records = $fetchAttendance();
                $total_attendance = count($attendance_records);
                $present_days = 0;
                foreach ($attendance_records as $r) {
                    if (strtolower($r['status']) === 'present') $present_days++;
                }
                $attendance_percentage = $total_attendance > 0 ? round(($present_days / $total_attendance) * 100) : 0;
            }
        }
    }

    // ============================================================
    // BULK ATTENDANCE (working day check FIRST)
    // ============================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_attendance'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            die("CSRF Validation Failed.");
        }

        if (!isWorkingDay($today, $pdo)) {
            $toast_msg = "❌ Today ($today_day_name) is not a working day! Bulk attendance cannot be marked.";
            $toast_type = "error";
        } else {
            $status = $_POST['bulk_status'] ?? 'present';

            if ($class_id > 0) {
                $class_students = $pdo->prepare("SELECT student_id FROM student WHERE class_id = ?");
                $class_students->execute([$class_id]);
                $student_ids = $class_students->fetchAll(PDO::FETCH_COLUMN);

                foreach ($student_ids as $sid) {
                    $check_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
                    $check_stmt->execute([$sid, $today]);
                    $existing = $check_stmt->fetch();

                    if ($existing) {
                        $upd_stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND date = ?");
                        $upd_stmt->execute([$status, $sid, $today]);
                    } else {
                        $ins_stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, class_id) VALUES (?, ?, ?, ?)");
                        $ins_stmt->execute([$sid, $today, $status, $class_id]);
                    }
                }

                $toast_msg = "✅ Bulk attendance marked for all students!";
                $toast_type = "success";

                $attendance_records = $fetchAttendance();
                $total_attendance = count($attendance_records);
                $present_days = 0;
                foreach ($attendance_records as $r) {
                    if (strtolower($r['status']) === 'present') $present_days++;
                }
                $attendance_percentage = $total_attendance > 0 ? round(($present_days / $total_attendance) * 100) : 0;
            }
        }
    }

} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📅 Attendance | Teacher Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --primary-light: #a78bfa;
            --primary-glow: rgba(139,92,246,0.4);
            --secondary: #6366f1;
            --accent: #06b6d4;
            --gradient-main: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            --gradient-primary: linear-gradient(135deg, var(--primary), var(--secondary));
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --gradient-danger: linear-gradient(135deg, #ef4444, #f87171);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --bg: #0b0f1a;
            --bg-card: rgba(17,24,39,0.85);
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
            --text: #0f172a;
            --text-muted: #64748b;
            --text-light: #334155;
            --border: rgba(0,0,0,0.08);
            --border-light: rgba(0,0,0,0.12);
            --shadow: 0 8px 32px rgba(0,0,0,0.08);
            --glass-bg: rgba(255,255,255,0.6);
            --glass-border: rgba(255,255,255,0.3);
            --gradient-main: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 50%, #c4b5fd 100%);
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
        ::-webkit-scrollbar { width: 6px; }
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
        .teacher-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; color: white;
            cursor: pointer; border: 2px solid var(--border-light);
            transition: var(--transition); flex-shrink: 0; text-transform: uppercase;
        }
        .teacher-avatar:hover { transform: scale(1.05); border-color: var(--primary); }
        .sidebar {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 20px 15px;
            margin-bottom: 28px;
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
        .menu-item .shortcut {
            margin-left: auto; font-size: 0.6rem;
            background: var(--glass-bg); padding: 2px 8px;
            border-radius: var(--radius-full); color: var(--text-muted);
            font-weight: 600;
        }
        .menu-item.active .shortcut { background: rgba(255,255,255,0.2); color: white; }
        .menu-divider { height: 1px; background: var(--border); margin: 12px 0; }
        .sidebar-footer {
            margin-top: 12px; padding-top: 12px;
            border-top: 1px solid var(--border);
            font-size: 0.7rem; color: var(--text-muted); text-align: center;
        }
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
            margin-bottom: 20px;
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
        .btn-sm { padding: 5px 14px; font-size: 0.75rem; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
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
        .stats-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 18px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--glass-bg); backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border); border-radius: var(--radius);
            padding: 20px 22px; transition: var(--transition);
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); border-color: var(--border-light); }
        .stat-card .stat-icon { font-size: 1.6rem; margin-bottom: 8px; display: inline-block; }
        .stat-card .stat-value {
            font-size: 2rem; font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }
        .stat-card .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-top: 2px; }
        .alert-warning {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            background: rgba(245,158,11,0.15);
            border: 1px solid #f59e0b;
            color: #fbbf24;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 12px;
            font-size: 0.9rem;
        }
        .alert-success {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            background: rgba(16,185,129,0.15);
            border: 1px solid #10b981;
            color: #34d399;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 12px;
            font-size: 0.9rem;
        }
        .alert-error {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            background: rgba(239,68,68,0.15);
            border: 1px solid #ef4444;
            color: #f87171;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 12px;
            font-size: 0.9rem;
        }
        .text-muted { color: var(--text-muted); }
        .text-sm { font-size: 0.8rem; }
        @media (max-width: 768px) {
            .top-nav { flex-direction: column; gap: 12px; }
            body { padding: 16px; }
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

    <!-- Top Nav -->
    <nav class="top-nav">
        <div class="brand">
            <i class="fa-solid fa-chalkboard-user"></i>
            Teacher <span style="-webkit-text-fill-color:var(--text-muted);font-weight:400;">Hub</span>
        </div>
        <div class="top-nav-actions">
            <button class="nav-btn" onclick="toggleTheme()"><i class="fa-solid fa-moon"></i></button>
            <div class="teacher-avatar" onclick="window.location.href='profile.php'">
                <?= strtoupper(substr($teacher_name, 0, 2)) ?>
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
        <a href="students.php" class="menu-item">
            <i class="fa-solid fa-users"></i> Students <span class="shortcut">Alt+S</span>
        </a>
        <a href="attendance.php" class="menu-item active">
            <i class="fa-solid fa-fingerprint"></i> Attendance <span class="shortcut">Alt+A</span>
        </a>
        <a href="attendance_report.php" class="menu-item">
            <i class="fa-solid fa-chart-bar"></i> Report <span class="shortcut">Alt+R</span>
        </a>
        <a href="profile.php" class="menu-item">
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

            <?php if (!empty($toast_msg)): ?>
                <div class="alert-<?= $toast_type === 'success' ? 'success' : 'error' ?>">
                    <i class="fa-solid <?= $toast_type === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                    <span><?= htmlspecialchars($toast_msg) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$is_working_day): ?>
                <div class="alert-warning">
                    <i class="fa-solid fa-calendar-xmark" style="font-size:1.3rem;"></i>
                    <div>
                        Today (<strong><?= $today_day_name ?>, <?= date('F j, Y') ?></strong>) is not a working day. Attendance cannot be marked.
                        <br><small><?= date('N', strtotime($today)) >= 6 ? '📌 Weekend days are not working days.' : '📌 Public holiday.' ?></small>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert-success">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Today (<strong><?= $today_day_name ?>, <?= date('F j, Y') ?></strong>) is a working day. You can mark attendance.</span>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?= $attendance_percentage ?>%</div>
                    <div class="stat-label">Overall Attendance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?= $present_days ?></div>
                    <div class="stat-label">Total Present</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-value"><?= $total_attendance ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>

            <!-- Bulk Attendance -->
            <div class="card" id="bulkAttendance">
                <div class="card-header">
                    <h3><i class="fa-solid fa-users"></i> Bulk Attendance Marking</h3>
                    <span class="text-muted text-sm">Today: <?= date('F j, Y') ?></span>
                </div>
                <form method="POST" action="attendance.php" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="bulk_attendance" value="1">
                    <select name="bulk_status" style="padding:10px 16px;background:var(--glass-bg);border:1px solid var(--border-light);border-radius:var(--radius-sm);color:var(--text);">
                        <option value="present">✅ Present All</option>
                        <option value="absent">❌ Absent All</option>
                        <option value="late">⏰ Late All</option>
                    </select>
                    <button type="submit" class="btn btn-primary" <?= !$is_working_day ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-check-double"></i> Mark All <?= $total_students ?> Students
                    </button>
                </form>
            </div>

            <!-- Individual Attendance -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-user-check"></i> Individual Attendance</h3>
                    <a href="../admin/scan_attendance.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-qrcode"></i> Scan QR</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Today's Status</th>
                                <th>Mark Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_students > 0): ?>
                                <?php foreach ($students as $row):
                                    $today_att = 'Not marked';
                                    foreach ($attendance_records as $r) {
                                        if ($r['student_id'] == $row['student_id'] && $r['date'] === $today) {
                                            $today_att = ucfirst($r['status']);
                                            break;
                                        }
                                    }
                                    $badge_class = $today_att === 'Present' ? 'badge-success' : ($today_att === 'Not marked' ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><span class="badge <?= $badge_class ?>"><?= $today_att ?></span></td>
                                        <td>
                                            <form method="POST" action="attendance.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="student_id" value="<?= $row['student_id'] ?>">
                                                <input type="hidden" name="mark_attendance" value="1">
                                                <select name="attendance_status" style="padding:6px 12px;background:var(--glass-bg);border:1px solid var(--border-light);border-radius:var(--radius-sm);color:var(--text);font-size:0.8rem;">
                                                    <option value="present">✅ Present</option>
                                                    <option value="absent">❌ Absent</option>
                                                    <option value="late">⏰ Late</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary" <?= !$is_working_day ? 'disabled' : '' ?>>Mark</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:30px;">No students assigned to your class.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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

        console.log('📅 Attendance loaded - Working day: <?= $is_working_day ? "YES" : "NO" ?>');
    </script>

</body>
</html>