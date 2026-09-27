<?php
// admin/add_admin.php - Add New Admin
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$toast_msg = '';
$toast_type = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
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
                INSERT INTO admin (username, name, email, contact, password) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins_stmt->execute([
                $username,
                $name,
                $email,
                $contact,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            $toast_msg = "✅ New admin added successfully!";
            $toast_type = "success";
        } catch (PDOException $e) {
            $toast_msg = "❌ Error: " . $e->getMessage();
            $toast_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👑 Add Admin | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Same styles as add_student.php */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --secondary: #8b5cf6;
            --accent: #06b6d4;
            --gradient-main: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            --gradient-primary: linear-gradient(135deg, var(--primary), var(--secondary));
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --gradient-danger: linear-gradient(135deg, #ef4444, #f87171);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --bg: #0b0f1a;
            --bg-card: rgba(17, 24, 39, 0.85);
            --bg-card-hover: rgba(30, 41, 59, 0.8);
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --text-light: #cbd5e1;
            --border: rgba(255, 255, 255, 0.06);
            --border-light: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            --radius: 16px;
            --radius-sm: 10px;
            --radius-full: 9999px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --backdrop-blur: blur(20px);
        }

        [data-theme="light"] {
            --bg: #f1f5f9;
            --bg-card: rgba(255, 255, 255, 0.85);
            --bg-card-hover: rgba(241, 245, 249, 0.9);
            --text: #0f172a;
            --text-muted: #64748b;
            --text-light: #334155;
            --border: rgba(0, 0, 0, 0.08);
            --border-light: rgba(0, 0, 0, 0.12);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            --glass-bg: rgba(255, 255, 255, 0.6);
            --glass-border: rgba(255, 255, 255, 0.3);
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
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

        .page-container {
            position: relative; z-index: 1;
            width: 100%; max-width: 580px;
            background: var(--bg-card);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 32px 36px;
            box-shadow: var(--shadow);
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .page-header .icon {
            width: 48px; height: 48px; border-radius: 50%;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: white; flex-shrink: 0;
        }
        .page-header h1 { font-size: 1.5rem; font-weight: 700; }
        .page-header p { font-size: 0.85rem; color: var(--text-muted); margin-top: 2px; }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 0.8rem; font-weight: 600;
            color: var(--text-muted); margin-bottom: 4px;
        }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px;
            background: var(--glass-bg); border: 1px solid var(--border-light);
            border-radius: var(--radius-sm); color: var(--text);
            font-size: 0.9rem; transition: var(--transition);
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .form-group input::placeholder { color: var(--text-muted); opacity: 0.6; }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 24px; border-radius: var(--radius-full);
            border: none; font-weight: 600; font-size: 0.9rem;
            cursor: pointer; transition: var(--transition);
            text-decoration: none; width: 100%; justify-content: center;
        }
        .btn-primary { background: var(--gradient-primary); color: white; }
        .btn-primary:hover { box-shadow: 0 4px 24px var(--primary-glow); transform: translateY(-2px); }
        .btn-secondary { background: var(--glass-bg); color: var(--text); border: 1px solid var(--border-light); }
        .btn-secondary:hover { background: var(--bg-card); }

        .toast {
            padding: 12px 16px; border-radius: var(--radius-sm);
            margin-bottom: 16px; font-weight: 500; font-size: 0.9rem;
            display: flex; align-items: center; gap: 10px;
        }
        .toast.success { background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #34d399; }
        .toast.error { background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #f87171; }

        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--text-muted); text-decoration: none;
            font-size: 0.85rem; margin-top: 16px; transition: var(--transition);
        }
        .back-link:hover { color: var(--text); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        @media (max-width: 500px) {
            .page-container { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="bg-gradient-overlay"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="page-container">
        <div class="page-header">
            <div class="icon"><i class="fa-solid fa-user-tie"></i></div>
            <div>
                <h1>Add Admin</h1>
                <p>Create a new administrator account</p>
            </div>
        </div>

        <?php if (!empty($toast_msg)): ?>
            <div class="toast <?= $toast_type ?>">
                <i class="fa-solid <?= $toast_type === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
                <?= $toast_msg ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="add_admin" value="1">

            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" placeholder="Enter username" required>
            </div>

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" placeholder="Enter full name" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Enter email">
                </div>
                <div class="form-group">
                    <label>Contact</label>
                    <input type="text" name="contact" placeholder="Enter contact number">
                </div>
            </div>

            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Add Admin
            </button>
        </form>

        <a href="dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>

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
        document.addEventListener('keydown', function(e) {
            if (e.altKey && e.key === 'Backspace') {
                window.location.href = 'dashboard.php';
            }
        });
    </script>

</body>
</html>