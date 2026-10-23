<?php
session_start();
require_once 'config/db.php';
date_default_timezone_set('Asia/Colombo');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

$msg = '';
$error = '';
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1; 
// Step 1: Enter Email
// Step 2: Enter OTP Code
// Step 3: Enter New Password

// Step 1: Handle Email Submission & Send OTP via PHPMailer
if (isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $full_name = $row['full_name'] ?? 'Valued Customer';
        
        $otp = rand(100000, 999999); // Generate 6-digit OTP
        $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes")); // Valid for 10 mins
        
        // Save OTP to database
        $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("sss", $otp, $expiry, $email);
            $update_stmt->execute();
        } else {
            die("Prepare failed: " . $conn->error);
        }
        
        // Send email using PHPMailer (Same configuration as register.php)
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'lasadaimesh@gmail.com'; 
            $mail->Password   = 'dvmx ibup fbgc iaes';   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('lasadaimesh@gmail.com', 'CraftedCeylon Admin');
            $mail->addAddress($email, $full_name);

            $mail->isHTML(true);
            $mail->Subject = 'CraftedCeylon - Password Reset OTP Code';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                    <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px;'>
                        <h2 style='color: #198754; text-align: center;'>CraftedCeylon</h2>
                        <p>Hello <b>{$full_name}</b>,</p>
                        <p>We received a request to reset your password. Please use the following One-Time Password (OTP):</p>
                        <div style='text-align: center; margin: 20px 0;'>
                            <span style='font-size: 28px; font-weight: bold; letter-spacing: 5px; color: #198754; background: #e8f5e9; padding: 10px 20px; border-radius: 5px;'>{$otp}</span>
                        </div>
                        <p style='font-size: 12px; color: #777;'>This code will expire in 10 minutes. If you did not request this, please ignore this email.</p>
                    </div>
                </div>
            ";

            $mail->send();
            
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_step'] = 2; // Move to Step 2 (OTP Verification)
            $step = 2;
            
            $msg = "A password reset OTP has been successfully sent to your email.";
        } catch (Exception $e) {
            $error = "Failed to send reset email. Mail Error: " . $mail->ErrorInfo;
            $step = 1;
        }
    } else {
        $error = "No account found with this email address.";
        $step = 1;
    }
}

// Step 2: Verify OTP
if (isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);
    $email = $_SESSION['reset_email'] ?? '';
    
    $stmt = $conn->prepare("SELECT user_id, otp_expiry FROM users WHERE email = ? AND otp_code = ?");
    $stmt->bind_param("ss", $email, $entered_otp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (strtotime($row['otp_expiry']) >= time()) {
            $_SESSION['reset_step'] = 3; // Move to Step 3 (New Password)
            $step = 3;
        } else {
            $error = "OTP has expired. Please request a new one.";
            $step = 2;
        }
    } else {
        $error = "Invalid OTP code. Please try again.";
        $step = 2;
    }
}

// Step 3: Reset Password (using password_hash column)
if (isset($_POST['reset_password'])) {
    $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    $email = $_SESSION['reset_email'] ?? '';
    
    if (!empty($email)) {
        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ?, otp_code = NULL, otp_expiry = NULL WHERE email = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ss", $new_password, $email);
            $update_stmt->execute();
        } else {
            die("Prepare failed: " . $conn->error);
        }
        
        // Clear Sessions
        unset($_SESSION['reset_step']);
        unset($_SESSION['reset_email']);
        
        header("Location: login.php?msg=password_reset_success");
        exit();
    } else {
        $error = "Session expired. Please start over.";
        $step = 1;
    }
}

// Resend OTP Handler using PHPMailer
if (isset($_POST['resend_otp'])) {
    $email = $_SESSION['reset_email'] ?? '';
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $full_name = ($res->num_rows > 0) ? $res->fetch_assoc()['full_name'] : 'Customer';

        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
        
        $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("sss", $otp, $expiry, $email);
            $update_stmt->execute();
        }
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'lasadaimesh@gmail.com'; 
            $mail->Password   = 'dvmx ibup fbgc iaes';   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('lasadaimesh@gmail.com', 'CraftedCeylon Admin');
            $mail->addAddress($email, $full_name);

            $mail->isHTML(true);
            $mail->Subject = 'CraftedCeylon - New Password Reset OTP Code';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                    <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px;'>
                        <h2 style='color: #198754; text-align: center;'>CraftedCeylon</h2>
                        <p>Hello <b>{$full_name}</b>,</p>
                        <p>Here is your new OTP code for password reset:</p>
                        <div style='text-align: center; margin: 20px 0;'>
                            <span style='font-size: 28px; font-weight: bold; letter-spacing: 5px; color: #198754; background: #e8f5e9; padding: 10px 20px; border-radius: 5px;'>{$otp}</span>
                        </div>
                        <p style='font-size: 12px; color: #777;'>This code will expire in 10 minutes.</p>
                    </div>
                </div>
            ";
            $mail->send();
            $msg = "A new OTP code has been resent to your email.";
        } catch (Exception $e) {
            $error = "Failed to resend email. Mail Error: " . $mail->ErrorInfo;
        }
        
        $step = 2;
    } else {
        $step = 1;
    }
}

include 'includes/header.php';
?>

<div class="container my-5" style="max-width: 500px;">
    <div class="card shadow-sm border p-4">
        <h3 class="fw-bold text-success mb-3 text-center"><i class="bi bi-key"></i> Forgot Password</h3>
        <p class="text-muted text-center small mb-4">Recover your account securely using step-by-step OTP verification.</p>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show small" role="alert">
                <i class="bi bi-check-circle me-1"></i> <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <!-- Step 1: Enter Email Form -->
            <form action="forgot-password.php" method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Enter Your Registered Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="name@example.com">
                </div>
                <button type="submit" name="send_otp" class="btn btn-success w-100 fw-bold py-2">
                    <i class="bi bi-send me-1"></i> Send OTP Code
                </button>
            </form>

        <?php elseif ($step == 2): ?>
            <!-- Step 2: Enter OTP Form -->
            <form action="forgot-password.php" method="POST" class="mb-3">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Enter 6-digit OTP Code</label>
                    <input type="text" name="otp" class="form-control text-center fs-4 tracking-widest" maxlength="6" required placeholder="123456">
                </div>
                <button type="submit" name="verify_otp" class="btn btn-success w-100 fw-bold py-2">
                    <i class="bi bi-check-circle me-1"></i> Verify OTP
                </button>
            </form>

            <!-- Resend OTP Form Option -->
            <form action="forgot-password.php" method="POST" class="text-center mt-2">
                <p class="text-muted small mb-1">Didn't receive the OTP code?</p>
                <button type="submit" name="resend_otp" class="btn btn-link text-success text-decoration-none fw-bold p-0 small">
                    <i class="bi bi-arrow-clockwise me-1"></i> Resend OTP Code
                </button>
            </form>

        <?php elseif ($step == 3): ?>
            <!-- Step 3: Enter New Password with Show/Hide Option -->
            <form action="forgot-password.php" method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold">New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" id="newPassword" class="form-control" required placeholder="Enter new password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" name="reset_password" class="btn btn-success w-100 fw-bold py-2">
                    <i class="bi bi-shield-lock me-1"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4 border-top pt-3">
            <a href="login.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</div>

<!-- Password View/Hide JavaScript -->
<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const passwordInput = document.getElementById('newPassword');
    const eyeIcon = document.getElementById('eyeIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('bi-eye');
        eyeIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('bi-eye-slash');
        eyeIcon.classList.add('bi-eye');
    }
});
</script>

<?php include 'includes/footer.php'; ?>