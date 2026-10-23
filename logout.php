<?php
// Session එක ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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