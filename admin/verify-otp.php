<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Colombo');

// Admin කෙනෙකුගේ reset email එක හෝ session එක නැත්නම් forgot-password වෙත හරවා යැවීම
if (!isset($_SESSION['admin_reset_email'])) {
    header("Location: forgot-password.php");
    exit();
}

$error = '';

if (isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);
    $email = $_SESSION['admin_reset_email'];

    // Database එකෙන් admin ගේ OTP සහ expiry කාලය පරීක්ෂා කිරීම
    $stmt = $conn->prepare("SELECT admin_id, reset_otp, otp_expiry FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        // Check if OTP matches and is not expired
        if ($admin['reset_otp'] == $entered_otp) {
            if (strtotime($admin['otp_expiry']) > time()) {
                
                // OTP එක නිවැරදි බැවින් ඊළඟ පියවරට (New password ඇතුළත් කිරීමට) අවසර දීම
                $_SESSION['admin_otp_verified'] = true;

                header("Location: reset-password.php");
                exit();
            } else {
                $error = "OTP code has expired. Please request a new one.";
            }
        } else {
            $error = "Invalid OTP code. Please check and try again.";
        }
    } else {
        $error = "Admin account not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin OTP Verification - CraftedCeylon</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Creative Blurred Background & Dark Styling -->
    <style>
        body {
            position: relative;
            background-color: #0d0d12;
            color: #ffffff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(135deg, rgba(13, 13, 18, 0.95), rgba(20, 30, 25, 0.9));
            background-size: cover;
            background-position: center;
            z-index: -1;
        }

        .dark-glass-card {
            background: rgba(26, 26, 40, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
            color: #ffffff;
        }

        .form-control {
            background-color: rgba(18, 18, 22, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-radius: 10px;
        }
        .form-control:focus {
            background-color: rgba(18, 18, 22, 0.95);
            border-color: #198754;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
    </style>
</head>
<body>

<div class="container my-5" style="max-width: 500px;">
    <div class="dark-glass-card p-4">
        <div class="text-center mb-4">
            <div class="bg-success bg-opacity-25 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-sm" style="width: 60px; height: 60px; font-size: 1.5rem;">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h3 class="fw-bold text-white">Admin OTP Verification</h3>
            <p class="text-secondary small">We have sent a 6-digit OTP code to <b class="text-white"><?php echo htmlspecialchars($_SESSION['admin_reset_email']); ?></b></p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger bg-danger text-white border-0 shadow-sm alert-dismissible fade show small" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error; ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="verify-otp.php" method="POST">
            <div class="mb-4">
                <label class="form-label fw-semibold small text-secondary">Enter 6-Digit OTP</label>
                <input type="text" name="otp" class="form-control text-center fs-4 tracking-widest" required maxlength="6" placeholder="------">
            </div>

            <button type="submit" name="verify_otp" class="btn btn-success w-100 fw-bold py-2 mb-3 shadow-sm">
                <i class="bi bi-check-circle me-1"></i> Verify OTP Code
            </button>
        </form>

        <div class="text-center mt-3 border-top border-secondary border-opacity-25 pt-3">
            <a href="forgot-password.php" class="text-decoration-none text-secondary small"><i class="bi bi-arrow-left"></i> Resend OTP or Change Email</a>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>