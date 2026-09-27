<?php
// admin/manage_working_days.php - Principal manages working days & holidays
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

$toast_msg = '';
$toast_type = '';

// ============================================================
// HANDLE: Save working days
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_working_days'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF Validation Failed.");
    }

    $days = $_POST['working_days'] ?? [];
    $days = array_map('intval', $days);

    if (empty($days)) {
        $toast_msg = "❌ Please select at least one working day!";
        $toast_type = "error";
    } else {
        if (setWorkingDays($pdo, $days)) {
            $toast_msg = "✅ Working days updated successfully!";
            $toast_type = "success";
        } else {
            $toast_msg = "❌ Failed to update working days.";
            $toast_type = "error";
        }
    }
}

// ============================================================
// HANDLE: Add holiday
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF Validation Failed.");
    }

    $holiday_date = $_POST['holiday_date'] ?? '';
    $description = trim(htmlspecialchars($_POST['description'] ?? ''));

    if (empty($holiday_date)) {
        $toast_msg = "❌ Please select a date.";
        $toast_type = "error";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO holidays (holiday_date, description) VALUES (?, ?)");
            $stmt->execute([$holiday_date, $description]);
            $toast_msg = "✅ Holiday added successfully!";
            $toast_type = "success";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $toast_msg = "❌ This date is already a holiday!";
            } else {
                $toast_msg = "❌ Error: " . $e->getMessage();
            }
            $toast_type = "error";
        }
    }
}

// ============================================================
// HANDLE: Delete holiday  (FIXED: holiday_id not id)
// ============================================================
if (isset($_GET['delete_holiday'])) {
    $id = (int)$_GET['delete_holiday'];
    try {
        $stmt = $pdo->prepare("DELETE FROM holidays WHERE holiday_id = ?");
        $stmt->execute([$id]);
        $toast_msg = "✅ Holiday deleted successfully!";
        $toast_type = "success";
    } catch (PDOException $e) {
        $toast_msg = "❌ Failed to delete holiday.";
        $toast_type = "error";
    }
}

// ============================================================
// FETCH DATA
// ============================================================
$current_working_days = getWorkingDays($pdo);
$holidays = $pdo->query("SELECT * FROM holidays ORDER BY holiday_date ASC")->fetchAll(PDO::FETCH_ASSOC);

$day_names = [
    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
];

