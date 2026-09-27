<?php
// attendance_handler.php
require_once '../config/db.php';
session_start();

if(isset($_POST['mark_present']) && isset($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, CURDATE(), 'Present') 
                           ON DUPLICATE KEY UPDATE status = 'Present'");
    if($stmt->execute([$_SESSION['student_id']])) {
        echo "success";
    }
}
?>