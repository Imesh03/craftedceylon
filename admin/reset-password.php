<?php
session_start();
require_once '../config/db.php';

// OTP එක verify වී නැත්නම් මෙම පිටුවට ඒම වැළැක්වීම
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header("Location: forgot-password.php");
    exit();
}

$error = '';

if (isset($_POST['update_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $email = $_SESSION['verify_email'];

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Password එක Hash කිරීම
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Database එක update කර OTP තීරු ඉවත් කිරීම
        $update = $conn->prepare("UPDATE admins SET password_hash = ?, reset_otp = NULL, otp_expiry = NULL WHERE email = ?");
        $update->bind_param("ss", $hashed_password, $email);
        $update->execute();

        // Sessions ඉවත් කිරීම
        unset($_SESSION['verify_email']);
        unset($_SESSION['otp_verified']);

        echo "<script>alert('Password reset successfully! Please login with your new password.'); window.location='login.php';</script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - CraftedCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #198754, #212529); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .admin-card { border-radius: 15px; background: rgba(255, 255, 255, 0.95); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card admin-card shadow-lg p-4 border-0">
                    <div class="text-center mb-4">
                        <i class="bi bi-lock-fill text-success display-4"></i>
                        <h3 class="fw-bold text-dark mt-2">New Password</h3>
                        <p class="text-muted small">Enter your new secure password</p>
                    </div>

                    <?php if(!empty($error)): ?>
                        <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="reset-password.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">New Password</label>
                            <input type="password" name="new_password" class="form-control" required placeholder="Enter new password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required placeholder="Re-enter new password">
                        </div>
                        <button type="submit" name="update_password" class="btn btn-success w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-shield-lock"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>