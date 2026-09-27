
 <?php
// teacher/dashboard.php - TEACHER DASHBOARD WITH QR SCAN (FULL FIXED VERSION)
require_once __DIR__ . '/../config/db.php';
session_start();

// ✅ Security: Check if teacher is logged in
if (!isset($_SESSION['teacher_id']) || empty($_SESSION['teacher_id'])) {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
// ✅ ගුරුවරයාගේ සම්පූර්ණ නම session එකෙන් ගන්න
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_class_id = $_SESSION['teacher_class_id'] ?? 0;

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$toast_msg = '';
$toast_type = '';

// Initialize variables
$students = [];
$total_students = 0;
$male_count = 0;
$female_count = 0;
$attendance_records = [];
$attendance_percentage = 0;
$present_days = 0;
$total_attendance = 0;
$class_name = 'Not Assigned';
$grade = 'N/A';
$today_status = 'Not marked';

try {
    // =============================================================
    // GET TEACHER INFO
    // =============================================================
    $teacher_stmt = $pdo->prepare("SELECT * FROM teacher WHERE teacher_id = ?");
    $teacher_stmt->execute([$teacher_id]);
    $teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    // Get class name from class table
    $class_id = $teacher['class_id'] ?? 0;
    if ($class_id > 0) {
        $class_stmt = $pdo->prepare("SELECT class_name, grade FROM class WHERE class_id = ?");
        $class_stmt->execute([$class_id]);
        $class_info = $class_stmt->fetch(PDO::FETCH_ASSOC);
        if ($class_info) {
            $class_name = $class_info['class_name'] ?? 'N/A';
            $grade = $class_info['grade'] ?? 'N/A';
        }
    }

    // =============================================================
    // GET STUDENTS FOR THIS TEACHER'S CLASS
    // =============================================================
    if ($class_id > 0) {
        $students_stmt = $pdo->prepare("
            SELECT s.*, c.class_name, c.grade 
            FROM student s
            LEFT JOIN class c ON s.class_id = c.class_id
            WHERE s.class_id = ?
            ORDER BY s.name ASC
        ");
        $students_stmt->execute([$class_id]);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_students = count($students);

        // Gender breakdown
        foreach ($students as $s) {
            if ($s['gender'] === 'Male') $male_count++;
            else $female_count++;
        }

        // =============================================================
        // GET ATTENDANCE RECORDS
        // =============================================================
        $att_stmt = $pdo->prepare("
            SELECT a.*, s.name as student_name 
            FROM attendance a
            JOIN student s ON a.student_id = s.student_id
            WHERE s.class_id = ?
            ORDER BY a.date DESC LIMIT 50
        ");
        $att_stmt->execute([$class_id]);
        $attendance_records = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate attendance stats
        $total_attendance = count($attendance_records);
        $present_days = 0;
        foreach ($attendance_records as $r) {
            if (strtolower($r['status']) === 'present') $present_days++;
        }
        $attendance_percentage = $total_attendance > 0 ? round(($present_days / $total_attendance) * 100) : 0;

        // =============================================================
        // TODAY'S ATTENDANCE STATUS
        // =============================================================
        $today = date('Y-m-d');
        $today_status = 'Not marked';
        foreach ($attendance_records as $r) {
            if ($r['date'] === $today) {
                $today_status = ucfirst($r['status']);
                break;
            }
        }

        // =============================================================
        // MARK INDIVIDUAL ATTENDANCE
        // =============================================================
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
            if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                die("CSRF Validation Failed.");
            }

            $student_id = (int)($_POST['student_id'] ?? 0);
            $status = $_POST['attendance_status'] ?? 'present';
            $date = date('Y-m-d');

            if ($student_id > 0) {
                $check_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
                $check_stmt->execute([$student_id, $date]);
                $existing = $check_stmt->fetch();

                if ($existing) {
                    $upd_stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND date = ?");
                    $upd_stmt->execute([$status, $student_id, $date]);
                } else {
                    $ins_stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, class_id) VALUES (?, ?, ?, ?)");
                    $ins_stmt->execute([$student_id, $date, $status, $class_id]);
                }

                $toast_msg = "✅ Attendance marked for student!";
                $toast_type = "success";
                // Refresh attendance
                $att_stmt->execute([$class_id]);
                $attendance_records = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_attendance = count($attendance_records);
                $present_days = 0;
                foreach ($attendance_records as $r) {
                    if (strtolower($r['status']) === 'present') $present_days++;
                }
                $attendance_percentage = $total_attendance > 0 ? round(($present_days / $total_attendance) * 100) : 0;
            }
        }

        // =============================================================
        // BULK ATTENDANCE
        // =============================================================
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_attendance'])) {
            if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                die("CSRF Validation Failed.");
            }

            $status = $_POST['bulk_status'] ?? 'present';
            $date = date('Y-m-d');

            $class_students = $pdo->prepare("SELECT student_id FROM student WHERE class_id = ?");
            $class_students->execute([$class_id]);
            $student_ids = $class_students->fetchAll(PDO::FETCH_COLUMN);

            foreach ($student_ids as $sid) {
                $check_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
                $check_stmt->execute([$sid, $date]);
                $existing = $check_stmt->fetch();

                if ($existing) {
                    $upd_stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND date = ?");
                    $upd_stmt->execute([$status, $sid, $date]);
                } else {
                    $ins_stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, class_id) VALUES (?, ?, ?, ?)");
                    $ins_stmt->execute([$sid, $date, $status, $class_id]);
                }
            }

            $toast_msg = "✅ Bulk attendance marked for all students!";
            $toast_type = "success";
            $att_stmt->execute([$class_id]);
            $attendance_records = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_attendance = count($attendance_records);
            $present_days = 0;
            foreach ($attendance_records as $r) {
                if (strtolower($r['status']) === 'present') $present_days++;
            }
            $attendance_percentage = $total_attendance > 0 ? round(($present_days / $total_attendance) * 100) : 0;
        }

        // =============================================================
        // UPDATE TEACHER PROFILE
        // =============================================================
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
            }
        }

    } else {
        // No class assigned
        $today_status = 'No class';
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
    <title>👨‍🏫 Teacher Panel | Central College</title>
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
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            padding: 12px 30px; background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border-bottom: 1px solid var(--glass-border);
            display: flex; justify-content: space-between; align-items: center;
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
            position: relative; font-size: 1rem;
        }
        .nav-btn:hover { background: var(--primary); border-color: var(--primary);
            transform: scale(1.05); box-shadow: 0 0 20px var(--primary-glow); }
        .nav-btn .badge {
            position: absolute; top: -5px; right: -5px;
            background: #ef4444; color: white; font-size: 0.6rem;
            font-weight: 700; padding: 2px 6px; border-radius: var(--radius-full);
            min-width: 18px; text-align: center;
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.2); } }
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
            position: fixed; top: 70px; left: 0; bottom: 0; width: 220px;
            background: var(--glass-bg); backdrop-filter: var(--backdrop-blur);
            border-right: 1px solid var(--glass-border);
            padding: 20px 15px; z-index: 50; transition: var(--transition);
            overflow-y: auto;
        }
        .sidebar::-webkit-scrollbar { width: 3px; }
        .menu-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; color: var(--text-muted);
            text-decoration: none; border-radius: var(--radius-sm);
            font-weight: 500; font-size: 0.9rem;
            transition: var(--transition); margin-bottom: 4px; cursor: pointer;
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
            margin-top: auto; padding-top: 12px;
            border-top: 1px solid var(--border);
            font-size: 0.7rem; color: var(--text-muted); text-align: center;
        }
        .sidebar-toggle { display: none; background: none; border: none; color: var(--text); font-size: 1.4rem; cursor: pointer; }

        .main-content {
            margin-left: 220px; margin-top: 70px; padding: 30px;
            min-height: calc(100vh - 70px); position: relative; z-index: 1;
            transition: var(--transition);
        }
        .page-content { animation: pageFadeIn 0.6s ease-out; }
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .toast-container {
            position: fixed; top: 80px; right: 30px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px; max-width: 400px; width: 100%;
        }
        .toast {
            padding: 16px 20px; border-radius: var(--radius-sm);
            backdrop-filter: var(--backdrop-blur); border: 1px solid var(--glass-border);
            box-shadow: var(--shadow); animation: slideInRight 0.5s ease-out;
            display: flex; align-items: center; gap: 12px;
            font-weight: 500; font-size: 0.9rem;
        }
        .toast.success { background: rgba(16,185,129,0.2); border-color: #10b981; color: #34d399; }
        .toast.error { background: rgba(239,68,68,0.2); border-color: #ef4444; color: #f87171; }
        .toast.warning { background: rgba(245,158,11,0.2); border-color: #f59e0b; color: #fbbf24; }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .toast .toast-close { margin-left: auto; background: none; border: none; color: currentColor; cursor: pointer; opacity: 0.6; font-size: 1.1rem; transition: var(--transition); }
        .toast .toast-close:hover { opacity: 1; }

        .welcome-banner {
            background: var(--glass-bg); backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border); border-radius: var(--radius);
            padding: 28px 32px; margin-bottom: 28px; position: relative; overflow: hidden;
        }
        .welcome-banner .banner-bg {
            position: absolute; top: -50%; right: -10%; width: 400px; height: 400px;
            background: var(--gradient-primary); border-radius: 50%;
            opacity: 0.08; filter: blur(60px);
        }
        .welcome-banner h1 { font-size: 1.8rem; font-weight: 800; position: relative; z-index: 1; }
        .welcome-banner h1 span {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .welcome-banner p { color: var(--text-muted); margin-top: 4px; position: relative; z-index: 1; }
        .welcome-banner .class-info {
            display: inline-block; background: var(--glass-bg); padding: 4px 16px;
            border-radius: var(--radius-full); margin-top: 8px; font-size: 0.85rem;
            border: 1px solid var(--border-light);
        }
        .welcome-banner .class-info i { color: var(--primary); margin-right: 6px; }
        .welcome-banner .welcome-time {
            position: absolute; right: 30px; top: 50%; transform: translateY(-50%);
            text-align: right; z-index: 1;
        }
        .welcome-banner .welcome-time .time {
            font-size: 2rem; font-weight: 800; font-family: 'Courier New', monospace;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .welcome-banner .welcome-time .date { font-size: 0.8rem; color: var(--text-muted); }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }
        .stat-card .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-top: 2px; }
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
        .btn-success { background: var(--gradient-success); color: white; border: none; }
        .btn-danger { background: var(--gradient-danger); color: white; border: none; }
        .btn-warning { background: var(--gradient-warning); color: white; border: none; }
        .btn-sm { padding: 5px 14px; font-size: 0.75rem; }
        .btn-icon { width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .btn-block { width: 100%; justify-content: center; padding: 12px; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table thead th {
            text-align: left; padding: 12px 10px; color: var(--text-muted);
            font-weight: 600; font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1px solid var(--border);
        }
        table tbody td { padding: 12px 10px; border-bottom: 1px solid var(--border); color: var(--text-light); }
        table tbody tr:hover { background: var(--glass-bg); }

        .badge {
            padding: 3px 12px; border-radius: var(--radius-full);
            font-size: 0.7rem; font-weight: 600; display: inline-block;
        }
        .badge-primary { background: rgba(139,92,246,0.15); color: var(--primary-light); }
        .badge-success { background: rgba(16,185,129,0.15); color: #10b981; }
        .badge-danger { background: rgba(239,68,68,0.15); color: #ef4444; }
        .badge-warning { background: rgba(245,158,11,0.15); color: #f59e0b; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; background: var(--glass-bg);
            border: 1px solid var(--border-light); border-radius: var(--radius-sm);
            color: var(--text); font-size: 0.9rem; transition: var(--transition);
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .dashboard-grid {
            display: grid; grid-template-columns: 1.6fr 1fr; gap: 24px; margin-bottom: 24px;
        }

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
            background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .quick-action-btn span { font-size: 0.75rem; font-weight: 500; }

        .quick-action-btn.qr-scan {
            background: var(--gradient-primary);
            color: white;
            border: none;
        }
        .quick-action-btn.qr-scan i {
            -webkit-text-fill-color: white;
            background: none;
        }

        .fab {
            position: fixed; bottom: 30px; right: 30px; z-index: 99;
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--gradient-primary); border: none; color: white;
            font-size: 1.4rem; cursor: pointer; box-shadow: 0 4px 24px var(--primary-glow);
            transition: var(--transition); display: flex; align-items: center; justify-content: center;
        }
        .fab:hover { transform: scale(1.1) rotate(90deg); }
        .fab-menu {
            position: fixed; bottom: 100px; right: 30px; z-index: 98;
            display: flex; flex-direction: column; gap: 10px;
            opacity: 0; pointer-events: none; transform: translateY(20px) scale(0.8);
            transition: var(--transition);
        }
        .fab-menu.open { opacity: 1; pointer-events: all; transform: translateY(0) scale(1); }
        .fab-menu .fab-item {
            width: 44px; height: 44px; border-radius: 50%; border: none;
            color: white; font-size: 1rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition); box-shadow: var(--shadow);
            text-decoration: none;
        }
        .fab-menu .fab-item:hover { transform: scale(1.1); }

        .shortcuts-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(10px);
            z-index: 999; display: none; align-items: center; justify-content: center;
        }
        .shortcuts-overlay.open { display: flex; }
        .shortcuts-box {
            background: var(--bg-card); border: 1px solid var(--border-light);
            border-radius: var(--radius); padding: 40px;
            max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;
        }
        .shortcuts-box h2 { margin-bottom: 20px; font-size: 1.4rem; }
        .shortcuts-box .shortcut-row {
            display: flex; justify-content: space-between; padding: 8px 0;
            border-bottom: 1px solid var(--border); font-size: 0.9rem;
        }
        .shortcuts-box .shortcut-row kbd {
            background: var(--glass-bg); padding: 2px 10px; border-radius: 4px;
            font-family: monospace; font-size: 0.8rem; border: 1px solid var(--border-light);
        }

        @media (max-width: 1024px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; top: 70px; }
            .sidebar.open { transform: translateX(0); }
            .sidebar-toggle { display: block; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav { padding: 10px 16px; }
            .top-nav .brand { font-size: 1rem; }
            .welcome-banner .welcome-time { display: none; }
            .welcome-banner h1 { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: repeat(2,1fr); gap: 12px; }
            .quick-actions-grid { grid-template-columns: 1fr 1fr; }
            .toast-container { top: 70px; right: 10px; left: 10px; max-width: 100%; }
            .stat-card .stat-value { font-size: 1.4rem; }
            .fab { bottom: 16px; right: 16px; width: 48px; height: 48px; font-size: 1.2rem; }
            .fab-menu { bottom: 80px; right: 16px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 14px; }
            .stat-card .stat-value { font-size: 1.2rem; }
            .card { padding: 16px; }
            .top-nav .brand { font-size: 0.85rem; }
            .nav-btn { width: 34px; height: 34px; font-size: 0.8rem; }
            .teacher-avatar { width: 34px; height: 34px; font-size: 0.8rem; }
            .quick-actions-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .quick-action-btn { padding: 12px; }
            .quick-action-btn i { font-size: 1.2rem; }
            .quick-action-btn span { font-size: 0.65rem; }
        }

        .mt-12 { margin-top: 12px; }
        .mt-16 { margin-top: 16px; }
        .mt-20 { margin-top: 20px; }
        .mb-12 { margin-bottom: 12px; }
        .mb-16 { margin-bottom: 16px; }
        .mb-20 { margin-bottom: 20px; }
        .text-center { text-align: center; }
        .text-muted { color: var(--text-muted); }
        .text-sm { font-size: 0.8rem; }
        .text-xs { font-size: 0.7rem; }
        .font-mono { font-family: 'Courier New', monospace; }
        .w-full { width: 100%; }

        @media print {
            .top-nav, .sidebar, .fab, .fab-menu, .toast-container,
            .bg-gradient-overlay, .orb, #particles-container { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .card { break-inside: avoid; border: 1px solid #ddd; background: white !important; backdrop-filter: none !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
</head>
<body>

    <div class="bg-gradient-overlay"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div id="particles-container"></div>

    <div class="toast-container" id="toastContainer">
        <?php if (!empty($toast_msg)): ?>
            <div class="toast <?= $toast_type ?>">
                <span><?= $toast_msg ?></span>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>
    </div>

    <div class="shortcuts-overlay" id="shortcutsOverlay">
        <div class="shortcuts-box">
            <div class="flex-between">
                <h2><i class="fa-solid fa-keyboard"></i> Keyboard Shortcuts</h2>
                <button onclick="closeShortcuts()" style="background:none;border:none;color:var(--text);font-size:1.5rem;cursor:pointer;">&times;</button>
            </div>
            <div class="shortcut-row"><span>Dashboard</span> <kbd>Alt + D</kbd></div>
            <div class="shortcut-row"><span>Students</span> <kbd>Alt + S</kbd></div>
            <div class="shortcut-row"><span>Attendance</span> <kbd>Alt + A</kbd></div>
            <div class="shortcut-row"><span>Profile</span> <kbd>Alt + P</kbd></div>
            <div class="shortcut-row"><span>Toggle Theme</span> <kbd>Alt + T</kbd></div>
            <div class="shortcut-row"><span>Fullscreen</span> <kbd>Alt + F</kbd></div>
            <div class="shortcut-row"><span>Logout</span> <kbd>Alt + L</kbd></div>
            <div class="shortcut-row"><span>Close</span> <kbd>Esc</kbd></div>
            <button onclick="closeShortcuts()" class="btn btn-primary w-full mt-16" style="justify-content:center;">Close</button>
        </div>
    </div>

    <nav class="top-nav">
        <div style="display:flex;align-items:center;gap:14px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
            <div class="brand">
                <i class="fa-solid fa-chalkboard-user"></i>
                Teacher <span style="-webkit-text-fill-color:var(--text-muted);font-weight:400;">Hub</span>
            </div>
        </div>
        <div class="top-nav-actions">
            <button class="nav-btn" onclick="toggleTheme()" id="themeToggle">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>
            <button class="nav-btn" onclick="toggleFullscreen()"><i class="fa-solid fa-expand"></i></button>
            <button class="nav-btn" onclick="openShortcuts()"><i class="fa-solid fa-keyboard"></i></button>
            <button class="nav-btn" id="notificationBtn">
                <i class="fa-solid fa-bell"></i>
                <span class="badge"><?= $total_students ?></span>
            </button>
            <div class="teacher-avatar" onclick="window.location.href='?page=profile'">
                <?= strtoupper(substr($teacher_name, 0, 2)) ?>
            </div>
        </div>
    </nav>

    <aside class="sidebar" id="sidebar">
        <div style="margin-bottom:16px;padding:0 10px;">
            <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Navigation</div>
        </div>
        <a href="?page=dashboard" class="menu-item <?= $page === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i> Dashboard <span class="shortcut">Alt+D</span>
        </a>
        <a href="?page=students" class="menu-item <?= $page === 'students' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> Students <span class="shortcut">Alt+S</span>
        </a>
        <a href="?page=attendance" class="menu-item <?= $page === 'attendance' ? 'active' : '' ?>">
            <i class="fa-solid fa-fingerprint"></i> Attendance <span class="shortcut">Alt+A</span>
        </a>
        <a href="?page=profile" class="menu-item <?= $page === 'profile' ? 'active' : '' ?>">
            <i class="fa-solid fa-user-gear"></i> Profile <span class="shortcut">Alt+P</span>
        </a>
        <div class="menu-divider"></div>
        <a href="../logout.php" class="menu-item" style="color:#ef4444;">
            <i class="fa-solid fa-power-off"></i> Logout <span class="shortcut">Alt+L</span>
        </a>
        <div class="sidebar-footer">
            <div style="font-size:0.6rem;opacity:0.5;">v5.0 • Secure Session</div>
            <?php if ($class_id > 0): ?>
                <div style="font-size:0.6rem;opacity:0.3;margin-top:4px;"><?= $grade ?> - <?= $class_name ?></div>
            <?php endif; ?>
        </div>
    </aside>

    <main class="main-content" id="mainContent">

        <?php if ($page === 'dashboard'): ?>
            <div class="page-content">
                <div class="welcome-banner">
                    <div class="banner-bg"></div>
                    <!-- ✅ FIXED: සම්පූර්ණ teacher name එක display කරන්න -->
                    <h1>Welcome back, <span><?= htmlspecialchars($teacher_name) ?></span> 👨‍🏫</h1>
                    <p>Your teaching command center is ready.</p>
                    <div class="class-info">
                        <i class="fa-solid fa-users"></i> <?= $total_students ?> Students
                        <?php if ($class_id > 0): ?>
                            <i class="fa-solid fa-school" style="margin-left:16px;"></i> <?= $grade ?> - <?= $class_name ?>
                        <?php endif; ?>
                        <i class="fa-solid fa-calendar" style="margin-left:16px;"></i> <?= date('F j, Y') ?>
                    </div>
                    <div class="welcome-time">
                        <div class="time" id="liveClock">00:00:00</div>
                        <div class="date" id="liveDate">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🎓</div>
                        <div class="stat-value"><?= $total_students ?></div>
                        <div class="stat-label">My Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👨</div>
                        <div class="stat-value"><?= $male_count ?></div>
                        <div class="stat-label">Male Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👩</div>
                        <div class="stat-value"><?= $female_count ?></div>
                        <div class="stat-label">Female Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <div class="stat-value"><?= $attendance_percentage ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                        <span class="stat-trend <?= $attendance_percentage >= 75 ? '' : 'down' ?>">
                            <?= $attendance_percentage >= 75 ? '▲ Good' : '▼ Needs improvement' ?>
                        </span>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-clock-rotate-left"></i> My Students</h3>
                            <a href="?page=students" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Today</th></tr></thead>
                                <tbody>
                                    <?php if ($total_students > 0): ?>
                                        <?php foreach (array_slice($students, 0, 6) as $row):
                                            $today_att = 'Not marked';
                                            foreach ($attendance_records as $r) {
                                                if ($r['student_id'] == $row['student_id'] && $r['date'] === date('Y-m-d')) {
                                                    $today_att = ucfirst($r['status']);
                                                    break;
                                                }
                                            }
                                            $badge_class = $today_att === 'Present' ? 'badge-success' : ($today_att === 'Not marked' ? 'badge-warning' : 'badge-danger');
                                        ?>
                                            <tr>
                                                <td class="font-mono text-muted text-sm">STU-00<?= $row['student_id'] ?></td>
                                                <td><?= htmlspecialchars($row['name']) ?></td>
                                                <td><span class="badge <?= ($row['gender'] ?? '') === 'Male' ? 'badge-primary' : 'badge-success' ?>"><?= htmlspecialchars($row['gender'] ?? 'N/A') ?></span></td>
                                                <td><span class="badge <?= $badge_class ?>"><?= $today_att ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if ($total_students > 6): ?>
                                            <tr><td colspan="4" class="text-center text-muted text-sm">+ <?= $total_students - 6 ?> more</td></tr>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">No students assigned.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="card mb-16">
                            <div class="card-header"><h3><i class="fa-solid fa-bolt"></i> Quick Actions</h3></div>
                            <div class="quick-actions-grid">
                                <a href="../admin/scan_attendance.php" class="quick-action-btn qr-scan">
                                    <i class="fa-solid fa-qrcode"></i>
                                    <span>📱 Scan QR</span>
                                </a>
                                <a href="?page=attendance" class="quick-action-btn">
                                    <i class="fa-solid fa-fingerprint"></i>
                                    <span>Mark Attendance</span>
                                </a>
                                <a href="?page=attendance#bulkAttendance" class="quick-action-btn">
                                    <i class="fa-solid fa-users"></i>
                                    <span>Bulk Attendance</span>
                                </a>
                                <a href="?page=profile" class="quick-action-btn">
                                    <i class="fa-solid fa-user-edit"></i>
                                    <span>Update Profile</span>
                                </a>
                                <a href="attendance_report.php" class="quick-action-btn" style="background:var(--gradient-primary);color:white;border:none;">
                                    <i class="fa-solid fa-chart-bar" style="-webkit-text-fill-color:white;background:none;"></i>
                                    <span>Attendance Report</span>
                                </a>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h3><i class="fa-solid fa-calendar-day"></i> Today's Status</h3></div>
                            <div style="text-align:center;padding:10px 0;">
                                <div style="font-size:2.5rem;font-weight:800;background:var(--gradient-primary);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                                    <?= $today_status ?>
                                </div>
                                <p class="text-muted" style="font-size:0.85rem;"><?= date('l, F j, Y') ?></p>
                                <?php if ($total_students > 0): ?>
                                    <div style="margin-top:12px;">
                                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> <?= $present_days ?> Present</span>
                                        <span class="badge badge-danger"><i class="fa-solid fa-times"></i> <?= $total_attendance - $present_days ?> Absent</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'students'): ?>
            <div class="page-content">
                <div class="card mb-20">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-users"></i> My Students</h3>
                        <span class="text-muted text-sm">Total: <?= $total_students ?></span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>ID</th><th>Name</th><th>Gender</th><th>Contact</th></tr>
                            </thead>
                            <tbody>
                                <?php if ($total_students > 0): ?>
                                    <?php foreach ($students as $row): ?>
                                        <tr>
                                            <td class="font-mono text-muted text-sm">STU-00<?= $row['student_id'] ?></td>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td><span class="badge <?= ($row['gender'] ?? '') === 'Male' ? 'badge-primary' : 'badge-success' ?>"><?= htmlspecialchars($row['gender'] ?? 'N/A') ?></span></td>
                                            <td><?= htmlspecialchars($row['contact'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">No students assigned to your class.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card" style="border-left:4px solid var(--primary);">
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <i class="fa-solid fa-qrcode" style="font-size:2.5rem;color:var(--primary);"></i>
                        <div>
                            <h3 style="margin-bottom:4px;">📱 QR Attendance System</h3>
                            <p class="text-muted">Ask students to show their QR code from their profile. Then click <strong>"Scan QR"</strong> in Quick Actions to mark attendance.</p>
                            <a href="../admin/scan_attendance.php" class="btn btn-primary mt-12"><i class="fa-solid fa-qrcode"></i> Open Scanner</a>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'attendance'): ?>
            <div class="page-content">
                <div class="card mb-20" id="bulkAttendance">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-users"></i> Bulk Attendance Marking</h3>
                        <span class="text-muted text-sm">Today: <?= date('F j, Y') ?></span>
                    </div>
                    <form method="POST" action="?page=attendance" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="bulk_attendance" value="1">
                        <select name="bulk_status" style="padding:10px 16px;background:var(--glass-bg);border:1px solid var(--border-light);border-radius:var(--radius-sm);color:var(--text);">
                            <option value="present">✅ Present All</option>
                            <option value="absent">❌ Absent All</option>
                            <option value="late">⏰ Late All</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Mark All <?= $total_students ?> Students</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-user-check"></i> Individual Attendance</h3>
                        <a href="../admin/scan_attendance.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-qrcode"></i> Scan QR</a>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Student</th><th>Today's Status</th><th>Mark Attendance</th></tr></thead>
                            <tbody>
                                <?php if ($total_students > 0): ?>
                                    <?php foreach ($students as $row): 
                                        $today_att = 'Not marked';
                                        foreach ($attendance_records as $r) {
                                            if ($r['student_id'] == $row['student_id'] && $r['date'] === date('Y-m-d')) {
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
                                                <form method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="student_id" value="<?= $row['student_id'] ?>">
                                                    <input type="hidden" name="mark_attendance" value="1">
                                                    <select name="attendance_status" style="padding:6px 12px;background:var(--glass-bg);border:1px solid var(--border-light);border-radius:var(--radius-sm);color:var(--text);font-size:0.8rem;">
                                                        <option value="present">✅ Present</option>
                                                        <option value="absent">❌ Absent</option>
                                                        <option value="late">⏰ Late</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">Mark</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No students assigned.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'profile'): ?>
            <div class="page-content">
                <div class="dashboard-grid">
                    <div class="card text-center" style="padding:30px;">
                        <div class="teacher-avatar" style="width:100px;height:100px;font-size:2.5rem;margin:0 auto 16px;">
                            <?= strtoupper(substr($teacher_name, 0, 2)) ?>
                        </div>
                        <h2><?= htmlspecialchars($teacher_name) ?></h2>
                        <p class="text-muted">Teacher</p>
                        <p class="text-sm text-muted">ID: #<?= $teacher_id ?></p>
                        <?php if ($class_id > 0): ?>
                            <div class="mt-12">
                                <span class="badge badge-primary"><?= $grade ?> - <?= $class_name ?></span>
                                <span class="badge badge-success"><?= $total_students ?> Students</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3><i class="fa-solid fa-user-edit"></i> Update Profile</h3></div>
                        <form method="POST" action="?page=profile">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="update_teacher" value="1">
                            <div class="form-group"><label>Full Name</label><input type="text" name="teacher_name" value="<?= htmlspecialchars($teacher_name) ?>" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="teacher_email" value="<?= htmlspecialchars($teacher['email'] ?? '') ?>" placeholder="Enter email"></div>
                            <div class="form-group"><label>Contact</label><input type="text" name="teacher_contact" value="<?= htmlspecialchars($teacher['contact'] ?? '') ?>" placeholder="Enter contact"></div>
                            <div class="form-group" style="border-top:1px solid var(--border);padding-top:16px;margin-top:8px;">
                                <label style="color:#f59e0b;"><i class="fa-solid fa-key"></i> Change Password (optional)</label>
                                <input type="password" name="new_password" placeholder="Enter new password">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-save"></i> Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <button class="fab" id="fab" onclick="toggleFabMenu()"><i class="fa-solid fa-plus"></i></button>
    <div class="fab-menu" id="fabMenu">
        <a href="../admin/scan_attendance.php" class="fab-item" style="background:#8b5cf6;" title="Scan QR"><i class="fa-solid fa-qrcode"></i></a>
        <a href="?page=attendance" class="fab-item" style="background:#10b981;" title="Mark Attendance"><i class="fa-solid fa-fingerprint"></i></a>
        <a href="?page=attendance#bulkAttendance" class="fab-item" style="background:#f59e0b;" title="Bulk Attendance"><i class="fa-solid fa-users"></i></a>
        <a href="../logout.php" class="fab-item" style="background:#ef4444;" title="Logout"><i class="fa-solid fa-power-off"></i></a>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const target = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', target);
            document.getElementById('themeIcon').className = target === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
            localStorage.setItem('theme', target);
        }
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
            document.getElementById('themeIcon').className = 'fa-solid fa-sun';
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });

        function toggleFullscreen() {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen();
            else if (document.exitFullscreen) document.exitFullscreen();
        }

        function updateClock() {
            const now = new Date();
            const clock = document.getElementById('liveClock');
            const date = document.getElementById('liveDate');
            if (clock) clock.textContent = now.toLocaleTimeString('en-US', { hour12: false });
            if (date) date.textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        updateClock();
        setInterval(updateClock, 1000);

        let fabOpen = false;
        function toggleFabMenu() {
            fabOpen = !fabOpen;
            document.getElementById('fabMenu').classList.toggle('open', fabOpen);
            document.getElementById('fab').querySelector('i').className = fabOpen ? 'fa-solid fa-times' : 'fa-solid fa-plus';
        }
        document.addEventListener('click', function(e) {
            const fab = document.getElementById('fab');
            const menu = document.getElementById('fabMenu');
            if (!fab.contains(e.target) && !menu.contains(e.target)) {
                fabOpen = false;
                menu.classList.remove('open');
                fab.querySelector('i').className = 'fa-solid fa-plus';
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                e.preventDefault();
                switch (e.key.toLowerCase()) {
                    case 'd': window.location.href = '?page=dashboard'; break;
                    case 's': window.location.href = '?page=students'; break;
                    case 'a': window.location.href = '?page=attendance'; break;
                    case 'p': window.location.href = '?page=profile'; break;
                    case 't': toggleTheme(); break;
                    case 'f': toggleFullscreen(); break;
                    case 'l': window.location.href = '../logout.php'; break;
                }
            }
            if (e.key === 'Escape') closeShortcuts();
        });

        function openShortcuts() { document.getElementById('shortcutsOverlay').classList.add('open'); }
        function closeShortcuts() { document.getElementById('shortcutsOverlay').classList.remove('open'); }

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

        document.querySelectorAll('.toast').forEach(function(toast) {
            setTimeout(function() {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(50px)';
                setTimeout(function() { toast.remove(); }, 400);
            }, 5000);
        });

        document.getElementById('notificationBtn')?.addEventListener('click', function() {
            alert('🔔 Class Summary:\n\n📚 Class: <?= $grade ?> - <?= $class_name ?>\n🎓 Students: <?= $total_students ?>\n📊 Attendance: <?= $attendance_percentage ?>%');
        });

        console.log('%c👨‍🏫 Teacher Dashboard v5.0 (Fixed)', 'font-size:24px;font-weight:800;color:#8b5cf6;');
        console.log('%c🚀 Welcome, <?= addslashes($teacher_name) ?>', 'font-size:14px;color:#94a3b8;');

        let typed = '';
        document.addEventListener('keydown', function(e) {
            if (e.key.length === 1) {
                typed += e.key.toLowerCase();
                if (typed.length > 6) typed = typed.substring(1);
                if (typed === 'matrix') {
                    typed = '';
                    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789$+-*/=%"\'#&_(),.;:?!\\|{}<>[]^~';
                    let div = document.createElement('div');
                    div.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.85);display:flex;justify-content:center;align-items:center;flex-direction:column;pointer-events:none;';
                    let text = '';
                    for (let i = 0; i < 50; i++) text += chars[Math.floor(Math.random() * chars.length)];
                    div.innerHTML = `
                        <div style="font-family:monospace;font-size:3rem;color:#0f0;text-shadow:0 0 20px #0f0;animation:matrixRain 1.5s linear;text-align:center;word-break:break-all;max-width:90%;">${text}</div>
                        <div style="font-family:monospace;font-size:1.2rem;color:#0f0;margin-top:20px;opacity:0.7;">█ MATRIX LOADED █</div>
                        <style>@keyframes matrixRain { 0% { opacity:0; transform:scale(0.5) rotateX(90deg); } 50% { opacity:1; transform:scale(1.2) rotateX(0); } 100% { opacity:1; transform:scale(1) rotateX(0); } }</style>
                    `;
                    document.body.appendChild(div);
                    setTimeout(function() {
                        div.style.opacity = '0';
                        div.style.transition = 'opacity 0.5s';
                        setTimeout(function() { div.remove(); }, 600);
                    }, 3000);
                }
            }
        });

        console.log('✅ All systems ready!');
    </script>

</body>
</html>