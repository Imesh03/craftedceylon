<?php
// Session එක ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection එක ලබා ගැනීම
require_once 'config/db.php';

// පාරිභෝගිකයා ලොග් වී ඇත්නම් සහ log_id එක සටහන් වී ඇත්නම් logout_time එක Update කිරීම
if (isset($_SESSION['user_id']) && isset($_SESSION['login_log_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $log_id = intval($_SESSION['login_log_id']);
    $current_time = date('Y-m-d H:i:s');

    // 1. user_login_logs වලට logout_time එක එකතු කිරීම
    $stmt = $conn->prepare("UPDATE user_login_logs SET logout_time = ? WHERE log_id = ? AND user_id = ?");
    $stmt->bind_param("sii", $current_time, $log_id, $user_id);
    $stmt->execute();

    // 2. user_activities වලට logout activity එක සටහන් කිරීම
    $act_stmt = $conn->prepare("INSERT INTO user_activities (user_id, action_name, page_url) VALUES (?, 'User Logged Out', 'logout.php')");
    $act_stmt->bind_param("i", $user_id);
    $act_stmt->execute();
}

// සියලුම Session variables ඉවත් කිරීම
$_SESSION = array();

// Session Cookie එකක් පවතී නම් එය මැකීම
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session එක සම්පූර්ණයෙන්ම විනාශ කිරීම (Destroy)
session_destroy();

// ලොග් අවුට් වූ පසු index.php පිටුව වෙත යොමු කිරීම
header("Location: index.php?logout=success");
exit();
?>