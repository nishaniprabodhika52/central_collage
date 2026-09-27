<?php
// config/db.php - Database Connection + Working Days Functions
$host = 'localhost';
$db   = 'central_college_db'; 
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// ============================================================
// GET WORKING DAYS from settings (default: Mon-Fri = 1,2,3,4,5)
// ============================================================
function getWorkingDays($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM school_settings WHERE setting_key = 'working_days'");
        $stmt->execute();
        $value = $stmt->fetchColumn();
        if ($value) {
            return array_map('intval', explode(',', $value));
        }
    } catch (PDOException $e) {
        // Table might not exist yet
    }
    return [1, 2, 3, 4, 5]; // Default: Monday to Friday
}

// ============================================================
// SET WORKING DAYS (called from principal's settings page)
// ============================================================
function setWorkingDays($pdo, $days_array) {
    $value = implode(',', array_map('intval', $days_array));
    try {
        $stmt = $pdo->prepare("
            INSERT INTO school_settings (setting_key, setting_value) 
            VALUES ('working_days', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$value, $value]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ============================================================
// CHECK IF A DATE IS A WORKING DAY
// ============================================================
function isWorkingDay($date, $pdo) {
    // 1. Check if it's a public holiday
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ?");
        $stmt->execute([$date]);
        if ($stmt->fetchColumn() > 0) {
            return false; // Public holiday
        }
    } catch (PDOException $e) {
        // Holidays table might not exist, skip
    }

    // 2. Check if the day-of-week is configured as a working day
    $day_of_week = (int)date('N', strtotime($date)); // 1=Mon ... 7=Sun
    $working_days = getWorkingDays($pdo);

    if (!in_array($day_of_week, $working_days)) {
        return false;
    }

    return true; // Working day
}

// ============================================================
// GET REASON WHY A DATE IS NOT A WORKING DAY (for messages)
// ============================================================
function getNonWorkingReason($date, $pdo) {
    // Check holiday first
    try {
        $stmt = $pdo->prepare("SELECT description FROM holidays WHERE holiday_date = ?");
        $stmt->execute([$date]);
        $desc = $stmt->fetchColumn();
        if ($desc) {
            return "Public Holiday: " . $desc;
        }
    } catch (PDOException $e) {}

    // Check day-of-week
    $day_of_week = (int)date('N', strtotime($date));
    $working_days = getWorkingDays($pdo);
    if (!in_array($day_of_week, $working_days)) {
        $day_names = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
        return $day_names[$day_of_week] . " is not a configured working day";
    }

    return "Not a working day";
}

// ============================================================
// GET DAY NAMES FOR A WORKING DAYS ARRAY (for display)
// ============================================================
function getWorkingDayNames($days_array) {
    $day_names = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
    $names = [];
    foreach ($days_array as $d) {
        if (isset($day_names[$d])) $names[] = $day_names[$d];
    }
    return $names;
}
?>