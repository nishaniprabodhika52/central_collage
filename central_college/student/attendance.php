<?php
// student/attendance.php - Attendance Page
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

try {
    // Fetch student data
    $stmt = $pdo->prepare("SELECT * FROM student WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    // Fetch attendance records
    $att_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 30");
    $att_stmt->execute([$student_id]);
    $attendance_records = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_days = count($attendance_records);
    $present_days = 0;
    foreach ($attendance_records as $r) {
        if (strtolower($r['status']) === 'present') $present_days++;
    }
    $attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100) : 100;

    // Today's attendance
    $today_status = 'Not marked';
    foreach ($attendance_records as $r) {
        if ($r['date'] === date('Y-m-d')) {
            $today_status = ucfirst($r['status']);
            break;
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
    <title>📅 Attendance | Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Same CSS as dashboard.php */
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
        .btn-sm { padding: 5px 14px; font-size: 0.75rem; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table thead th { text-align: left; padding: 12px 10px; color: var(--text-muted); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
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

        .badge-success { background: rgba(16,185,129,0.15); color: #10b981; padding: 3px 12px; border-radius: var(--radius-full); font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .text-muted { color: var(--text-muted); }
        .text-sm { font-size: 0.8rem; }
        .mt-12 { margin-top: 12px; }
        .mb-16 { margin-bottom: 16px; }

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
        .stat-card .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-top: 2px; }

        .chart-container { position: relative; height: 200px; width: 100%; max-width: 300px; margin: 0 auto; }

        @media (max-width: 768px) {
            .top-nav { flex-direction: column; gap: 12px; }
            body { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2,1fr); gap: 12px; }
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
        <a href="dashboard.php" class="menu-item">
            <i class="fa-solid fa-border-all"></i> Dashboard <span class="shortcut">Alt+D</span>
        </a>
        <a href="subjects.php" class="menu-item">
            <i class="fa-solid fa-book"></i> Subjects <span class="shortcut">Alt+S</span>
        </a>
        <a href="attendance.php" class="menu-item active">
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

            <!-- Info Card -->
            <div class="card mb-16" style="border-left: 4px solid var(--primary);">
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                    <i class="fa-solid fa-shield-halved" style="font-size:2.5rem;color:var(--primary);"></i>
                    <div>
                        <h3 style="margin-bottom:4px;">📱 Attendance System</h3>
                        <p class="text-muted">Your attendance is marked securely by your Class Teacher via QR Code scanning.</p>
                        <span class="badge-success mt-12"><i class="fa-solid fa-check-circle"></i> Today: <?= $today_status ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-value"><?= $attendance_percentage ?>%</div><div class="stat-label">Attendance Rate</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?= $present_days ?></div><div class="stat-label">Present Days</div></div>
                <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-value"><?= $total_days ?></div><div class="stat-label">Total Days</div></div>
            </div>

            <!-- Attendance Records -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-list"></i> Attendance History</h3>
                    <span class="text-sm text-muted"><?= $total_days ?> records</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (count($attendance_records) > 0): ?>
                                <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['date'] ?? 'N/A') ?></td>
                                        <td><span class="status-badge <?= strtolower($record['status'] ?? 'not-marked') ?>"><?= ucfirst($record['status'] ?? 'Not marked') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" style="text-align:center;color:var(--text-muted);">No attendance records found.</td></tr>
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

        console.log('📅 Attendance loaded');
    </script>

</body>
</html>