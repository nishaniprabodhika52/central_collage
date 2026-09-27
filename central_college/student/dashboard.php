<?php
// student/dashboard.php - Dashboard Page (FULL UI UPGRADE - same as subjects.php)
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, c.grade 
        FROM student s 
        LEFT JOIN class c ON s.class_id = c.class_id 
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    $profile_score = 50;
    if (!empty($student['contact'])) $profile_score += 20;
    if (!empty($student['address'])) $profile_score += 20;
    if (!empty($student['date_of_birth'])) $profile_score += 10;

    $att_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 30");
    $att_stmt->execute([$student_id]);
    $attendance_records = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_days = count($attendance_records);
    $present_days = 0;
    foreach ($attendance_records as $r) {
        if (strtolower($r['status']) === 'present') $present_days++;
    }
    $attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100) : 100;

    $sub_stmt = $pdo->query("SELECT * FROM subject LIMIT 10");
    $subjects = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);

    $today_status = 'Not marked';
    foreach ($attendance_records as $r) {
        if ($r['date'] === date('Y-m-d')) {
            $today_status = ucfirst($r['status']);
            break;
        }
    }

    // QR CODE
    $qr_payload = json_encode([
        'id'      => $student['student_id'],
        'name'    => $student['name'],
        'class'   => ($student['grade'] ?? '') . ' - ' . ($student['class_name'] ?? 'N/A'),
        'reg_no'  => 'STU-00' . $student['student_id']
    ]);
    $qr_encoded = base64_encode($qr_payload);
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_encoded);

} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎓 Dashboard | Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            position: relative;
            z-index: 1;
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
        .student-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; color: white;
            cursor: pointer; border: 2px solid var(--border-light);
            transition: var(--transition); flex-shrink: 0; text-transform: uppercase;
        }
        .student-avatar:hover { transform: scale(1.05); border-color: var(--primary); }

        .sidebar {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 20px 15px;
            margin-bottom: 28px;
            transition: var(--transition);
            position: relative;
            z-index: 1;
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

        .welcome-banner {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 28px 32px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }
        .welcome-banner .banner-bg {
            position: absolute; top: -50%; right: -10%; width: 400px; height: 400px;
            background: var(--gradient-primary); border-radius: 50%;
            opacity: 0.08; filter: blur(60px);
        }
        .welcome-banner h1 { font-size: 1.8rem; font-weight: 800; position: relative; z-index: 1; }
        .welcome-banner h1 span {
            background: var(--gradient-primary);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .welcome-banner p { color: var(--text-muted); margin-top: 4px; position: relative; z-index: 1; }
        .welcome-banner .welcome-time {
            position: absolute; right: 30px; top: 50%; transform: translateY(-50%);
            text-align: right; z-index: 1;
        }
        .welcome-banner .welcome-time .time {
            font-size: 2rem; font-weight: 800; font-family: 'Courier New', monospace;
            background: var(--gradient-primary);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .welcome-banner .welcome-time .date { font-size: 0.8rem; color: var(--text-muted); }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--glass-bg); backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border); border-radius: var(--radius);
            padding: 20px 22px; transition: var(--transition);
            position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: var(--gradient-primary); opacity: 0; transition: var(--transition);
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); border-color: var(--border-light); }
        .stat-card:hover::before { opacity: 1; }
        .stat-card .stat-icon { font-size: 1.6rem; margin-bottom: 8px; display: inline-block; }
        .stat-card .stat-value {
            font-size: 2rem; font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }
        .stat-card .stat-label {
            font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;
            letter-spacing: 0.5px; font-weight: 600; margin-top: 2px;
        }
        .stat-card .stat-trend {
            position: absolute; top: 16px; right: 16px; font-size: 0.7rem; font-weight: 600;
            padding: 2px 10px; border-radius: var(--radius-full);
            background: rgba(16,185,129,0.15); color: #10b981;
        }
        .stat-card .stat-trend.down { background: rgba(239,68,68,0.15); color: #ef4444; }

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
        .btn-sm { padding: 5px 14px; font-size: 0.75rem; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table thead th {
            text-align: left; padding: 12px 10px; color: var(--text-muted);
            font-weight: 600; font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1px solid var(--border);
        }
        table tbody td { padding: 12px 10px; border-bottom: 1px solid var(--border); color: var(--text-light); }
        table tbody tr:hover { background: var(--glass-bg); }

        .status-badge {
            padding: 3px 12px; border-radius: var(--radius-full);
            font-size: 0.7rem; font-weight: 600; display: inline-block;
        }
        .status-badge.present { background: rgba(16,185,129,0.15); color: #10b981; }
        .status-badge.absent { background: rgba(239,68,68,0.15); color: #ef4444; }
        .status-badge.late { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .status-badge.not-marked { background: rgba(148,163,184,0.15); color: #94a3b8; }

        .dashboard-grid {
            display: grid; grid-template-columns: 1.6fr 1fr;
            gap: 24px; margin-bottom: 24px;
        }

        .attendance-circle-container {
            display: flex; align-items: center; gap: 30px; padding: 10px 0;
        }
        .attendance-circle { width: 120px; height: 120px; border-radius: 50%; position: relative; flex-shrink: 0; }
        .attendance-circle canvas { width: 100%; height: 100%; }
        .attendance-circle .center-text {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;
        }
        .attendance-circle .center-text .percentage {
            font-size: 1.8rem; font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            line-height: 1;
        }
        .attendance-circle .center-text .label {
            font-size: 0.6rem; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .attendance-stats { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .attendance-stats .stat-row {
            display: flex; justify-content: space-between;
            padding: 6px 0; border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }
        .attendance-stats .stat-row .label { color: var(--text-muted); }
        .attendance-stats .stat-row .value { font-weight: 600; }
        .attendance-stats .stat-row .value.present { color: #10b981; }
        .attendance-stats .stat-row .value.absent { color: #ef4444; }

        .quick-actions-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
        }
        .quick-action-btn {
            background: var(--glass-bg); border: 1px solid var(--border-light);
            border-radius: var(--radius-sm); padding: 16px; text-align: center;
            cursor: pointer; transition: var(--transition); color: var(--text);
            text-decoration: none;
        }
        .quick-action-btn:hover { background: var(--bg-card); transform: translateY(-3px); box-shadow: var(--shadow); }
        .quick-action-btn i {
            font-size: 1.6rem; display: block; margin-bottom: 6px;
            background: var(--gradient-primary);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .quick-action-btn span { font-size: 0.75rem; font-weight: 500; }

        .badge {
            padding: 3px 12px; border-radius: var(--radius-full);
            font-size: 0.7rem; font-weight: 600; display: inline-block;
        }
        .badge-primary { background: rgba(99,102,241,0.15); color: var(--primary-light); }
        .badge-success { background: rgba(16,185,129,0.15); color: #10b981; }

        .text-muted { color: var(--text-muted); }
        .text-sm { font-size: 0.8rem; }
        .font-mono { font-family: 'Courier New', monospace; }
        .mt-12 { margin-top: 12px; }
        .mb-16 { margin-bottom: 16px; }

        @media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .welcome-banner .welcome-time { display: none; }
            .welcome-banner h1 { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: repeat(2,1fr); gap: 12px; }
            .attendance-circle-container { flex-direction: column; align-items: center; }
            .top-nav { flex-direction: column; gap: 12px; }
            body { padding: 16px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 14px; }
            .stat-card .stat-value { font-size: 1.2rem; }
            .card { padding: 16px; }
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
            <i class="fa-solid fa-graduation-cap"></i>
            EduCore <span style="-webkit-text-fill-color:var(--text-muted);font-weight:400;">OS</span>
        </div>
        <div class="top-nav-actions">
            <button class="nav-btn" onclick="toggleTheme()"><i class="fa-solid fa-moon"></i></button>
            <div class="student-avatar" onclick="window.location.href='profile.php'">
                <?= strtoupper(substr($student['name'] ?? 'S', 0, 2)) ?>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div style="margin-bottom:16px;padding:0 10px;">
            <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Navigation</div>
        </div>
        <a href="dashboard.php" class="menu-item active">
            <i class="fa-solid fa-border-all"></i> Dashboard <span class="shortcut">Alt+D</span>
        </a>
        <a href="subjects.php" class="menu-item">
            <i class="fa-solid fa-book"></i> Subjects <span class="shortcut">Alt+S</span>
        </a>
        <a href="attendance.php" class="menu-item">
            <i class="fa-solid fa-fingerprint"></i> Attendance <span class="shortcut">Alt+A</span>
        </a>
        <a href="profile.php" class="menu-item">
            <i class="fa-solid fa-user"></i> Profile <span class="shortcut">Alt+P</span>
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

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="banner-bg"></div>
                <h1>Welcome back, <span><?= htmlspecialchars(explode(' ', trim($student['name'] ?? 'Student'))[0]) ?></span> 👋</h1>
                <p>Your academic command center is ready. Let's make today productive!</p>
                <div class="welcome-time">
                    <div class="time" id="liveClock">00:00:00</div>
                    <div class="date" id="liveDate">Loading...</div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-value"><?= count($subjects) ?></div>
                    <div class="stat-label">Total Subjects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-value"><?= $attendance_percentage ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                    <span class="stat-trend <?= $attendance_percentage >= 75 ? '' : 'down' ?>">
                        <?= $attendance_percentage >= 75 ? '▲ Good' : '▼ Low' ?>
                    </span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👤</div>
                    <div class="stat-value"><?= $profile_score ?>%</div>
                    <div class="stat-label">Profile Complete</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📌</div>
                    <div class="stat-value"><?= $total_days ?></div>
                    <div class="stat-label">Days Recorded</div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <div>
                    <!-- Attendance Card -->
                    <div class="card mb-16">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-fingerprint"></i> Attendance Overview</h3>
                            <span class="text-sm text-muted">Today: <strong style="color:var(--text);"><?= $today_status ?></strong></span>
                        </div>
                        <div class="attendance-circle-container">
                            <div class="attendance-circle">
                                <canvas id="attendanceCanvas" width="120" height="120"></canvas>
                                <div class="center-text">
                                    <div class="percentage"><?= $attendance_percentage ?>%</div>
                                    <div class="label">Attendance</div>
                                </div>
                            </div>
                            <div class="attendance-stats">
                                <div class="stat-row"><span class="label">Present</span><span class="value present"><?= $present_days ?> days</span></div>
                                <div class="stat-row"><span class="label">Absent</span><span class="value absent"><?= $total_days - $present_days ?> days</span></div>
                                <div class="stat-row"><span class="label">Total Days</span><span class="value"><?= $total_days ?></span></div>
                                <div class="stat-row" style="border-bottom:none;padding-bottom:0;">
                                    <span class="label">Today</span>
                                    <span class="value" style="color:<?= $today_status === 'Present' ? '#10b981' : ($today_status === 'Not marked' ? '#94a3b8' : '#ef4444') ?>;"><?= $today_status ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header"><h3><i class="fa-solid fa-bolt"></i> Quick Actions</h3></div>
                        <div class="quick-actions-grid">
                            <a href="attendance.php" class="quick-action-btn"><i class="fa-solid fa-fingerprint"></i><span>View Attendance</span></a>
                            <a href="subjects.php" class="quick-action-btn"><i class="fa-solid fa-book"></i><span>View Subjects</span></a>
                            <a href="profile.php" class="quick-action-btn"><i class="fa-solid fa-user-edit"></i><span>Update Profile</span></a>
                            <button class="quick-action-btn" onclick="window.print()"><i class="fa-solid fa-print"></i><span>Print Dashboard</span></button>
                        </div>
                    </div>
                </div>

                <!-- QR Code Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-qrcode"></i> My Digital ID</h3>
                    </div>
                    <div style="text-align:center;padding:10px 0;">
                        <p class="text-muted text-sm" style="margin-bottom:12px;">Show this QR to your teacher for attendance</p>
                        <img src="<?= $qr_url ?>" alt="Student QR"
                             style="width:180px;height:180px;border-radius:12px;border:3px solid var(--primary);padding:10px;background:white;transition:var(--transition);">
                        <div class="mt-12">
                            <span class="badge badge-primary">ID: <?= $student['student_id'] ?></span>
                            <span class="badge badge-success"><?= htmlspecialchars($student['name']) ?></span>
                        </div>
                        <button onclick="window.print()" class="btn btn-sm btn-primary mt-12">
                            <i class="fa-solid fa-print"></i> Print ID
                        </button>
                    </div>
                </div>
            </div>

            <!-- Subjects Preview -->
            <div class="card mb-16">
                <div class="card-header">
                    <h3><i class="fa-solid fa-book-open"></i> Your Subjects</h3>
                    <a href="subjects.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Subject Name</th></tr></thead>
                        <tbody>
                            <?php if (count($subjects) > 0): ?>
                                <?php $i = 1; foreach (array_slice($subjects, 0, 5) as $sub): ?>
                                    <tr>
                                        <td class="font-mono" style="color:var(--primary);">SUB-00<?= $i++ ?></td>
                                        <td><?= htmlspecialchars($sub['subject_name'] ?? 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($subjects) > 5): ?>
                                    <tr><td colspan="2" class="text-muted" style="text-align:center;">+ <?= count($subjects) - 5 ?> more</td></tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-muted" style="text-align:center;">No subjects assigned.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Attendance</h3>
                    <a href="attendance.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (count($attendance_records) > 0): ?>
                                <?php foreach (array_slice($attendance_records, 0, 6) as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['date']) ?></td>
                                        <td><span class="status-badge <?= strtolower($record['status']) ?>"><?= ucfirst($record['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($attendance_records) > 6): ?>
                                    <tr><td colspan="2" class="text-muted" style="text-align:center;">+ <?= count($attendance_records) - 6 ?> more</td></tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-muted" style="text-align:center;">No attendance records.</td></tr>
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

        // Live Clock
        function updateClock() {
            const now = new Date();
            const clock = document.getElementById('liveClock');
            const date = document.getElementById('liveDate');
            if (clock) clock.textContent = now.toLocaleTimeString('en-US', { hour12: false });
            if (date) date.textContent = now.toLocaleDateString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Attendance Circle Chart
        const attCanvas = document.getElementById('attendanceCanvas');
        if (attCanvas) {
            const ctx = attCanvas.getContext('2d');
            const percentage = <?= $attendance_percentage ?>;
            const color = percentage >= 75 ? '#10b981' : percentage >= 50 ? '#f59e0b' : '#ef4444';
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent'],
                    datasets: [{
                        data: [percentage, 100 - percentage],
                        backgroundColor: [color, 'rgba(148,163,184,0.15)'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '75%',
                    plugins: { legend: { display: false } },
                    responsive: true,
                    maintainAspectRatio: true
                }
            });
        }

        // Particles
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

        console.log('🎓 Student Dashboard loaded (UI upgraded)');
    </script>

</body>
</html>