// Today's status preview
$today = date('Y-m-d');
$is_today_working = isWorkingDay($today, $pdo);
$today_reason = !$is_today_working ? getNonWorkingReason($today, $pdo) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📅 Working Days & Holidays</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99,102,241,0.4);
            --gradient-primary: linear-gradient(135deg, #6366f1, #8b5cf6);
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --bg: #0b0f1a;
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
            --text: #0f172a;
            --text-muted: #64748b;
            --border: rgba(0,0,0,0.08);
            --border-light: rgba(0,0,0,0.12);
            --glass-bg: rgba(255,255,255,0.6);
            --glass-border: rgba(255,255,255,0.3);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 30px;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: var(--radius-full); }

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
        .brand {
            font-size: 1.2rem; font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            display: flex; align-items: center; gap: 10px;
        }
        .brand i { -webkit-text-fill-color: var(--primary); font-size: 1.4rem; }
        .top-nav-actions { display: flex; align-items: center; gap: 12px; }
        .nav-btn {
            width: 40px; height: 40px; border-radius: 50%;
            border: 1px solid var(--border-light);
            background: var(--glass-bg); color: var(--text);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: var(--transition);
        }
        .nav-btn:hover { background: var(--primary); border-color: var(--primary); }
        .principal-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: var(--gradient-primary);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: white;
            cursor: pointer; border: 2px solid var(--border-light);
            text-transform: uppercase;
        }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--text-muted); text-decoration: none;
            font-size: 0.9rem; margin-bottom: 20px;
            padding: 8px 16px;
            background: var(--glass-bg);
            border-radius: var(--radius-full);
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }
        .back-link:hover { color: var(--text); transform: translateX(-4px); }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        .card {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 24px;
            transition: var(--transition);
        }
        .card:hover { border-color: var(--border-light); box-shadow: var(--shadow); }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .card-header h3 {
            font-size: 1.05rem; font-weight: 700;
            display: flex; align-items: center; gap: 10px;
        }
        .card-header h3 i { color: var(--primary); }

        .day-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .day-checkbox { position: relative; }
        .day-checkbox input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        .day-checkbox label {
            display: block;
            padding: 14px 10px;
            text-align: center;
            background: var(--glass-bg);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            user-select: none;
        }
        .day-checkbox label:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        .day-checkbox input[type="checkbox"]:checked + label {
            background: var(--gradient-primary);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 20px var(--primary-glow);
        }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px; border-radius: var(--radius-full);
            border: none; font-weight: 600; font-size: 0.85rem;
            cursor: pointer; transition: var(--transition);
            text-decoration: none;
        }
        .btn-primary { background: var(--gradient-primary); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 24px var(--primary-glow); }
        .btn-success { background: var(--gradient-success); color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 4px 24px rgba(16,185,129,0.4); }
        .btn-danger { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .btn-danger:hover { background: rgba(239,68,68,0.25); }
        .btn-sm { padding: 6px 14px; font-size: 0.75rem; }
        .btn-block { width: 100%; justify-content: center; padding: 12px; }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 0.8rem; font-weight: 600;
            color: var(--text-muted); margin-bottom: 6px;
        }
        .form-group input {
            width: 100%; padding: 10px 14px;
            background: var(--glass-bg);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 0.9rem;
            font-family: inherit;
            transition: var(--transition);
        }
        .form-group input:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table thead th {
            text-align: left; padding: 12px 10px;
            color: var(--text-muted); font-weight: 600;
            font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1px solid var(--border);
        }
        table tbody td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            color: var(--text-light);
        }
        table tbody tr:hover { background: var(--glass-bg); }

        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .alert-success {
            background: rgba(16,185,129,0.15);
            border: 1px solid #10b981;
            color: #34d399;
        }
        .alert-error {
            background: rgba(239,68,68,0.15);
            border: 1px solid #ef4444;
            color: #f87171;
        }

        .today-status {
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.9rem;
        }
        .today-status.working {
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.3);
            color: #34d399;
        }
        .today-status.not-working {
            background: rgba(245,158,11,0.12);
            border: 1px solid rgba(245,158,11,0.3);
            color: #fbbf24;
        }
        .today-status i { font-size: 1.6rem; }

        .badge {
            padding: 3px 12px;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-primary { background: rgba(99,102,241,0.15); color: #818cf8; }

        .text-muted { color: var(--text-muted); }
        .text-sm { font-size: 0.8rem; }

        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            body { padding: 16px; }
            .top-nav { flex-direction: column; gap: 12px; }
            .day-checkboxes { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="brand">
            <i class="fa-solid fa-calendar-days"></i>
            Working Days & Holidays
        </div>
        <div class="top-nav-actions">
            <button class="nav-btn" onclick="toggleTheme()"><i class="fa-solid fa-moon" id="themeIcon"></i></button>
            <div class="principal-avatar" onclick="window.location.href='dashboard.php'">
                <?= strtoupper(substr($principal_name, 0, 2)) ?>
            </div>
        </div>
    </nav>

    <a href="dashboard.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>

    <?php if (!empty($toast_msg)): ?>
        <div class="alert alert-<?= $toast_type === 'success' ? 'success' : 'error' ?>">
            <i class="fa-solid <?= $toast_type === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
            <span><?= htmlspecialchars($toast_msg) ?></span>
        </div>
    <?php endif; ?>

    <div class="today-status <?= $is_today_working ? 'working' : 'not-working' ?>">
        <i class="fa-solid <?= $is_today_working ? 'fa-calendar-check' : 'fa-calendar-xmark' ?>"></i>
        <div>
            <strong>Today: <?= date('l, F j, Y') ?></strong><br>
            <span style="font-size:0.85rem;opacity:0.9;">
                <?php if ($is_today_working): ?>
                    ✅ This is a working day — Attendance can be marked.
                <?php else: ?>
                    ⚠️ Not a working day — <?= htmlspecialchars($today_reason) ?>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-calendar-week"></i> Configure Working Days</h3>
                <span class="text-sm text-muted"><?= count($current_working_days) ?> days/week</span>
            </div>
            <p class="text-muted text-sm" style="margin-bottom:16px;">
                Select which days of the week are working days. Attendance can only be marked on these days.
            </p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="save_working_days" value="1">

                <div class="day-checkboxes">
                    <?php foreach ($day_names as $num => $name): ?>
                        <div class="day-checkbox">
                            <input type="checkbox" 
                                   name="working_days[]" 
                                   value="<?= $num ?>" 
                                   id="day_<?= $num ?>"
                                   <?= in_array($num, $current_working_days) ? 'checked' : '' ?>>
                            <label for="day_<?= $num ?>"><?= $name ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-save"></i> Save Working Days
                </button>
            </form>

            <div style="margin-top:16px;">
                <div class="text-muted text-sm" style="margin-bottom:8px;">Quick presets:</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-sm btn-primary" onclick="setPreset([1,2,3,4,5])">Mon-Fri</button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="setPreset([1,2,3,4,5,6])">Mon-Sat</button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="setPreset([1,2,3,4,5,6,7])">All 7 Days</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-umbrella-beach"></i> Add Public Holiday</h3>
                <span class="text-sm text-muted"><?= count($holidays) ?> total</span>
            </div>
            <p class="text-muted text-sm" style="margin-bottom:16px;">
                Add specific dates that are holidays (not working days).
            </p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="add_holiday" value="1">

                <div class="form-group">
                    <label>Holiday Date *</label>
                    <input type="date" name="holiday_date" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="e.g. New Year, Vesak Poya">
                </div>

                <button type="submit" class="btn btn-success btn-block">
                    <i class="fa-solid fa-plus"></i> Add Holiday
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-list"></i> All Holidays (<?= count($holidays) ?>)</h3>
            <span class="text-sm text-muted">Sorted by date</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($holidays) > 0): ?>
                        <?php $i = 1; foreach ($holidays as $h): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><span class="badge badge-primary"><?= date('M j, Y', strtotime($h['holiday_date'])) ?></span></td>
                                <td><?= date('l', strtotime($h['holiday_date'])) ?></td>
                                <td><?= htmlspecialchars($h['description'] ?? '-') ?></td>
                                <td>
                                    <a href="?delete_holiday=<?= $h['holiday_id'] ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this holiday?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px;">
                                No holidays added yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const target = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', target);
            document.getElementById('themeIcon').className = 
                target === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
            localStorage.setItem('theme', target);
        }
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
            document.getElementById('themeIcon').className = 'fa-solid fa-sun';
        }

        function setPreset(days) {
            for (let i = 1; i <= 7; i++) {
                const cb = document.getElementById('day_' + i);
                if (cb) cb.checked = days.includes(i);
            }
        }

        console.log('📅 Working Days Management loaded');
    </script>

</body>
</html>