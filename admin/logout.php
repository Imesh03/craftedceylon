<?php
// Session එක ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
date_default_timezone_set('Asia/Colombo');

// Update logout time in activity logs if session log_id exists
if (isset($_SESSION['admin_log_id'])) {
    $log_id = $_SESSION['admin_log_id'];
    $logout_time = date("Y-m-d H:i:s");
    
    $update_log = $conn->prepare("UPDATE admin_activity_logs SET logout_time = ?, activity_description = 'Logged Out' WHERE log_id = ?");
    $update_log->bind_param("si", $logout_time, $log_id);
    $update_log->execute();
}

// Admin session පමණක් හෝ සියලුම session විනාශ කිරීම
unset($_SESSION['admin_logged']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_log_id']);

// අවශ්‍ය නම් සම්පූර්ණ session එකම destroy කිරීම සඳහා:
// session_destroy();

// සාර්ථකව ඉවත් වී admin login පිටුවට යැවීම
header("Location: login.php");
exit();
?>