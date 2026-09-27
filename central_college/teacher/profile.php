<?php
// teacher/profile.php - Teacher Profile Page
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
    $class_name = 'Not Assigned';
    $grade = 'N/A';

    if ($class_id > 0) {
        $class_stmt = $pdo->prepare("SELECT class_name, grade FROM class WHERE class_id = ?");
        $class_stmt->execute([$class_id]);
        $class_info = $class_stmt->fetch(PDO::FETCH_ASSOC);
        if ($class_info) {
            $class_name = $class_info['class_name'] ?? 'N/A';
            $grade = $class_info['grade'] ?? 'N/A';
        }
    }

    // Get students count
    $students_count = 0;
    if ($class_id > 0) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM student WHERE class_id = ?");
        $count_stmt->execute([$class_id]);
        $students_count = $count_stmt->fetchColumn();
    }

    // Update Profile
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die("CSRF Validation Failed.");
        }

        $new_name = trim(htmlspecialchars($_POST['teacher_name'] ?? ''));
        $new_email = trim(htmlspecialchars($_POST['teacher_email'] ?? ''));
        $new_contact = trim(htmlspecialchars($_POST['teacher_contact'] ?? ''));
        $new_password = trim($_POST['new_password'] ?? '');

        $query = "UPDATE teacher SET name = ?, email = ?, contact = ?";
        $params = [$new_name, $new_email, $new_contact];

        if (!empty($new_password)) {
            $query .= ", password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $query .= " WHERE teacher_id = ?";
        $params[] = $teacher_id;

        $up_stmt = $pdo->prepare($query);
        if ($up_stmt->execute($params)) {
            $_SESSION['teacher_name'] = $new_name;
            $teacher_name = $new_name;
            $toast_msg = "✅ Profile updated successfully!";
            $toast_type = "success";
            $teacher_stmt->execute([$teacher_id]);
            $teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $toast_msg = "❌ Update failed.";
            $toast_type = "error";
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
    <title>👤 Profile | Teacher Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Same CSS as dashboard.php */
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
        .btn-primary:hover { box-shadow: 0 4px 24px var(--primary-glow); }

        .dashboard-grid {
            display: grid; grid-template-columns: 1fr 1.2fr;
            gap: 24px;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; }
        .form-group input {
            width: 100%; padding: 10px 14px; background: var(--glass-bg);
            border: 1px solid var(--border-light); border-radius: var(--radius-sm);
            color: var(--text); font-size: 0.9rem; transition: var(--transition);
            font-family: inherit;
        }
        .form-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }

        .teacher-avatar-lg {
            width: 100px; height: 100px; border-radius: 50%;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 2.5rem; color: white;
            margin: 0 auto 16px; border: 3px solid var(--border-light);
            text-transform: uppercase;
        }

        .badge {
            padding: 3px 12px; border-radius: var(--radius-full);
            font-size: 0.7rem; font-weight: 600; display: inline-block;
        }
        .badge-primary { background: rgba(139,92,246,0.15); color: var(--primary-light); }
        .badge-success { background: rgba(16,185,129,0.15); color: #10b981; }

        .text-center { text-align: center; }
        .text-muted { color: var(--text-muted); }
        .text-sm { font-size: 0.8rem; }
        .mt-12 { margin-top: 12px; }

        .toast {
            padding: 12px 16px; border-radius: var(--radius-sm);
            margin-bottom: 16px; font-weight: 500; font-size: 0.9rem;
            display: flex; align-items: center; gap: 10px;
        }
        .toast.success { background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #34d399; }
        .toast.error { background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #f87171; }

        @media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .top-nav { flex-direction: column; gap: 12px; }
            body { padding: 16px; }
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
        <a href="attendance.php" class="menu-item">
            <i class="fa-solid fa-fingerprint"></i> Attendance <span class="shortcut">Alt+A</span>
        </a>
        <a href="profile.php" class="menu-item active">
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
                <div class="toast <?= $toast_type ?>">
                    <i class="fa-solid <?= $toast_type === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
                    <?= $toast_msg ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="card text-center" style="padding:30px;">
                    <div class="teacher-avatar-lg"><?= strtoupper(substr($teacher_name, 0, 2)) ?></div>
                    <h2><?= htmlspecialchars($teacher_name) ?></h2>
                    <p class="text-muted">Teacher</p>
                    <p class="text-sm text-muted">ID: #<?= $teacher_id ?></p>
                    <?php if ($class_id > 0): ?>
                        <div class="mt-12">
                            <span class="badge badge-primary"><?= $grade ?> - <?= $class_name ?></span>
                            <span class="badge badge-success"><?= $students_count ?> Students</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header"><h3><i class="fa-solid fa-user-edit"></i> Update Profile</h3></div>
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="update_teacher" value="1">
                        <div class="form-group"><label>Full Name</label><input type="text" name="teacher_name" value="<?= htmlspecialchars($teacher_name) ?>" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="teacher_email" value="<?= htmlspecialchars($teacher['email'] ?? '') ?>" placeholder="Enter email"></div>
                        <div class="form-group"><label>Contact</label><input type="text" name="teacher_contact" value="<?= htmlspecialchars($teacher['contact'] ?? '') ?>" placeholder="Enter contact"></div>
                        <div class="form-group" style="border-top:1px solid var(--border);padding-top:16px;margin-top:8px;">
                            <label style="color:#f59e0b;"><i class="fa-solid fa-key"></i> Change Password (optional)</label>
                            <input type="password" name="new_password" placeholder="Enter new password">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;padding:12px;"><i class="fa-solid fa-save"></i> Update Profile</button>
                    </form>
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

        console.log('👤 Profile loaded');
    </script>

</body>
</html>