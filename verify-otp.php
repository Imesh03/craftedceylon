<?php
session_start();
require_once 'config/db.php';
date_default_timezone_set('Asia/Colombo');

if (!isset($_SESSION['verify_email']) || !isset($_SESSION['temp_user'])) {
    header("Location: register.php");
    exit();
}

$error = '';

if (isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);
    $temp = $_SESSION['temp_user'];

    // Check if OTP matches and is not expired
    if ($temp['otp_code'] == $entered_otp) {
        if (strtotime($temp['otp_expiry']) > time()) {
            
            // NOW save the user to Database as verified (Since OTP is correct)
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, is_verified) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("sss", $temp['full_name'], $temp['email'], $temp['password_hash']);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;

                // Set login session
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $temp['full_name'];
                $_SESSION['user_email'] = $temp['email'];

                // Clear temporary sessions
                unset($_SESSION['verify_email']);
                unset($_SESSION['temp_user']);

                header("Location: account.php");
                exit();
            } else {
                $error = "Database error during registration: " . $conn->error;
            }
        } else {
            $error = "OTP code has expired. Please register again.";
        }
    } else {
        $error = "Invalid OTP code. Please check and try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP - CraftedCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="text-center mb-4">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h3 class="fw-bold">Email Verification</h3>
                        <p class="text-muted small">We have sent a 6-digit OTP code to <b><?php echo htmlspecialchars($_SESSION['verify_email']); ?></b></p>
                    </div>

                    <?php if(!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                            <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="verify-otp.php" method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Enter 6-Digit OTP</label>
                            <input type="text" name="otp" class="form-control text-center fs-4 tracking-widest" required maxlength="6" placeholder="------">
                        </div>

                        <button type="submit" name="verify_otp" class="btn btn-success w-100 fw-bold py-2 mb-3">
                            <i class="bi bi-check-circle me-1"></i> Verify Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>