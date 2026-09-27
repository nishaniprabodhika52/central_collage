<?php
// admin/mark_attendance_ajax.php - AJAX Handler (with working day check)
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['principal_id']) && !isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Student ID']);
    exit();
}

try {
    // Check student exists
    $check = $pdo->prepare("SELECT name, class_id FROM student WHERE student_id = ?");
    $check->execute([$student_id]);
    $student = $check->fetch();
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    // ✅ CHECK WORKING DAY FIRST
    $date = date('Y-m-d');
    if (!isWorkingDay($date, $pdo)) {
        $reason = getNonWorkingReason($date, $pdo);
        echo json_encode([
            'success' => false,
            'message' => '❌ Cannot mark attendance. ' . $reason
        ]);
        exit();
    }

    // Mark attendance
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
    $stmt->execute([$student_id, $date]);
    $existing = $stmt->fetch();

    if ($existing) {
        if (strtolower($existing['status']) !== 'present') {
            $upd = $pdo->prepare("UPDATE attendance SET status = 'present' WHERE student_id = ? AND date = ?");
            $upd->execute([$student_id, $date]);
            echo json_encode(['success' => true, 'message' => '✅ Updated to Present for ' . $student['name']]);
        } else {
            echo json_encode(['success' => true, 'message' => 'ℹ️ Already marked Present for ' . $student['name']]);
        }
    } else {
        $ins = $pdo->prepare("INSERT INTO attendance (student_id, date, status, class_id) VALUES (?, ?, 'present', ?)");
        $ins->execute([$student_id, $date, $student['class_id']]);
        echo json_encode(['success' => true, 'message' => '✅ Attendance marked for ' . $student['name']]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>