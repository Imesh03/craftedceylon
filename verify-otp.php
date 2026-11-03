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

include 'includes/header.php';
?>

<!-- Creative Blurred Background & Dark Styling -->
<style>
    body {
        position: relative;
        background-color: #0d0d12;
        color: #ffffff;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('assets/images/cover.jpg');
        background-size: cover;
        background-position: center;
        filter: blur(20px) brightness(0.35);
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

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="dark-glass-card p-4">
                <div class="text-center mb-4">
                    <div class="bg-success bg-opacity-25 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-sm" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h3 class="fw-bold text-white">Email Verification</h3>
                    <p class="text-secondary small">We have sent a 6-digit OTP code to <b class="text-white"><?php echo htmlspecialchars($_SESSION['verify_email']); ?></b></p>
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
                        <i class="bi bi-check-circle me-1"></i> Verify Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>