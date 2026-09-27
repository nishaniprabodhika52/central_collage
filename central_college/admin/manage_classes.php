
<?php
// admin/manage_classes.php - Class & Subject Management
require_once __DIR__ . '/../config/db.php';
session_start();

// Access control (Principal or Admin only)
if (!isset($_SESSION['principal_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['principal_name'] ?? $_SESSION['admin_name'] ?? 'User';
$user_role = isset($_SESSION['principal_id']) ? 'Principal' : 'Admin';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$toast_msg = '';
$toast_type = '';

// --- Handlers: Class CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF Validation Failed.");
    }

    // 1. Add Class
    if (isset($_POST['add_class'])) {
        $name = trim(htmlspecialchars($_POST['class_name'] ?? ''));
        $grade = trim(htmlspecialchars($_POST['grade'] ?? ''));
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO class (class_name, grade) VALUES (?, ?)");
                $stmt->execute([$name, $grade]);
                $toast_msg = "✅ Class added successfully!";
                $toast_type = "success";
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // 2. Edit Class
    if (isset($_POST['edit_class'])) {
        $id = (int)$_POST['class_id'];
        $name = trim(htmlspecialchars($_POST['class_name'] ?? ''));
        $grade = trim(htmlspecialchars($_POST['grade'] ?? ''));
        if (!empty($name) && $id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE class SET class_name = ?, grade = ? WHERE class_id = ?");
                $stmt->execute([$name, $grade, $id]);
                $toast_msg = "✅ Class updated successfully!";
                $toast_type = "success";
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // 3. Delete Class
    if (isset($_GET['delete_class'])) {
        $id = (int)$_GET['delete_class'];
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM class WHERE class_id = ?");
                $stmt->execute([$id]);
                $toast_msg = "✅ Class deleted successfully!";
                $toast_type = "success";
            } catch (PDOException $e) {
                $toast_msg = "❌ Cannot delete: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // 4. Add Subject
    if (isset($_POST['add_subject'])) {
        $name = trim(htmlspecialchars($_POST['subject_name'] ?? ''));
        $code = trim(htmlspecialchars($_POST['subject_code'] ?? ''));
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subject (subject_name, code) VALUES (?, ?)");
                $stmt->execute([$name, $code]);
                $toast_msg = "✅ Subject added successfully!";
                $toast_type = "success";
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // 5. Edit Subject
    if (isset($_POST['edit_subject'])) {
        $id = (int)$_POST['subject_id'];
        $name = trim(htmlspecialchars($_POST['subject_name'] ?? ''));
        $code = trim(htmlspecialchars($_POST['subject_code'] ?? ''));
        if (!empty($name) && $id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE subject SET subject_name = ?, code = ? WHERE subject_id = ?");
                $stmt->execute([$name, $code, $id]);
                $toast_msg = "✅ Subject updated successfully!";
                $toast_type = "success";
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // 6. Delete Subject
    if (isset($_GET['delete_subject'])) {
        $id = (int)$_GET['delete_subject'];
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM subject WHERE subject_id = ?");
                $stmt->execute([$id]);
                $toast_msg = "✅ Subject deleted successfully!";
                $toast_type = "success";
            } catch (PDOException $e) {
                $toast_msg = "❌ Cannot delete: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // 7. Assign Subject to Class (Update subject.class_id)
    if (isset($_POST['assign_subject'])) {
        $class_id = (int)$_POST['assign_class_id'];
        $subject_id = (int)$_POST['assign_subject_id'];
        if ($class_id > 0 && $subject_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE subject SET class_id = ? WHERE subject_id = ?");
                $stmt->execute([$class_id, $subject_id]);
                $toast_msg = "✅ Subject assigned to class successfully!";
                $toast_type = "success";
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }

    // 8. Unassign Subject (Set subject.class_id = NULL)
    if (isset($_GET['unassign_subject'])) {
        $subject_id = (int)$_GET['unassign_subject'];
        if ($subject_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE subject SET class_id = NULL WHERE subject_id = ?");
                $stmt->execute([$subject_id]);
                $toast_msg = "✅ Subject unassigned successfully!";
                $toast_type = "success";
            } catch (PDOException $e) {
                $toast_msg = "❌ Error: " . $e->getMessage();
                $toast_type = "error";
            }
        }
    }
}

// --- Fetch Data ---
$classes = $pdo->query("SELECT * FROM class ORDER BY grade ASC, class_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT * FROM subject ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT teacher_id, name FROM teacher ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Assigned subjects (with class details)
$assigned_list = $pdo->query("
    SELECT s.*, c.class_name, c.grade 
    FROM subject s 
    LEFT JOIN class c ON s.class_id = c.class_id 
    WHERE s.class_id IS NOT NULL AND s.class_id != 0 
    ORDER BY c.grade ASC, s.subject_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Unassigned subjects (for dropdown)
$unassigned = $pdo->query("SELECT * FROM subject WHERE class_id IS NULL OR class_id = 0 ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

$active_tab = $_GET['tab'] ?? 'classes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 Class & Subject Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99,102,241,0.4);
            --gradient-primary: linear-gradient(135deg, #6366f1, #8b5cf6);
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
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
            transition: var(--transition);
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: var(--radius-full); }

        .bg-gradient-overlay {
            position: fixed; top:0; left:0; width:100%; height:100%;
            background: linear-gradient(135deg, #0f172a, #1e1b4b, #312e81);
            z-index:0; opacity:0.3;
            animation: gradientShift 15s ease-in-out infinite alternate;
        }
        @keyframes gradientShift {
            0% { opacity:0.3; transform:scale(1) rotate(0deg); }
            100% { opacity:0.5; transform:scale(1.1) rotate(2deg); }
        }
        .orb {
            position: fixed; border-radius:50%; filter:blur(80px); opacity:0.3;
            z-index:0; pointer-events:none;
            animation: orbFloat 20s ease-in-out infinite alternate;
        }
        .orb-1 { width:400px; height:400px; background:var(--primary); top:-100px; right:-100px; }
        .orb-2 { width:300px; height:300px; background:#8b5cf6; bottom:-50px; left:-50px; animation-delay:-5s; }

        .page-container {
            position:relative; z-index:1;
            max-width:1200px; margin:0 auto;
        }

        /* Top Nav */
        .top-nav {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 16px 24px;
            margin-bottom: 24px;
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
        .nav-actions { display: flex; align-items: center; gap: 12px; }
        .nav-btn {
            width: 40px; height: 40px; border-radius: 50%;
            border: 1px solid var(--border-light);
            background: var(--glass-bg); color: var(--text);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: var(--transition);
        }
        .nav-btn:hover { background: var(--primary); border-color: var(--primary); box-shadow: 0 0 20px var(--primary-glow); }
        .avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; color: white;
            cursor: pointer; border: 2px solid var(--border-light);
            text-transform: uppercase;
        }
        .avatar:hover { transform: scale(1.05); border-color: var(--primary); }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--text-muted); text-decoration: none;
            font-size: 0.9rem; margin-bottom: 20px;
            transition: var(--transition);
            padding: 8px 16px;
            background: var(--glass-bg);
            border-radius: var(--radius-full);
            border: 1px solid var(--border-light);
        }
        .back-link:hover { color: var(--text); transform: translateX(-4px); }

        .tabs {
            display: flex; gap: 4px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 6px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 10px 24px;
            border: none;
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover { color: var(--text); background: var(--glass-bg); }
        .tab-btn.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 20px var(--primary-glow);
        }
        .tab-btn.active i { color: white; }

        .tab-content { display: none; animation: fadeIn 0.4s ease-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        .card {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            transition: var(--transition);
        }
        .card:hover { border-color: var(--border-light); box-shadow: var(--shadow); }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header h3 {
            font-size: 1.1rem; font-weight: 700;
            display: flex; align-items: center; gap: 10px;
        }
        .card-header h3 i { color: var(--primary); }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 20px; border-radius: var(--radius-full);
            border: none; font-weight: 600; font-size: 0.85rem;
            cursor: pointer; transition: var(--transition);
            text-decoration: none;
            background: var(--glass-bg);
            color: var(--text);
            border: 1px solid var(--border-light);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .btn-primary { background: var(--gradient-primary); color: white; border: none; }
        .btn-primary:hover { box-shadow: 0 4px 24px var(--primary-glow); }
        .btn-success { background: linear-gradient(135deg, #10b981, #34d399); color: white; border: none; }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #f87171); color: white; border: none; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; border: none; }
        .btn-sm { padding: 5px 14px; font-size: 0.75rem; }
        .btn-block { width: 100%; justify-content: center; padding: 12px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
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

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table thead th {
            text-align: left; padding: 12px 10px;
            color: var(--text-muted); font-weight: 600; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
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

        .toast-container {
            position: fixed; top: 80px; right: 30px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px; max-width: 400px;
        }
        .toast {
            padding: 14px 20px; border-radius: var(--radius-sm);
            backdrop-filter: var(--backdrop-blur); border: 1px solid var(--glass-border);
            box-shadow: var(--shadow); animation: slideInRight 0.4s ease-out;
            display: flex; align-items: center; gap: 12px;
            font-weight: 500; font-size: 0.9rem;
        }
        .toast.success { background: rgba(16,185,129,0.2); border-color: #10b981; color: #34d399; }
        .toast.error { background: rgba(239,68,68,0.2); border-color: #ef4444; color: #f87171; }
        @keyframes slideInRight {
            from { opacity:0; transform:translateX(50px); }
            to { opacity:1; transform:translateX(0); }
        }
        .toast .toast-close { margin-left:auto; background:none; border:none; color:currentColor; cursor:pointer; font-size:1.2rem; opacity:0.6; }

        .flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .text-muted { color: var(--text-muted); }
        .text-sm { font-size: 0.8rem; }
        .mt-12 { margin-top: 12px; }
        .mb-12 { margin-bottom: 12px; }
        .gap-8 { gap: 8px; }

        @media (max-width: 768px) {
            body { padding: 12px; }
            .top-nav { flex-direction: column; gap: 12px; }
            .form-grid { grid-template-columns: 1fr; }
            .card { padding: 16px; }
            .tab-btn { padding: 8px 14px; font-size: 0.8rem; }
            .toast-container { top: 70px; right: 10px; left: 10px; max-width:100%; }
        }
    </style>
</head>
<body>

<div class="bg-gradient-overlay"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer">
    <?php if (!empty($toast_msg)): ?>
        <div class="toast <?= $toast_type ?>">
            <span><?= $toast_msg ?></span>
            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
</div>

<div class="page-container">

    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="brand">
            <i class="fa-solid fa-layer-group"></i>
            Class / Subject Management
        </div>
        <div class="nav-actions">
            <button class="nav-btn" onclick="toggleTheme()"><i class="fa-solid fa-moon" id="themeIcon"></i></button>
            <div class="avatar" onclick="window.location.href='dashboard.php'"><?= strtoupper(substr($user_name, 0, 2)) ?></div>
        </div>
    </nav>

    <a href="dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn <?= $active_tab === 'classes' ? 'active' : '' ?>" onclick="switchTab('classes')">
            <i class="fa-solid fa-school"></i> Classes
        </button>
        <button class="tab-btn <?= $active_tab === 'subjects' ? 'active' : '' ?>" onclick="switchTab('subjects')">
            <i class="fa-solid fa-book-open"></i> Subjects
        </button>
        <button class="tab-btn <?= $active_tab === 'assignments' ? 'active' : '' ?>" onclick="switchTab('assignments')">
            <i class="fa-solid fa-link"></i> Assignments
        </button>
    </div>

    <!-- ==================== TAB 1: CLASSES ==================== -->
    <div id="tab-classes" class="tab-content <?= $active_tab === 'classes' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-school"></i> All Classes (<?= count($classes) ?>)</h3>
                <button class="btn btn-primary" onclick="toggleForm('addClassForm')"><i class="fa-solid fa-plus"></i> Add New Class</button>
            </div>

            <!-- Add Class Form -->
            <div id="addClassForm" style="display:none; background:var(--glass-bg); padding:20px; border-radius:var(--radius-sm); margin-bottom:20px; border:1px solid var(--border-light);">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="add_class" value="1">
                    <div class="form-grid">
                        <div class="form-group"><label>Class Name *</label><input type="text" name="class_name" placeholder="e.g. 10-A" required></div>
                        <div class="form-group"><label>Grade *</label><input type="text" name="grade" placeholder="e.g. 10" required></div>
                    </div>
                    <button type="submit" class="btn btn-success btn-block"><i class="fa-solid fa-save"></i> Add Class</button>
                </form>
            </div>

            <!-- Classes List -->
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Class</th><th>Grade</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($classes) > 0): ?>
                            <?php foreach ($classes as $cl): ?>
                                <tr>
                                    <td><?= $cl['class_id'] ?></td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($cl['class_name']) ?></span></td>
                                    <td><?= htmlspecialchars($cl['grade']) ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editClass(<?= htmlspecialchars(json_encode($cl)) ?>)"><i class="fa-solid fa-edit"></i></button>
                                        <a href="?tab=classes&delete_class=<?= $cl['class_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this class?')"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-muted text-center">No classes found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Class Modal (Inline) -->
        <div id="editClassModal" style="display:none; background:var(--glass-bg); padding:20px; border-radius:var(--radius-sm); border:1px solid var(--border-light); margin-top:10px;">
            <h4 style="margin-bottom:12px;"><i class="fa-solid fa-pen"></i> Edit Class</h4>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="edit_class" value="1">
                <input type="hidden" name="class_id" id="edit_class_id">
                <div class="form-grid">
                    <div class="form-group"><label>Class Name</label><input type="text" name="class_name" id="edit_class_name" required></div>
                    <div class="form-group"><label>Grade</label><input type="text" name="grade" id="edit_grade" required></div>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-save"></i> Update</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editClassModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== TAB 2: SUBJECTS ==================== -->
    <div id="tab-subjects" class="tab-content <?= $active_tab === 'subjects' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-book-open"></i> All Subjects (<?= count($subjects) ?>)</h3>
                <button class="btn btn-primary" onclick="toggleForm('addSubjectForm')"><i class="fa-solid fa-plus"></i> Add New Subject</button>
            </div>

            <!-- Add Subject Form -->
            <div id="addSubjectForm" style="display:none; background:var(--glass-bg); padding:20px; border-radius:var(--radius-sm); margin-bottom:20px; border:1px solid var(--border-light);">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="add_subject" value="1">
                    <div class="form-grid">
                        <div class="form-group"><label>Subject Name *</label><input type="text" name="subject_name" placeholder="e.g. Mathematics" required></div>
                        <div class="form-group"><label>Code</label><input type="text" name="subject_code" placeholder="e.g. MAT101"></div>
                    </div>
                    <button type="submit" class="btn btn-success btn-block"><i class="fa-solid fa-save"></i> Add Subject</button>
                </form>
            </div>

            <!-- Subjects List -->
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Subject</th><th>Code</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($subjects) > 0): ?>
                            <?php foreach ($subjects as $sub): ?>
                                <tr>
                                    <td><?= $sub['subject_id'] ?></td>
                                    <td><span class="badge badge-success"><?= htmlspecialchars($sub['subject_name']) ?></span></td>
                                    <td><?= htmlspecialchars($sub['code'] ?? '-') ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editSubject(<?= htmlspecialchars(json_encode($sub)) ?>)"><i class="fa-solid fa-edit"></i></button>
                                        <a href="?tab=subjects&delete_subject=<?= $sub['subject_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this subject?')"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-muted text-center">No subjects found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Subject Modal -->
        <div id="editSubjectModal" style="display:none; background:var(--glass-bg); padding:20px; border-radius:var(--radius-sm); border:1px solid var(--border-light); margin-top:10px;">
            <h4 style="margin-bottom:12px;"><i class="fa-solid fa-pen"></i> Edit Subject</h4>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="edit_subject" value="1">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                <div class="form-grid">
                    <div class="form-group"><label>Subject Name</label><input type="text" name="subject_name" id="edit_subject_name" required></div>
                    <div class="form-group"><label>Code</label><input type="text" name="subject_code" id="edit_subject_code"></div>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-save"></i> Update</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editSubjectModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== TAB 3: ASSIGNMENTS ==================== -->
    <div id="tab-assignments" class="tab-content <?= $active_tab === 'assignments' ? 'active' : '' ?>">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-link"></i> Class-Subject Assignments</h3>
                <button class="btn btn-primary" onclick="toggleForm('assignForm')"><i class="fa-solid fa-plus"></i> Assign Subject to Class</button>
            </div>

            <!-- Assign Form -->
            <div id="assignForm" style="display:none; background:var(--glass-bg); padding:20px; border-radius:var(--radius-sm); margin-bottom:20px; border:1px solid var(--border-light);">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="assign_subject" value="1">
                    <div class="form-grid">
                        <div class="form-group"><label>Subject *</label>
                            <select name="assign_subject_id" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($unassigned as $sub): ?>
                                    <option value="<?= $sub['subject_id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?> (<?= htmlspecialchars($sub['code'] ?? 'N/A') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">* Shows only subjects not yet assigned to any class.</small>
                        </div>
                        <div class="form-group"><label>Class *</label>
                            <select name="assign_class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?= $cl['class_id'] ?>"><?= htmlspecialchars($cl['grade'] . ' - ' . $cl['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success btn-block"><i class="fa-solid fa-link"></i> Assign Subject</button>
                </form>
            </div>

            <!-- Already Assigned Subjects List -->
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Subject</th><th>Code</th><th>Assigned Class</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (count($assigned_list) > 0): ?>
                            <?php foreach ($assigned_list as $as): ?>
                                <tr>
                                    <td><?= $as['subject_id'] ?></td>
                                    <td><span class="badge badge-success"><?= htmlspecialchars($as['subject_name']) ?></span></td>
                                    <td><?= htmlspecialchars($as['code'] ?? '-') ?></td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($as['grade'] . ' - ' . $as['class_name']) ?></span></td>
                                    <td>
                                        <a href="?tab=assignments&unassign_subject=<?= $as['subject_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to unassign this subject?')"><i class="fa-solid fa-unlink"></i> Unassign</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-muted text-center">No subjects assigned to any class yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
    // Theme Toggle
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

    // Tab Switching
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
    }

    // Toggle Forms
    function toggleForm(id) {
        const el = document.getElementById(id);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }

    // Edit Class
    function editClass(data) {
        document.getElementById('edit_class_id').value = data.class_id;
        document.getElementById('edit_class_name').value = data.class_name;
        document.getElementById('edit_grade').value = data.grade;
        document.getElementById('editClassModal').style.display = 'block';
        document.getElementById('editClassModal').scrollIntoView({ behavior: 'smooth' });
    }

    // Edit Subject
    function editSubject(data) {
        document.getElementById('edit_subject_id').value = data.subject_id;
        document.getElementById('edit_subject_name').value = data.subject_name;
        document.getElementById('edit_subject_code').value = data.code || '';
        document.getElementById('editSubjectModal').style.display = 'block';
        document.getElementById('editSubjectModal').scrollIntoView({ behavior: 'smooth' });
    }

    // Auto dismiss toasts
    document.querySelectorAll('.toast').forEach(function(toast) {
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            setTimeout(function() { toast.remove(); }, 400);
        }, 5000);
    });

    // Keyboard shortcut: Alt + B to go back to dashboard
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key === 'b') {
            window.location.href = 'dashboard.php';
        }
    });

    console.log('📚 manage_classes.php (English version) loaded successfully!');
</script>

</body>
</html>