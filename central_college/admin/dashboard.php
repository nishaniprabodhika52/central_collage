<?php
// admin/dashboard.php - PRINCIPAL DASHBOARD (with Working Days Integration)
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['principal_id']) || empty($_SESSION['principal_id'])) {
    header("Location: ../login.php");
    exit();
}

$principal_id = $_SESSION['principal_id'];
$principal_name = $_SESSION['principal_name'] ?? 'Principal';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$toast_msg = '';
$toast_type = '';

try {
    // Stats
    $total_students = $pdo->query("SELECT COUNT(*) FROM student")->fetchColumn();
    $total_teachers = $pdo->query("SELECT COUNT(*) FROM teacher")->fetchColumn();
    $total_classes = $pdo->query("SELECT COUNT(*) FROM class")->fetchColumn();
    $total_subjects = $pdo->query("SELECT COUNT(*) FROM subject")->fetchColumn();
    $total_principals = $pdo->query("SELECT COUNT(*) FROM principal")->fetchColumn();
    $total_holidays = $pdo->query("SELECT COUNT(*) FROM holidays")->fetchColumn();

    $male_count = $pdo->query("SELECT COUNT(*) FROM student WHERE gender = 'Male'")->fetchColumn();
    $female_count = $pdo->query("SELECT COUNT(*) FROM student WHERE gender = 'Female'")->fetchColumn();

    $classes_list = $pdo->query("SELECT class_id, class_name, grade FROM class ORDER BY grade ASC")->fetchAll(PDO::FETCH_ASSOC);

    $recent_stmt = $pdo->query("
        SELECT s.*, c.class_name, c.grade 
        FROM student s 
        LEFT JOIN class c ON s.class_id = c.class_id 
        ORDER BY s.student_id DESC LIMIT 8
    ");
    $recent_students = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filter_class = isset($_GET['filter_class']) ? $_GET['filter_class'] : '';

    $sql = "SELECT s.*, c.class_name, c.grade FROM student s LEFT JOIN class c ON s.class_id = c.class_id WHERE 1=1";
    $params = [];

    if (!empty($search_query)) {
        $sql .= " AND (s.name LIKE ? OR s.student_id LIKE ? OR s.contact LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    if (!empty($filter_class)) {
        $sql .= " AND s.class_id = ?";
        $params[] = $filter_class;
    }
    $sql .= " ORDER BY s.student_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $principals_stmt = $pdo->query("SELECT p_id, username, name, email, contact, role FROM principal ORDER BY p_id");
    $principals = $principals_stmt->fetchAll(PDO::FETCH_ASSOC);

    $teachers_stmt = $pdo->query("SELECT * FROM teacher ORDER BY name");
    $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Current principal data
    $curr_pr_stmt = $pdo->prepare("SELECT * FROM principal WHERE p_id = ?");
    $curr_pr_stmt->execute([$principal_id]);
    $principal = $curr_pr_stmt->fetch(PDO::FETCH_ASSOC);

    // =============================================================
    // Working Days Info
    // =============================================================
    $today = date('Y-m-d');
    $is_today_working = isWorkingDay($today, $pdo);
    $today_reason = !$is_today_working ? getNonWorkingReason($today, $pdo) : '';
    $current_working_days = getWorkingDays($pdo);
    $working_day_names = getWorkingDayNames($current_working_days);

    // =============================================================
    // HANDLE ACTIONS
    // =============================================================

    // UPDATE PRINCIPAL PROFILE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_principal'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            die("CSRF Validation Failed.");
        }

        $new_name = trim(htmlspecialchars($_POST['principal_name'] ?? ''));
        $new_email = trim(htmlspecialchars($_POST['principal_email'] ?? ''));
        $new_contact = trim(htmlspecialchars($_POST['principal_contact'] ?? ''));
        $new_password = trim($_POST['new_password'] ?? '');

        $query = "UPDATE principal SET name = ?, email = ?, contact = ?";
        $params_update = [$new_name, $new_email, $new_contact];

        if (!empty($new_password)) {
            $query .= ", password = ?";
            $params_update[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $query .= " WHERE p_id = ?";
        $params_update[] = $principal_id;

        $up_stmt = $pdo->prepare($query);
        if ($up_stmt->execute($params_update)) {
            $_SESSION['principal_name'] = $new_name;
            $principal_name = $new_name;
            $toast_msg = "✅ Profile updated successfully!";
            $toast_type = "success";
            $curr_pr_stmt->execute([$principal_id]);
            $principal = $curr_pr_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $toast_msg = "❌ Update failed.";
            $toast_type = "error";
        }
    }

    // ADD STUDENT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            die("CSRF Validation Failed.");
        }

        $name = trim(htmlspecialchars($_POST['name'] ?? ''));
        $gender = $_POST['gender'] ?? 'Male';
        $contact = trim(htmlspecialchars($_POST['contact'] ?? ''));
        $dob = $_POST['date_of_birth'] ?? null;
        $address = trim(htmlspecialchars($_POST['address'] ?? ''));
        $class_id = !empty($_POST['class_id']) ? $_POST['class_id'] : null;

        if (!empty($name)) {
            try {
                $ins_stmt = $pdo->prepare("
                    INSERT INTO student (name, gender, contact, date_of_birth, address, class_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins_stmt->execute([$name, $gender, $contact, $dob, $address, $class_id]);
                $toast_msg = "✅ Student registered successfully!";
                $toast_type = "success";
                $stmt->execute($params);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $recent_stmt->execute();
                $recent_students = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_students = $pdo->query("SELECT COUNT(*) FROM student")->fetchColumn();
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // DELETE STUDENT
    if (isset($_GET['delete_id'])) {
        $del_id = $_GET['delete_id'];
        try {
            $del_stmt = $pdo->prepare("DELETE FROM student WHERE student_id = ?");
            $del_stmt->execute([$del_id]);
            $toast_msg = "✅ Student deleted!";
            $toast_type = "success";
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $recent_stmt->execute();
            $recent_students = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_students = $pdo->query("SELECT COUNT(*) FROM student")->fetchColumn();
        } catch (PDOException $e) {
            $toast_msg = "❌ Cannot delete student.";
            $toast_type = "error";
        }
    }

    // ADD TEACHER
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            die("CSRF Validation Failed.");
        }

        $name = trim(htmlspecialchars($_POST['name'] ?? ''));
        $email = trim(htmlspecialchars($_POST['email'] ?? ''));
        $contact = trim(htmlspecialchars($_POST['contact'] ?? ''));
        $subject = trim(htmlspecialchars($_POST['subject'] ?? ''));
        $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $password = trim($_POST['password'] ?? '');

        if (!empty($name) && !empty($email) && !empty($password)) {
            try {
                $ins_stmt = $pdo->prepare("
                    INSERT INTO teacher (name, email, contact, subject, password, class_id, username) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $ins_stmt->execute([
                    $name,
                    $email,
                    $contact,
                    $subject,
                    password_hash($password, PASSWORD_DEFAULT),
                    $class_id,
                    $email
                ]);
                $toast_msg = "✅ Teacher added successfully!";
                $toast_type = "success";
                $teachers_stmt->execute();
                $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_teachers = $pdo->query("SELECT COUNT(*) FROM teacher")->fetchColumn();
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        } else {
            $toast_msg = "⚠️ Please fill all required fields!";
            $toast_type = "warning";
        }
    }

    // DELETE TEACHER
    if (isset($_GET['delete_teacher_id'])) {
        $del_id = $_GET['delete_teacher_id'];
        try {
            $del_stmt = $pdo->prepare("DELETE FROM teacher WHERE teacher_id = ?");
            $del_stmt->execute([$del_id]);
            $toast_msg = "✅ Teacher deleted!";
            $toast_type = "success";
            $teachers_stmt->execute();
            $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_teachers = $pdo->query("SELECT COUNT(*) FROM teacher")->fetchColumn();
        } catch (PDOException $e) {
            $toast_msg = "❌ Cannot delete teacher.";
            $toast_type = "error";
        }
    }

    // ADD PRINCIPAL
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_principal'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            die("CSRF Validation Failed.");
        }

        $username = trim(htmlspecialchars($_POST['username'] ?? ''));
        $name = trim(htmlspecialchars($_POST['name'] ?? ''));
        $email = trim(htmlspecialchars($_POST['email'] ?? ''));
        $contact = trim(htmlspecialchars($_POST['contact'] ?? ''));
        $password = trim($_POST['password'] ?? '');

        if (!empty($username) && !empty($password)) {
            try {
                $ins_stmt = $pdo->prepare("
                    INSERT INTO principal (username, name, email, contact, password, role) 
                    VALUES (?, ?, ?, ?, ?, 'principal')
                ");
                $ins_stmt->execute([$username, $name, $email, $contact, password_hash($password, PASSWORD_DEFAULT)]);
                $toast_msg = "✅ Principal added successfully!";
                $toast_type = "success";
                $principals_stmt->execute();
                $principals = $principals_stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_principals = $pdo->query("SELECT COUNT(*) FROM principal")->fetchColumn();
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // DELETE PRINCIPAL
    if (isset($_GET['delete_principal_id'])) {
        $del_id = $_GET['delete_principal_id'];
        if ($del_id != $principal_id) {
            try {
                $del_stmt = $pdo->prepare("DELETE FROM principal WHERE p_id = ?");
                $del_stmt->execute([$del_id]);
                $toast_msg = "✅ Principal deleted!";
                $toast_type = "success";
                $principals_stmt->execute();
                $principals = $principals_stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_principals = $pdo->query("SELECT COUNT(*) FROM principal")->fetchColumn();
            } catch (PDOException $e) {
                $toast_msg = "❌ Cannot delete principal.";
                $toast_type = "error";
            }
        } else {
            $toast_msg = "❌ You cannot delete yourself!";
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>👑 Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99,102,241,0.4);
            --gradient-primary: linear-gradient(135deg, #6366f1, #8b5cf6);
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
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
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
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            z-index: 0; opacity: 0.3;
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
        .orb-2 { width: 300px; height: 300px; background: #8b5cf6; bottom: -50px; left: -50px; animation-delay: -5s; }
        .orb-3 { width: 200px; height: 200px; background: #06b6d4; top: 50%; left: 50%; animation-delay: -10s; }
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
            background: #818cf8; border-radius: 50%;
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

        .working-status-banner {
            padding: 16px 24px; border-radius: var(--radius-sm);
            margin-bottom: 24px; display: flex; align-items: center; gap: 14px;
            font-size: 0.9rem;
        }
        .working-status-banner.working {
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.3);
            color: #34d399;
        }
        .working-status-banner.not-working {
            background: rgba(245,158,11,0.12);
            border: 1px solid rgba(245,158,11,0.3);
            color: #fbbf24;
        }
        .working-status-banner i { font-size: 1.4rem; }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
        .stat-card .stat-label {
            font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;
            letter-spacing: 0.5px; font-weight: 600; margin-top: 2px;
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
        .btn-success { background: linear-gradient(135deg, #10b981, #34d399); color: white; border: none; }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #f87171); color: white; border: none; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; border: none; }
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
        .badge-primary { background: rgba(99,102,241,0.15); color: #818cf8; }
        .badge-success { background: rgba(16,185,129,0.15); color: #10b981; }
        .badge-danger { background: rgba(239,68,68,0.15); color: #ef4444; }
        .badge-warning { background: rgba(245,158,11,0.15); color: #f59e0b; }

        .dashboard-grid {
            display: grid; grid-template-columns: 1.6fr 1fr;
            gap: 24px; margin-bottom: 24px;
        }
        .chart-container { position: relative; height: 200px; width: 100%; }

        .quick-actions-grid {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 12px;
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
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .quick-action-btn span { font-size: 0.75rem; font-weight: 500; }

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

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px; background: var(--glass-bg);
            border: 1px solid var(--border-light); border-radius: var(--radius-sm);
            color: var(--text); font-size: 0.9rem; transition: var(--transition);
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .tools-bar {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
            margin-bottom: 24px; padding: 16px;
            background: var(--glass-bg); border: 1px solid var(--glass-border);
            border-radius: var(--radius);
        }

        @media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } .form-grid { grid-template-columns: 1fr; } }
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
            .fab { bottom: 16px; right: 16px; width: 48px; height: 48px; font-size: 1.2rem; }
            .fab-menu { bottom: 80px; right: 16px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 14px; }
            .stat-card .stat-value { font-size: 1.2rem; }
            .card { padding: 16px; }
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
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
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
            <div class="shortcut-row"><span>Teachers</span> <kbd>Alt + T</kbd></div>
            <div class="shortcut-row"><span>Principals</span> <kbd>Alt + A</kbd></div>
            <div class="shortcut-row"><span>Manage Classes</span> <kbd>Alt + C</kbd></div>
            <div class="shortcut-row"><span>Working Days</span> <kbd>Alt + W</kbd></div>
            <div class="shortcut-row"><span>Profile</span> <kbd>Alt + P</kbd></div>
            <div class="shortcut-row"><span>Toggle Theme</span> <kbd>Alt + F</kbd></div>
            <div class="shortcut-row"><span>Logout</span> <kbd>Alt + L</kbd></div>
            <div class="shortcut-row"><span>Close</span> <kbd>Esc</kbd></div>
            <button onclick="closeShortcuts()" class="btn btn-primary w-full mt-16" style="justify-content:center;">Close</button>
        </div>
    </div>

    <nav class="top-nav">
        <div style="display:flex;align-items:center;gap:14px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
            <div class="brand">
                <i class="fa-solid fa-crown"></i>
                Principal <span style="-webkit-text-fill-color:var(--text-muted);font-weight:400;">Panel</span>
            </div>
        </div>
        <div class="top-nav-actions">
            <button class="nav-btn" onclick="toggleTheme()" id="themeToggle">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>
            <button class="nav-btn" onclick="toggleFullscreen()"><i class="fa-solid fa-expand"></i></button>
            <button class="nav-btn" onclick="openShortcuts()"><i class="fa-solid fa-keyboard"></i></button>
            <button class="nav-btn" id="notificationBtn"><i class="fa-solid fa-bell"></i><span class="badge"><?= $total_students ?></span></button>
            <div class="principal-avatar" onclick="window.location.href='?page=profile'">
                <?= strtoupper(substr($principal_name, 0, 2)) ?>
            </div>
        </div>
    </nav>

    <aside class="sidebar" id="sidebar">
        <div style="margin-bottom:16px;padding:0 10px;"><div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Navigation</div></div>
        <a href="dashboard.php" class="menu-item <?= $page === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i> Dashboard <span class="shortcut">Alt+D</span>
        </a>
        <a href="?page=students" class="menu-item <?= $page === 'students' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> Students <span class="shortcut">Alt+S</span>
        </a>
        <a href="?page=teachers" class="menu-item <?= $page === 'teachers' ? 'active' : '' ?>">
            <i class="fa-solid fa-chalkboard-user"></i> Teachers <span class="shortcut">Alt+T</span>
        </a>
        <a href="?page=principals" class="menu-item <?= $page === 'principals' ? 'active' : '' ?>">
            <i class="fa-solid fa-user-tie"></i> Principals <span class="shortcut">Alt+A</span>
        </a>
        <a href="manage_classes.php" class="menu-item">
            <i class="fa-solid fa-layer-group"></i> Manage Classes <span class="shortcut">Alt+C</span>
        </a>
        <!-- ✅ NEW: Working Days Link -->
        <a href="manage_working_days.php" class="menu-item">
            <i class="fa-solid fa-calendar-days"></i> Working Days <span class="shortcut">Alt+W</span>
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
        </div>
    </aside>

    <main class="main-content" id="mainContent">

        <?php if ($page === 'dashboard'): ?>
            <div class="page-content">
                <div class="welcome-banner">
                    <div class="banner-bg"></div>
                    <h1>Welcome back, <span><?= htmlspecialchars(explode(' ', trim($principal_name))[0]) ?></span> 👑</h1>
                    <p>Your principal command center is ready.</p>
                    <div class="welcome-time">
                        <div class="time" id="liveClock">00:00:00</div>
                        <div class="date" id="liveDate">Loading...</div>
                    </div>
                </div>

                <!-- ✅ Working Day Status Banner -->
                <div class="working-status-banner <?= $is_today_working ? 'working' : 'not-working' ?>">
                    <i class="fa-solid <?= $is_today_working ? 'fa-calendar-check' : 'fa-calendar-xmark' ?>"></i>
                    <div>
                        <strong>Today: <?= date('l, F j, Y') ?></strong><br>
                        <span style="font-size:0.85rem;opacity:0.9;">
                            <?php if ($is_today_working): ?>
                                ✅ Working day — Attendance can be marked.
                            <?php else: ?>
                                ⚠️ Not a working day — <?= htmlspecialchars($today_reason) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon">🎓</div><div class="stat-value"><?= $total_students ?></div><div class="stat-label">Total Students</div></div>
                    <div class="stat-card"><div class="stat-icon">👨‍🏫</div><div class="stat-value"><?= $total_teachers ?></div><div class="stat-label">Total Teachers</div></div>
                    <div class="stat-card"><div class="stat-icon">📚</div><div class="stat-value"><?= $total_classes ?></div><div class="stat-label">Total Classes</div></div>
                    <div class="stat-card"><div class="stat-icon">📖</div><div class="stat-value"><?= $total_subjects ?></div><div class="stat-label">Total Subjects</div></div>
                    <div class="stat-card"><div class="stat-icon">👑</div><div class="stat-value"><?= $total_principals ?></div><div class="stat-label">Total Principals</div></div>
                    <div class="stat-card"><div class="stat-icon">🏖️</div><div class="stat-value"><?= $total_holidays ?></div><div class="stat-label">Total Holidays</div></div>
                </div>

                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Students</h3>
                            <a href="?page=students" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>ID</th><th>Name</th><th>Class</th><th>Gender</th></tr></thead>
                                <tbody>
                                    <?php if (count($recent_students) > 0): ?>
                                        <?php foreach ($recent_students as $row): ?>
                                            <tr>
                                                <td class="font-mono text-muted text-sm">STU-00<?= $row['student_id'] ?></td>
                                                <td><?= htmlspecialchars($row['name']) ?></td>
                                                <td><span class="badge badge-primary"><?= !empty($row['grade']) ? htmlspecialchars($row['grade'] . ' - ' . $row['class_name']) : 'N/A' ?></span></td>
                                                <td><span class="badge <?= $row['gender'] === 'Male' ? 'badge-primary' : 'badge-success' ?>"><?= htmlspecialchars($row['gender']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">No students found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="card mb-16">
                            <div class="card-header"><h3><i class="fa-solid fa-chart-pie"></i> Gender Distribution</h3></div>
                            <div class="chart-container"><canvas id="genderChart"></canvas></div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h3><i class="fa-solid fa-bolt"></i> Quick Actions</h3></div>
                            <div class="quick-actions-grid">
                                <a href="?page=students#addStudent" class="quick-action-btn"><i class="fa-solid fa-user-plus"></i><span>Add Student</span></a>
                                <a href="?page=teachers#addTeacher" class="quick-action-btn"><i class="fa-solid fa-chalkboard-user"></i><span>Add Teacher</span></a>
                                <a href="?page=principals#addPrincipal" class="quick-action-btn"><i class="fa-solid fa-user-tie"></i><span>Add Principal</span></a>
                                <a href="?page=profile" class="quick-action-btn"><i class="fa-solid fa-user-edit"></i><span>Update Profile</span></a>
                                <a href="manage_classes.php" class="quick-action-btn">
                                    <i class="fa-solid fa-layer-group"></i><span>Manage Classes</span>
                                </a>
                                <!-- ✅ NEW: Working Days Quick Action -->
                                <a href="manage_working_days.php" class="quick-action-btn" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:white;border:none;">
                                    <i class="fa-solid fa-calendar-days" style="-webkit-text-fill-color:white;background:none;"></i>
                                    <span>Working Days</span>
                                </a>
                                <a href="attendance_report.php" class="quick-action-btn" style="background:var(--gradient-primary);color:white;border:none;">
                                    <i class="fa-solid fa-chart-bar" style="-webkit-text-fill-color:white;background:none;"></i>
                                    <span>Attendance Report</span>
                                </a>
                                <a href="scan_attendance.php" class="quick-action-btn" style="background:linear-gradient(135deg,#10b981,#34d399);color:white;border:none;">
                                    <i class="fa-solid fa-qrcode" style="-webkit-text-fill-color:white;background:none;"></i>
                                    <span>Scan QR</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'students'): ?>
            <div class="page-content">
                <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="stat-card"><div class="stat-icon">👨</div><div class="stat-value"><?= $male_count ?></div><div class="stat-label">Male Students</div></div>
                    <div class="stat-card"><div class="stat-icon">👩</div><div class="stat-value"><?= $female_count ?></div><div class="stat-label">Female Students</div></div>
                    <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-value"><?= $total_students ?></div><div class="stat-label">Total Students</div></div>
                </div>

                <div class="tools-bar">
                    <form action="?page=students" method="GET" style="display:flex;flex-wrap:wrap;gap:12px;flex:1;">
                        <input type="hidden" name="page" value="students">
                        <input type="text" name="search" placeholder="🔍 Search..." value="<?= htmlspecialchars($search_query) ?>" style="padding:10px 14px;background:var(--glass-bg);border:1px solid var(--border-light);border-radius:var(--radius-sm);color:var(--text);flex:1;">
                        <select name="filter_class" style="padding:10px 14px;background:var(--glass-bg);border:1px solid var(--border-light);border-radius:var(--radius-sm);color:var(--text);flex:1;">
                            <option value="">-- All Classes --</option>
                            <?php foreach ($classes_list as $cl): ?>
                                <option value="<?= $cl['class_id'] ?>" <?= $filter_class == $cl['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['grade'] . ' - ' . $cl['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Search</button>
                        <a href="?page=students" class="btn">Clear</a>
                    </form>
                    <button class="btn btn-success" onclick="window.print()"><i class="fa-solid fa-print"></i> Export</button>
                </div>

                <div class="card mb-20" id="addStudent">
                    <div class="card-header"><h3><i class="fa-solid fa-user-plus"></i> Add New Student</h3></div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="add_student" value="1">
                        <div class="form-grid">
                            <div class="form-group"><label>Full Name *</label><input type="text" name="name" required></div>
                            <div class="form-group"><label>Gender *</label><select name="gender" required><option value="Male">Male</option><option value="Female">Female</option></select></div>
                            <div class="form-group"><label>Date of Birth</label><input type="date" name="date_of_birth"></div>
                            <div class="form-group"><label>Contact</label><input type="text" name="contact" placeholder="Contact"></div>
                            <div class="form-group"><label>Address</label><input type="text" name="address" placeholder="Address"></div>
                            <div class="form-group"><label>Class</label><select name="class_id"><option value="">-- Select --</option><?php foreach ($classes_list as $cl): ?><option value="<?= $cl['class_id'] ?>"><?= htmlspecialchars($cl['grade'] . ' - ' . $cl['class_name']) ?></option><?php endforeach; ?></select></div>
                        </div>
                        <button type="submit" class="btn btn-success btn-block"><i class="fa-solid fa-save"></i> Register Student</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h3><i class="fa-solid fa-users"></i> All Students (<?= count($students) ?>)</h3></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Class</th><th>Contact</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if (count($students) > 0): foreach ($students as $row): ?>
                                    <tr>
                                        <td class="font-mono text-muted text-sm">STU-00<?= $row['student_id'] ?></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><span class="badge <?= $row['gender'] === 'Male' ? 'badge-primary' : 'badge-success' ?>"><?= htmlspecialchars($row['gender']) ?></span></td>
                                        <td><span class="badge badge-primary"><?= !empty($row['grade']) ? htmlspecialchars($row['grade'] . ' - ' . $row['class_name']) : 'N/A' ?></span></td>
                                        <td><?= htmlspecialchars($row['contact'] ?? '-') ?></td>
                                        <td><a href="?page=students&delete_id=<?= $row['student_id'] ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Delete?')"><i class="fa-solid fa-trash"></i></a></td>
                                    </tr>
                                <?php endforeach; else: ?><tr><td colspan="6" class="text-center text-muted">No students.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'teachers'): ?>
            <div class="page-content">
                <div class="card mb-20">
                    <div class="card-header"><h3><i class="fa-solid fa-chalkboard-user"></i> All Teachers (<?= count($teachers) ?>)</h3></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Subject</th><th>Class</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if (count($teachers) > 0): foreach ($teachers as $row): ?>
                                    <tr>
                                        <td>#<?= $row['teacher_id'] ?></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['contact'] ?? '-') ?></td>
                                        <td><span class="badge badge-primary"><?= htmlspecialchars($row['subject'] ?? 'N/A') ?></span></td>
                                        <td><span class="badge badge-success"><?= !empty($row['class_id']) ? 'Class '.$row['class_id'] : 'N/A' ?></span></td>
                                        <td><a href="?page=teachers&delete_teacher_id=<?= $row['teacher_id'] ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Delete?')"><i class="fa-solid fa-trash"></i></a></td>
                                    </tr>
                                <?php endforeach; else: ?><tr><td colspan="7" class="text-center text-muted">No teachers.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card" id="addTeacher">
                    <div class="card-header"><h3><i class="fa-solid fa-user-plus"></i> Add New Teacher</h3></div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="add_teacher" value="1">
                        <div class="form-grid">
                            <div class="form-group"><label>Full Name *</label><input type="text" name="name" required></div>
                            <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                            <div class="form-group"><label>Contact</label><input type="text" name="contact"></div>
                            <div class="form-group"><label>Subject</label><input type="text" name="subject"></div>
                            <div class="form-group"><label>Class</label><select name="class_id"><option value="">-- Select --</option><?php foreach ($classes_list as $cl): ?><option value="<?= $cl['class_id'] ?>"><?= htmlspecialchars($cl['grade'] . ' - ' . $cl['class_name']) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Password *</label><input type="password" name="password" required></div>
                        </div>
                        <button type="submit" class="btn btn-success btn-block"><i class="fa-solid fa-save"></i> Add Teacher</button>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'principals'): ?>
            <div class="page-content">
                <div class="card mb-20">
                    <div class="card-header"><h3><i class="fa-solid fa-user-tie"></i> System Principals</h3><span class="text-muted text-sm">Total: <?= $total_principals ?></span></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($principals as $row): ?>
                                    <tr>
                                        <td>#<?= $row['p_id'] ?></td>
                                        <td><span class="badge badge-primary"><?= htmlspecialchars($row['username']) ?></span></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                                        <td>
                                            <?php if ($row['p_id'] != $principal_id): ?>
                                                <a href="?page=principals&delete_principal_id=<?= $row['p_id'] ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Delete?')"><i class="fa-solid fa-trash"></i></a>
                                            <?php else: ?>
                                                <span class="text-muted text-xs">(You)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card" id="addPrincipal">
                    <div class="card-header"><h3><i class="fa-solid fa-user-plus"></i> Add New Principal</h3></div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="add_principal" value="1">
                        <div class="form-grid">
                            <div class="form-group"><label>Username *</label><input type="text" name="username" required></div>
                            <div class="form-group"><label>Full Name *</label><input type="text" name="name" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email"></div>
                            <div class="form-group"><label>Contact</label><input type="text" name="contact"></div>
                            <div class="form-group"><label>Password *</label><input type="password" name="password" required></div>
                        </div>
                        <button type="submit" class="btn btn-success btn-block"><i class="fa-solid fa-save"></i> Add Principal</button>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'profile'): ?>
            <div class="page-content">
                <div class="dashboard-grid">
                    <div class="card text-center" style="padding:30px;">
                        <div class="principal-avatar" style="width:100px;height:100px;font-size:2.5rem;margin:0 auto 16px;">
                            <?= strtoupper(substr($principal_name, 0, 2)) ?>
                        </div>
                        <h2><?= htmlspecialchars($principal_name) ?></h2>
                        <p class="text-muted">Principal</p>
                        <p class="text-sm text-muted">ID: #<?= $principal_id ?></p>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3><i class="fa-solid fa-user-edit"></i> Update Profile</h3></div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="update_principal" value="1">
                            <div class="form-group"><label>Full Name</label><input type="text" name="principal_name" value="<?= htmlspecialchars($principal_name) ?>" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="principal_email" value="<?= htmlspecialchars($principal['email'] ?? '') ?>" placeholder="Enter email"></div>
                            <div class="form-group"><label>Contact</label><input type="text" name="principal_contact" value="<?= htmlspecialchars($principal['contact'] ?? '') ?>" placeholder="Enter contact"></div>
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
        <a href="?page=students#addStudent" class="fab-item" style="background:#10b981;" title="Add Student"><i class="fa-solid fa-user-plus"></i></a>
        <a href="?page=teachers#addTeacher" class="fab-item" style="background:#8b5cf6;" title="Add Teacher"><i class="fa-solid fa-chalkboard-user"></i></a>
        <a href="?page=principals#addPrincipal" class="fab-item" style="background:#3b82f6;" title="Add Principal"><i class="fa-solid fa-user-tie"></i></a>
        <a href="manage_classes.php" class="fab-item" style="background:#f59e0b;" title="Manage Classes"><i class="fa-solid fa-layer-group"></i></a>
        <a href="manage_working_days.php" class="fab-item" style="background:#eab308;" title="Working Days"><i class="fa-solid fa-calendar-days"></i></a>
        <a href="scan_attendance.php" class="fab-item" style="background:#06b6d4;" title="Scan QR"><i class="fa-solid fa-qrcode"></i></a>
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

        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
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
        updateClock(); setInterval(updateClock, 1000);

        const genderCanvas = document.getElementById('genderChart');
        if (genderCanvas) {
            new Chart(genderCanvas, {
                type: 'doughnut',
                data: { labels: ['Male', 'Female'], datasets: [{ data: [<?= $male_count ?>, <?= $female_count ?>], backgroundColor: ['#6366f1', '#10b981'], borderWidth: 0 }] },
                options: { cutout: '70%', plugins: { legend: { position: 'bottom', labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-muted'), font: { size: 12 } } } }, responsive: true }
            });
        }

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
                    case 'd': window.location.href = 'dashboard.php'; break;
                    case 's': window.location.href = '?page=students'; break;
                    case 't': window.location.href = '?page=teachers'; break;
                    case 'a': window.location.href = '?page=principals'; break;
                    case 'c': window.location.href = 'manage_classes.php'; break;
                    case 'w': window.location.href = 'manage_working_days.php'; break;
                    case 'p': window.location.href = '?page=profile'; break;
                    case 'f': toggleTheme(); break;
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
            alert('🔔 Notifications:\n📚 Students: <?= $total_students ?>\n👨‍🏫 Teachers: <?= $total_teachers ?>\n👑 Principals: <?= $total_principals ?>\n🏖️ Holidays: <?= $total_holidays ?>');
        });

        console.log('%c👑 Principal Panel v5.0 (with Working Days)', 'font-size:20px;font-weight:800;color:#6366f1;');
        console.log('%c🚀 Welcome back, <?= addslashes($principal_name) ?>', 'font-size:14px;color:#94a3b8;');
        console.log('📅 Today is <?= $is_today_working ? "a working day ✅" : "NOT a working day ⚠️" ?>');
    </script>

</body>
</html>