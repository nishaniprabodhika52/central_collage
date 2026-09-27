<?php
// logout.php - Destroy session and log out the user
session_start(); // Initialize the session

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session cookie from the browser for security purposes
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session completely
session_destroy();

// 4. Redirect user back to the login page
header("Location: login.php");
exit();
?>