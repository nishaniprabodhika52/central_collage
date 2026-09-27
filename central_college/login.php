<?php
// login.php - Professional Animated Login Page
require_once __DIR__ . '/config/db.php';
session_start();

if (isset($_SESSION['principal_id'])) {
    header("Location: admin/dashboard.php");
    exit();
} else if (isset($_SESSION['student_id'])) {
    header("Location: student/dashboard.php");
    exit();
} else if (isset($_SESSION['teacher_id'])) {
    header("Location: teacher/dashboard.php");
    exit();
}

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        // ============================================================
        // PRINCIPAL LOGIN
        // ============================================================
        if ($role === 'principal') {
            $stmt = $pdo->prepare("SELECT * FROM principal WHERE username = ?");
            $stmt->execute([$username]);
            $principal = $stmt->fetch();

            if ($principal) {
                // Handle bcrypt, md5, or plain text passwords
                $valid = false;
                if (password_verify($password, $principal['password'])) {
                    $valid = true;
                } elseif (md5($password) === $principal['password']) {
                    $valid = true;
                } elseif ($password === $principal['password']) {
                    $valid = true;
                }

                if ($valid) {
                    $_SESSION['principal_id'] = $principal['p_id'];
                    $_SESSION['principal_name'] = $principal['name'];
                    $_SESSION['principal_email'] = $principal['email'] ?? '';
                    $_SESSION['principal_role'] = 'principal';
                    header("Location: admin/dashboard.php");
                    exit();
                } else {
                    $error_message = "Invalid Principal Password!";
                }
            } else {
                $error_message = "Principal Username not found!";
            }
        }

        // ============================================================
        // STUDENT LOGIN
        // ============================================================
        else if ($role === 'student') {
            $stmt = $pdo->prepare("SELECT * FROM student WHERE name = ?");
            $stmt->execute([$username]);
            $student = $stmt->fetch();

            if ($student) {
                if (isset($student['password']) && !empty($student['password'])) {
                    if (password_verify($password, $student['password']) || $password === $student['password']) {
                        $_SESSION['student_id'] = $student['student_id'];
                        $_SESSION['student_name'] = $student['name'];
                        header("Location: student/dashboard.php");
                        exit();
                    } else {
                        $error_message = "Invalid Student Password!";
                    }
                } else {
                    $_SESSION['student_id'] = $student['student_id'];
                    $_SESSION['student_name'] = $student['name'];
                    header("Location: student/dashboard.php");
                    exit();
                }
            } else {
                $error_message = "Student Name not found!";
            }
        }

        // ============================================================
        // TEACHER LOGIN
        // ============================================================
        else if ($role === 'teacher') {
            $stmt = $pdo->prepare("SELECT * FROM teacher WHERE email = ?");
            $stmt->execute([$username]);
            $teacher = $stmt->fetch();

            if ($teacher) {
                $password_valid = false;
                if (isset($teacher['password']) && !empty($teacher['password'])) {
                    if (password_verify($password, $teacher['password']) ||
                        $password === $teacher['password'] ||
                        md5($password) === $teacher['password']) {
                        $password_valid = true;
                    }
                } else {
                    $password_valid = true;
                }

                if ($password_valid) {
                    $_SESSION['teacher_id'] = $teacher['teacher_id'];
                    $_SESSION['teacher_name'] = $teacher['name'];
                    $_SESSION['teacher_email'] = $teacher['email'] ?? '';
                    $_SESSION['teacher_class_id'] = isset($teacher['class_id']) ? (int)$teacher['class_id'] : 0;
                    header("Location: teacher/dashboard.php");
                    exit();
                } else {
                    $error_message = "Invalid Teacher Password!";
                }
            } else {
                $error_message = "Teacher Email not found!";
            }
        } else {
            $error_message = "Please select a valid role!";
        }

    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Secure System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0f172a, #1d4ed8, #06b6d4);
            overflow: hidden;
            position: relative;
        }
        body::before, body::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }
        body::before { width: 450px; height: 450px; top: -120px; left: -120px; animation: move1 8s infinite ease-in-out; }
        body::after { width: 350px; height: 350px; bottom: -100px; right: -100px; animation: move2 10s infinite ease-in-out; }
        @keyframes move1 { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(40px); } }
        @keyframes move2 { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-30px); } }
        .login-box {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 400px;
            padding: 40px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.35);
            text-align: center;
            color: white;
            animation: fadeIn 1s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        h1 { font-size: 2rem; margin-bottom: 20px; }
        .input-group { margin-bottom: 15px; text-align: left; }
        label { display: block; margin-bottom: 5px; font-size: 0.85rem; color: #bfdbfe; }
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            background: rgba(255,255,255,0.1);
            color: white;
            outline: none;
            transition: 0.3s;
        }
        input:focus, select:focus { background: rgba(255,255,255,0.2); border-color: #60a5fa; }
        select option { color: #000; }
        button {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: none;
            border-radius: 10px;
            background: #3b82f6;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover { background: #2563eb; }
        .error-alert {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            text-align: left;
        }
        .footer { margin-top: 20px; font-size: 0.75rem; color: #94a3b8; }
    </style>
</head>
<body>
<div class="login-box">
    <h1>🔐 Login</h1>
    <?php if (!empty($error_message)): ?>
        <div class="error-alert">⚠️ <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <form action="login.php" method="POST">
        <div class="input-group">
            <label>Login As</label>
            <select name="role" id="role">
                <option value="student" style="color:black;">🎓 Student</option>
                <option value="teacher" style="color:black;">👨‍🏫 Teacher</option>
                <option value="principal" style="color:black;">👑 Principal</option>
            </select>
        </div>
        <div class="input-group">
            <label id="labelUsername">Username / Email</label>
            <input type="text" name="username" id="inputUsername" placeholder="Enter username or email" required>
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password">
        </div>
        <button type="submit">Login Now</button>
    </form>
    <div class="footer">&copy; 2026 Secure Login System</div>
</div>

<script>
    document.getElementById('role').addEventListener('change', function() {
        const label = document.getElementById('labelUsername');
        const input = document.getElementById('inputUsername');
        if (this.value === 'teacher' || this.value === 'principal') {
            label.textContent = '📧 Email Address';
            input.placeholder = 'Enter your email address';
        } else if (this.value === 'student') {
            label.textContent = '👤 Student Name';
            input.placeholder = 'Enter your full name';
        }
    });
</script>
</body>
</html>